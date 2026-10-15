/**
 * Decorative flat illustration (a team at a desk with floating cards and
 * screens), served as a static, cacheable asset from `public/images`. The
 * whole drawing is scaled to fit the content area, centred on its own mint
 * background colour so the area is filled edge to edge; in dark mode it is
 * dimmed. Hidden from assistive technology.
 */
export function DashboardBackdrop() {
    return (
        <div
            className="pointer-events-none absolute inset-0 -z-10 bg-background p-4"
            aria-hidden="true"
        >
            <img
                src="/images/admin-dashboard-backdrop.svg"
                alt=""
                decoding="async"
                className="size-full object-contain dark:opacity-20"
            />
        </div>
    );
}
