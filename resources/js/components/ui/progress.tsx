import * as React from 'react';
import { cn } from '@/lib/utils';

export interface ProgressProps {
    value: number;
    className?: string;
}

function getProgressColorClass(value: number): string {
    if (value >= 70) {
        return 'bg-emerald-500';
    }
    if (value >= 30) {
        return 'bg-amber-500';
    }
    return 'bg-red-500';
}

export function Progress({ value, className }: Readonly<ProgressProps>) {
    const clampedValue = Math.min(100, Math.max(0, value));
    const isOngoing = clampedValue > 0 && clampedValue < 100;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={clampedValue}
            className={cn('h-1.5 w-full overflow-hidden rounded-full bg-muted', className)}
        >
            <div
                className={cn(
                    'relative h-full rounded-full transition-[width] duration-300',
                    getProgressColorClass(clampedValue)
                )}
                style={{ width: `${clampedValue}%` }}
            >
                {isOngoing && <div className="progress-shimmer absolute inset-0 rounded-full" />}
            </div>
        </div>
    );
}

export default Progress;
