<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Services\Receipts\ReceiptTexts;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function show(ContentPage $contentPage): Response
    {
        // The printed form's texts are edited as pages but never shown as one.
        abort_if(! $contentPage->is_published || in_array($contentPage->slug, ReceiptTexts::SLUGS, true), 404);

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
