<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Target of the receipt QR code. The random token proves the receipt is
 * genuine; only non-personal details are shown.
 */
class ReceiptVerificationController extends Controller
{
    public function show(string $token): Response
    {
        $application = Application::query()
            ->with('examPeriod')
            ->where('verification_token', $token)
            ->whereNotNull('reference_number')
            ->first();

        return Inertia::render('public/verify', [
            'receipt' => $application === null ? null : [
                'reference_number' => $application->reference_number,
                'exam_period' => $application->examPeriod->localizedName(),
                'status_label' => $application->status->label(),
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ],
        ]);
    }
}
