import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/hooks/use-trans';

/**
 * Browser-history navigation ("previous page" / "next page"). Inertia keeps
 * every visit in the history stack, so back/forward restore real pages.
 * Arrows follow the reading direction: in Arabic "previous" points right.
 */
export function HistoryNav({ className = '' }: { className?: string }) {
    const { t, isRtl } = useTrans();
    const BackIcon = isRtl ? ChevronRight : ChevronLeft;
    const ForwardIcon = isRtl ? ChevronLeft : ChevronRight;
    const buttonClass =
        'size-8 rounded-full text-white hover:bg-white/15 hover:text-white';

    return (
        <div
            className={`flex items-center gap-1 ${className}`}
            role="group"
            aria-label={t('nav.history')}
        >
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={buttonClass}
                        onClick={() => window.history.back()}
                        aria-label={t('nav.previous_page')}
                        data-test="history-back"
                    >
                        <BackIcon className="size-5" aria-hidden />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{t('nav.previous_page')}</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={buttonClass}
                        onClick={() => window.history.forward()}
                        aria-label={t('nav.next_page')}
                        data-test="history-forward"
                    >
                        <ForwardIcon className="size-5" aria-hidden />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{t('nav.next_page')}</TooltipContent>
            </Tooltip>
        </div>
    );
}
