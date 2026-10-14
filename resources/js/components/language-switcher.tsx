import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-trans';
import { update } from '@/routes/locale';

/**
 * Switches between Arabic (RTL) and English (LTR). The choice is stored in
 * the session (and the account), then the page reloads its props.
 */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { locale } = useTrans();
    const target = locale === 'ar' ? 'en' : 'ar';

    return (
        <Button
            variant="ghost"
            size="sm"
            className={className}
            lang={target}
            onClick={() =>
                router.post(
                    update.url(),
                    { locale: target },
                    { preserveScroll: true },
                )
            }
            data-test="language-switcher"
        >
            <Languages className="size-4" aria-hidden />
            {target === 'en' ? 'English' : 'العربية'}
        </Button>
    );
}
