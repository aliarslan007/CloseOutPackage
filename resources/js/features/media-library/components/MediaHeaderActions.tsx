import { UploadCloud, Clock, AlertCircle, HardDrive, Server } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { MediaStatsResponse } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';

export interface MediaHeaderActionsProps {
    readonly stats: MediaStatsResponse | null;
    readonly showDropzone: boolean;
    readonly onToggleDropzone: () => void;
    readonly className?: string;
}

function getQuotaFillClass(percentage: number): string {
    if (percentage >= 90) return 'bg-destructive';
    if (percentage >= 75) return 'bg-amber-500';
    return 'bg-primary';
}

function computePercentage(used: number, limit: number | null): number | null {
    if (limit === null || limit <= 0) return null;
    return Math.min(100, Math.round((used / limit) * 100));
}

function getQuotaTitle(used: number, limit: number | null, percentage: number | null): string {
    if (limit && percentage !== null) {
        return `Storage: ${formatFileSize(used)} used of ${formatFileSize(limit)} (${percentage}%)`;
    }
    return `Storage: ${formatFileSize(used)} used (Unlimited)`;
}

export function MediaHeaderActions({
    stats,
    showDropzone,
    onToggleDropzone,
    className,
}: MediaHeaderActionsProps) {
    const counts = stats?.counts;
    const quota = stats?.quota;
    const system = stats?.system ?? null;
    const percentage = quota ? computePercentage(quota.used_bytes, quota.limit_bytes) : null;
    const quotaTitle = quota ? getQuotaTitle(quota.used_bytes, quota.limit_bytes, percentage) : '';
    const systemTitle = system
        ? `System-wide: ${formatFileSize(system.total_bytes)}\n${system.by_role
              .map((entry) => `${entry.label}: ${formatFileSize(entry.used_bytes)}`)
              .join('\n')}`
        : '';

    return (
        <div className={cn('flex items-center gap-2 sm:gap-2.5 flex-wrap sm:flex-nowrap justify-start sm:justify-end w-full sm:w-auto', className)}>
            {/* Photos Count Pill */}
            {counts && (
                <div
                    className="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg border border-border/80 bg-card px-2.5 py-1 text-xs text-muted-foreground shadow-2xs shrink-0"
                    title={`${counts.ready} ready, ${counts.processing} processing, ${counts.failed} failed`}
                >
                    <span className="font-semibold text-foreground">
                        {counts.total} <span className="hidden sm:inline font-normal text-muted-foreground">Photo{counts.total === 1 ? '' : 's'}</span>
                    </span>

                    {counts.processing > 0 && (
                        <span className="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                            <Clock className="h-3 w-3 animate-spin" />
                            <span>{counts.processing}</span>
                        </span>
                    )}

                    {counts.failed > 0 && (
                        <span className="inline-flex items-center gap-1 font-medium text-destructive">
                            <AlertCircle className="h-3 w-3" />
                            <span>{counts.failed}</span>
                        </span>
                    )}
                </div>
            )}

            {/* System-wide Storage Pill (admins) */}
            {system && (
                <div
                    className="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg border border-border/80 bg-card px-2.5 py-1 text-xs text-muted-foreground shadow-2xs shrink-0"
                    title={systemTitle}
                >
                    <Server className="h-3.5 w-3.5 text-muted-foreground/80 shrink-0" />
                    <span className="hidden sm:inline text-muted-foreground/70 text-[11px]">
                        System
                    </span>
                    <span className="font-semibold text-foreground">
                        {formatFileSize(system.total_bytes)}
                    </span>
                </div>
            )}

            {/* Storage Quota Pill */}
            {quota && (
                <div
                    className="inline-flex items-center gap-1.5 sm:gap-2 rounded-lg border border-border/80 bg-card px-2.5 py-1 text-xs text-muted-foreground shadow-2xs shrink-0"
                    title={quotaTitle}
                >
                    <HardDrive className="h-3.5 w-3.5 text-muted-foreground/80 shrink-0" />
                    <span className="font-semibold text-foreground">
                        {formatFileSize(quota.used_bytes)}
                    </span>
                    {quota.limit_bytes ? (
                        <>
                            <span className="hidden md:inline text-muted-foreground/70 text-[11px]">
                                / {formatFileSize(quota.limit_bytes)}
                            </span>
                            {percentage !== null && (
                                <div className="hidden lg:block w-12 h-1.5 bg-muted rounded-full overflow-hidden shrink-0">
                                    <div
                                        className={cn('h-full rounded-full transition-all duration-300', getQuotaFillClass(percentage))}
                                        style={{ width: `${percentage}%` }}
                                    />
                                </div>
                            )}
                        </>
                    ) : (
                        <span className="hidden md:inline text-[10px] font-medium text-primary bg-primary/10 rounded px-1 py-0.5">
                            Unlimited
                        </span>
                    )}
                </div>
            )}

            {/* Upload Photos Toggle Action */}
            <Button
                type="button"
                size="sm"
                variant={showDropzone ? 'secondary' : 'default'}
                onClick={onToggleDropzone}
                className="gap-1.5 h-8 text-xs font-semibold shrink-0 shadow-xs"
            >
                <UploadCloud className="h-3.5 w-3.5" />
                <span>{showDropzone ? 'Close Upload' : 'Upload Photos'}</span>
            </Button>
        </div>
    );
}
