import { cn } from '@/lib/utils';
import { PortalMetrics, PortalStatusFilter } from '../portal.types';

interface PortalKpiCardsProps {
    metrics: PortalMetrics;
    marketCount: number;
    activeFilter: PortalStatusFilter;
    onSelectFilter: (filter: PortalStatusFilter) => void;
}

interface Tile {
    filter: PortalStatusFilter;
    label: string;
    value: number;
    caption: string;
    dotClassName: string;
}

function marketCaption(marketCount: number): string {
    if (marketCount === 0) {
        return 'No markets yet';
    }

    return marketCount === 1 ? '1 market' : `${marketCount} markets`;
}

/**
 * Portfolio status tiles. Each one is also the status filter for the site list
 * below, so the number and the filter can never disagree.
 *
 * Deliberately not the shared StatsCard: that card's p-6/size-12 dashboard
 * proportions are right for internal dashboards and wrong for a customer-facing
 * page where four tiles sit directly under a heading.
 */
export function PortalKpiCards({
    metrics,
    marketCount,
    activeFilter,
    onSelectFilter,
}: Readonly<PortalKpiCardsProps>) {
    const tiles: Tile[] = [
        {
            filter: 'all',
            label: 'Total sites',
            value: metrics.total,
            caption: marketCaption(marketCount),
            dotClassName: 'bg-muted-foreground/40',
        },
        {
            filter: 'in_progress',
            label: 'In progress',
            value: metrics.inProgress,
            caption: 'Work underway on site',
            dotClassName: 'bg-info',
        },
        {
            filter: 'approved',
            label: 'Fully approved',
            value: metrics.approved,
            caption: 'Every package signed off',
            dotClassName: 'bg-success',
        },
        {
            filter: 'attention',
            label: 'Needs your review',
            value: metrics.needAttention,
            caption: metrics.needAttention > 0 ? 'Waiting on you' : 'All clear',
            dotClassName: metrics.needAttention > 0 ? 'bg-warning' : 'bg-success/60',
        },
    ];

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {tiles.map((tile) => {
                const isSelected = activeFilter === tile.filter;

                return (
                    <button
                        key={tile.filter}
                        type="button"
                        aria-pressed={isSelected}
                        onClick={() => onSelectFilter(tile.filter)}
                        className={cn(
                            'rounded-xl border bg-card p-4 text-left shadow-card transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                            // Selected = a blue border sitting just inside a black
                            // one, so the active filter reads even next to the
                            // inactive tiles' grey border.
                            isSelected
                                ? 'border-primary bg-card shadow-card ring-1 ring-foreground/80'
                                : 'border-border/80 hover:border-foreground/20 hover:bg-secondary/40'
                        )}
                    >
                        <span className="flex items-center gap-2">
                            <span
                                className={cn('size-2 shrink-0 rounded-full', tile.dotClassName)}
                            />
                            <span className="text-sm font-medium text-muted-foreground">
                                {tile.label}
                            </span>
                        </span>
                        <span className="mt-2 block font-heading text-2xl font-bold tabular-nums tracking-tight text-foreground">
                            {tile.value}
                        </span>
                        <span className="mt-0.5 block text-xs text-muted-foreground">
                            {tile.caption}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

export default PortalKpiCards;
