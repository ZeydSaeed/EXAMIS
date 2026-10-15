/**
 * Locale-aware formatting. Dates are shown in the user's locale but with
 * Latin digits for Arabic so reference numbers and dates read the same in
 * printed receipts, SMS and the portal.
 */
function intlLocale(locale: string): string {
    return locale === 'ar' ? 'ar-IQ-u-nu-latn' : 'en-GB';
}

export function formatDateTime(
    value: string | null | undefined,
    locale: string,
): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(intlLocale(locale), {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export function formatDate(
    value: string | null | undefined,
    locale: string,
): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(intlLocale(locale), {
        dateStyle: 'long',
    }).format(new Date(value));
}

export function formatFileSize(bytes: number, locale: string): string {
    const formatter = new Intl.NumberFormat(intlLocale(locale), {
        maximumFractionDigits: 1,
    });

    if (bytes < 1024 * 1024) {
        return `${formatter.format(bytes / 1024)} KB`;
    }

    return `${formatter.format(bytes / (1024 * 1024))} MB`;
}

export function formatPercent(fraction: number, locale: string): string {
    return new Intl.NumberFormat(intlLocale(locale), {
        style: 'percent',
        maximumFractionDigits: 0,
    }).format(fraction);
}
