/**
 * Coarse relative time for conversation timestamps ("3d ago"). Deliberately
 * imprecise: on a review thread the reader needs the order and the rough lag,
 * not the exact minute.
 *
 * `now` is injectable so the output can be checked without freezing the clock.
 */
export function formatRelativeTime(iso: string | null | undefined, now: Date = new Date()): string {
    if (!iso) return '';

    const then = new Date(iso);
    if (Number.isNaN(then.getTime())) return '';

    const minutes = Math.max(0, Math.round((now.getTime() - then.getTime()) / 60_000));
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes}m ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;

    const days = Math.floor(hours / 24);
    if (days < 30) return `${days}d ago`;

    return then.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
