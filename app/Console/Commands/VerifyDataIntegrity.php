<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cross-checks the database against document storage. Run after every
 * restore drill and nightly. Exit code 1 when any invariant is broken.
 * Prints counts and identifiers only, never personal data.
 */
#[Signature('examis:verify-integrity {--hashes : Re-hash every stored document (slow)} {--json : Machine-readable output}')]
#[Description('Verify database and document-storage consistency (restore drills, nightly checks)')]
class VerifyDataIntegrity extends Command
{
    public function handle(): int
    {
        $report = [
            'counts' => [
                'users' => DB::table('users')->count(),
                'applications' => Application::query()->count(),
                'submitted_applications' => Application::query()->whereNotNull('reference_number')->count(),
                'documents' => ApplicationDocument::query()->count(),
                'application_events' => DB::table('application_events')->count(),
                'audit_events' => DB::table('audit_events')->count(),
            ],
            'problems' => [],
        ];

        $this->checkReferenceNumbers($report);
        $this->checkSubmittedApplicationsHaveHistory($report);
        $this->checkDocuments($report, (bool) $this->option('hashes'));

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['metric', 'value'], collect($report['counts'])->map(fn (int $value, string $key): array => [$key, $value])->values()->all());

            foreach ($report['problems'] as $problem) {
                $this->error($problem);
            }

            $report['problems'] === [] ? $this->info('All integrity checks passed.') : $this->warn(count($report['problems']).' problem(s) found.');
        }

        return $report['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{counts: array<string, int>, problems: list<string>}  $report
     */
    private function checkReferenceNumbers(array &$report): void
    {
        $missing = Application::query()
            ->where('status', '!=', ApplicationStatus::Draft)
            ->whereNull('reference_number')
            ->count();

        if ($missing > 0) {
            $report['problems'][] = "{$missing} submitted application(s) without a reference number.";
        }

        $periodsBehind = DB::table('exam_periods')
            ->whereRaw('last_sequence < (select count(*) from applications where applications.exam_period_id = exam_periods.id and applications.reference_number is not null)')
            ->pluck('code');

        if ($periodsBehind->isNotEmpty()) {
            $report['problems'][] = 'Numbering counter behind issued references for period(s): '.$periodsBehind->implode(', ').'. New submissions would collide.';
        }
    }

    /**
     * @param  array{counts: array<string, int>, problems: list<string>}  $report
     */
    private function checkSubmittedApplicationsHaveHistory(array &$report): void
    {
        $withoutHistory = Application::query()
            ->whereNotNull('reference_number')
            ->whereDoesntHave('events', fn ($query) => $query->where('to_status', ApplicationStatus::Submitted))
            ->count();

        if ($withoutHistory > 0) {
            $report['problems'][] = "{$withoutHistory} submitted application(s) without a submission event.";
        }
    }

    /**
     * @param  array{counts: array<string, int>, problems: list<string>}  $report
     */
    private function checkDocuments(array &$report, bool $verifyHashes): void
    {
        $referenced = [];
        $missing = 0;
        $hashMismatch = 0;

        ApplicationDocument::query()
            ->where('status', '!=', 'rejected')
            ->select(['id', 'disk', 'path', 'sha256'])
            ->lazyById(500)
            ->each(function (ApplicationDocument $document) use (&$referenced, &$missing, &$hashMismatch, $verifyHashes): void {
                $disk = Storage::disk($document->disk);
                $referenced[$document->disk][$document->path] = true;

                if (! $disk->exists($document->path)) {
                    $missing++;

                    return;
                }

                if ($verifyHashes && hash('sha256', (string) $disk->get($document->path)) !== $document->sha256) {
                    $hashMismatch++;
                }
            });

        if ($missing > 0) {
            $report['problems'][] = "{$missing} document(s) referenced in the database are missing from storage.";
        }

        if ($hashMismatch > 0) {
            $report['problems'][] = "{$hashMismatch} document(s) whose content no longer matches the recorded SHA-256.";
        }

        $disk = (string) config('examis.documents.disk');
        $orphans = collect(Storage::disk($disk)->allFiles())
            ->reject(fn (string $path): bool => str_starts_with($path, '.health/') || isset($referenced[$disk][$path]))
            ->count();

        $report['counts']['orphan_files'] = $orphans;
    }
}
