<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\ExamPeriod;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $periods = ExamPeriod::query()
            ->acceptingApplications()
            ->orderBy('closes_at')
            ->get()
            ->map(fn (ExamPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->localizedName(),
                'academic_year' => $period->academic_year,
                'opens_at' => $period->opens_at->toIso8601String(),
                'closes_at' => $period->closes_at->toIso8601String(),
            ]);

        $pages = ContentPage::query()
            ->where('is_published', true)
            ->whereIn('slug', ContentPage::PUBLIC_SLUGS)
            ->get(['slug', 'title_ar', 'title_en'])
            ->map(fn (ContentPage $page): array => ['slug' => $page->slug, 'title' => $page->localizedTitle()]);

        return Inertia::render('welcome', [
            'openPeriods' => $periods,
            'pages' => $pages,
            'canRegister' => Route::has('register'),
        ]);
    }
}
