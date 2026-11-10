<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends a new student to choose their branch and specialization, then on
 * to their sign-in card with the student number, QR code and one-time
 * access code (ADR-0009).
 */
class RegisterResponse implements Responsable
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return to_route('apply.specialization');
    }
}
