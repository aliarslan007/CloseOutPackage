import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MediaPickerItem } from '@/features/media-library/components/MediaPickerItem';
import { MediaPickerGrid } from '@/features/media-library/components/MediaPickerGrid';
import { MediaPickerScopeBar } from '@/features/media-library/components/MediaPickerScopeBar';
import { MediaAssetDeleteDialog } from '@/features/media-library/components/MediaAssetDeleteDialog';
import { MediaUploaderFilter, uploaderSectionLabel } from '@/features/media-library/components/MediaUploaderFilter';
import { useMediaPicker } from '@/features/media-library/hooks/use-media-picker';
import { useTeamSupervisors } from '@/features/media-library/hooks/use-team-supervisors';
import type { MediaAsset } from '@/features/media-library/types/media-library.types';
import { usePermissions } from '@/lib/permissions';
import { ImagePlus, Loader2, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { sowMediaImportApi } from '../../api/sow-media-import.api';
import { sowErrorMessage as errorMessage } from '../../utils/sow-error';

export interface SowMediaLibraryModalProps {
    isOpen: boolean;
    onClose: () => void;
    reportId?: number;
    marketId?: number;
    /** Project context for the "This Project" scope (omit for standalone SOWs). */
    projectId?: number | null;
    /** Shown as the project option in the scope dropdown. */
    projectName?: string;
    /** media_asset_ids already attached to the report — hidden from the picker. */
    alreadyAttachedIds: number[];
    onImported?: () => void;
    /** Single-pick mode: skips the import POST and returns the chosen asset. */
    singleSelect?: boolean;
    onPick?: (asset: MediaAsset) => void;
    title?: string;
    ctaLabel?: string;
}

/**
 * §1.1 — bulk-select field-survey photos from the Media Library. Runs on the
 * same picker core as the closeout-package dialog (scope filter, live search,
 * cursor pagination). Pulls all user media by default without market-id bounding.
 */
export function SowMediaLibraryModal({
    isOpen,
    onClose,
    reportId,
    projectId,
    projectName,
    alreadyAttachedIds,
    onImported,
    singleSelect = false,
    onPick,
    title,
    ctaLabel,
}: Readonly<SowMediaLibraryModalProps>) {
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [isImporting, setIsImporting] = React.useState(false);
    const [assetToDelete, setAssetToDelete] = React.useState<MediaAsset | null>(null);
    const excludeIds = React.useMemo(
        () => alreadyAttachedIds,
        // Parent builds this array inline — compare by value, not identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [alreadyAttachedIds.join(',')]
    );
    const picker = useMediaPicker({
        isOpen,
        projectId,
        marketId: undefined, // Do not filter by marketId: pull all media in the user's library
        initialScope: 'all',
        excludeIds,
        resetSearchOnOpen: true,
    });
    const { user, isAdmin, isManager, isFieldSupervisor } = usePermissions();
    const showUploaderFilter = isAdmin || isManager || isFieldSupervisor;
    const { supervisors } = useTeamSupervisors(showUploaderFilter && isOpen);

    React.useEffect(() => {
        if (isOpen) setSelectedIds(new Set());
    }, [isOpen]);

    const toggle = (asset: MediaAsset) => {
        if (singleSelect) {
            setSelectedIds((prev) => (prev.has(asset.id) ? new Set() : new Set([asset.id])));
            return;
        }
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(asset.id)) next.delete(asset.id);
            else next.add(asset.id);
            return next;
        });
    };

    const importSelected = async () => {
        if (selectedIds.size === 0) return;
        if (singleSelect) {
            const picked = picker.assets.find((asset) => selectedIds.has(asset.id));
            if (picked) onPick?.(picked);
            onClose();
            return;
        }
        if (reportId === undefined) return;
        setIsImporting(true);
        try {
            const imported = await sowMediaImportApi.importMedia(reportId, Array.from(selectedIds));
            if (imported > 0) {
                toast.success(`${imported} photo${imported === 1 ? '' : 's'} imported.`);
            } else {
                toast.success('Photos already attached — nothing new to import.');
            }
            onImported?.();
            onClose();
        } catch (err) {
            toast.error(errorMessage(err, 'Failed to import photos.'));
        } finally {
            setIsImporting(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{title ?? 'Import from Media Library'}</DialogTitle>
                    <DialogDescription>
                        {singleSelect ? (
                            <>
                                Choose one photo to use. You can change it again after creating the
                                report.
                            </>
                        ) : (
                            <>
                                Select field-survey photos to add to this SOW report (
                                {selectedIds.size} selected).
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <MediaPickerScopeBar
                    scope={picker.scope}
                    onScopeChange={picker.setScope}
                    scopeProjectId={picker.scopeProjectId}
                    search={picker.search}
                    onSearchChange={picker.setSearch}
                    projectId={projectId ?? undefined}
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

                <div className="max-h-[420px] overflow-y-auto px-0.5 py-0.5">
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
                                isDisabled={false}
                                onToggle={toggle}
                                onDelete={setAssetToDelete}
                            />
                        ))}
                    </MediaPickerGrid>
                </div>

                <DialogFooter className="flex items-center justify-between gap-2">
                    <div className="text-xs text-muted-foreground">
                        {picker.assets.length} shown
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={onClose}>
                            <X className="size-3.5" />
                            Cancel
                        </Button>
                        <Button
                            onClick={importSelected}
                            disabled={selectedIds.size === 0 || isImporting}
                            className="gap-1.5"
                        >
                            {isImporting ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <ImagePlus className="size-3.5" />
                            )}
                            {isImporting
                                ? 'Importing…'
                                : (ctaLabel ?? `Import ${selectedIds.size || ''}`.trim())}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>

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
        </Dialog>
    );
}
