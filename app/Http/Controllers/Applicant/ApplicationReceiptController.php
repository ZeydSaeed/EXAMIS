<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Students\CloseStudentAccount;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Audit\AuditLogger;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class ApplicationReceiptController extends Controller
{
    /**
     * Generated on demand from the current persisted state; every issue is
     * audited (ADR-0007). The student opening their own form ends their
     * one-time account (ADR-0010); staff printing it do not.
     */
    public function show(Request $request, Application $application, ReceiptRenderer $renderer, AuditLogger $audit, CloseStudentAccount $closeAccount): Response
    {
        Gate::authorize('downloadReceipt', $application);

        $pdf = $renderer->render($application);

        $audit->record('receipt.downloaded', $application, ['status' => $application->status->value]);

        if ($request->user()->isApplicant()) {
            $closeAccount->handle($request->user(), $application);
        }

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
