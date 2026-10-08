import { Button } from '@/components/ui/button';
import { ChevronDown, Images, Loader2 } from 'lucide-react';
import * as React from 'react';

export interface MediaPickerGridProps {
    isLoading: boolean;
    isLoadingMore: boolean;
    hasMore: boolean;
    onLoadMore: () => void;
    emptyHint?: string;
    children: React.ReactNode;
}

/** Shared picker results: spinner, empty state, grid, explicit Load More. */
export function MediaPickerGrid({
    isLoading,
    isLoadingMore,
    hasMore,
    onLoadMore,
    emptyHint = 'Upload photos to your library or switch scope to “All Photos”.',
    children,
}: Readonly<MediaPickerGridProps>) {
    if (isLoading && React.Children.count(children) === 0) {
        return (
            <div className="flex h-48 flex-col items-center justify-center gap-2 text-xs text-muted-foreground">
                <Loader2 className="size-6 animate-spin text-primary" />
                <span>Loading library photos...</span>
            </div>
        );
    }

    if (!isLoading && React.Children.count(children) === 0) {
        return (
            <div className="flex h-48 flex-col items-center justify-center space-y-1 text-center text-xs text-muted-foreground">
                <Images className="mb-1 size-8 text-muted-foreground/40" />
                <p className="font-semibold text-foreground">No photos found</p>
                <p>{emptyHint}</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{children}</div>
            {hasMore && (
                <div className="flex justify-center pb-1 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onLoadMore}
                        disabled={isLoadingMore}
                        className="h-11 sm:h-8 gap-1.5 text-sm sm:text-xs font-semibold"
                    >
                        {isLoadingMore ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <ChevronDown className="size-3.5" />
                        )}
                        {isLoadingMore ? 'Loading…' : 'Load more photos'}
                    </Button>
                </div>
            )}
        </div>
    );
}
