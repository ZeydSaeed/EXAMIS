<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Authenticate by username. Deactivated accounts fail with the same
     * generic message as a wrong password, so account state is not leaked.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('username', Str::lower((string) $request->input('username')))->first();

            if ($user === null || ! $user->isActive() || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canRegister' => Route::has('register'),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perHour((int) config('examis.security.login_attempts_per_ip_per_hour'))->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('student-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by('student-login|'.$request->ip()),
                Limit::perHour((int) config('examis.security.login_attempts_per_ip_per_hour'))->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('student-code', function (Request $request) {
            return [
                Limit::perMinute(5)->by('student-code|'.$request->session()->getId().'|'.$request->ip()),
                Limit::perHour((int) config('examis.security.login_attempts_per_ip_per_hour'))->by('login-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            return [
                Limit::perMinute(3)->by('register|'.$request->ip()),
                Limit::perDay(30)->by('register-day|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(3)->by('reset|'.Str::lower((string) $request->input('username')).'|'.$request->ip()),
                Limit::perHour(20)->by('reset-ip|'.$request->ip()),
            ];
        });
    }
}
