import type { ComponentProps } from 'react';

import { ExamisMark } from '@/components/examis/examis-mark';

/**
 * Compact brand icon used by the auth and public layouts: the EXAMIS mark,
 * decorative (the surrounding link or heading carries the name). Pass
 * `surface="light"` where it sits on a light background.
 */
export default function AppLogoIcon(props: ComponentProps<typeof ExamisMark>) {
    return <ExamisMark {...props} />;
}
