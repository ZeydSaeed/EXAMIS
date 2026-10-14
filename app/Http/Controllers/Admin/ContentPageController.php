<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/content/index', [
            'pages' => ContentPage::query()->orderBy('slug')->get()->map(fn (ContentPage $page): array => [
                'slug' => $page->slug,
                'title' => $page->localizedTitle(),
                'is_published' => $page->is_published,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function edit(ContentPage $contentPage): Response
    {
        return Inertia::render('admin/content/edit', [
            'page' => $contentPage->only(['slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'is_published']),
        ]);
    }

    public function update(Request $request, ContentPage $contentPage, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'title_ar' => ['required', 'string', 'max:150'],
            'title_en' => ['required', 'string', 'max:150'],
            'body_ar' => ['required', 'string', 'max:20000'],
            'body_en' => ['required', 'string', 'max:20000'],
            'is_published' => ['required', 'boolean'],
        ]);

        $contentPage->fill($validated);
        $contentPage->updated_by = $request->user()->id;
        $contentPage->save();

        $audit->record('content.updated', $contentPage, ['slug' => $contentPage->slug]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return to_route('admin.content.index');
    }
}
