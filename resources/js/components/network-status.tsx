import { router } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { useTrans } from '@/hooks/use-trans';

/**
 * Announces offline state and failed requests. A failed request means the
 * outcome is unknown, so the message tells the user to check the status
 * rather than assuming success or failure.
 */
export function NetworkStatus() {
    const { t } = useTrans();
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );

    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        window.addEventListener('online', update);
        window.addEventListener('offline', update);

        const removeNetworkError = router.on('networkError', () => {
            toast.error(t('network.request_failed'), {
                id: 'network-error',
                duration: 10000,
            });
        });

        const removeHttpException = router.on('httpException', (event) => {
            const status = event.detail.response.status;

            if (status === 419) {
                toast.error(t('network.session_expired'), { id: 'http-error' });
                router.reload();

                return false;
            }

            if (status === 429) {
                toast.error(t('network.too_many_requests'), {
                    id: 'http-error',
                });

                return false;
            }

            if (status >= 500) {
                toast.error(t('network.server_error'), { id: 'http-error' });

                return false;
            }
        });

        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
            removeNetworkError();
            removeHttpException();
        };
    }, [t]);

    if (online) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className="no-print fixed inset-x-0 bottom-0 z-50 flex items-center justify-center gap-2 bg-warning px-4 py-2 text-sm font-medium text-black"
        >
            <WifiOff className="size-4" aria-hidden />
            {t('network.offline')}
        </div>
    );
}
