import { router } from '@inertiajs/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';

/** Distance from either end before the matching button appears. */
const THRESHOLD = 240;

/**
 * The element that scrolls on this page: the layout's `[data-scroll-root]`
 * panel when it overflows, otherwise the window.
 */
function scrollRoot(): HTMLElement {
    const panel = document.querySelector<HTMLElement>('[data-scroll-root]');

    if (panel && panel.scrollHeight > panel.clientHeight + 1) {
        return panel;
    }

    return (
        (document.scrollingElement as HTMLElement) ?? document.documentElement
    );
}

/**
 * Large floating "to top" and "to bottom" buttons with smooth scrolling,
 * shown only on pages that scroll and only when they help. Smooth motion
 * is skipped for people who ask their device to reduce motion.
 */
export function ScrollButtons({ className }: { className?: string }) {
    const { t } = useTrans();
    const [state, setState] = useState({ up: false, down: false });

    useEffect(() => {
        let frame = 0;
        const update = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const root = scrollRoot();
                const below =
                    root.scrollHeight - root.clientHeight - root.scrollTop;

                setState({
                    up: root.scrollTop > THRESHOLD,
                    down: below > THRESHOLD,
                });
            });
        };

        update();
        // Capture: scrolling inner panels does not bubble to the document.
        document.addEventListener('scroll', update, {
            capture: true,
            passive: true,
        });
        window.addEventListener('resize', update);
        const stopNavigate = router.on('navigate', update);
        const observer = new ResizeObserver(update);
        observer.observe(document.body);

        return () => {
            cancelAnimationFrame(frame);
            document.removeEventListener('scroll', update, { capture: true });
            window.removeEventListener('resize', update);
            stopNavigate();
            observer.disconnect();
        };
    }, []);

    const scroll = (to: 'top' | 'bottom') => {
        const root = scrollRoot();
        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        root.scrollTo({
            top: to === 'top' ? 0 : root.scrollHeight,
            behavior: reduce ? 'auto' : 'smooth',
        });
    };

    const button =
        'flex size-12 items-center justify-center rounded-full border border-[var(--examis-navy)]/15 bg-white/90 text-[var(--examis-navy)] shadow-lg backdrop-blur transition-all duration-300 hover:scale-105 hover:bg-white focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none active:scale-95 sm:size-14 dark:border-white/15 dark:bg-[#0a1230]/90 dark:text-white dark:hover:bg-[#0a1230]';

    return (
        <div
            className={cn(
                'no-print pointer-events-none fixed end-4 bottom-32 z-40 flex flex-col gap-3 sm:end-6 sm:bottom-28',
                className,
            )}
        >
            <button
                type="button"
                onClick={() => scroll('top')}
                aria-label={t('common.scroll_top')}
                title={t('common.scroll_top')}
                tabIndex={state.up ? 0 : -1}
                aria-hidden={!state.up}
                className={cn(
                    button,
                    state.up
                        ? 'pointer-events-auto opacity-100'
                        : 'translate-y-2 opacity-0',
                )}
                data-test="scroll-top"
            >
                <ChevronUp className="size-6 sm:size-7" aria-hidden />
            </button>
            <button
                type="button"
                onClick={() => scroll('bottom')}
                aria-label={t('common.scroll_bottom')}
                title={t('common.scroll_bottom')}
                tabIndex={state.down ? 0 : -1}
                aria-hidden={!state.down}
                className={cn(
                    button,
                    state.down
                        ? 'pointer-events-auto opacity-100'
                        : '-translate-y-2 opacity-0',
                )}
                data-test="scroll-bottom"
            >
                <ChevronDown className="size-6 sm:size-7" aria-hidden />
            </button>
        </div>
    );
}
