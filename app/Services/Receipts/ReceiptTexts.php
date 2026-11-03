<?php

namespace App\Services\Receipts;

use App\Models\ContentPage;
use InvalidArgumentException;

/**
 * Printed-form texts an administrator edits under Settings, kept as content
 * pages that the portal never shows. Lists hold one item per paragraph and
 * are numbered on the form. Until a page exists the form uses the wording
 * in lang/{ar,en}/receipt.php.
 */
final class ReceiptTexts
{
    public const DOCUMENTS = 'receipt-documents';

    public const STAGES = 'receipt-stages';

    public const PLEDGE = 'receipt-pledge';

    public const LETTER = 'receipt-letter';

    /** In the order of the printed form. */
    public const SLUGS = [self::LETTER, self::DOCUMENTS, self::STAGES, self::PLEDGE];

    /**
     * Filled in on the request letter: the student's details, or room left
     * to write by hand.
     */
    public const LETTER_PLACEHOLDERS = ['العام_الدراسي', 'الفرع', 'الاختصاص', 'فراغ_الصف', 'فراغ_المدرسة', 'سنة_الميلاد'];

    /**
     * The wording before any edit, in both languages.
     *
     * @return array{title_ar: string, title_en: string, body_ar: string, body_en: string}
     */
    public static function defaults(string $slug): array
    {
        $text = [];

        foreach (['ar', 'en'] as $locale) {
            $line = fn (string $key) => __('receipt.'.$key, [], $locale);

            [$title, $body] = match ($slug) {
                self::DOCUMENTS => [$line('documents_page.title'), implode("\n\n", $line('documents_page.items'))],
                self::STAGES => [rtrim($line('documents_page.stages_title'), ':'), implode("\n\n", $line('documents_page.stages'))],
                self::PLEDGE => [$line('pledge.title'), $line('pledge.text')],
                self::LETTER => [$line('letter.subject'), sprintf(
                    '%s {العام_الدراسي}%s %s ({الفرع}) %s ({الاختصاص})%s %s ({فراغ_الصف}) %s ({فراغ_المدرسة}) %s ({سنة_الميلاد}).',
                    $line('letter.request'),
                    $locale === 'ar' ? '،' : ',',
                    $line('letter.branch'),
                    $line('letter.specialization'),
                    $locale === 'ar' ? '،' : ',',
                    $line('letter.last_grade'),
                    $line('letter.school'),
                    $line('letter.birth_year'),
                )],
                default => throw new InvalidArgumentException("Not a printed form text: {$slug}"),
            };

            $text['title_'.$locale] = (string) $title;
            $text['body_'.$locale] = (string) $body;
        }

        /** @var array{title_ar: string, title_en: string, body_ar: string, body_en: string} $text */
        return $text;
    }

    /**
     * Each text in the current language, its body split into paragraphs.
     *
     * @return array<string, array{title: string, paragraphs: list<string>}>
     */
    public static function current(): array
    {
        $pages = ContentPage::query()->whereIn('slug', self::SLUGS)->get()->keyBy('slug');
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $texts = [];

        foreach (self::SLUGS as $slug) {
            $page = $pages->get($slug);
            $text = $page !== null ? $page->only(['title_'.$locale, 'body_'.$locale]) : self::defaults($slug);

            $texts[$slug] = [
                'title' => trim((string) $text['title_'.$locale]),
                'paragraphs' => self::paragraphs((string) $text['body_'.$locale]),
            ];
        }

        return $texts;
    }

    /**
     * Letter paragraph as HTML: the text escaped, each placeholder replaced by
     * its (already safe) HTML. A word stays joined to the brackets after it,
     * so no line starts with an empty pair.
     *
     * @param  array<string, string>  $values  placeholder => HTML
     */
    public static function fillLetter(string $paragraph, array $values): string
    {
        $html = str_replace(' (', '&nbsp;(', e($paragraph));

        foreach ($values as $placeholder => $value) {
            $html = str_replace('{'.$placeholder.'}', $value, $html);
        }

        return $html;
    }

    /**
     * @return list<string>
     */
    private static function paragraphs(string $body): array
    {
        $paragraphs = preg_split('/\R\s*\R/u', trim($body)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $paragraph): string => trim((string) preg_replace('/\s+/u', ' ', $paragraph)),
            $paragraphs,
        ), fn (string $paragraph): bool => $paragraph !== ''));
    }
}
