import { useCallback, useEffect, useRef, useState } from 'react';
import { mediaLibraryApi } from '../api/media-library.api';
import { registerAssetCompletedListener } from '../stores/upload-queue.store';
import type { CursorPaginatedResponse, MediaAsset } from '../types/media-library.types';

export interface MediaLibraryFilters {
    search?: string;
    status?: string;
    market_id?: number | null;
    project_id?: number | null;
    unassigned?: boolean;
    sort?: string;
    user_id?: number | null;
}

function upsertAsset(prev: MediaAsset[], newAsset: MediaAsset): MediaAsset[] {
    const exists = prev.some((a) => a.id === newAsset.id);
    if (exists) {
        return prev.map((a) => (a.id === newAsset.id ? newAsset : a));
    }
    return [newAsset, ...prev];
}

function buildQueryParams(
    filters: MediaLibraryFilters,
    cursor: string | null
): Record<string, unknown> {
    const params: Record<string, unknown> = {};
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.market_id) params.market_id = filters.market_id;
    if (filters.unassigned) params.unassigned = 1;
    else if (filters.project_id) params.project_id = filters.project_id;
    if (filters.sort) params.sort = filters.sort;
    if (filters.user_id) params.user_id = filters.user_id;
    if (cursor) params.cursor = cursor;
    return params;
}

function mergeLoadedAssets(
    prev: MediaAsset[],
    incoming: MediaAsset[],
    isReset: boolean,
    cursor: string | null
): MediaAsset[] {
    if (isReset) {
        return incoming;
    }
    if (cursor === null) {
        const incomingMap = new Map(incoming.map((a) => [a.id, a]));
        return prev.map((a) => incomingMap.get(a.id) ?? a);
    }
    const existingIds = new Set(prev.map((a) => a.id));
    const uniqueNew = incoming.filter((a) => !existingIds.has(a.id));
    return [...prev, ...uniqueNew];
}

export function useMediaLibrary(initialFilters: MediaLibraryFilters = {}) {
    const [assets, setAssets] = useState<MediaAsset[]>([]);
    const [filters, setFilters] = useState<MediaLibraryFilters>(initialFilters);
    const initialFiltersRef = useRef(initialFilters);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [isLoadingMore, setIsLoadingMore] = useState<boolean>(false);
    const [error, setError] = useState<Error | null>(null);

    const loadAssets = useCallback(
        async (isReset = true, cursor: string | null = null) => {
            if (isReset) {
                setIsLoading(true);
            } else {
                setIsLoadingMore(true);
            }
            setError(null);

            try {
                const params = buildQueryParams(filters, cursor);
                const response: CursorPaginatedResponse<MediaAsset> =
                    await mediaLibraryApi.getMediaAssets(params);

                setAssets((prev) => mergeLoadedAssets(prev, response.data, isReset, cursor));
                setNextCursor(response.next_cursor);
            } catch (err: unknown) {
                setError(err instanceof Error ? err : new Error('Failed to load media assets.'));
            } finally {
                setIsLoading(false);
                setIsLoadingMore(false);
            }
        },
        [filters],
    );

    useEffect(() => {
        loadAssets(true, null);
    }, [loadAssets]);

    // Automatically poll while any assets in the grid are processing in the background queue
    useEffect(() => {
        const hasProcessing = assets.some((a) => a.status === 'processing');
        if (!hasProcessing) return;

        const timer = setInterval(() => {
            loadAssets(false, null);
        }, 2000);

        return () => clearInterval(timer);
    }, [assets, loadAssets]);

    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Automatically receive newly uploaded assets from any component without needing hard refresh
    useEffect(() => {
        const handleAssetCompleted = (newAsset: MediaAsset) => {
            setAssets((prev) => upsertAsset(prev, newAsset));
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
            debounceTimerRef.current = setTimeout(() => {
                loadAssets(false, null);
            }, 1500);
        };
        const unregister = registerAssetCompletedListener(handleAssetCompleted);
        return () => {
            unregister();
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, [loadAssets]);

    const loadMore = useCallback(() => {
        if (!nextCursor || isLoadingMore || isLoading) return;
        loadAssets(false, nextCursor);
    }, [nextCursor, isLoadingMore, isLoading, loadAssets]);

    const updateFilters = useCallback((newFilters: Partial<MediaLibraryFilters>) => {
        setFilters((prev) => ({ ...prev, ...newFilters }));
    }, []);

    const resetFilters = useCallback(() => {
        setFilters(initialFiltersRef.current);
    }, []);

    const removeAssetLocal = useCallback((assetId: number) => {
        setAssets((prev) => prev.filter((a) => a.id !== assetId));
    }, []);

    const prependAssetLocal = useCallback((newAsset: MediaAsset) => {
        setAssets((prev) => upsertAsset(prev, newAsset));
    }, []);

    return {
        assets,
        isLoading,
        isLoadingMore,
        hasMore: Boolean(nextCursor),
        error,
        filters,
        updateFilters,
        resetFilters,
        loadMore,
        refetch: () => loadAssets(true, null),
        removeAssetLocal,
        prependAssetLocal,
    };
}
