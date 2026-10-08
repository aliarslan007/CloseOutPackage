import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/**
 * Dynamically resolves asset URLs to support subfolder deployments (e.g. /cop/ on production)
 * even if assets were compiled with default /build/ base path.
 */
export function resolveAssetUrl(url: string): string {
    if (!url) return url;
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:')) {
        return url;
    }

    if (typeof globalThis !== 'undefined') {
        const pathname = globalThis.location.pathname;
        if (pathname.startsWith('/cop/') || pathname === '/cop') {
            if (url.startsWith('/build/')) {
                return `/cop${url}`;
            }
            if (url.startsWith('build/')) {
                return `/cop/${url}`;
            }
        }
    }

    return url;
}
