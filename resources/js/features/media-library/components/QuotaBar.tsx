import { cn } from '@/lib/utils';
import { formatFileSize } from '../utils/format-file-size';

export interface QuotaBarProps {
    usedBytes: number;
    limitBytes: number | null;
    className?: string;
}

function getFillClass(percent: number): string {
    if (percent >= 90) return 'bg-destructive';
    if (percent >= 75) return 'bg-amber-500';
    return 'bg-primary';
}

function getTextClass(percent: number): string {
    if (percent >= 90) return 'text-destructive font-semibold';
    if (percent >= 75) return 'text-amber-600 dark:text-amber-400 font-medium';
    return 'text-muted-foreground';
}

export function QuotaBar({ usedBytes, limitBytes, className }: QuotaBarProps) {
    if (limitBytes === null || limitBytes <= 0) {
        return (
            <div className={cn('text-xs text-muted-foreground flex items-center gap-1.5', className)}>
                <span className="font-medium text-foreground">{formatFileSize(usedBytes)}</span> used
                <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                    Unlimited
                </span>
            </div>
        );
    }

    const percentage = Math.min(100, Math.round((usedBytes / limitBytes) * 100));
    const fillClass = getFillClass(percentage);
    const textClass = getTextClass(percentage);

    return (
        <div className={cn('space-y-1.5 min-w-[200px]', className)}>
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">
                    <strong className="font-semibold text-foreground">{formatFileSize(usedBytes)}</strong> of{' '}
                    {formatFileSize(limitBytes)}
                </span>
                <span className={textClass}>
                    {percentage >= 90 && (
                        <span className="inline-block h-1.5 w-1.5 rounded-full bg-destructive mr-1 animate-pulse" />
                    )}
                    {percentage}%
                </span>
            </div>

            <div
                role="meter"
                aria-label="Storage quota usage"
                aria-valuenow={percentage}
                aria-valuemin={0}
                aria-valuemax={100}
                className="h-2 w-full overflow-hidden rounded-full bg-secondary"
            >
                <div
                    className={cn('h-full transition-all duration-300 rounded-full', fillClass)}
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}
