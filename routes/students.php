<?php

use App\Http\Controllers\Applicant;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\Public;
use App\Http\Middleware\KeepStudentsOnTheirPath;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public portal
|--------------------------------------------------------------------------
*/

Route::get('/', Public\StartController::class)->name('start');
Route::get('portal', Public\HomeController::class)->name('home');
Route::get('pages/{contentPage}', [Public\ContentPageController::class, 'show'])->name('pages.show');

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
| Students come in through the QR link; no page posts to student/login
| since the form was removed.
|
*/

Route::middleware('guest:web')->group(function () {
    Route::get('apply/terms', [Public\ApplicationTermsController::class, 'show'])->name('apply.terms');
    Route::post('apply/terms', [Public\ApplicationTermsController::class, 'accept'])->name('apply.terms.accept');

    Route::get('register', [StudentRegistrationController::class, 'create'])->name('register');
    Route::post('register', [StudentRegistrationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

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
| Applicant area
|--------------------------------------------------------------------------
*/

Route::middleware('auth:web')->group(function () {
    Route::post('student/logout', [StudentRegistrationController::class, 'destroy'])->name('student.logout');

    Route::get('dashboard', Applicant\DashboardController::class)->name('dashboard');

    Route::get('apply/specialization', [Applicant\SpecializationController::class, 'show'])->name('apply.specialization');
    Route::post('apply/specialization', [Applicant\SpecializationController::class, 'store'])
        ->middleware('throttle:application-writes')
        ->name('apply.specialization.store');

    Route::middleware(KeepStudentsOnTheirPath::class)->group(function () {
        Route::get('student/card', [Applicant\StudentCardController::class, 'show'])->name('student.card');
        Route::post('student/access-code', [Applicant\StudentCardController::class, 'regenerate'])
            ->middleware('throttle:6,1')
            ->name('student.access-code');

        Route::get('notifications', [Applicant\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [Applicant\NotificationController::class, 'markAllRead'])->name('notifications.read');
    });

    Route::middleware('throttle:application-writes')->group(function () {
        Route::post('applications', [Applicant\ApplicationController::class, 'store'])->name('applications.store');
        Route::put('applications/{application}', [Applicant\ApplicationController::class, 'update'])->name('applications.update');
        Route::post('applications/{application}/submit', [Applicant\ApplicationSubmissionController::class, 'store'])->name('applications.submit');
        Route::post('applications/{application}/finish', [Applicant\ApplicationSubmissionController::class, 'finish'])->name('applications.finish');
        Route::post('applications/{application}/withdraw', [Applicant\ApplicationWithdrawalController::class, 'store'])->name('applications.withdraw');
    });

    Route::post('applications/{application}/documents', [Applicant\ApplicationDocumentController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('applications.documents.store');

    Route::get('applications/{application}', [Applicant\ApplicationController::class, 'show'])->name('applications.show');
    Route::get('applications/{application}/edit', [Applicant\ApplicationController::class, 'edit'])->name('applications.edit');
    Route::get('applications/{application}/receipt', [Applicant\ApplicationReceiptController::class, 'show'])->name('applications.receipt');
    Route::get('applications/{application}/printed', [Applicant\ApplicationSubmissionController::class, 'printed'])->name('applications.printed');

    Route::get('documents/{document}', [Applicant\ApplicationDocumentController::class, 'show'])->name('documents.show');
});
