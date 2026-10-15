import type { ImgHTMLAttributes } from 'react';

type Props = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'alt'> & {
    /** Accessible name; omit when the mark is decorative. */
    title?: string;
    /**
     * Surface the mark sits on. `dark` (green bar, navy sidebar/header) uses
     * the copy whose black book and lettering are pearl; `light` uses the original
     * colours and switches to the dark copy in dark mode.
     */
    surface?: 'dark' | 'light';
};

/**
 * EXAMIS mark: the supplied raster logo (graduate in a mortarboard over an
 * open book, "EXAMIS" wordmark) with its beige background removed. Two
 * transparent PNGs:
 * `examis-logo.png` (original colours) and `examis-logo-on-dark.png`.
 */
export function ExamisMark({
    title,
    surface = 'dark',
    className = '',
    ...props
}: Props) {
    const shared = {
        alt: title ?? '',
        'aria-hidden': title ? undefined : true,
        decoding: 'async' as const,
        draggable: false,
        ...props,
    };

    if (surface === 'dark') {
        return (
            <img
                src="/images/examis-logo-on-dark.png"
                className={`object-contain ${className}`}
                {...shared}
            />
        );
    }

    return (
        <>
            <img
                src="/images/examis-logo.png"
                className={`object-contain dark:hidden ${className}`}
                {...shared}
            />
            <img
                src="/images/examis-logo-on-dark.png"
                className={`hidden object-contain dark:block ${className}`}
                {...shared}
                alt=""
                aria-hidden
            />
        </>
    );
}
