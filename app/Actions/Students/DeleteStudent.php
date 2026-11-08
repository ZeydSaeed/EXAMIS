<?php

namespace App\Actions\Students;

use App\Exceptions\DomainRuleViolation;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Database\TableEditor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a student for good from the staff database screen: the account,
 * every application, uploaded file, history entry, notification, remembered
 * device and session. Before anything is removed, the rows and files are
 * copied to a snapshot on the private disk (kept 30 days, see
 * examis:prune-deleted-students), so a mistake can still be undone by hand.
 *
 * Application history is append-only in PostgreSQL; this transaction is the
 * one place allowed to delete from it (examis.allow_history_delete).
 */
class DeleteStudent
{
    public const SNAPSHOT_DIRECTORY = 'deleted-students';

    public function __construct(
        private AuditLogger $audit,
        private TableEditor $tables,
    ) {}

    /**
     * @return string The snapshot directory on the local disk.
     */
    public function handle(User $student, User $actor): string
    {
        if (! $student->isApplicant()) {
            throw new DomainRuleViolation(__('database.errors.not_a_student'), 'database');
        }

        $applications = Application::query()->where('user_id', $student->id)->get();
        $applicationIds = $applications->modelKeys();
        $documents = ApplicationDocument::query()->whereIn('application_id', $applicationIds)->get();
        $snapshot = $this->snapshot($student, $applications, $documents);

        DB::transaction(function () use ($student, $actor, $applications, $applicationIds, $documents, $snapshot): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SET LOCAL examis.allow_history_delete = 'on'");
            }

            DB::table('application_events')->whereIn('application_id', $applicationIds)->delete();
            DB::table('application_documents')->whereIn('application_id', $applicationIds)->delete();
            DB::table('applications')->whereIn('id', $applicationIds)->delete();
            DB::table('notifications')->where('notifiable_type', $student->getMorphClass())->where('notifiable_id', $student->id)->delete();
            DB::table('trusted_devices')->where('user_id', $student->id)->delete();
            DB::table('sessions')->where('user_id', $student->id)->delete();

            $this->audit->record('student.deleted', $student, [
                'student_number' => $student->student_number,
                'applications' => $applications->pluck('reference_number')->filter()->values()->all(),
                'documents' => $documents->count(),
                'snapshot' => $snapshot,
            ], $actor);

            DB::table('users')->where('id', $student->id)->delete();
        }, 3);

        // Files go only after the rows are gone for good.
        foreach ($documents as $document) {
            Storage::disk($document->disk)->delete($document->path);
        }

        foreach ($applications as $application) {
            Storage::disk('local')->deleteDirectory('form-cache/'.$application->uuid);
        }

        return $snapshot;
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, ApplicationDocument>  $documents
     */
    private function snapshot(User $student, Collection $applications, Collection $documents): string
    {
        $applicationIds = $applications->modelKeys();
        $disk = Storage::disk('local');
        $directory = self::SNAPSHOT_DIRECTORY.'/'.now()->utc()->format('Ymd\THis\Z').'-'.($student->student_number ?? $student->id);
        $rows = fn (string $table, string $column, array $ids): array => DB::table($table)->whereIn($column, $ids)->get()
            ->map(fn (object $row): array => $this->tables->withoutSecrets((array) $row))->all();

        $disk->put($directory.'/rows.json', (string) json_encode([
            'users' => $rows('users', 'id', [$student->id]),
            'applications' => $rows('applications', 'id', $applicationIds),
            'application_documents' => $rows('application_documents', 'application_id', $applicationIds),
            'application_events' => $rows('application_events', 'application_id', $applicationIds),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        foreach ($documents as $document) {
            $source = Storage::disk($document->disk);

            if ($source->exists($document->path)) {
                $disk->writeStream($directory.'/files/'.$document->path, $source->readStream($document->path));
            }
        }

        return $directory;
    }
}
