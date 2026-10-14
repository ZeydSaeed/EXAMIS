import { toast } from 'sonner';

/**
 * Registers the service worker (public/sw.js). The worker only caches public
 * static assets (ADR-0008). When a new version is waiting, the user is asked
 * before it activates so an open form is never swapped out underneath them.
 */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    /*
     * Reload only after the user accepted an update. The first install also
     * fires `controllerchange` (clients.claim) and must not reload the page,
     * or a half-filled form would be lost.
     */
    let updateAccepted = false;

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                const promptUpdate = (worker: ServiceWorker) => {
                    const isArabic = document.documentElement.lang === 'ar';

                    toast(
                        isArabic
                            ? 'يتوفر إصدار جديد من المنصة.'
                            : 'A new version is available.',
                        {
                            duration: Infinity,
                            action: {
                                label: isArabic ? 'تحديث' : 'Reload',
                                onClick: () => {
                                    updateAccepted = true;
                                    worker.postMessage({
                                        type: 'SKIP_WAITING',
                                    });
                                },
                            },
                        },
                    );
                };

                if (registration.waiting) {
                    promptUpdate(registration.waiting);
                }

                registration.addEventListener('updatefound', () => {
                    const worker = registration.installing;

                    worker?.addEventListener('statechange', () => {
                        if (
                            worker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            promptUpdate(worker);
                        }
                    });
                });
            })
            .catch(() => {
                // The portal works without a service worker; nothing to recover.
            });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (updateAccepted) {
                updateAccepted = false;
                window.location.reload();
            }
        });
    });
}

/**
 * Drops every cache the worker holds. Called on logout so nothing from the
 * session survives on a shared device.
 */
export function clearServiceWorkerCaches(): void {
    navigator.serviceWorker?.controller?.postMessage({ type: 'CLEAR_CACHES' });

    if ('caches' in window) {
        void caches
            .keys()
            .then((keys) => Promise.all(keys.map((key) => caches.delete(key))));
    }
}
