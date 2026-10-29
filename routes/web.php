<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Applicant;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Public;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public portal
|--------------------------------------------------------------------------
*/

Route::get('/', Public\StartController::class)->name('start');
Route::get('portal', Public\HomeController::class)->name('home');
Route::get('pages/{contentPage}', [Public\ContentPageController::class, 'show'])->name('pages.show');
Route::post('locale', [Public\LocaleController::class, 'update'])->name('locale.update');
Route::get('health/ready', Public\ReadinessController::class)->name('health.ready');

Route::get('track', [Public\TrackApplicationController::class, 'create'])->name('track.create');
Route::post('track', [Public\TrackApplicationController::class, 'store'])
    ->middleware('throttle:public-lookup')
    ->name('track.store');

Route::get('verify/{token}', [Public\ReceiptVerificationController::class, 'show'])
    ->whereUuid('token')
    ->middleware('throttle:public-lookup')
    ->name('receipts.verify');

/*
|--------------------------------------------------------------------------
| Student sign-in (one identifier + trusted device or access code, ADR-0009)
|--------------------------------------------------------------------------
|
| GET /login (Fortify) renders the student form. Staff sign in with a
| password at /staff/login, which posts to Fortify's own login endpoint.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('apply/terms', [Public\ApplicationTermsController::class, 'show'])->name('apply.terms');
    Route::post('apply/terms', [Public\ApplicationTermsController::class, 'accept'])->name('apply.terms.accept');

    Route::get('staff/login', StaffLoginController::class)->name('staff.login');

    Route::post('student/login', [StudentLoginController::class, 'store'])
        ->middleware('throttle:student-login')
        ->name('student.login');
    Route::get('student/verify', [StudentLoginController::class, 'code'])->name('student.code');
    Route::post('student/verify', [StudentLoginController::class, 'verify'])
        ->middleware('throttle:student-code')
        ->name('student.verify');
});

Route::get('s/{publicId}', [StudentLoginController::class, 'qr'])
    ->whereUuid('publicId')
    ->middleware('throttle:student-login')
    ->name('student.qr');

/*
|--------------------------------------------------------------------------
| Password reset (by username, see ADR-0002)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Applicant area
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Applicant\DashboardController::class)->name('dashboard');

    Route::get('student/card', [Applicant\StudentCardController::class, 'show'])->name('student.card');
    Route::post('student/access-code', [Applicant\StudentCardController::class, 'regenerate'])
        ->middleware('throttle:6,1')
        ->name('student.access-code');

    Route::middleware('throttle:application-writes')->group(function () {
        Route::post('applications', [Applicant\ApplicationController::class, 'store'])->name('applications.store');
        Route::put('applications/{application}', [Applicant\ApplicationController::class, 'update'])->name('applications.update');
        Route::post('applications/{application}/submit', [Applicant\ApplicationSubmissionController::class, 'store'])->name('applications.submit');
        Route::post('applications/{application}/withdraw', [Applicant\ApplicationWithdrawalController::class, 'store'])->name('applications.withdraw');
    });

    Route::post('applications/{application}/documents', [Applicant\ApplicationDocumentController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('applications.documents.store');

    Route::get('applications/{application}', [Applicant\ApplicationController::class, 'show'])->name('applications.show');
    Route::get('applications/{application}/edit', [Applicant\ApplicationController::class, 'edit'])->name('applications.edit');
    Route::get('applications/{application}/receipt', [Applicant\ApplicationReceiptController::class, 'show'])->name('applications.receipt');

    Route::get('documents/{document}', [Applicant\ApplicationDocumentController::class, 'show'])->name('documents.show');

    Route::get('notifications', [Applicant\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [Applicant\NotificationController::class, 'markAllRead'])->name('notifications.read');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:admin.access', 'staff.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('home', 'admin/home')->name('home');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('applications', [Admin\ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [Admin\ApplicationController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/transition', [Admin\ApplicationTransitionController::class, 'store'])->name('applications.transition');
        Route::post('applications/{application}/assign', [Admin\ApplicationAssignmentController::class, 'store'])->name('applications.assign');

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
        });

        Route::middleware('can:users.manage')->group(function () {
            Route::get('users', [Admin\StaffUserController::class, 'index'])->name('users.index');
            Route::post('users', [Admin\StaffUserController::class, 'store'])->name('users.store');
            Route::put('users/{user}/roles', [Admin\StaffUserController::class, 'updateRoles'])->name('users.roles');
            Route::put('users/{user}/status', [Admin\StaffUserController::class, 'updateStatus'])->name('users.status');
            Route::post('students/{user}/access-code', [Admin\StudentAccessController::class, 'store'])->name('students.access-code');
        });

        Route::get('audit', [Admin\AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit.index');
        Route::get('operations', Admin\OperationsController::class)->middleware('can:operations.view')->name('operations');

        Route::middleware('can:reports.view')->group(function () {
            Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{period}/export', [Admin\ReportController::class, 'export'])->name('reports.export');
        });
    });

require __DIR__.'/settings.php';
