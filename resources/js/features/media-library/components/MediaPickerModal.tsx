import { Button } from '@/components/ui/button';
import { usePermissions } from '@/lib/permissions';
import { Images, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { mediaLibraryApi } from '../api/media-library.api';
import { useMediaPicker } from '../hooks/use-media-picker';
import { useTeamSupervisors } from '../hooks/use-team-supervisors';
import type { MediaAsset } from '../types/media-library.types';
import { MediaAssetDeleteDialog } from './MediaAssetDeleteDialog';
import { MediaPickerGrid } from './MediaPickerGrid';
import { MediaPickerItem } from './MediaPickerItem';
import { MediaPickerScopeBar } from './MediaPickerScopeBar';
import { MediaUploaderFilter, uploaderSectionLabel } from './MediaUploaderFilter';

export interface MediaPickerModalProps {
    isOpen: boolean;
    onClose: () => void;
    projectId?: number;
    categoryName?: string;
    categorySelectionId?: number;
    availableSlots?: number;
    /** Shown as the default project option in the scope dropdown. */
    projectName?: string;
    /** Already-attached asset ids to hide (SOW report photos). */
    excludeIds?: number[];
    /**
     * Host-managed assignment (change orders etc.): receives the selected
     * asset ids instead of the COP category-assign flow. When present, slot
     * limits and the category target header do not apply.
     */
    onAssign?: (assetIds: number[]) => Promise<void>;
    onAssignAssets?: (assets: MediaAsset[]) => Promise<void> | void;
    onAssigned?: () => void;
    /** Submit-button label for host-managed assignment (defaults to the COP wording). */
    submitLabel?: string;
}

export function MediaPickerModal({
    isOpen,
    onClose,
    projectId,
    categoryName,
    categorySelectionId,
    availableSlots,
    projectName,
    excludeIds,
    onAssign,
    onAssignAssets,
    onAssigned,
    submitLabel,
}: Readonly<MediaPickerModalProps>) {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [assetToDelete, setAssetToDelete] = useState<MediaAsset | null>(null);
    // All Photos is the default scope; the project option is a deliberate filter.
    const picker = useMediaPicker({ isOpen, projectId, initialScope: 'all', excludeIds });
    const { user, isAdmin, isManager, isFieldSupervisor } = usePermissions();
    const showUploaderFilter = isAdmin || isManager || isFieldSupervisor;
    const { supervisors } = useTeamSupervisors(showUploaderFilter && isOpen);

    useEffect(() => {
        if (isOpen) setSelectedIds(new Set());
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) return;
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && !isSubmitting) onClose();
        };
        globalThis.addEventListener('keydown', handleKeyDown);
        return () => globalThis.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, isSubmitting, onClose]);

    const handleToggleSelect = (asset: MediaAsset) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(asset.id)) {
                next.delete(asset.id);
            } else {
                if (availableSlots !== undefined && next.size >= availableSlots) {
                    toast.warning(`Category capacity limit reached (${availableSlots} slots).`);
                    return prev;
                }
                next.add(asset.id);
            }
            return next;
        });
    };

    const handleAssign = async () => {
        if (selectedIds.size === 0 || (onAssign === undefined && onAssignAssets === undefined && categorySelectionId === undefined)) return;
        setIsSubmitting(true);
        try {
            const ids = Array.from(selectedIds);

            if (onAssignAssets) {
                const selectedAssets = picker.assets.filter((a) => selectedIds.has(a.id));
                // The host owns the result toast: it may clamp to its own cap.
                await onAssignAssets(selectedAssets);
                onAssigned?.();
                onClose();
                return;
            }

            if (onAssign) {
                await onAssign(ids);
                toast.success(`Added ${ids.length} photo(s).`);
                onAssigned?.();
                onClose();
                return;
            }

            // COP category flow (guarded above against undefined).
            if (categorySelectionId === undefined) return;

            await mediaLibraryApi.assignMediaAsset({
                media_asset_ids: ids,
                category_selection_id: categorySelectionId,
            });
            toast.success(`Added ${ids.length} photo(s) to ${categoryName}.`);
            onAssigned?.();
            onClose();
        } catch (err: unknown) {
            const data = (
                err as {
                    response?: {
                        data?: {
                            message?: string;
                            errors?: Record<string, string[]>;
                        };
                    };
                }
            )?.response?.data;
            const message =
                data?.errors?.category_selection_id?.[0] ??
                data?.message ??
                'Failed to add selected photo(s).';
            toast.error(message);
        } finally {
            setIsSubmitting(false);
        }
    };

    const isSlotLimitReached = availableSlots !== undefined && selectedIds.size >= availableSlots;

    if (!isOpen) return null;

    return (
        <>
            <div
                className="fixed inset-0 z-40 bg-black/50 sm:hidden"
                onClick={onClose}
                aria-hidden="true"
            />
            <dialog
                open
                aria-label="Media Library Picker"
                /* max-h-dvh defeats the UA `dialog` max-height, which otherwise
                   leaves a strip of page visible on phones. The safe-area inset
                   stays: the drawer is full-bleed, so content must clear the notch. */
                className="fixed inset-y-0 right-0 left-auto z-40 m-0 flex h-dvh max-h-dvh w-full max-w-full flex-col overflow-hidden border-0 border-l border-border bg-card p-0 shadow-2xl transition-all duration-300 animate-in slide-in-from-right sm:max-w-2xl md:max-w-3xl lg:max-w-4xl"
            >
                <div className="flex items-start justify-between px-4 pt-[calc(0.5rem+env(safe-area-inset-top))] pb-3 sm:pb-4 sm:pt-[calc(1rem+env(safe-area-inset-top))] border-b border-border bg-muted/20">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                            <Images className="size-4 text-indigo-600 dark:text-indigo-400" />
                            <span>Media Library</span>
                        </div>
                        {availableSlots !== undefined && (
                            <p className="text-xs text-muted-foreground">
                                Under this scope you can add up to{' '}
                                <span className="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                    {availableSlots}
                                </span>{' '}
                                {availableSlots === 1 ? 'picture' : 'pictures'}.
                            </p>
                        )}
                        {categoryName !== undefined && (
                            <p className="text-[11px] text-muted-foreground flex items-center gap-1.5 font-medium">
                                <span className="inline-block size-1.5 rounded-full bg-indigo-500 animate-pulse" />
                                <span>Drag any photo directly onto any category on the page.</span>
                            </p>
                        )}
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onClose}
                        disabled={isSubmitting}
                        className="size-10 sm:size-8 rounded-md text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                        aria-label="Close media library"
                    >
                        <X className="size-4" />
                    </Button>
                </div>

                <MediaPickerScopeBar
                    scope={picker.scope}
                    onScopeChange={picker.setScope}
                    scopeProjectId={picker.scopeProjectId}
                    search={picker.search}
                    onSearchChange={picker.setSearch}
                    projectId={projectId}
                    projectName={projectName}
                    extraFilters={
                        showUploaderFilter ? (
                            <MediaUploaderFilter
                                currentUserId={picker.userId}
                                loggedInUserId={user?.id}
                                technicians={supervisors}
                                sectionLabel={uploaderSectionLabel(isFieldSupervisor, isAdmin)}
                                onChange={(userId) => picker.setUserId(userId ?? null)}
                            />
                        ) : null
                    }
                />

                <div className="flex-1 min-h-0 overflow-y-auto overscroll-y-contain p-4">
                    <MediaPickerGrid
                        isLoading={picker.isLoading}
                        isLoadingMore={picker.isLoadingMore}
                        hasMore={picker.nextCursor !== null}
                        onLoadMore={picker.loadMore}
                    >
                        {picker.assets.map((asset) => (
                            <MediaPickerItem
                                key={asset.id}
                                asset={asset}
                                isSelected={selectedIds.has(asset.id)}
                                isDisabled={isSlotLimitReached}
                                selectedIds={selectedIds}
                                onToggle={handleToggleSelect}
                                onDelete={setAssetToDelete}
                            />
                        ))}
                    </MediaPickerGrid>
                </div>

                <div className="px-3.5 pt-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] border-t border-border bg-muted/20 flex flex-col sm:flex-row items-center justify-between gap-2.5">
                    <span className="text-xs text-muted-foreground">
                        <span className="font-semibold text-foreground">{selectedIds.size}</span> of{' '}
                        {availableSlots} selected
                        {picker.assets.length > 0 && (
                            <span className="text-muted-foreground/60">
                                {' '}
                                ({picker.assets.length} photos)
                            </span>
                        )}
                    </span>
                    <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClose}
                            disabled={isSubmitting}
                            className="h-11 text-sm sm:h-8 sm:text-xs flex-1 sm:flex-none"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={handleAssign}
                            disabled={selectedIds.size === 0 || isSubmitting}
                            loading={isSubmitting}
                            className="h-11 text-sm sm:h-8 sm:text-xs flex-1 sm:flex-none bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-xs"
                        >
                            {selectedIds.size === 0
                                ? (submitLabel ?? 'Insert Photos in COP')
                                : `Insert ${selectedIds.size} Photo(s)`}
                        </Button>
                    </div>
                </div>
            </dialog>

            <MediaAssetDeleteDialog
                asset={assetToDelete}
                isOpen={Boolean(assetToDelete)}
                onClose={() => setAssetToDelete(null)}
                onDeleted={(assetId) => {
                    picker.removeAsset(assetId);
                    setSelectedIds((prev) => {
                        const next = new Set(prev);
                        next.delete(assetId);
                        return next;
                    });
                }}
            />
        </>
    );
}
