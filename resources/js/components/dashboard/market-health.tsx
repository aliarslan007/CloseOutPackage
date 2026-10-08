import * as React from 'react';
import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface MarketHealthItem {
    market_id: number | null;
    market: string;
    count: number;
    completed: number;
    in_progress: number;
    not_started: number;
    attention: number;
}

type SegmentKey = 'attention' | 'not_started' | 'in_progress' | 'completed';

interface Segment {
    key: SegmentKey;
    label: string;
    className: string;
}

const SEGMENTS: Segment[] = [
    { key: 'attention', label: 'Needs action', className: 'bg-destructive' },
    { key: 'not_started', label: 'Not started', className: 'bg-muted-foreground/50' },
    { key: 'in_progress', label: 'In progress', className: 'bg-primary' },
    { key: 'completed', label: 'Completed', className: 'bg-emerald-500' },
];

const projectsLink = (marketId: number | null) =>
    marketId === null
        ? route('projects.index')
        : route('projects.index', { market_id: marketId });

export function MarketHealth({ items }: { items: MarketHealthItem[] }) {
    if (items.length === 0) {
        return <p className="text-xs text-muted-foreground">No market distribution data available.</p>;
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] text-muted-foreground">
                {SEGMENTS.map((item) => (
                    <span key={item.key} className="flex items-center gap-1.5">
                        <span className={cn('size-2 rounded-full', item.className)} />
                        {item.label}
                    </span>
                ))}
            </div>

            <div className="space-y-3">
                {items.map((item) => {
                    const total = Math.max(item.count, 1);
                    const segments = SEGMENTS.map((segment) => ({
                        ...segment,
                        value: item[segment.key],
                    }));

                    return (
                        <Link
                            key={item.market_id ?? 'unknown'}
                            href={projectsLink(item.market_id)}
                            className="group block space-y-1.5 rounded-lg px-1 py-1 transition-colors hover:bg-muted/40"
                        >
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-1.5 text-xs font-medium">
                                    <MapPin className="size-3.5 shrink-0 text-primary" />
                                    {item.market}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {item.count} project{item.count === 1 ? '' : 's'}
                                </span>
                            </div>
                            <div
                                className="flex h-2 w-full gap-0.5 overflow-hidden rounded-full"
                                role="img"
                                aria-label={`${item.market}: ${item.attention} need action, ${item.not_started} not started, ${item.in_progress} in progress, ${item.completed} completed`}
                            >
                                {segments
                                    .filter((segment) => segment.value > 0)
                                    .map((segment) => (
                                        <div
                                            key={segment.key}
                                            className={cn('h-full rounded-full', segment.className)}
                                            style={{ width: `${(segment.value / total) * 100}%` }}
                                        />
                                    ))}
                            </div>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export default MarketHealth;
