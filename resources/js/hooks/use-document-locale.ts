import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Keeps <html lang/dir> in sync with the server locale, including after a
 * language switch that redirects back to the same page.
 */
export function useDocumentLocale(): void {
    const { locale, direction } = usePage().props;

    useEffect(() => {
        document.documentElement.lang = locale;
        document.documentElement.dir = direction;
    }, [locale, direction]);
}
