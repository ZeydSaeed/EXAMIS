<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Backups are taken by root on the server (deploy/backup), never by the web
 * application. This screen shows the status file the backup job writes and
 * leaves request files for it: "request" for a manual backup, and
 * "delete-<kind>-<set>" to delete a set; systemd path units see them and
 * start examis-backup@manual or examis-backup@delete.
 */
class BackupController extends Controller
{
    /** Sets holding the database and the uploaded files. */
    private const FULL_KINDS = ['daily', 'manual', 'weekly'];

    public function index(Request $request): Response
    {
        $status = $this->readStatus();

        return Inertia::render('admin/backups/index', [
            'status' => $status,
            'requestedAt' => $this->requestedAt(),
            'deleting' => $this->pendingDeletions(),
            'kept' => $this->lastFullSet($status),
            'flash' => $request->session()->get('status'),
        ]);
    }

    public function store(AuditLogger $audit): RedirectResponse
    {
        $directory = (string) config('examis.backups.status_dir');

        if ($this->requestedAt() !== null) {
            throw ValidationException::withMessages(['backup' => __('backups.errors.pending')]);
        }

        File::ensureDirectoryExists($directory, 0750);
        File::put($directory.'/request', now()->utc()->toIso8601String()."\n");

        $audit->record('backup.requested');

        return to_route('admin.backups.index')->with('status', __('backups.requested'));
    }

    /**
     * Asks the backup job to delete one set. The job checks again and always
     * keeps the last full set.
     */
    public function destroy(string $kind, string $set, AuditLogger $audit): RedirectResponse
    {
        $status = $this->readStatus();

        abort_unless(in_array($set, $this->setNames($status, $kind), true), 404);

        if ($this->lastFullSet($status) === "{$kind}/{$set}") {
            throw ValidationException::withMessages(['backup' => __('backups.errors.last_full')]);
        }

        $directory = (string) config('examis.backups.status_dir');
        File::ensureDirectoryExists($directory, 0750);
        File::put("{$directory}/delete-{$kind}-{$set}", now()->utc()->toIso8601String()."\n");

        $audit->record('backup.delete_requested', null, ['kind' => $kind, 'set' => $set]);

        return to_route('admin.backups.index')->with('status', __('backups.delete_requested'));
    }

    private function requestedAt(): ?string
    {
        $file = config('examis.backups.status_dir').'/request';

        return File::exists($file) ? trim(File::get($file)) : null;
    }

    /**
     * @return list<string> "<kind>/<set>" of each deletion not done yet
     */
    private function pendingDeletions(): array
    {
        $deleting = [];

        foreach (File::glob(config('examis.backups.status_dir').'/delete-*') as $file) {
            if (preg_match('/^delete-(6h|daily|manual|weekly)-(\d{8}T\d{6}Z)$/', basename($file), $match) === 1) {
                $deleting[] = "{$match[1]}/{$match[2]}";
            }
        }

        return $deleting;
    }

    /**
     * The only full set left, which cannot be deleted, as "<kind>/<set>".
     *
     * @param  array<string, mixed>|null  $status
     */
    private function lastFullSet(?array $status): ?string
    {
        $full = [];

        foreach (self::FULL_KINDS as $kind) {
            foreach ($this->setNames($status, $kind) as $name) {
                $full[] = "{$kind}/{$name}";
            }
        }

        return count($full) === 1 ? $full[0] : null;
    }

    /**
     * @param  array<string, mixed>|null  $status
     * @return list<string>
     */
    private function setNames(?array $status, string $kind): array
    {
        $sets = $status['kinds'][$kind]['sets'] ?? [];
        $names = [];

        foreach (is_array($sets) ? $sets : [] as $set) {
            if (is_array($set) && is_string($set['name'] ?? null)) {
                $names[] = $set['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readStatus(): ?array
    {
        $file = config('examis.backups.status_dir').'/status.json';

        if (! File::exists($file)) {
            return null;
        }

        $status = json_decode(File::get($file), true);

        return is_array($status) ? $status : null;
    }
}
