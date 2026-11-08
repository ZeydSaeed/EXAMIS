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
 * asks for a manual backup by creating a request file; a systemd path unit
 * sees it and starts examis-backup@manual.service.
 */
class BackupController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/backups/index', [
            'status' => $this->readStatus(),
            'requestedAt' => $this->requestedAt(),
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

    private function requestedAt(): ?string
    {
        $file = config('examis.backups.status_dir').'/request';

        return File::exists($file) ? trim(File::get($file)) : null;
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
