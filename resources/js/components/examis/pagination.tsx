import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';

/**
 * Previous / next navigation that works for both offset and cursor
 * paginators; arrows follow the reading direction.
 */
export function Pagination({
    prev,
    next,
    summary,
}: {
    prev: string | null;
    next: string | null;
    summary?: string;
}) {
    const { t, isRtl } = useTrans();
    const Back = isRtl ? ChevronRight : ChevronLeft;
    const Forward = isRtl ? ChevronLeft : ChevronRight;

    if (!prev && !next) {
        return summary ? (
            <p className="text-sm text-muted-foreground">{summary}</p>
        ) : null;
    }

    return (
        <nav
            className="no-print flex items-center justify-between gap-3"
            aria-label={t('common.pagination')}
        >
            <p className="text-sm text-muted-foreground">{summary}</p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild={!!prev}
                    disabled={!prev}
                >
                    {prev ? (
                        <Link href={prev} preserveScroll prefetch>
                            <Back className="size-4" aria-hidden />
                            {t('common.previous')}
                        </Link>
                    ) : (
                        <span>
                            <Back className="size-4" aria-hidden />
                            {t('common.previous')}
                        </span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    asChild={!!next}
                    disabled={!next}
                >
                    {next ? (
                        <Link href={next} preserveScroll prefetch>
                            {t('common.next')}
                            <Forward className="size-4" aria-hidden />
                        </Link>
                    ) : (
                        <span>
                            {t('common.next')}
                            <Forward className="size-4" aria-hidden />
                        </span>
                    )}
                </Button>
            </div>
        </nav>
    );
}

/** Page numbers around the current page, with gaps marked by `null`. */
function pageWindow(current: number, last: number): (number | null)[] {
    const pages = new Set([1, last, current - 1, current, current + 1]);
    const sorted = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page],
    );
}

/**
 * Numbered navigation for offset paginators; `hrefFor` builds each page's URL
 * so the current filters are kept.
 */
export function NumberedPagination({
    current,
    last,
    hrefFor,
    summary,
}: {
    current: number;
    last: number;
    hrefFor: (page: number) => string;
    summary?: string;
}) {
    const { t, isRtl } = useTrans();
    const Back = isRtl ? ChevronRight : ChevronLeft;
    const Forward = isRtl ? ChevronLeft : ChevronRight;

    const step = (page: number, label: string, icon: ReactNode) =>
        page >= 1 && page <= last ? (
            <Button variant="outline" size="icon" className="size-8" asChild>
                <Link
                    href={hrefFor(page)}
                    preserveState
                    preserveScroll
                    prefetch
                    aria-label={label}
                >
                    {icon}
                </Link>
            </Button>
        ) : (
            <Button
                variant="outline"
                size="icon"
                className="size-8"
                disabled
                aria-label={label}
            >
                {icon}
            </Button>
        );

    return (
        <nav
            className="no-print flex flex-wrap items-center justify-between gap-3"
            aria-label={t('common.pagination')}
        >
            <p className="text-sm text-muted-foreground">{summary}</p>
            {last > 1 && (
                <div className="flex items-center gap-1">
                    {step(
                        current - 1,
                        t('common.previous'),
                        <Back className="size-4" aria-hidden />,
                    )}
                    {pageWindow(current, last).map((page, index) =>
                        page === null ? (
                            <span
                                key={`gap-${index}`}
                                className="px-1 text-sm text-muted-foreground"
                                aria-hidden
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={page}
                                variant={
                                    page === current ? 'default' : 'outline'
                                }
                                size="sm"
                                className="h-8 min-w-8 px-2 tabular-nums"
                                asChild
                            >
                                <Link
                                    href={hrefFor(page)}
                                    preserveState
                                    preserveScroll
                                    prefetch
                                    aria-current={
                                        page === current ? 'page' : undefined
                                    }
                                >
                                    {page}
                                </Link>
                            </Button>
                        ),
                    )}
                    {step(
                        current + 1,
                        t('common.next'),
                        <Forward className="size-4" aria-hidden />,
                    )}
                </div>
            )}
        </nav>
    );
}
