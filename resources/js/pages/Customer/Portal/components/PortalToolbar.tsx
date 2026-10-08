import { cn } from '@/lib/utils';
import { ArrowUpDown, LayoutGrid, List, MapPin, Search, X } from 'lucide-react';
import { PortalSortOption, PortalViewMode } from '../portal.types';

interface PortalToolbarProps {
    searchQuery: string;
    onSearchChange: (query: string) => void;
    selectedMarket: string;
    onMarketChange: (market: string) => void;
    availableMarkets: string[];
    sortBy: PortalSortOption;
    onSortChange: (sort: PortalSortOption) => void;
    viewMode: PortalViewMode;
    onViewModeChange: (mode: PortalViewMode) => void;
}

const SELECT_CLASS =
    'h-10 appearance-none rounded-lg border border-border bg-card pl-9 pr-9 text-sm font-medium text-foreground transition-colors hover:border-foreground/20 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer';

export function PortalToolbar({
    searchQuery,
    onSearchChange,
    selectedMarket,
    onMarketChange,
    availableMarkets,
    sortBy,
    onSortChange,
    viewMode,
    onViewModeChange,
}: Readonly<PortalToolbarProps>) {
    return (
        // No card chrome: this renders beside the "Your projects" heading, and a
        // bordered block of its own would read as a separate panel above the list.
        <div className="flex flex-col gap-2 xl:flex-row xl:items-center">
            {/* Search input */}
            <div className="relative xl:w-72">
                <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="search"
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                    placeholder="Search sites by name, PS number, or city"
                    aria-label="Search sites by name, PS number, or city"
                    className="h-10 w-full rounded-lg border border-border bg-card pl-10 pr-10 text-sm text-foreground transition-colors placeholder:text-muted-foreground hover:border-foreground/20 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
                {searchQuery && (
                    <button
                        type="button"
                        onClick={() => onSearchChange('')}
                        aria-label="Clear search"
                        className="absolute right-2.5 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2 xl:justify-end">
                {/* Market filter dropdown */}
                {availableMarkets.length > 0 && (
                    <div className="relative flex items-center">
                        <MapPin className="pointer-events-none absolute left-3 size-4 text-muted-foreground" />
                        <select
                            value={selectedMarket}
                            onChange={(e) => onMarketChange(e.target.value)}
                            aria-label="Filter by market"
                            className={SELECT_CLASS}
                        >
                            <option value="all">All markets</option>
                            {availableMarkets.map((market) => (
                                <option key={market} value={market}>
                                    {market}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {/* Sort selector */}
                <div className="relative flex items-center">
                    <ArrowUpDown className="pointer-events-none absolute left-3 size-4 text-muted-foreground" />
                    <select
                        value={sortBy}
                        onChange={(e) => onSortChange(e.target.value as PortalSortOption)}
                        aria-label="Sort sites"
                        className={SELECT_CLASS}
                    >
                        <option value="activity">Recently active</option>
                        <option value="progress_desc">Highest progress</option>
                        <option value="progress_asc">Lowest progress</option>
                        <option value="name_asc">Site name (A–Z)</option>
                    </select>
                </div>

                {/* View switcher: List vs Grid */}
                <div className="flex items-center gap-1 rounded-lg border border-border bg-card p-1">
                    <button
                        type="button"
                        onClick={() => onViewModeChange('list')}
                        aria-label="List view"
                        aria-pressed={viewMode === 'list'}
                        className={cn(
                            'flex size-8 items-center justify-center rounded-md transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            viewMode === 'list'
                                ? 'bg-secondary text-foreground'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground',
                        )}
                    >
                        <List className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onViewModeChange('grid')}
                        aria-label="Grid view"
                        aria-pressed={viewMode === 'grid'}
                        className={cn(
                            'flex size-8 items-center justify-center rounded-md transition-colors',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            viewMode === 'grid'
                                ? 'bg-secondary text-foreground'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground',
                        )}
                    >
                        <LayoutGrid className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

export default PortalToolbar;
