<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * The user is shared through an explicit allow-list (never the raw
     * model) and permissions are plain strings for UI hints only: the server
     * re-authorizes every action.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'organization' => $locale === 'en' ? config('examis.organization.name_en') : config('examis.organization.name_ar'),
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'translations' => Inertia::once(fn (): array => (array) trans('ui', [], $locale))->as('translations-'.$locale),
            'auth' => [
                'user' => $this->sharedUser($request->user()),
            ],
            'status' => $request->session()->get('status'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sharedUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'has_password' => $user->hasPassword(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'account_type' => $user->account_type->value,
            'locale' => $user->locale,
            'unread_notifications' => $user->isApplicant() ? $user->unreadNotifications()->count() : 0,
            'permissions' => array_values(array_map(
                fn (Permission $permission): string => $permission->value,
                array_filter(Permission::cases(), fn (Permission $permission): bool => $user->hasPermission($permission)),
            )),
        ];
    }
}
