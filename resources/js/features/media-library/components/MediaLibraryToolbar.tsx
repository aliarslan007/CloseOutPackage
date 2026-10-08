import { useState, useEffect, useCallback } from 'react';
import { Search, X, LayoutGrid, Grid2X2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAccessibleProjects } from '../hooks/use-accessible-projects';
import type { MediaLibraryFilters } from '../hooks/use-media-library';
import type { MediaLibraryProject, TeamSupervisor } from '../types/media-library.types';
import { MediaUploaderFilter } from './MediaUploaderFilter';
import { ActiveFilterChips } from './ActiveFilterChips';

export interface MediaLibraryToolbarProps {
    readonly filters: MediaLibraryFilters;
    readonly projects?: MediaLibraryProject[];
    readonly technicians?: TeamSupervisor[];
    readonly loggedInUserId?: number;
    readonly showUploaderFilter?: boolean;
    readonly uploaderSectionLabel?: string;
    readonly onFiltersChange: (newFilters: Partial<MediaLibraryFilters>) => void;
    readonly onResetFilters: () => void;
    readonly isDense?: boolean;
    readonly onToggleDense?: () => void;
}

export function MediaLibraryToolbar({
    filters,
    projects: initialProjects,
    technicians = [],
    loggedInUserId,
    showUploaderFilter = false,
    uploaderSectionLabel,
    onFiltersChange,
    onResetFilters,
    isDense = false,
    onToggleDense,
}: MediaLibraryToolbarProps) {
    const { projects } = useAccessibleProjects(initialProjects);
    const [searchValue, setSearchValue] = useState(filters.search || '');

    useEffect(() => {
        setSearchValue(filters.search || '');
    }, [filters.search]);

    // Debounce search input by 300ms
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchValue !== (filters.search || '')) {
                onFiltersChange({ search: searchValue.trim() || undefined });
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [searchValue, filters.search, onFiltersChange]);

    const handleClearSearch = useCallback(() => {
        setSearchValue('');
        onFiltersChange({ search: undefined });
    }, [onFiltersChange]);

    const selectedProjectName = filters.unassigned
        ? 'Unassigned media'
        : (projects.find((p) => p.id === filters.project_id)?.name ?? 'All Projects');

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {/* Search Input */}
                <div className="relative flex-1 max-w-md">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Search photos by filename..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        className="pl-9 pr-8 h-9 text-xs"
                    />
                    {searchValue && (
                        <button
                            type="button"
                            onClick={handleClearSearch}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer"
                            aria-label="Clear search input"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>

                {/* Filter and View Controls */}
                <div className="flex flex-wrap items-center gap-2">
                    {/* Filter 1: Uploader Filter (for Admin & Manager) */}
                    {showUploaderFilter && (
                        <MediaUploaderFilter
                            currentUserId={filters.user_id}
                            loggedInUserId={loggedInUserId}
                            technicians={technicians}
                            sectionLabel={uploaderSectionLabel}
                            onChange={(userId) => onFiltersChange({ user_id: userId })}
                        />
                    )}

                    {/* Filter 2: Project Filter */}
                    <select
                        value={filters.unassigned ? 'unassigned' : (filters.project_id ?? '')}
                        onChange={(e) => {
                            const val = e.target.value;
                            if (val === 'unassigned') {
                                onFiltersChange({ project_id: undefined, unassigned: true });
                            } else {
                                onFiltersChange({ project_id: val ? Number(val) : undefined, unassigned: undefined });
                            }
                        }}
                        className="h-9 min-w-[170px] max-w-[240px] rounded-lg border border-border bg-card pl-3 pr-8 text-xs text-foreground shadow-2xs focus:outline-none focus:ring-1 focus:ring-primary truncate cursor-pointer"
                        aria-label="Filter photos by project"
                        title={selectedProjectName}
                    >
                        <option value="">All Projects</option>
                        {projects.map((p) => (
                            <option key={p.id} value={p.id} title={p.name}>
                                {p.name}
                            </option>
                        ))}
                        <option value="unassigned">Generic photos</option>
                    </select>

                    {/* Sort Selector */}
                    <select
                        value={filters.sort || '-created_at'}
                        onChange={(e) => onFiltersChange({ sort: e.target.value })}
                        className="h-9 min-w-[140px] rounded-lg border border-border bg-card pl-3 pr-8 text-xs text-foreground shadow-2xs focus:outline-none focus:ring-1 focus:ring-primary truncate cursor-pointer"
                        aria-label="Sort photos by"
                    >
                        <option value="-created_at">Newest first</option>
                        <option value="created_at">Oldest first</option>
                        <option value="original_filename">Filename (A-Z)</option>
                        <option value="-original_filename">Filename (Z-A)</option>
                        <option value="-file_size_bytes">Largest size</option>
                        <option value="file_size_bytes">Smallest size</option>
                    </select>

                    {/* Status Filter */}
                    <div className="flex items-center rounded-lg border border-border bg-card p-0.5 text-xs shadow-2xs">
                        {(['all', 'ready', 'processing', 'failed'] as const).map((status) => {
                            const isCurrent = (filters.status || 'all') === status;
                            return (
                                <button
                                    key={status}
                                    type="button"
                                    onClick={() => onFiltersChange({ status })}
                                    className={`rounded-md px-2.5 py-1 font-medium capitalize transition-colors cursor-pointer ${
                                        isCurrent
                                            ? 'bg-primary text-primary-foreground shadow-2xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {status}
                                </button>
                            );
                        })}
                    </div>

                    {/* Grid Density Toggle */}
                    {onToggleDense && (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={onToggleDense}
                            className="h-9 w-9 text-muted-foreground hover:text-foreground hidden sm:inline-flex"
                            title={isDense ? 'Switch to Large Inspection View (2 per row)' : 'Switch to Compact Grid (5 per row)'}
                            aria-label={isDense ? 'Switch to Large Inspection View (2 per row)' : 'Switch to Compact Grid (5 per row)'}
                        >
                            {isDense ? <Grid2X2 className="h-4 w-4" /> : <LayoutGrid className="h-4 w-4" />}
                        </Button>
                    )}
                </div>
            </div>

            {/* Active Filter Chips */}
            <ActiveFilterChips
                filters={filters}
                projects={projects}
                technicians={technicians}
                loggedInUserId={loggedInUserId}
                onFiltersChange={onFiltersChange}
                onClearSearch={handleClearSearch}
                onResetFilters={onResetFilters}
            />
        </div>
    );
}
