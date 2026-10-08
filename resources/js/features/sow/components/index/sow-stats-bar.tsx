import type { SowStatus } from '@/features/sow/types/sow.types';
import { cn } from '@/lib/utils';
import { Archive, CheckCircle2, Clock, FileEdit, LayoutGrid } from 'lucide-react';
import * as React from 'react';

export interface SowStatsBarProps {
    counts: Record<'all' | SowStatus, number>;
    activeFilter: 'all' | SowStatus;
    onSelectFilter: (filter: 'all' | SowStatus) => void;
    className?: string;
}

interface StatPillItem {
    id: 'all' | SowStatus;
    label: string;
    icon: React.ReactNode;
    activeClass: string;
    indicatorDot?: string;
}

const STAT_ITEMS: StatPillItem[] = [
    {
        id: 'all',
        label: 'All Reports',
        icon: <LayoutGrid className="size-3.5" />,
        activeClass: 'border-primary/50 bg-primary/10 text-primary',
    },
    {
        id: 'draft',
        label: 'Drafts',
        icon: <FileEdit className="size-3.5 text-zinc-500" />,
        indicatorDot: 'bg-zinc-400',
        activeClass: 'border-zinc-400/50 bg-muted text-foreground',
    },
    {
        id: 'in_review',
        label: 'Awaiting Review',
        icon: <Clock className="size-3.5 text-amber-500" />,
        indicatorDot: 'bg-amber-500',
        activeClass: 'border-amber-500/50 bg-amber-500/10 text-amber-900 dark:text-amber-200 font-semibold',
    },
    {
        id: 'published',
        label: 'Published',
        icon: <CheckCircle2 className="size-3.5 text-emerald-500" />,
        indicatorDot: 'bg-emerald-500',
        activeClass: 'border-emerald-500/50 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200 font-semibold',
    },
    {
        id: 'archived',
        label: 'Archived',
        icon: <Archive className="size-3.5 text-muted-foreground" />,
        indicatorDot: 'bg-muted-foreground/50',
        activeClass: 'border-border bg-accent text-accent-foreground',
    },
];

export function SowStatsBar({
    counts,
    activeFilter,
    onSelectFilter,
    className,
}: Readonly<SowStatsBarProps>) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-1.5 sm:gap-2 text-xs',
                className
            )}
            role="tablist"
            aria-label="Filter SOW by status"
        >
            {STAT_ITEMS.map((item) => {
                const count = item.id === 'all' ? counts.all : (counts[item.id] ?? 0);
                const isActive = activeFilter === item.id;
                const isActionRequired = item.id === 'in_review' && count > 0;

                return (
                    <button
                        key={item.id}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onSelectFilter(item.id)}
                        className={cn(
                            'group inline-flex items-center gap-2 rounded-lg border border-border/60 bg-card px-2.5 py-1.5 text-xs font-medium text-muted-foreground',
                            'shadow-2xs transition-all duration-150 hover:border-border hover:bg-muted/50 hover:text-foreground cursor-pointer',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            isActive && item.activeClass,
                            isActionRequired && !isActive && 'border-amber-500/30 bg-amber-500/[0.04]'
                        )}
                    >
                        <span className="flex items-center gap-1.5">
                            {item.indicatorDot ? (
                                <span className="relative flex size-2 items-center justify-center">
                                    {isActionRequired && (
                                        <span className="absolute size-full animate-ping rounded-full bg-amber-400 opacity-60" />
                                    )}
                                    <span className={cn('size-1.5 rounded-full', item.indicatorDot)} />
                                </span>
                            ) : (
                                item.icon
                            )}
                            <span>{item.label}</span>
                        </span>

                        <span
                            className={cn(
                                'rounded-full px-1.5 py-0.2 font-mono text-[10px] font-semibold tabular-nums',
                                isActive
                                    ? 'bg-foreground/10 text-foreground'
                                    : 'bg-muted text-muted-foreground group-hover:bg-background',
                                isActionRequired && !isActive && 'bg-amber-500/20 text-amber-700 dark:text-amber-300'
                            )}
                        >
                            {count}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
