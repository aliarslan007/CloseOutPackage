import { ImagePlus, SearchX, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { MediaAsset } from '../types/media-library.types';
import { MediaAssetCard } from './MediaAssetCard';
import { MediaGridSkeleton } from './skeletons/MediaGridSkeleton';

export interface MediaLibraryGridProps {
    assets: MediaAsset[];
    isLoading: boolean;
    isLoadingMore: boolean;
    hasMore: boolean;
    onLoadMore: () => void;
    onViewAsset: (asset: MediaAsset) => void;
    onDeleteAsset?: (asset: MediaAsset) => void;
    onSelectAsset?: (asset: MediaAsset) => void;
    onAnnotateAsset?: (asset: MediaAsset) => void;
    selectedAssetId?: number | null;
    isSelectable?: boolean;
    isDense?: boolean;
    hasActiveFilters?: boolean;
    onResetFilters?: () => void;
    onOpenUpload?: () => void;
}

export function MediaLibraryGrid({
    assets,
    isLoading,
    isLoadingMore,
    hasMore,
    onLoadMore,
    onViewAsset,
    onDeleteAsset,
    onSelectAsset,
    onAnnotateAsset,
    selectedAssetId,
    isSelectable = false,
    isDense = false,
    hasActiveFilters = false,
    onResetFilters,
    onOpenUpload,
}: MediaLibraryGridProps) {
    if (isLoading) {
        return <MediaGridSkeleton count={isDense ? 15 : 6} isDense={isDense} />;
    }

    if (assets.length === 0) {
        if (hasActiveFilters) {
            return (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40 p-12 text-center">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground mb-3">
                        <SearchX className="h-6 w-6" />
                    </div>
                    <h3 className="text-sm font-semibold text-foreground">No media content available</h3>
                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                        No media content is available for the selected filters.
                    </p>
                    {onResetFilters && (
                        <Button type="button" variant="outline" size="sm" onClick={onResetFilters} className="mt-4">
                            Clear Filters
                        </Button>
                    )}
                </div>
            );
        }

        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40 p-12 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary mb-3">
                    <ImagePlus className="h-6 w-6" />
                </div>
                <h3 className="text-sm font-semibold text-foreground">Media library is empty</h3>
                <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                    Upload photos in bulk to store cell tower construction images and pick them into Closeout Packages anytime.
                </p>
                {onOpenUpload && (
                    <Button type="button" size="sm" onClick={onOpenUpload} className="mt-4 gap-1.5">
                        <ImagePlus className="h-4 w-4" /> Upload Photos
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div
                className={cn(
                    'grid transition-all',
                    isDense
                        ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-3.5 gap-y-4'
                        : 'grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6 lg:gap-x-7 lg:gap-y-7',
                )}
            >
                {assets.map((asset) => (
                    <MediaAssetCard
                        key={asset.id}
                        asset={asset}
                        isSelectable={isSelectable}
                        isSelected={selectedAssetId === asset.id}
                        isDense={isDense}
                        onSelect={onSelectAsset}
                        onView={onViewAsset}
                        onDelete={onDeleteAsset}
                        onAnnotate={onAnnotateAsset}
                    />
                ))}
            </div>

            {hasMore && (
                <div className="flex justify-center pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onLoadMore}
                        disabled={isLoadingMore}
                        className="gap-2 text-xs"
                    >
                        {isLoadingMore ? (
                            <>
                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Loading more photos...
                            </>
                        ) : (
                            'Load more photos'
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
