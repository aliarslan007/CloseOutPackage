/**
 * Formatting helpers shared by the change-order pages and cards.
 */

export function formatCurrency(amount: number | string): string {
    const num = typeof amount === 'number' ? amount : Number.parseFloat(String(amount)) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(num);
}

export function formatDate(isoString?: string | null): string {
    if (!isoString) return '—';
    try {
        const d = new Date(isoString);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return isoString;
    }
}

export function formatTime(isoString?: string | null): string {
    if (!isoString) return '';
    try {
        const d = new Date(isoString);
        return d.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return '';
    }
}

export function getInitials(name?: string): string {
    if (!name) return 'CO';
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase() ?? '')
        .join('');
}

export function formatVendors(vendors?: string[] | null): string {
    return vendors?.join(', ') || '';
}

export function getOccurrenceDateBadgeInfo(dateString?: string | null): {
    label: string;
    variant: 'future' | 'today' | 'past' | 'none';
} {
    if (!dateString) {
        return { label: '', variant: 'none' };
    }
    const today = new Date().toISOString().split('T')[0];
    const target = dateString.split('T')[0];
    if (target > today) {
        return { label: 'Scheduled (Upcoming)', variant: 'future' };
    }
    if (target === today) {
        return { label: 'Occurred Today', variant: 'today' };
    }
    return { label: 'Occurred on Site', variant: 'past' };
}

export const getFieldDateBadgeInfo = getOccurrenceDateBadgeInfo;

