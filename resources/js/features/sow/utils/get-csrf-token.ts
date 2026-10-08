/** Reads the XSRF-TOKEN cookie Laravel sets for axios; needed by the keepalive fetch. */
export function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
