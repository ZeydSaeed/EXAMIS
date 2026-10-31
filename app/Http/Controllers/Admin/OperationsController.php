<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Operations\HealthReport;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function __invoke(HealthReport $health): Response
    {
        return Inertia::render('admin/operations', [
            'checks' => $health->checks(),
            'queue' => $health->queueMetrics(),
            'security' => [
                'failed_logins_last_hour' => AuditEvent::query()->where('action', 'auth.failed')->where('created_at', '>=', now()->subHour())->count(),
                'lockouts_last_hour' => AuditEvent::query()->where('action', 'auth.lockout')->where('created_at', '>=', now()->subHour())->count(),
            ],
            'student_numbers' => $this->studentNumbers(),
            'environment' => [
                'scanner' => config('examis.documents.scanner'),
                'documents_disk' => config('examis.documents.disk'),
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
            ],
        ]);
    }

    /**
     * Every registration takes a number for good, so the range only ever
     * fills up (ADR-0010).
     *
     * @return array{used: int, remaining: int, warn_above: int}
     */
    private function studentNumbers(): array
    {
        $min = (int) config('examis.students.student_number_min');
        $max = (int) config('examis.students.student_number_max');
        $capacity = $max - $min + 1;
        $used = User::query()->whereBetween('student_number', [$min, $max])->count();

        return [
            'used' => $used,
            'remaining' => $capacity - $used,
            'warn_above' => (int) floor($capacity * 0.8),
        ];
    }
}
