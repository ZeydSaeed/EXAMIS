<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Audit\AuditLogger;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class ApplicationReceiptController extends Controller
{
    /**
     * Generated on demand from the current persisted state; every issue is
     * audited (ADR-0007).
     */
    public function show(Application $application, ReceiptRenderer $renderer, AuditLogger $audit): Response
    {
        Gate::authorize('downloadReceipt', $application);

        $pdf = $renderer->render($application);

        $audit->record('receipt.downloaded', $application, ['status' => $application->status->value]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            // The Arabic name in filename*, with an ASCII name for older clients.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $renderer->filename($application).'.pdf',
                'receipt-'.$application->reference_number.'.pdf',
            ),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
