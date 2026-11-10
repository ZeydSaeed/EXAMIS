<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\ApplicationTermsController;
use App\Http\Responses\RegisterResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registration lives on the student portal under the student guard. Fortify
 * serves the staff portal only and has a single guard, so it cannot do this.
 */
class StudentRegistrationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        // Registration starts with reading and accepting the application terms.
        return ApplicationTermsController::accepted($request)
            ? Inertia::render('auth/register')
            : to_route('apply.terms');
    }

    public function store(Request $request, CreateNewUser $creator, RegisterResponse $response): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->login($creator->create($request->all()));

        $request->session()->regenerate();

        return $response->toResponse($request);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::clearHistory();

        return to_route('start');
    }
}
