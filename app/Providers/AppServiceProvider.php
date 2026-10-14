<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Documents\DocumentScanner;
use App\Services\Documents\StructuralDocumentScanner;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentScanner::class, function (): DocumentScanner {
            return match (config('examis.documents.scanner')) {
                'structural' => new StructuralDocumentScanner((int) config('examis.documents.max_image_pixels')),
                default => throw new InvalidArgumentException('Unknown document scanner ['.config('examis.documents.scanner').'].'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Every staff permission is a Gate ability of the same name, so routes
     * can use `can:applications.view` and Blade/React receive plain strings.
     */
    protected function configureAuthorization(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by('upload|'.($request->user() === null ? $request->ip() : $request->user()->id));
        });

        RateLimiter::for('application-writes', function (Request $request) {
            return Limit::perMinute(60)->by('app-write|'.($request->user() === null ? $request->ip() : $request->user()->id));
        });

        RateLimiter::for('public-lookup', function (Request $request) {
            return [
                Limit::perMinute(10)->by('lookup|'.$request->ip()),
                Limit::perHour(100)->by('lookup-hour|'.$request->ip()),
            ];
        });
    }
}
