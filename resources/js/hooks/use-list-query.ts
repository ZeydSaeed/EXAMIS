import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Query = Record<string, string | number | undefined>;

type Paginator = {
    per_page: number;
    /** Offset paginators only; cursor paginators start over on resize. */
    current_page?: number;
    from?: number | null;
};

const MIN_ROWS = 5;
const MAX_ROWS = 100;
const SEARCH_DELAY_MS = 350;

/** Drops empty values so the URL only carries active filters. */
export function compactQuery(query: Query): Record<string, string> {
    return Object.fromEntries(
        Object.entries(query)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)]),
    );
}

/**
 * State shared by the staff list pages: filter URLs, live search (waits for
 * a pause in typing, then goes back to page one) and a page size that fits
 * as many rows as the table area can show without scrolling.
 */
export function useListQuery<F extends Query & { q?: string }>({
    url,
    filters,
    paginator,
}: {
    url: (options: { query: Record<string, string> }) => string;
    filters: F;
    paginator: Paginator;
}) {
    const [search, setSearch] = useState(filters.q ?? '');
    const tableArea = useRef<HTMLDivElement>(null);
    const { per_page: perPage, current_page: currentPage, from } = paginator;

    const urlFor = useCallback(
        (changes: Query) =>
            url({
                query: compactQuery({
                    ...filters,
                    per_page: perPage,
                    page: currentPage,
                    ...changes,
                }),
            }),
        [url, filters, perPage, currentPage],
    );

    const visit = useCallback(
        (changes: Query) =>
            router.get(
                urlFor(changes),
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            ),
        [urlFor],
    );

    useEffect(() => {
        if (search.trim() === (filters.q ?? '').trim()) {
            return;
        }

        const timer = window.setTimeout(
            () => visit({ q: search.trim(), page: 1, cursor: undefined }),
            SEARCH_DELAY_MS,
        );

        return () => window.clearTimeout(timer);
    }, [search, filters.q, visit]);

    useEffect(() => {
        const area = tableArea.current;

        if (!area) {
            return;
        }

        let timer: number | undefined;
        let navigating = false;

        // Once a visit to another page starts (e.g. the user opens a row),
        // stop fitting: a resize visit would cancel that navigation.
        const stopListening = router.on('before', (event) => {
            if (event.detail.visit.url.pathname !== window.location.pathname) {
                navigating = true;
                window.clearTimeout(timer);
            }
        });

        const fit = () => {
            if (navigating) {
                return;
            }

            const rem = parseFloat(
                getComputedStyle(document.documentElement).fontSize,
            );
            const frame = area.querySelector<HTMLElement>('.examis-data-table');
            const head = frame?.querySelector('thead')?.offsetHeight;
            const row = frame
                ?.querySelector('tbody tr')
                ?.getBoundingClientRect().height;
            const scrollbar =
                frame && frame.scrollWidth > frame.clientWidth
                    ? frame.offsetHeight - frame.clientHeight
                    : 0;
            const rowHeight = row || rem * 2.4;
            const rows = Math.floor(
                (area.clientHeight - (head || rowHeight) - scrollbar - 2) /
                    rowHeight,
            );
            const fitted = Math.min(MAX_ROWS, Math.max(MIN_ROWS, rows));

            if (fitted !== perPage) {
                visit({
                    per_page: fitted,
                    page:
                        currentPage === undefined
                            ? undefined
                            : Math.floor(((from ?? 1) - 1) / fitted) + 1,
                    cursor: undefined,
                });
            }
        };

        const observer = new ResizeObserver(() => {
            window.clearTimeout(timer);
            timer = window.setTimeout(fit, 150);
        });

        observer.observe(area);

        return () => {
            stopListening();
            observer.disconnect();
            window.clearTimeout(timer);
        };
    }, [perPage, currentPage, from, visit]);

    return { search, setSearch, urlFor, visit, tableArea };
}
