<?php

namespace App\Http\Responses;

use App\Services\Students\TrustedDevices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Sends a new student to their sign-in card, which shows their student
 * number, QR code and the one-time access code (ADR-0009). The registering
 * browser is remembered unless the student said it is a shared device.
 */
class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private TrustedDevices $devices) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        if ($request->boolean('trust_device') && $request->user() !== null) {
            $this->devices->trust($request, $request->user());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('auth.registered', ['username' => $request->user()?->username]),
            'duration' => 15000,
        ]);

        return to_route('student.card');
    }
}
