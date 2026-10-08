import { cn } from '@/lib/utils';
import type { CompositeStatus } from '../../types/sow.types';

const DARK_BADGE_BG = 'bg-slate-900/95';

const STATUS_META: Record<
    CompositeStatus,
    { label: string; dot: string; text: string; bg: string }
> = {
    not_needed: {
        label: 'Original (No edits)',
        dot: 'bg-muted-foreground/60',
        text: 'text-foreground',
        bg: 'bg-background/95',
    },
    pending: {
        label: 'Generating…',
        dot: 'bg-amber-400',
        text: 'text-amber-300',
        bg: DARK_BADGE_BG,
    },
    processing: {
        label: 'Rendering…',
        dot: 'bg-blue-400 animate-pulse',
        text: 'text-blue-300',
        bg: DARK_BADGE_BG,
    },
    ready: {
        label: 'Annotated',
        dot: 'bg-emerald-400',
        text: 'text-emerald-300',
        bg: DARK_BADGE_BG,
    },
    failed: {
        label: 'Render failed',
        dot: 'bg-rose-400',
        text: 'text-rose-300',
        bg: DARK_BADGE_BG,
    },
};

export interface SowCompositeStatusBadgeProps {
    status: CompositeStatus;
    className?: string;
}

/** Per-thumbnail compositing status with high contrast readability. */
export function SowCompositeStatusBadge({
    status,
    className,
}: Readonly<SowCompositeStatusBadgeProps>) {
    const meta = STATUS_META[status];

    return (
        <span
            title={meta.label}
            className={cn(
                'inline-flex items-center gap-1.5 rounded border border-border/80 px-1.5 py-0.5 text-[10px] font-semibold shadow-xs backdrop-blur-xs',
                meta.bg,
                meta.text,
                className
            )}
        >
            <span className={cn('size-1.5 rounded-full shrink-0', meta.dot)} />
            {meta.label}
        </span>
    );
}
