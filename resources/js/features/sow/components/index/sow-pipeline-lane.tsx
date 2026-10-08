import type { SowReport, SowStatus } from '@/features/sow/types/sow.types';
import { cn } from '@/lib/utils';
import { Clock, FileCheck2, FileEdit, Sparkles } from 'lucide-react';
import * as React from 'react';
import { SowCard } from './sow-card';

export interface PipelineLaneProps {
    status: SowStatus;
    reports: SowReport[];
    className?: string;
}

interface LaneMeta {
    title: string;
    hint: string;
    dot: string;
    badgeBg: string;
    icon: React.ReactNode;
    emptyText: string;
}

const LANE_CONFIG: Record<SowStatus, LaneMeta> = {
    draft: {
        title: 'Draft',
        hint: 'Field surveys in progress',
        dot: 'bg-zinc-400 dark:bg-zinc-500',
        badgeBg: 'bg-muted text-muted-foreground',
        icon: <FileEdit className="size-3.5 text-zinc-500" />,
        emptyText: 'No drafts in progress. Create an SOW to get started.',
    },
    in_review: {
        title: 'Awaiting Review',
        hint: 'Requires manager approval',
        dot: 'bg-amber-500',
        badgeBg: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300 font-semibold',
        icon: <Clock className="size-3.5 text-amber-500" />,
        emptyText: 'All submitted reports have been reviewed.',
    },
    published: {
        title: 'Published',
        hint: 'Finalized & customer visible',
        dot: 'bg-emerald-500',
        badgeBg: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300',
        icon: <FileCheck2 className="size-3.5 text-emerald-500" />,
        emptyText: 'No published reports yet.',
    },
    archived: {
        title: 'Archived',
        hint: 'Stored read-only',
        dot: 'bg-muted-foreground/40',
        badgeBg: 'bg-muted text-muted-foreground',
        icon: <Sparkles className="size-3.5 text-muted-foreground" />,
        emptyText: 'No archived reports.',
    },
};

export function SowPipelineLane({ status, reports, className }: Readonly<PipelineLaneProps>) {
    const meta = LANE_CONFIG[status] ?? LANE_CONFIG.draft;
    const needsDecision = status === 'in_review' && reports.length > 0;

    return (
        <section
            aria-label={meta.title}
            className={cn(
                'flex flex-1 min-w-[290px] flex-col rounded-2xl border border-border/50 bg-muted/30 p-2 sm:p-2.5',
                'transition-all duration-150',
                needsDecision && 'ring-1 ring-amber-500/20 bg-amber-500/[0.02]',
                className
            )}
        >
            {/* Lane Header */}
            <header className="mb-2.5 px-2 pt-1 pb-1">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="relative flex size-2.5 items-center justify-center">
                            {needsDecision && (
                                <span className="absolute size-full animate-ping rounded-full bg-amber-400 opacity-75" />
                            )}
                            <span className={cn('size-2 rounded-full', meta.dot)} />
                        </span>
                        <h2 className="text-xs font-semibold tracking-tight text-foreground sm:text-sm">
                            {meta.title}
                        </h2>
                    </div>

                    <span
                        className={cn(
                            'inline-flex h-5 min-w-[20px] items-center justify-center rounded-full px-1.5 font-mono text-[11px] tabular-nums',
                            meta.badgeBg
                        )}
                    >
                        {reports.length}
                    </span>
                </div>
                <p className="mt-0.5 text-[11px] text-muted-foreground pl-4">{meta.hint}</p>
            </header>

            {/* Lane Cards Container */}
            <div className="flex flex-1 flex-col gap-2.5 overflow-y-auto pr-0.5">
                {reports.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border/80 p-6 text-center">
                        <div className="flex size-8 items-center justify-center rounded-full bg-muted/60 text-muted-foreground">
                            {meta.icon}
                        </div>
                        <p className="mt-2 text-xs font-medium text-muted-foreground/80 max-w-[220px]">
                            {meta.emptyText}
                        </p>
                    </div>
                ) : (
                    reports.map((report) => <SowCard key={report.id} report={report} />)
                )}
            </div>
        </section>
    );
}
