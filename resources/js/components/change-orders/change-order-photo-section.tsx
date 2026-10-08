import { ChangeOrderPhotoGallery } from '@/components/change-orders/change-order-photo-gallery';
import type {
    ChangeOrder,
    ChangeOrderPhoto,
} from '@/components/change-orders/change-order-types';
import { useChangeOrderPhotos } from '@/components/change-orders/use-change-order-photos';
import { useMediaAssetEditor } from '@/components/change-orders/use-media-asset-editor';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import { MediaStagingPanel } from '@/features/media-edit';
import {
    MediaCanvasEditorDialog,
    MediaPickerModal,
    type MediaAsset,
} from '@/features/media-library';
import { ImagePlus, Images, Loader2, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface ChangeOrderPhotoSectionProps {
    changeOrder: ChangeOrder;
    /** Shown in the media picker header. */
    projectName?: string;
    /**
     * Max photos a single change order may carry. Supplied by the backend
     * (`photoLimit` prop) so client and server never drift apart.
     */
    photoLimit?: number;
}

function PhotoEmptyState({ canManage }: Readonly<{ canManage: boolean }>) {
    return (
        <div className="flex flex-col items-center justify-center gap-2.5 rounded-lg border border-dashed border-border/80 bg-muted/20 px-6 py-10 text-center">
            <span className="flex size-11 items-center justify-center rounded-full border border-border bg-card text-muted-foreground">
                <ImagePlus className="size-5" />
            </span>
            <p className="max-w-md text-[11px] leading-relaxed text-muted-foreground">
                {canManage
                    ? 'Add site photos, drawings or vendor quotes so the customer can see the work behind this change order.'
                    : 'Photos added to this change order will appear here.'}
            </p>
        </div>
    );
}

/**
 * Supporting photos for a change order.
 *
 * Deliberately its own section rather than a block inside the cover-note card:
 * a ten or twenty photo grid would stretch that card far past the activity
 * history beside it and leave a large dead column behind.
 */
export function ChangeOrderPhotoSection({
    changeOrder,
    projectName,
    photoLimit,
}: Readonly<ChangeOrderPhotoSectionProps>) {
    const {
        canManage,
        photos,
        room,
        fileInputRef,
        progress,
        isUploading,
        staging,
        chooseFiles,
        uploadStaged,
        browseFiles,
        attach,
        photoToRemove,
        requestRemove,
        cancelRemove,
        confirmRemove,
        isRemoving,
    } = useChangeOrderPhotos(changeOrder, photoLimit);

    const [pickerOpen, setPickerOpen] = React.useState(false);
    // Every change-order photo sits on a Media Library asset, so the canvas
    // editor opens on that source asset.
    const editor = useMediaAssetEditor();
    const stagedCount = staging.items.length;

    const handleEditPhoto = (photo: ChangeOrderPhoto) => {
        if (photo.media_asset_id) void editor.open(photo.media_asset_id);
    };

    /**
     * The change order keeps its own copy of the file, so editing the library
     * asset in place would never reach this page. Saving a new annotated copy
     * and attaching it is what actually lands here.
     */
    const handleAnnotatedCopy = (saved: MediaAsset) => {
        void attach([saved.id]).catch(() =>
            toast.error('Could not add the annotated copy to this change order.')
        );
    };
    const count = photos.length;
    const pluralSuffix = count === 1 ? '' : 's';
    const countLabel =
        count === 0 ? 'No photos attached' : `${count} photo${pluralSuffix} attached`;

    return (
        <div className="overflow-hidden rounded-xl border border-border/80 bg-card shadow-xs">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border/70 bg-muted/20 px-4 py-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <ImagePlus className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <p className="text-xs font-semibold text-foreground">{countLabel}</p>
                        <p className="text-[11px] text-muted-foreground">
                            Shown to the customer on the decision page.
                        </p>
                    </div>
                </div>

                {canManage && (
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(event) => void chooseFiles(event)}
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isUploading || room === 0}
                            onClick={browseFiles}
                            className="h-8 gap-1.5 text-xs cursor-pointer"
                        >
                            <Upload className="size-3.5" />
                            <span>Upload from device</span>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={isUploading || room === 0}
                            onClick={() => setPickerOpen(true)}
                            className="h-8 gap-1.5 text-xs cursor-pointer"
                        >
                            <Images className="size-3.5" />
                            <span>From library</span>
                        </Button>
                    </div>
                )}
            </div>

            {progress !== null && (
                <div className="flex flex-col gap-2 border-b border-border/70 bg-muted/10 px-4 py-2.5">
                    <div className="flex items-center gap-2 text-[11px] text-muted-foreground">
                        <Loader2 className="size-3.5 shrink-0 animate-spin text-primary" />
                        <span className="truncate">
                            Uploading {progress.done + 1} of {progress.total}: {progress.label}
                        </span>
                        <span className="ml-auto shrink-0 font-mono tabular-nums">
                            {progress.pct}%
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={progress.pct}
                        aria-label="Photo upload progress"
                        className="h-1 w-full overflow-hidden rounded-full bg-border"
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width] duration-300"
                            style={{ width: `${progress.pct}%` }}
                        />
                    </div>
                </div>
            )}

            {stagedCount > 0 && (
                <div className="border-b border-border/70 bg-muted/10 px-4 py-3">
                    <MediaStagingPanel
                        staging={staging}
                        onUpload={() => void uploadStaged()}
                        isUploading={isUploading}
                        uploadNoun="photo"
                    />
                </div>
            )}

            <div className="p-4">
                {count === 0 ? (
                    <PhotoEmptyState canManage={canManage} />
                ) : (
                    <ChangeOrderPhotoGallery
                        photos={photos}
                        onDelete={canManage ? requestRemove : undefined}
                        onEdit={canManage ? handleEditPhoto : undefined}
                    />
                )}
            </div>

            <MediaPickerModal
                isOpen={pickerOpen}
                onClose={() => setPickerOpen(false)}
                projectId={changeOrder.project_id}
                projectName={projectName}
                availableSlots={room}
                onAssign={attach}
            />

            <ConfirmDialog
                open={photoToRemove !== null}
                onOpenChange={(open) => {
                    if (!open) cancelRemove();
                }}
                title="Remove photo"
                description={`Remove "${photoToRemove?.original_name ?? ''}" from this change order? The original stays in the project media library.`}
                confirmLabel="Remove"
                variant="destructive"
                loading={isRemoving}
                onConfirm={() => void confirmRemove()}
            />

            {/* Canvas editor for photos that are already library assets */}
            <MediaCanvasEditorDialog
                asset={editor.asset}
                isOpen={editor.isOpen}
                onClose={editor.close}
                allowOverwrite={false}
                onSaved={handleAnnotatedCopy}
            />
        </div>
    );
}

export default ChangeOrderPhotoSection;
