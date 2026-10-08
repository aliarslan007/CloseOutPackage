import { Filter, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { MediaLibraryFilters } from '../hooks/use-media-library';
import type { MediaLibraryProject, TeamSupervisor } from '../types/media-library.types';

export interface ActiveFilterChipsProps {
    readonly filters: MediaLibraryFilters;
    readonly projects: MediaLibraryProject[];
    readonly technicians?: TeamSupervisor[];
    readonly loggedInUserId?: number;
    readonly onFiltersChange: (newFilters: Partial<MediaLibraryFilters>) => void;
    readonly onClearSearch: () => void;
    readonly onResetFilters: () => void;
}

function resolveUploaderChipLabel(
    userId?: number | null,
    loggedInUserId?: number,
    uploaderName?: string,
): string {
    if (userId === loggedInUserId) {
        return 'Uploader: My Photos';
    }
    if (uploaderName) {
        return `Uploader: ${uploaderName}`;
    }
    return `Uploader: User #${String(userId)}`;
}

export function ActiveFilterChips({
    filters,
    projects,
    technicians = [],
    loggedInUserId,
    onFiltersChange,
    onClearSearch,
    onResetFilters,
}: ActiveFilterChipsProps) {
    const hasActiveFilters = Boolean(
        filters.search ||
            (filters.status && filters.status !== 'all') ||
            filters.project_id ||
            filters.unassigned ||
            filters.user_id,
    );

    if (!hasActiveFilters) {
        return null;
    }

    const selectedTech = filters.user_id
        ? technicians.find((t) => t.id === filters.user_id)
        : null;

    const projectName = filters.unassigned
        ? 'Unassigned'
        : (projects.find((p) => p.id === filters.project_id)?.name ?? `Project #${String(filters.project_id)}`);

    return (
        <div className="flex flex-wrap items-center gap-1.5 pt-1">
            <span className="text-[11px] font-medium text-muted-foreground flex items-center gap-1">
                <Filter className="h-3 w-3" /> Filters:
            </span>

            {/* 1. Uploader Filter Chip */}
            {filters.user_id && (
                <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/60 px-2 py-0.5 text-xs">
                    {resolveUploaderChipLabel(filters.user_id, loggedInUserId, selectedTech?.name)}
                    <button
                        type="button"
                        onClick={() => onFiltersChange({ user_id: undefined })}
                        className="text-muted-foreground hover:text-foreground cursor-pointer"
                        aria-label="Remove uploader filter"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </span>
            )}

            {/* 2. Project Filter Chip */}
            {(Boolean(filters.project_id) || filters.unassigned) && (
                <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/60 px-2 py-0.5 text-xs">
                    Project: {projectName}
                    <button
                        type="button"
                        onClick={() => onFiltersChange({ project_id: undefined, unassigned: undefined })}
                        className="text-muted-foreground hover:text-foreground cursor-pointer"
                        aria-label="Remove project filter"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </span>
            )}

            {/* 3. Status Filter Chip */}
            {filters.status && filters.status !== 'all' && (
                <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/60 px-2 py-0.5 text-xs capitalize">
                    Status: {filters.status}
                    <button
                        type="button"
                        onClick={() => onFiltersChange({ status: undefined })}
                        className="text-muted-foreground hover:text-foreground cursor-pointer"
                        aria-label="Remove status filter"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </span>
            )}

            {/* 4. Search Query Chip */}
            {filters.search && (
                <span className="inline-flex items-center gap-1 rounded-md border border-border bg-muted/60 px-2 py-0.5 text-xs">
                    Query: &ldquo;{filters.search}&rdquo;
                    <button
                        type="button"
                        onClick={onClearSearch}
                        className="text-muted-foreground hover:text-foreground cursor-pointer"
                        aria-label="Remove search query filter"
                    >
                        <X className="h-3 w-3" />
                    </button>
                </span>
            )}

            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={onResetFilters}
                className="h-6 text-[11px] text-muted-foreground hover:text-foreground px-1.5"
            >
                Clear all
            </Button>
        </div>
    );
}
