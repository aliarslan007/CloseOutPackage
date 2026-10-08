import { cn } from '@/lib/utils';
import { STATUS_DESCRIPTIONS, STATUS_LABELS, TrackingStatus } from '../types/tracking.types';

const BADGE_CLASSES: Record<TrackingStatus, string> = {
    active: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30',
    idle: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30',
    offline: 'bg-muted text-muted-foreground border-border',
};

const DOT_CLASSES: Record<TrackingStatus, string> = {
    active: 'bg-emerald-500',
    idle: 'bg-amber-500',
    offline: 'bg-gray-400',
};

export function TrackingStatusBadge({ status }: Readonly<{ status: TrackingStatus }>) {
    return (
        <span
            // Managers read this as "logged in / logged out", so spell out that it
            // tracks the phone's location reporting instead.
            title={STATUS_DESCRIPTIONS[status]}
            className={cn(
                'inline-flex shrink-0 cursor-help items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                BADGE_CLASSES[status]
            )}
        >
            <span className={cn('size-1.5 rounded-full', DOT_CLASSES[status])} />
            {STATUS_LABELS[status]}
        </span>
    );
}
