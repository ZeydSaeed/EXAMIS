<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password sign-in for staff. It posts to Fortify's login endpoint, so
 * two-factor challenges and login throttling apply unchanged.
 */
class StaffLoginController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('auth/staff-login', [
            'status' => $request->session()->get('status'),
        ]);
    }
}
