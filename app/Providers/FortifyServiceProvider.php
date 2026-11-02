<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * A bcrypt hash of a discarded random value, checked when no account
     * matched so that case takes as long as a wrong password.
     */
    private const UNUSABLE_HASH = '$2y$12$HxPf28n8172LkwiS.REQDulRTzalpsxyH3zVG/4/AhMxjIY/S0En.';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Authenticate staff by email (ADR-0011). Student, unknown and deactivated
     * accounts fail like a wrong password, and with the same cost, so nothing
     * about an account is leaked.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->staff()->whereRaw('lower(email) = ?', [Str::lower(trim((string) $request->input('email')))])->first();

            $passwordMatches = Hash::check((string) $request->input('password'), $user->password ?? self::UNUSABLE_HASH);

            return $passwordMatches && $user?->isActive() ? $user : null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        // The form lives at /staff/login; Fortify's own /login only leads there.
        Fortify::loginView(fn () => to_route('staff.login'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
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

        RateLimiter::for('staff-invitation', function (Request $request) {
            return [
                Limit::perMinute(10)->by('staff-invitation|'.$request->ip()),
                Limit::perHour(60)->by('staff-invitation-hour|'.$request->ip()),
            ];
        });
    }
}
