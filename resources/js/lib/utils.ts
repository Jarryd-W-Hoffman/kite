import type { InertiaLinkProps } from '@inertiajs/react';
import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export const editorWidthClass = {
    narrow: 'max-w-2xl',
    normal: 'max-w-3xl',
    wide: 'max-w-5xl',
    full: 'max-w-none',
} as const;

export const editorFontClass = {
    sans: 'font-sans',
    serif: 'font-serif',
    mono: 'font-mono',
} as const;

/** Whether the system asks for less motion: scroll and move things instantly instead. */
export function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
