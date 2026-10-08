import * as React from 'react';
import { Lock } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface LockedBadgeProps {
    readonly className?: string;
}

/**
 * Neutral chip marking a package as locked. Deliberately not a `StatusBadge`
 * status — a locked package keeps its own workflow status.
 */
export function LockedBadge({ className }: LockedBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground',
                className
            )}
        >
            <Lock className="size-3" />
            Locked
        </span>
    );
}

export default LockedBadge;
