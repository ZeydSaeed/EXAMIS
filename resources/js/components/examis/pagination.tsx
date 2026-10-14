import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
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
