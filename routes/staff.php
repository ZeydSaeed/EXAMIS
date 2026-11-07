<?php

use App\Enums\Portal;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Applicant;
use App\Http\Controllers\Auth\StaffInvitationController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff sign-in and one-time links (ADR-0011)
|--------------------------------------------------------------------------
|
| The sign-in form posts to Fortify's login endpoint, which serves this
| portal only. `/` shares its path with the student start page, so it stays
| out of Wayfinder's run, which has no hosts to tell them apart.
|
*/

if (Portal::Staff->routeDomain() !== null) {
    Route::get('/', fn () => to_route(Auth::guard('staff')->check() ? 'admin.home' : 'staff.login'))->name('staff.root');
}

Route::middleware('guest:staff')->group(function () {
    Route::get('staff/login', StaffLoginController::class)->name('staff.login');

    Route::get('staff/invitation', [StaffInvitationController::class, 'show'])->name('staff.invitation.show');
    Route::post('staff/invitation/inspect', [StaffInvitationController::class, 'inspect'])
        ->middleware('throttle:staff-invitation')
        ->name('staff.invitation.inspect');
    Route::post('staff/invitation', [StaffInvitationController::class, 'store'])
        ->middleware('throttle:staff-invitation')
        ->name('staff.invitation.store');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:staff', 'can:admin.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('home', 'admin/home')->name('home');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('applications', [Admin\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [Admin\ApplicationController::class, 'show'])->name('applications.show');
        Route::get('applications/{application}/receipt', [Applicant\ApplicationReceiptController::class, 'show'])->name('applications.receipt');
        Route::post('applications/{application}/transition', [Admin\ApplicationTransitionController::class, 'store'])->name('applications.transition');
        Route::post('applications/{application}/decision', [Admin\ApplicationDecisionController::class, 'store'])->name('applications.decision');
        Route::post('applications/{application}/assign', [Admin\ApplicationAssignmentController::class, 'store'])->name('applications.assign');

        Route::get('documents/{document}', [Applicant\ApplicationDocumentController::class, 'show'])->name('documents.show');

        Route::middleware('can:periods.manage')->group(function () {
            Route::get('periods', [Admin\ExamPeriodController::class, 'index'])->name('periods.index');
            Route::get('periods/create', [Admin\ExamPeriodController::class, 'create'])->name('periods.create');
            Route::post('periods', [Admin\ExamPeriodController::class, 'store'])->name('periods.store');
            Route::get('periods/{period}/edit', [Admin\ExamPeriodController::class, 'edit'])->name('periods.edit');
            Route::put('periods/{period}', [Admin\ExamPeriodController::class, 'update'])->name('periods.update');
        });

        Route::middleware('can:content.manage')->group(function () {
            Route::get('content', [Admin\ContentPageController::class, 'index'])->name('content.index');
            Route::get('content/{contentPage}/edit', [Admin\ContentPageController::class, 'edit'])->name('content.edit');
            Route::put('content/{contentPage}', [Admin\ContentPageController::class, 'update'])->name('content.update');

            Route::post('message-templates', [Admin\MessageTemplateController::class, 'store'])->name('message-templates.store');
            Route::put('message-templates/{messageTemplate}', [Admin\MessageTemplateController::class, 'update'])->name('message-templates.update');
            Route::delete('message-templates/{messageTemplate}', [Admin\MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');
        });

        Route::middleware('can:users.manage')->group(function () {
            Route::get('users', [Admin\StaffUserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\StaffUserController::class, 'store'])->name('users.store');
            Route::put('users/{user}/phone', [Admin\StaffUserController::class, 'updatePhone'])->name('users.phone');
            Route::put('users/{user}/roles', [Admin\StaffUserController::class, 'updateRoles'])->name('users.roles');
            Route::put('users/{user}/permissions', [Admin\StaffUserController::class, 'updatePermissions'])->name('users.permissions');
            Route::put('users/{user}/status', [Admin\StaffUserController::class, 'updateStatus'])->name('users.status');
            Route::put('users/{user}/account', [Admin\StaffUserController::class, 'updateAccount'])->middleware('can:staff.credentials')->name('users.account');
            Route::post('students/{user}/access-code', [Admin\StudentAccessController::class, 'store'])->name('students.access-code');
        });

        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{period}/export', [Admin\ReportController::class, 'export'])->name('reports.export');

            Route::middleware('can:applications.view_all')->group(function () {
                Route::get('student-export', [Admin\StudentExportController::class, 'index'])->name('student-export.index');
                Route::get('student-export/download', [Admin\StudentExportController::class, 'download'])->name('student-export.download');
            });
        });
    });

/*
|--------------------------------------------------------------------------
| Staff account settings
|--------------------------------------------------------------------------
*/

Route::middleware('auth:staff')->group(function () {
    Route::get('settings', fn () => to_route('profile.edit'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Administrators set everyone else's password; only they change their own.
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(['can:users.manage', RequirePassword::class])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['throttle:6,1', 'can:users.manage'])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
