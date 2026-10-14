import type { SVGAttributes } from 'react';

/**
 * EXAMIS mark: an exam paper with a check. Placeholder until the authority
 * provides its approved emblem.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M9 3h16l8 8v24a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm15 2.5V12h6.5L24 5.5ZM12 17v2.5h16V17H12Zm0 5v2.5h9V22h-9Zm11.3 6.9-2.1-2.1-1.8 1.8 3.9 3.9 7.1-7.1-1.8-1.8-5.3 5.3Z" />
        </svg>
    );
}
