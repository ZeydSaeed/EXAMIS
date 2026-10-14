<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function show(ContentPage $contentPage): Response
    {
        abort_unless($contentPage->is_published, 404);

        return Inertia::render('public/page', [
            'page' => [
                'slug' => $contentPage->slug,
                'title' => $contentPage->localizedTitle(),
                'body' => $contentPage->localizedBody(),
                'updated_at' => $contentPage->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
