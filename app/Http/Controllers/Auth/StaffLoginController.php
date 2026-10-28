<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password sign-in for staff (and older applicant accounts that still have
 * a password). It posts to Fortify's login endpoint, so two-factor
 * challenges and login throttling apply unchanged.
 */
class StaffLoginController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('auth/staff-login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }
}
