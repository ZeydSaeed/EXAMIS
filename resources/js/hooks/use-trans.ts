import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

type Dictionary = { [key: string]: string | Dictionary };

function lookup(
    dictionary: Dictionary | undefined,
    key: string,
): string | undefined {
    let node: string | Dictionary | undefined = dictionary;

    for (const segment of key.split('.')) {
        if (node === undefined || typeof node === 'string') {
            return undefined;
        }

        node = node[segment];
    }

    return typeof node === 'string' ? node : undefined;
}

/**
 * Translates `ui.*` keys shared by the server (lang/{locale}/ui.php).
 * Replacements use Laravel's `:name` placeholder syntax. A missing key
 * renders the key itself so gaps are visible instead of blank.
 */
export function useTrans() {
    const { translations, locale, direction } = usePage().props;

    const t = useCallback(
        (
            key: string,
            replacements: Record<string, string | number> = {},
        ): string => {
            const text =
                lookup(translations as Dictionary | undefined, key) ?? key;

            // One pass over whole placeholder names, so `:to` never eats the
            // start of `:total`.
            return text.replace(
                /:([A-Za-z_]+)/g,
                (placeholder, name: string) =>
                    name in replacements
                        ? String(replacements[name])
                        : placeholder,
            );
        },
        [translations],
    );

    return { t, locale, direction, isRtl: direction === 'rtl' };
}
