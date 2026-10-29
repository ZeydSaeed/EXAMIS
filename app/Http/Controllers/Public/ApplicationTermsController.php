<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The application terms step before registration. The terms are the
 * published "eligibility" content page, so staff edit them in the admin
 * content screen. Acceptance is kept in the session and required by
 * registration (see CreateNewUser).
 */
class ApplicationTermsController extends Controller
{
    public const SESSION_KEY = 'apply.terms_accepted_at';

    public const SLUG = 'eligibility';

    public function show(): Response
    {
        $page = ContentPage::query()
            ->where('slug', self::SLUG)
            ->where('is_published', true)
            ->first();

        return Inertia::render('apply/terms', [
            'title' => $page?->localizedTitle(),
            'terms' => $page !== null ? self::items($page->localizedBody()) : [],
            'updatedAt' => $page?->updated_at?->toIso8601String(),
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accept' => ['accepted']]);

        $request->session()->put(self::SESSION_KEY, now()->toIso8601String());

        return to_route('register');
    }

    public static function accepted(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    public static function acceptedAt(Request $request): ?Carbon
    {
        $value = $request->session()->get(self::SESSION_KEY);

        return is_string($value) ? Carbon::parse($value) : null;
    }

    /**
     * One item per paragraph, without the leading "١." / "1." numbering:
     * the page numbers the items itself.
     *
     * @return list<string>
     */
    private static function items(string $body): array
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim($body)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $paragraph): string => trim((string) preg_replace('/^\s*[0-9٠-٩]+\s*[.)\-–]\s*/u', '', $paragraph)),
            $paragraphs,
        )));
    }
}
