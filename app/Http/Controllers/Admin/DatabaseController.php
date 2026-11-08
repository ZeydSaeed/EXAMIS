<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Students\DeleteStudent;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Database\TableEditor;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff "Students database" screen: every application table, its rows,
 * and a form per row. Reading needs database.view; adding, editing and
 * deleting need database.manage (routes/staff.php). Each write is audited.
 */
class DatabaseController extends Controller
{
    public function __construct(private TableEditor $tables) {}

    public function index(): Response
    {
        return Inertia::render('admin/database/index', [
            'tables' => array_map(fn (string $table): array => [
                'name' => $table,
                'label' => $this->label($table),
                'rows' => $this->tables->count($table),
                'writable' => $this->tables->isWritable($table),
            ], $this->tables->tables()),
        ]);
    }

    public function show(Request $request, string $table): Response
    {
        $this->ensureTable($table);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $rows = $this->tables->query($table, isset($filters['q']) ? trim($filters['q']) : null)
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString()
            ->through(fn (object $row): array => [
                'key' => $this->tables->keyToken($table, (array) $row),
                'values' => $this->tables->present($table, (array) $row, 80),
            ]);

        return Inertia::render('admin/database/table', [
            ...$this->tableProps($table),
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    public function create(string $table): Response
    {
        $this->ensureTable($table);
        abort_unless($this->tables->canInsert($table), 403);

        return Inertia::render('admin/database/row', [
            ...$this->tableProps($table),
            'row' => null,
            'rowKey' => null,
            'student' => null,
        ]);
    }

    public function store(Request $request, string $table, AuditLogger $audit): RedirectResponse
    {
        $this->ensureTable($table);
        abort_unless($this->tables->canInsert($table), 403);

        $values = $this->tables->values($table, (array) $request->input('values', []), true);

        $this->write(function () use ($table, $values): void {
            DB::table($table)->insert($values);
        });

        $audit->record('database.row_created', null, ['table' => $table, 'values' => $this->forAudit($values)]);

        return to_route('admin.database.table', $table)->with('status', __('database.created'));
    }

    public function edit(Request $request, string $table): Response
    {
        [$key, $row] = $this->row($request, $table);

        return Inertia::render('admin/database/row', [
            ...$this->tableProps($table),
            'row' => $this->tables->present($table, $row),
            'rowKey' => $this->tables->keyToken($table, $key),
            'student' => $table === 'users' && $row['account_type'] === 'applicant'
                ? ['student_number' => $row['student_number'] !== null ? (string) $row['student_number'] : (string) $row['id']]
                : null,
        ]);
    }

    public function update(Request $request, string $table, AuditLogger $audit): RedirectResponse
    {
        [$key, $row] = $this->row($request, $table);
        abort_unless($this->tables->isWritable($table), 403);

        $values = $this->tables->values($table, (array) $request->input('values', []), false);
        $changed = array_filter($values, fn (mixed $value, string $column): bool => $this->differs($row[$column] ?? null, $value), ARRAY_FILTER_USE_BOTH);

        if ($changed !== []) {
            $this->write(function () use ($table, $key, $changed): void {
                $this->tables->whereKey($table, $key)->update($changed);
            });

            $audit->record('database.row_updated', null, [
                'table' => $table,
                'key' => $key,
                'changes' => collect($changed)->map(fn (mixed $value, string $column): array => [
                    'from' => $this->forAudit([$column => $row[$column] ?? null])[$column] ?? null,
                    'to' => $this->forAudit([$column => $value])[$column] ?? null,
                ])->all(),
            ]);
        }

        return to_route('admin.database.edit', ['table' => $table, 'key' => $this->tables->keyToken($table, $key)])
            ->with('status', __('database.saved'));
    }

    public function destroy(Request $request, string $table, AuditLogger $audit, DeleteStudent $deleteStudent): RedirectResponse
    {
        [$key, $row] = $this->row($request, $table);
        abort_unless($this->tables->isWritable($table), 403);

        if ($table === 'users') {
            $this->ensureUserMayBeDeleted($request, $row);

            if ($row['account_type'] === 'applicant') {
                $deleteStudent->handle(User::query()->whereKey($row['id'])->firstOrFail(), $request->user());

                return $this->afterDelete($request, $table, __('database.student_deleted'));
            }
        }

        // An application goes with its uploaded files and history, which
        // cannot exist without it.
        if ($table === 'applications') {
            $deleteStudent->application(Application::query()->whereKey($row['id'])->firstOrFail(), $request->user());

            return $this->afterDelete($request, $table, __('database.application_deleted'));
        }

        $this->write(function () use ($table, $key): void {
            $this->tables->whereKey($table, $key)->delete();
        });

        $audit->record('database.row_deleted', null, ['table' => $table, 'key' => $key, 'row' => $this->forAudit($this->tables->withoutSecrets($row))]);

        return $this->afterDelete($request, $table, __('database.deleted'));
    }

    /**
     * Back to the table, or to the page that asked (the applications list
     * sends "stay").
     */
    private function afterDelete(Request $request, string $table, string $status): RedirectResponse
    {
        return ($request->boolean('stay') ? back() : to_route('admin.database.table', $table))->with('status', $status);
    }

    /**
     * A student goes only with everything they own and a typed confirmation;
     * a staff member who has acted on anything keeps their row, so the history
     * still names them (deactivate them on the Users page instead).
     *
     * @param  array<string, mixed>  $row
     */
    private function ensureUserMayBeDeleted(Request $request, array $row): void
    {
        if ((int) $row['id'] === $request->user()->id) {
            throw ValidationException::withMessages(['database' => __('database.errors.self')]);
        }

        if ($row['account_type'] === 'applicant') {
            $expected = $row['student_number'] !== null ? (string) $row['student_number'] : (string) $row['id'];

            if (trim((string) $request->input('confirm')) !== $expected) {
                throw ValidationException::withMessages(['confirm' => __('database.errors.confirm_student')]);
            }

            return;
        }

        $acted = DB::table('audit_events')->where('actor_id', $row['id'])->exists()
            || DB::table('application_events')->where('actor_id', $row['id'])->exists()
            || DB::table('application_documents')->where('uploaded_by', $row['id'])->exists();

        if ($acted) {
            throw ValidationException::withMessages(['database' => __('database.errors.staff_has_history')]);
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function row(Request $request, string $table): array
    {
        $this->ensureTable($table);
        $key = $this->tables->decodeKey($table, (string) $request->query('key', $request->input('key', '')));
        abort_if($key === null, 404);

        $row = $this->tables->find($table, $key);
        abort_if($row === null, 404);

        return [$key, $row];
    }

    private function ensureTable(string $table): void
    {
        abort_unless($this->tables->exists($table), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableProps(string $table): array
    {
        return [
            'table' => [
                'name' => $table,
                'label' => $this->label($table),
                'writable' => $this->tables->isWritable($table),
                'insertable' => $this->tables->canInsert($table),
            ],
            'columns' => $this->tables->columns($table),
        ];
    }

    private function label(string $table): string
    {
        $key = 'database.tables.'.$table;

        return __($key) === $key ? $table : __($key);
    }

    /**
     * Database errors (a broken constraint, a missing related row) come back
     * as a message on the form instead of a server error.
     */
    /**
     * @param  Closure(): void  $callback
     */
    private function write(Closure $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['database' => $this->explain($exception)]);
        }
    }

    /**
     * A refused write in words staff can act on: which linked table blocks a
     * delete, or which value points at a row that does not exist.
     */
    private function explain(QueryException $exception): string
    {
        $message = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        // 23503: foreign key; 23001: a foreign key declared ON DELETE RESTRICT.
        if (preg_match('/SQLSTATE\[(23503|23001)\]/', $message) === 1 || str_contains($message, 'FOREIGN KEY constraint failed')) {
            if (preg_match('/Key \(([a-z_]+)\)=.* is not present in table "([a-z_]+)"/', $message, $missing) === 1) {
                return __('database.errors.missing_reference', ['column' => $missing[1], 'table' => $this->label($missing[2])]);
            }

            if (preg_match_all('/on table "([a-z_]+)"/', $message, $tables) >= 2) {
                return __('database.errors.linked_rows', ['table' => $this->label((string) end($tables[1]))]);
            }

            return __('database.errors.linked_rows_unknown');
        }

        return __('database.errors.write_failed', ['message' => Str::limit(Str::before($message, "\n"), 300)]);
    }

    private function differs(mixed $stored, mixed $new): bool
    {
        if ($stored === null || $new === null) {
            return $stored !== $new;
        }

        if (is_bool($new)) {
            return (bool) $stored !== $new;
        }

        return (string) $stored !== (string) $new;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function forAudit(array $values): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) ? Str::limit($value, 200) : $value,
            $this->tables->withoutSecrets($values),
        );
    }
}
