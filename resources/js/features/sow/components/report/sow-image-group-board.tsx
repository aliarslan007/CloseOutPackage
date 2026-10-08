import { Button } from '@/components/ui/button';
import { ImagePlus, Images, Loader2, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { sowImagesApi } from '../../api/sow-images.api';
import { useCompositeStatuses } from '../../hooks/use-composite-statuses';
import { useSowImageGroups } from '../../hooks/use-sow-image-groups';
import { useSowReports } from '../../hooks/use-sow-reports';
import type { SowAnnotatedImage, SowReport } from '../../types/sow.types';
import { sowErrorMessage } from '../../utils/sow-error';
import { SowImageDeleteDialog } from './sow-image-delete-dialog';
import { SowImageGroupActionBar } from './sow-image-group-action-bar';
import { SowImageGroupCreateForm } from './sow-image-group-create-form';
import { SowImageGroupSection } from './sow-image-group-section';
import { SowImageStrip } from './sow-image-strip';

export interface SowImageGroupBoardProps {
    report: SowReport;
    canUpdate: boolean;
    maxImages?: number;
    selectedImageId?: number | null;
    onSelect: (imageId: number) => void;
    onImportClick: () => void;
    onUploadClick: () => void;
    isUploading: boolean;
    /** "Uploading… 3/12" while a multi-file batch runs; null when unknown. */
    uploadStatus?: string | null;
}

const UNGROUPED_TITLE = 'Other Photos';

/**
 * Groups photos into named sections that become the PDF's photo headings.
 *
 * Until the report has at least one group this renders the flat strip, so
 * existing reports look and behave exactly as before.
 */
export function SowImageGroupBoard({
    report,
    canUpdate,
    maxImages = 20,
    selectedImageId,
    onSelect,
    onImportClick,
    onUploadClick,
    isUploading,
    uploadStatus = null,
}: Readonly<SowImageGroupBoardProps>) {
    const { refresh } = useSowReports();
    const board = useSowImageGroups(report, canUpdate);
    const images = report.annotated_images ?? [];
    const atLimit = images.length >= maxImages;

    const { groups, imagesFor, ungrouped } = board;
    const boardImages = React.useMemo(
        () => [...groups.flatMap((group) => imagesFor(group.id)), ...ungrouped],
        [groups, imagesFor, ungrouped]
    );

    const { statuses } = useCompositeStatuses(
        report.id,
        boardImages.map((image) => image.id)
    );

    const [selecting, setSelecting] = React.useState(false);
    const [showCreate, setShowCreate] = React.useState(false);
    const [pendingId, setPendingId] = React.useState<number | null>(null);
    const [pendingDelete, setPendingDelete] = React.useState<SowAnnotatedImage | null>(null);

    // Whether the report HAS groups is a server fact, not local UI state — keying
    // this off the optimistic copy made an existing board fall back to the
    // "create a group" prompt whenever local state was reset (e.g. after a
    // submit/publish/reopen reload landed with stale local mirrors).
    const hasGroups = (report.image_groups ?? []).length > 0;

    const statusFor = (image: SowAnnotatedImage) => statuses[image.id] ?? image.composite_status;
    const canDeleteImage = (image: SowAnnotatedImage) => image.can_delete ?? canUpdate;

    const runImageAction = async (
        imageId: number,
        action: () => Promise<unknown>,
        success: string,
        failure: string
    ) => {
        setPendingId(imageId);
        try {
            await action();
            toast.success(success);
            refresh();
        } catch (err) {
            toast.error(sowErrorMessage(err, failure));
        } finally {
            setPendingId(null);
        }
    };

    const createGroup = (name: string) => {
        void board.createGroup(name).then(() => setShowCreate(false));
    };

    const newGroupForm = <SowImageGroupCreateForm busy={board.busy} onCreate={createGroup} />;

    // No groups on this report → keep the original flat strip so reports that
    // never opt in to grouping behave exactly as they did before.
    if (!hasGroups) {
        return (
            <div className="space-y-3">
                {canUpdate &&
                    (showCreate ? (
                        newGroupForm
                    ) : (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-8 text-xs"
                            onClick={() => setShowCreate(true)}
                        >
                            Group photos for the PDF
                        </Button>
                    ))}
                <SowImageStrip
                    report={report}
                    canUpdate={canUpdate}
                    maxImages={maxImages}
                    selectedImageId={selectedImageId}
                    onSelect={onSelect}
                    onImportClick={onImportClick}
                    onUploadClick={onUploadClick}
                    isUploading={isUploading}
                    uploadStatus={uploadStatus}
                />
            </div>
        );
    }

    const sectionProps = {
        canUpdate,
        selectable: selecting,
        selectedIds: board.selectedIds,
        busy: board.busy,
        statusFor,
        canDeleteImage,
        onToggleSelection: board.toggleSelection,
        onOpen: onSelect,
        onSetCover: (imageId: number) =>
            void runImageAction(
                imageId,
                () => sowImagesApi.update(report.id, imageId, { is_cover: true }),
                'Cover photo set.',
                'Failed to set cover photo.'
            ),
        onToggleInclude: (imageId: number, includeInPdf: boolean) =>
            void runImageAction(
                imageId,
                () => sowImagesApi.update(report.id, imageId, { include_in_pdf: !includeInPdf }),
                includeInPdf ? 'Removed from PDF.' : 'Included in PDF.',
                'Failed to update PDF inclusion.'
            ),
        onRemove: (imageId: number) =>
            setPendingDelete(boardImages.find((image) => image.id === imageId) ?? null),
        onReorderImages: (key: number | null, fromId: number, toId: number) =>
            void board.reorderImagesWithin(key, fromId, toId),
        onMoveImagesTo: (imageIds: number[], targetGroupId: number | null) =>
            void board.moveImagesTo(imageIds, targetGroupId),
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Photos{' '}
                    <span className="font-normal text-muted-foreground">
                        ({images.length} / {maxImages})
                    </span>
                </h3>
                {canUpdate && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            variant={showCreate ? 'secondary' : 'outline'}
                            className="h-8 text-xs"
                            disabled={board.busy}
                            onClick={() => setShowCreate((prev) => !prev)}
                        >
                            New group
                        </Button>
                        <Button
                            size="sm"
                            variant={selecting ? 'secondary' : 'outline'}
                            className="h-8 text-xs"
                            onClick={() => {
                                setSelecting((prev) => !prev);
                                board.clearSelection();
                            }}
                        >
                            {selecting ? 'Done selecting' : 'Select photos'}
                        </Button>
                        <Button
                            size="sm"
                            variant="default"
                            className="h-8 gap-1.5 text-xs"
                            disabled={isUploading || atLimit}
                            onClick={onUploadClick}
                        >
                            {isUploading ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Upload className="size-3.5" />
                            )}
                            {isUploading ? (uploadStatus ?? 'Uploading…') : 'Upload Photos'}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-8 gap-1.5 text-xs"
                            disabled={atLimit}
                            onClick={onImportClick}
                        >
                            <ImagePlus className="size-3.5" />
                            Import
                        </Button>
                    </div>
                )}
            </div>

            {showCreate && canUpdate && newGroupForm}

            {selecting && board.selectedIds.size > 0 && (
                <SowImageGroupActionBar
                    selectedCount={board.selectedIds.size}
                    groups={board.groups}
                    busy={board.busy}
                    onMoveTo={(groupId) => void board.moveSelectedTo(groupId)}
                    onClear={board.clearSelection}
                />
            )}

            <div className="space-y-3">
                {board.groups.map((group) => (
                    <SowImageGroupSection
                        key={group.id}
                        groupId={group.id}
                        title={group.name}
                        images={board.imagesFor(group.id)}
                        busyId={pendingId}
                        onRename={(groupId, name) => void board.renameGroup(groupId, name)}
                        onDelete={(groupId) => void board.deleteGroup(groupId)}
                        onReorderGroups={(fromId, toId) => void board.reorderGroups(fromId, toId)}
                        {...sectionProps}
                    />
                ))}

                {board.ungrouped.length > 0 && (
                    <SowImageGroupSection
                        groupId={null}
                        title={UNGROUPED_TITLE}
                        images={board.ungrouped}
                        busyId={pendingId}
                        onRename={() => {
                            /* The ungrouped bucket is not renamable. */
                        }}
                        onDelete={() => {
                            /* Deleting the bucket is meaningless. */
                        }}
                        {...sectionProps}
                    />
                )}
            </div>

            {images.length === 0 && (
                <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border py-10 text-center">
                    <div className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                        <Images className="size-5" />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        No photos yet — import field survey photos from the Media Library.
                    </p>
                    {canUpdate && (
                        <Button size="sm" variant="outline" className="gap-1.5" onClick={onImportClick}>
                            <ImagePlus className="size-3.5" />
                            Import from Media Library
                        </Button>
                    )}
                </div>
            )}

            <SowImageDeleteDialog
                reportId={report.id}
                image={pendingDelete}
                onClose={() => setPendingDelete(null)}
            />
        </div>
    );
}
