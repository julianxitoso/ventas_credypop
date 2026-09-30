import type { SVGAttributes } from 'react';

/**
 * Isotipo de CREDYPOP: los dos círculos superpuestos del logo.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 34"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <circle cx="16" cy="17" r="15" fill="#2B3086" />
            <circle cx="31" cy="17" r="16" fill="#EB4F1A" />
        </svg>
    );
}
