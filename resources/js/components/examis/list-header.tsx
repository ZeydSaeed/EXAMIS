import { Search, X } from 'lucide-react';
import type { ComponentProps, FormEvent, ReactNode } from 'react';
import { HeaderSlot } from '@/components/header-slots';
import { useTrans } from '@/hooks/use-trans';
import { cn } from '@/lib/utils';

/**
 * Class names for header-bar actions (buttons and links): square icon
 * buttons, labelled with `aria-label` and `title`.
 */
export const headerButtonClassName =
    'inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-white text-(--examis-navy)! shadow-sm transition-colors outline-none hover:bg-white/90 hover:text-(--examis-navy)! focus-visible:ring-2 focus-visible:ring-white/50 [&_svg]:size-4';

/**
 * Controls the staff list pages place in the app header bar: filters in the
 * start slot, the search box in the centre slot and page actions in the end
 * slot.
 */
export function ListHeader({
    search,
    onSearchChange,
    onSearchSubmit,
    placeholder,
    compactSearch = false,
    actions,
    children,
}: {
    search?: string;
    onSearchChange?: (value: string) => void;
    onSearchSubmit?: () => void;
    placeholder?: string;
    /** Narrower search box, for pages with several header filters. */
    compactSearch?: boolean;
    /** Page actions (e.g. "New cycle") for the end slot. */
    actions?: ReactNode;
    /** Filters for the start slot. */
    children?: ReactNode;
}) {
    const { t } = useTrans();

    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSearchSubmit?.();
    };

    return (
        <>
            {children && (
                <HeaderSlot name="start">
                    <div className="ms-4 flex min-w-0 items-center gap-2">
                        {children}
                    </div>
                </HeaderSlot>
            )}

            {actions && <HeaderSlot name="end">{actions}</HeaderSlot>}

            {onSearchChange && (
                <HeaderSlot name="center">
                    <form
                        onSubmit={submit}
                        role="search"
                        className={cn(
                            'relative',
                            compactSearch
                                ? 'w-[clamp(9rem,20vw,16rem)]'
                                : 'w-[clamp(9rem,34vw,30rem)]',
                        )}
                    >
                        <Search
                            className="pointer-events-none absolute inset-y-0 start-3 my-auto size-4 text-white/70"
                            aria-hidden
                        />
                        <input
                            type="search"
                            value={search ?? ''}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            placeholder={placeholder}
                            aria-label={t('admin.search')}
                            className="h-9 w-full rounded-full border border-white/25 bg-white/10 ps-9 pe-9 text-sm text-white shadow-inner transition-colors outline-none placeholder:text-white/65 hover:bg-white/15 focus:border-white/60 focus:bg-white/20 focus:ring-2 focus:ring-white/25 [&::-webkit-search-cancel-button]:hidden"
                        />
                        {search !== undefined && search !== '' && (
                            <button
                                type="button"
                                onClick={() => onSearchChange('')}
                                className="absolute inset-y-0 end-2 my-auto grid size-6 place-items-center rounded-full text-white/75 hover:bg-white/15 hover:text-white"
                                aria-label={t('admin.clear_search')}
                            >
                                <X className="size-3.5" aria-hidden />
                            </button>
                        )}
                    </form>
                </HeaderSlot>
            )}
        </>
    );
}

/**
 * A filter in the header bar. Unless `required`, the empty option reads
 * "<label>: All".
 */
export function HeaderFilter({
    label,
    value,
    options,
    onChange,
    required = false,
    className,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    required?: boolean;
    className?: string;
}) {
    const { t } = useTrans();

    return (
        <select
            aria-label={label}
            className={cn(
                'h-8 max-w-44 min-w-16 shrink cursor-pointer truncate rounded-md border border-white/25 bg-white/10 ps-2.5 pe-1.5 text-sm text-white transition-colors outline-none hover:bg-white/15 focus-visible:border-white/60 focus-visible:ring-2 focus-visible:ring-white/30 [&_option]:bg-background [&_option]:text-foreground',
                className,
            )}
            value={value}
            onChange={(event) => onChange(event.target.value)}
        >
            {!required && (
                <option value="">
                    {label}: {t('common.all')}
                </option>
            )}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

/** Removable chip for a filter that has no control in the header. */
export function HeaderFilterChip({
    label,
    onRemove,
}: {
    label: string;
    onRemove: () => void;
}) {
    const { t } = useTrans();

    return (
        <button
            type="button"
            onClick={onRemove}
            className="hidden h-7 max-w-40 items-center gap-1 rounded-full bg-white/15 ps-2.5 pe-1.5 text-xs text-white hover:bg-white/25 lg:inline-flex"
            aria-label={`${t('admin.remove_filter')}: ${label}`}
        >
            <span className="truncate">{label}</span>
            <X className="size-3.5 shrink-0" aria-hidden />
        </button>
    );
}

/** Icon button for the header end slot (e.g. "Add staff"). */
export function HeaderButton({
    className,
    ...props
}: ComponentProps<'button'>) {
    return (
        <button
            type="button"
            className={cn(headerButtonClassName, className)}
            {...props}
        />
    );
}
