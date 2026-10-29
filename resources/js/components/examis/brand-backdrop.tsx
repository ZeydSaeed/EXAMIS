/**
 * Decorative backdrop of the standalone entry pages (start, application
 * terms): brand glows over a faint grid. Place inside a `relative isolate`
 * container.
 */
export function BrandBackdrop() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0 -z-10">
            <div className="absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-[var(--examis-gold)]/20 blur-3xl dark:bg-[var(--examis-gold)]/10" />
            <div className="absolute -bottom-48 -left-32 size-[34rem] rounded-full bg-[var(--examis-green)]/20 blur-3xl dark:bg-[var(--examis-green)]/30" />
            <div className="absolute -right-32 -bottom-40 size-[30rem] rounded-full bg-[var(--examis-navy)]/15 blur-3xl dark:bg-sky-500/10" />
            <div className="absolute inset-0 bg-[linear-gradient(to_right,rgb(11_24_73/0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgb(11_24_73/0.05)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)] bg-[size:3rem_3rem] dark:bg-[linear-gradient(to_right,rgb(255_255_255/0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.05)_1px,transparent_1px)]" />
        </div>
    );
}
