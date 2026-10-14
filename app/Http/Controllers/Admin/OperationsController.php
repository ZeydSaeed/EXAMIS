<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
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
            'environment' => [
                'scanner' => config('examis.documents.scanner'),
                'documents_disk' => config('examis.documents.disk'),
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
            ],
        ]);
    }
}
