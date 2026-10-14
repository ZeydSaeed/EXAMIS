<?php

namespace App\Services\Operations;

use App\Enums\DocumentStatus;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dependency checks shared by the public readiness probe (pass/fail only)
 * and the admin operations page (details). Failure details are never
 * exposed publicly.
 */
class HealthReport
{
    /**
     * @return array{database: bool, cache: bool, storage: bool}
     */
    public function checks(): array
    {
        return [
            'database' => $this->attempt(fn (): bool => DB::select('select 1 as ok')[0]->ok == 1),
            'cache' => $this->attempt(function (): bool {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            }),
            'storage' => $this->attempt(function (): bool {
                $disk = Storage::disk((string) config('examis.documents.disk'));
                $path = '.health/'.Str::random(16);
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                return $ok;
            }),
        ];
    }

    public function isReady(): bool
    {
        return ! in_array(false, $this->checks(), true);
    }

    /**
     * @return array<string, int|null>
     */
    public function queueMetrics(): array
    {
        $usesDatabaseQueue = config('queue.default') === 'database' && Schema::hasTable('jobs');

        $oldestPending = $usesDatabaseQueue ? DB::table('jobs')->min('created_at') : null;

        return [
            'pending_jobs' => $usesDatabaseQueue ? DB::table('jobs')->count() : null,
            'oldest_pending_seconds' => $oldestPending !== null ? max(0, now()->getTimestamp() - (int) $oldestPending) : null,
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'documents_pending_scan' => ApplicationDocument::query()->where('status', DocumentStatus::Pending)->whereNull('replaced_at')->count(),
        ];
    }

    private function attempt(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
