import * as React from 'react';
import { toast } from 'sonner';
import { mediaLibraryApi } from '../api/media-library.api';
import type { PickerScope } from '../components/MediaPickerScopeBar';
import type { MediaAsset } from '../types/media-library.types';

export interface UseMediaPickerOptions {
    isOpen: boolean;
    /** Project context: the dropdown's default selection. */
    projectId?: number | null;
    /** Extra market filter (the SOW dialogs stay inside their report market). */
    marketId?: number | null;
    initialScope?: PickerScope;
    /** Asset ids to hide (already attached to the SOW report). */
    excludeIds?: number[];
    /** Reset search + scope every time the dialog opens (SOW behavior). */
    resetSearchOnOpen?: boolean;
}

/**
 * Shared data layer for the media pickers (COP drawer + SOW dialogs), so the
 * scope filter, live search and cursor pagination behave identically
 * everywhere. Stale responses are ignored, so fast typing can't reorder
 * pages.
 */
export function useMediaPicker({
    isOpen,
    projectId,
    marketId,
    initialScope,
    excludeIds,
    resetSearchOnOpen = false,
}: UseMediaPickerOptions) {
    const defaultScope: PickerScope = initialScope ?? (projectId ? 'project' : 'all');
    const [scope, setScopeState] = React.useState<PickerScope>(defaultScope);
    // Which project the `project` scope narrows to; defaults to the context one.
    const [scopeProjectId, setScopeProjectId] = React.useState<number | null>(projectId ?? null);
    const [search, setSearch] = React.useState('');
    // Uploader narrow-down (staff pickers only); null = everyone visible.
    const [userId, setUserIdState] = React.useState<number | null>(null);
    const [assets, setAssets] = React.useState<MediaAsset[]>([]);
    const [nextCursor, setNextCursor] = React.useState<string | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [isLoadingMore, setIsLoadingMore] = React.useState(false);
    const requestId = React.useRef(0);

    const excluded = React.useMemo(() => new Set(excludeIds ?? []), [excludeIds]);
    const openedRef = React.useRef(false);

    /** Scope and the project it narrows to always move together. */
    const setScope = React.useCallback((next: PickerScope, nextProjectId?: number) => {
        if (nextProjectId !== undefined) setScopeProjectId(nextProjectId);
        setScopeState(next);
    }, []);

    const fetchAssets = React.useCallback(
        async (reset: boolean, cursor: string | null) => {
            const id = ++requestId.current;
            if (reset) setIsLoading(true);
            else setIsLoadingMore(true);

            try {
                const params: Record<string, unknown> = { scope, sort: '-created_at' };
                // The context project travels even under the `all` and
                // `generic` scopes, so the picker keeps listing ready assets
                // only; `project` narrows to the project picked in the dropdown
                // instead, and `generic` asks for the untagged uploads.
                const targetProjectId = scope === 'project' ? (scopeProjectId ?? projectId) : projectId;
                if (targetProjectId) params.project_id = targetProjectId;
                // `scope` is a validated enum (project|market|all) — "generic"
                // is a dropdown concept the API spells as `all` + `unassigned`.
                if (scope === 'generic') {
                    params.scope = 'all';
                    params.unassigned = 1;
                }
                if (marketId) params.market_id = marketId;
                if (search.trim()) params.search = search.trim();
                if (userId) params.user_id = userId;
                if (cursor) params.cursor = cursor;

                const res = await mediaLibraryApi.getPickerAssets(params);
                if (requestId.current !== id) return;

                const incoming = (res.data ?? []).filter((asset) => !excluded.has(asset.id));
                setAssets((prev) => {
                    if (reset) return incoming;
                    const existing = new Set(prev.map((asset) => asset.id));
                    return [...prev, ...incoming.filter((asset) => !existing.has(asset.id))];
                });
                setNextCursor(res.next_cursor ?? null);
            } catch {
                if (requestId.current === id) toast.error('Failed to load photos from library.');
            } finally {
                if (requestId.current === id) {
                    setIsLoading(false);
                    setIsLoadingMore(false);
                }
            }
        },
        [scope, scopeProjectId, search, projectId, marketId, userId, excluded]
    );

    const loadMore = React.useCallback(() => {
        if (!isLoadingMore && nextCursor) void fetchAssets(false, nextCursor);
    }, [fetchAssets, isLoadingMore, nextCursor]);

    /** Remove a deleted asset from the local list (picker delete flow). */
    const removeAsset = React.useCallback((assetId: number) => {
        setAssets((prev) => prev.filter((a) => a.id !== assetId));
    }, []);

    // Open + any filter change refetches. The open-reset runs once per open:
    // without the guard, changing scope afterwards would look "dirty" and
    // snap straight back to the default (killing the This Project filter).
    React.useEffect(() => {
        if (!isOpen) {
            openedRef.current = false;
            return;
        }
        if (!openedRef.current) {
            openedRef.current = true;
            setUserIdState(null); // Fresh picker: never inherit a prior uploader filter.
            if (resetSearchOnOpen && (search !== '' || scope !== defaultScope)) {
                setSearch('');
                setScope(defaultScope);
                setScopeProjectId(projectId ?? null);
                setAssets([]);
                setNextCursor(null);
                return; // state change re-triggers → fetch with clean filters
            }
        }
        void fetchAssets(true, null);
    }, [isOpen, fetchAssets, resetSearchOnOpen, search, scope, defaultScope, projectId, setScope]);

    return {
        scope,
        setScope,
        scopeProjectId,
        search,
        setSearch,
        userId,
        setUserId: setUserIdState,
        assets,
        nextCursor,
        isLoading,
        isLoadingMore,
        loadMore,
        removeAsset,
    };
}
