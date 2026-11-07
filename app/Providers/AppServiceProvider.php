<?php

namespace App\Providers;

use App\Auth\AccountTypeUserProvider;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Documents\ClamAvDocumentScanner;
use App\Services\Documents\Clamd;
use App\Services\Documents\DocumentScanner;
use App\Services\Documents\StructuralDocumentScanner;
use App\Services\ReferenceData;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        $this->app->scoped(ReferenceData::class);

        $this->app->bind(DocumentScanner::class, function (): DocumentScanner {
            $structural = new StructuralDocumentScanner((int) config('examis.documents.max_image_pixels'));

            return match (config('examis.documents.scanner')) {
                'structural' => $structural,
                'clamav' => new ClamAvDocumentScanner($structural, new Clamd(
                    (string) config('examis.documents.clamav.socket'),
                    (int) config('examis.documents.clamav.timeout'),
                )),
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
        $this->configureRouteKeys();
    }

    /**
     * Applications and documents are found by UUID columns: anything else in
     * the path is a 404 before it reaches PostgreSQL, which would refuse it.
     */
    protected function configureRouteKeys(): void
    {
        Route::pattern('application', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
        Route::pattern('document', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
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

        // Only staff have passwords. The breach check calls an outside service,
        // so it runs in production only.
        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Every staff permission is a Gate ability of the same name, so routes
     * can use `can:applications.view` and Blade/React receive plain strings.
     */
    protected function configureAuthorization(): void
    {
        Auth::provider('account_type', fn ($app, array $config): AccountTypeUserProvider => new AccountTypeUserProvider(
            $app['hash'],
            $config['model'],
            $config['account_type'],
        ));

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
