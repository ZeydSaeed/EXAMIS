<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Audit\AuditLogger;
use App\Services\Receipts\ReceiptRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ApplicationReceiptController extends Controller
{
    /**
     * Follows the current persisted state, rebuilt only when that changes;
     * every issue is audited (ADR-0007). Opens in the browser, or saves as a file with
     * `download`, which is how phones get it. Opening it closes nothing: the
     * student confirms the print on the final page (ADR-0010).
     */
    public function show(Request $request, Application $application, ReceiptRenderer $renderer, AuditLogger $audit): Response
    {
        Gate::authorize('downloadReceipt', $application);

        $pdf = $renderer->cached($application);

        $audit->record('receipt.downloaded', $application, ['status' => $application->status->value]);

        return $renderer->response($application, $pdf, $request->boolean('download'));
    }
}
