import type { ComponentProps } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-trans';

/**
 * Mobile number field: while empty, the (Arabic) placeholder reads right to
 * left; once digits are typed the field switches to left to right.
 */
export function MobileInput({
    defaultValue,
    onChange,
    ...props
}: Omit<ComponentProps<typeof Input>, 'type' | 'dir'>) {
    const { isRtl } = useTrans();
    const [empty, setEmpty] = useState(!defaultValue);

    return (
        <Input
            {...props}
            type="tel"
            inputMode="tel"
            defaultValue={defaultValue}
            dir={empty && isRtl ? 'rtl' : 'ltr'}
            onChange={(event) => {
                setEmpty(event.target.value === '');
                onChange?.(event);
            }}
        />
    );
}
