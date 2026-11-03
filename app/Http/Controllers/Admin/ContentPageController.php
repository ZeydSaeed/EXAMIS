<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\DecideApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Models\MessageTemplate;
use App\Services\Audit\AuditLogger;
use App\Services\Receipts\ReceiptTexts;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/content/index', [
            'pages' => ContentPage::query()->whereNotIn('slug', ReceiptTexts::SLUGS)->orderBy('slug')->get()->map(fn (ContentPage $page): array => [
                'slug' => $page->slug,
                'title' => $page->localizedTitle(),
                'is_published' => $page->is_published,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]),
            // In the order of the printed form.
            'receiptPages' => ContentPage::query()->whereIn('slug', ReceiptTexts::SLUGS)->get()
                ->sortBy(fn (ContentPage $page): int|false => array_search($page->slug, ReceiptTexts::SLUGS, true))
                ->values()
                ->map(fn (ContentPage $page): array => [
                    'slug' => $page->slug,
                    'title' => $page->localizedTitle(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ]),
            'templates' => MessageTemplate::query()->orderBy('id')->get()->map(fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'title' => $template->title,
                'status' => $template->status->value,
                'status_label' => $template->status->label(),
                'body' => $template->body,
            ]),
            'decisionStatuses' => array_map(
                fn (ApplicationStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                DecideApplication::DECISIONS,
            ),
            'placeholders' => MessageTemplate::PLACEHOLDERS,
        ]);
    }

    public function edit(ContentPage $contentPage): Response
    {
        return Inertia::render('admin/content/edit', [
            'page' => $contentPage->only(['slug', 'title_ar', 'title_en', 'body_ar', 'body_en', 'is_published']),
            'receipt' => $this->isReceiptText($contentPage) ? [
                'kind' => $contentPage->slug === ReceiptTexts::LETTER ? 'letter' : ($contentPage->slug === ReceiptTexts::PLEDGE ? 'text' : 'list'),
                'placeholders' => $contentPage->slug === ReceiptTexts::LETTER ? ReceiptTexts::LETTER_PLACEHOLDERS : [],
            ] : null,
        ]);
    }

    public function update(Request $request, ContentPage $contentPage, AuditLogger $audit): RedirectResponse
    {
        $body = ['required', 'string', 'max:20000'];

        // The letter may only name the details the form knows how to fill.
        if ($contentPage->slug === ReceiptTexts::LETTER) {
            $body[] = function (string $attribute, string $value, Closure $fail): void {
                preg_match_all('/\{([^{}]*)\}/u', $value, $matches);

                if (array_diff($matches[1], ReceiptTexts::LETTER_PLACEHOLDERS) !== []) {
                    $fail(__('admin.errors.unknown_placeholder'));
                }
            };
        }

        $validated = $request->validate([
            'title_ar' => ['required', 'string', 'max:150'],
            'title_en' => ['required', 'string', 'max:150'],
            'body_ar' => $body,
            'body_en' => $body,
            'is_published' => ['required', 'boolean'],
        ]);

        // The printed form's texts are never published as portal pages.
        if ($this->isReceiptText($contentPage)) {
            $validated['is_published'] = false;
        }

        $contentPage->fill($validated);
        $contentPage->updated_by = $request->user()->id;
        $contentPage->save();

        $audit->record('content.updated', $contentPage, ['slug' => $contentPage->slug]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.saved')]);

        return to_route('admin.content.index');
    }

    private function isReceiptText(ContentPage $page): bool
    {
        return in_array($page->slug, ReceiptTexts::SLUGS, true);
    }
}
