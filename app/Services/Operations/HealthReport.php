<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dependency checks behind the public readiness probe (pass/fail only).
 * Failure details are never exposed publicly.
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
