import { SupervisorAvatar } from '@/features/media-library/components/SupervisorFolderCard';
import type { SowReport } from '@/features/sow/types/sow.types';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Camera, ChevronRight, FileDown, MapPin } from 'lucide-react';

export interface SowCardProps {
    report: SowReport;
    className?: string;
}

function timeAgo(iso: string): string {
    const diffMinutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    if (diffMinutes < 1) return 'just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 30) return `${diffDays}d ago`;
    return new Date(iso).toLocaleDateString();
}

function reportNumber(id: number): string {
    return `SOW-${String(id).padStart(4, '0')}`;
}

export function SowCard({ report, className }: Readonly<SowCardProps>) {
    const psNumber = report.ps_number?.trim();
    const imageCount = report.annotated_images_count ?? 0;
    const isPdfReady = report.status === 'published' && report.pdf_generation_status === 'ready';

    return (
        <Link
            href={route('sow.reports.show', { report: report.id })}
            className={cn(
                'group relative flex flex-col justify-between rounded-xl border border-border/70 bg-card p-3.5',
                'shadow-2xs transition-all duration-150',
                'hover:border-primary/40 hover:bg-card/95 hover:shadow-sm',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                className
            )}
        >
            <div>
                {/* Header: SOW ID + Quick Actions */}
                <div className="flex items-center justify-between gap-2">
                    <span className="inline-flex items-center rounded-md border border-border/80 bg-muted/50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-muted-foreground tabular-nums">
                        {reportNumber(report.id)}
                    </span>
                    {isPdfReady && (
                        <a
                            href={route('sow.reports.pdf', report.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            title="Download PDF report"
                            aria-label="Download PDF report"
                            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                        >
                            <FileDown className="size-3.5" />
                        </a>
                    )}
                </div>

                {/* Main: Site Name / Title */}
                <h3 className="mt-2 text-sm font-semibold tracking-tight text-foreground transition-colors group-hover:text-primary">
                    {report.site_name || report.title || 'Untitled Report'}
                </h3>

                {/* Subtitle / Location & PS# Tag */}
                <div className="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                    {report.market?.name && (
                        <span className="inline-flex items-center gap-1">
                            <MapPin className="size-3 shrink-0 text-muted-foreground/70" />
                            <span className="truncate max-w-[140px]">{report.market.name}</span>
                        </span>
                    )}
                    {psNumber && (
                        <span className="inline-flex items-center rounded border border-border/60 bg-muted/40 px-1.5 py-px font-mono text-[10px] font-medium text-muted-foreground">
                            PS# {psNumber}
                        </span>
                    )}
                </div>
            </div>

            {/* Footer: Creator left, telemetry right */}
            <div className="mt-3 flex items-center justify-between gap-2 border-t border-border/50 pt-2.5">
                {report.creator ? (
                    <div className="flex min-w-0 items-center gap-1.5">
                        <SupervisorAvatar
                            name={report.creator.name}
                            photoUrl={report.creator.profile_photo_url}
                            size="xs"
                        />
                        <span className="truncate text-[11px] font-medium text-muted-foreground">
                            {report.creator.name}
                        </span>
                    </div>
                ) : (
                    <span className="text-[11px] text-muted-foreground/60">—</span>
                )}

                <div className="flex shrink-0 items-center gap-1.5 text-[11px] text-muted-foreground tabular-nums">
                    <span className="inline-flex items-center gap-1 font-medium">
                        <Camera className="size-3 shrink-0 text-muted-foreground/80" />
                        {imageCount}
                    </span>
                    <span aria-hidden className="text-muted-foreground/40">·</span>
                    <span className="rounded bg-muted/60 px-1 py-0.2 font-mono text-[10px] font-medium">
                        R{report.revision}.0
                    </span>
                    <span aria-hidden className="text-muted-foreground/40">·</span>
                    <span>{timeAgo(report.updated_at)}</span>
                    <ChevronRight className="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-all duration-150 group-hover:translate-x-0.5 group-hover:opacity-100" />
                </div>
            </div>
        </Link>
    );
}
