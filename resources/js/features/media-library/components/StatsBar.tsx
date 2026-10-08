import { CheckCircle2, Clock, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MediaStatsResponse } from '../types/media-library.types';
import { QuotaBar } from './QuotaBar';

export interface StatsBarProps {
    readonly stats: MediaStatsResponse | null;
    readonly className?: string;
    readonly children?: React.ReactNode;
}

export function StatsBar({ stats, className, children }: StatsBarProps) {
    if (!stats) return null;

    const { quota, counts } = stats;

    return (
        <div
            className={cn(
                'flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-xl border border-border bg-card/70 px-3.5 py-2 text-xs shadow-2xs',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-2.5 sm:gap-3">
                <span className="font-semibold text-foreground">
                    {counts.total} Photo{counts.total === 1 ? '' : 's'}
                </span>

                <span className="text-border">|</span>

                <span className="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    {counts.ready} Ready
                </span>

                {counts.processing > 0 && (
                    <span className="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                        <Clock className="h-3.5 w-3.5 animate-spin" />
                        {counts.processing} Processing
                    </span>
                )}

                {counts.failed > 0 && (
                    <span className="inline-flex items-center gap-1 font-medium text-destructive">
                        <AlertCircle className="h-3.5 w-3.5" />
                        {counts.failed} Failed
                    </span>
                )}
            </div>

            <div className="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                <QuotaBar usedBytes={quota.used_bytes} limitBytes={quota.limit_bytes} className="min-w-[160px]" />
                {children}
            </div>
        </div>
    );
}
