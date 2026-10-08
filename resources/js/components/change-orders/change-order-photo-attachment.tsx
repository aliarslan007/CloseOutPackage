import { Button } from '@/components/ui/button';
import {
    MediaCanvasEditorDialog,
    MediaPickerModal,
    useChunkedUpload,
    useMediaConfig,
    type MediaAsset,
} from '@/features/media-library';
import { MediaStagingPanel, useMediaStaging } from '@/features/media-edit';
import { cn } from '@/lib/utils';
import { Images, Loader2, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import {
    collectValidFiles,
    hasMediaOrFileDrop,
    mediaAssetFileUrl,
    mediaAssetToPhoto,
    parseDroppedMediaAssets,
    photoKey,
    uploadThroughLibrary,
    type AttachedPhoto,
    type UploadProgress,
} from './change-order-photo-helpers';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ChangeOrderPhotoDropzone } from './change-order-photo-dropzone';
import { useMediaAssetEditor } from './use-media-asset-editor';
import { DEFAULT_CHANGE_ORDER_PHOTO_LIMIT } from './change-order-types';

export type { AttachedPhoto } from './change-order-photo-helpers';

export interface ChangeOrderPhotoAttachmentProps {
    projectId: number;
    projectName?: string;
    photos: AttachedPhoto[];
    onChange: (photos: AttachedPhoto[]) => void;
    disabled?: boolean;
    className?: string;
    /**
     * Max photos a single change order may carry. Supplied by the backend
     * (`photoLimit` prop) so client and server never drift apart.
     */
    maxPhotos?: number;
}

export function ChangeOrderPhotoAttachment({
    projectId,
    projectName,
    photos = [],
    onChange,
    disabled = false,
    className,
    maxPhotos = DEFAULT_CHANGE_ORDER_PHOTO_LIMIT,
}: Readonly<ChangeOrderPhotoAttachmentProps>) {
    const { config } = useMediaConfig();
    const { uploadFile } = useChunkedUpload();
    // Device files wait here so a photo can be marked up before it is uploaded.
    const staging = useMediaStaging();
    // Library picks and finished uploads are assets — they annotate in place.
    const editor = useMediaAssetEditor();

    const fileInputRef = React.useRef<HTMLInputElement>(null);
    const [pickerOpen, setPickerOpen] = React.useState(false);
    const [progress, setProgress] = React.useState<UploadProgress | null>(null);
    const [isDragOver, setIsDragOver] = React.useState(false);
    // A staged photo is dropped in two steps so a mis-click cannot lose one.
    const [photoToRemove, setPhotoToRemove] = React.useState<AttachedPhoto | null>(null);
    const dragCounter = React.useRef(0);

    const photoLimit = Math.max(1, maxPhotos);
    const remainingSlots = Math.max(0, photoLimit - photos.length);
    const isAtLimit = remainingSlots === 0;
    // Slots left once the files still waiting to be uploaded are counted.
    const room = Math.max(0, remainingSlots - staging.items.length);

    /**
     * Single funnel for every photo source (device upload, library pick, drag).
     * Dedupes assets already attached and clamps to the remaining cap, so the
     * client can never stage more photos than the server accepts.
     * Returns how many were actually added.
     */
    const addPhotos = (incoming: AttachedPhoto[], max: number): number => {
        const known = new Set(photos.map(photoKey).filter((key): key is number => key !== null));
        const additions = incoming
            .filter((photo) => {
                const key = photoKey(photo);
                if (key === null) return true;
                if (known.has(key)) return false;
                known.add(key);
                return true;
            })
            .slice(0, max);

        if (additions.length === 0) return 0;

        onChange([...photos, ...additions]);
        return additions.length;
    };

    const warnIfTruncated = (added: number, requested: number) => {
        if (added < requested) {
            toast.warning(
                `Only ${added} of ${requested} photo(s) were added — a change order can include at most ${photoLimit} photos.`
            );
        }
    };

    const handleFiles = async (filesToUpload: File[]) => {
        if (filesToUpload.length === 0 || !config || disabled) return;

        if (room === 0) {
            toast.error(`Photo limit reached — a change order can include at most ${photoLimit} photos.`);
            return;
        }

        // Cap the queue too, so we do not ship files that get discarded.
        const candidates = filesToUpload.slice(0, room);
        warnIfTruncated(candidates.length, filesToUpload.length);

        const valid = await collectValidFiles(candidates, config);
        staging.add(valid);
    };

    /** Commits the staged batch; the library assets it produces are attached. */
    const uploadStaged = async () => {
        const valid = staging.items.map((item) => item.file);
        if (valid.length === 0 || !config) return;

        setProgress({ done: 0, total: valid.length, label: valid[0]?.name ?? '', pct: 0 });

        const result = await uploadThroughLibrary(
            valid,
            config,
            projectId,
            uploadFile,
            (name) => setProgress((prev) => (prev ? { ...prev, label: name, pct: 0 } : prev)),
            (pct) => setProgress((prev) => (prev ? { ...prev, pct } : prev)),
            () => setProgress((prev) => (prev ? { ...prev, done: prev.done + 1 } : prev))
        );

        setProgress(null);
        // ponytail: one shot per batch — a partial failure drops the failed
        // files from staging (re-pick to retry) instead of tracking which ones
        // succeeded, which would re-upload duplicates on every retry.
        staging.reset();

        const filePreviewMap = new Map<string, string>();
        for (const file of valid) {
            try {
                filePreviewMap.set(file.name, URL.createObjectURL(file));
            } catch {
                // Ignore URL creation failure
            }
        }

        if (result.assets.length > 0) {
            // Clamp by remainingSlots, not room: the staged batch has just been
            // consumed, so those slots are free again — `room` is stale here.
            const added = addPhotos(
                result.assets.map((a) => mediaAssetToPhoto(a, filePreviewMap.get(a.original_filename))),
                remainingSlots
            );

            if (added > 0) {
                toast.success(`Attached ${added} photo(s) to this change order.`);
            }
        }

        if (result.failures > 0) {
            toast.error(
                result.failures === valid.length
                    ? 'Upload failed. Please check the files and try again.'
                    : `${result.failures} of ${valid.length} photo(s) failed to upload.`
            );
        }
    };

    const handleFilesChosen = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';
        void handleFiles(files);
    };

    const handleAssignFromLibrary = (selectedAssets: MediaAsset[]) => {
        if (room === 0) {
            toast.error(`Photo limit reached — a change order can include at most ${photoLimit} photos.`);
            return;
        }

        const added = addPhotos(selectedAssets.map((a) => mediaAssetToPhoto(a)), room);

        if (added === 0) {
            toast.info('Selected photo(s) are already attached.');
            return;
        }

        toast.success(`Added ${added} photo(s) from library.`);
        warnIfTruncated(added, selectedAssets.length);
    };

    const confirmRemove = () => {
        if (photoToRemove === null) return;
        onChange(photos.filter((photo) => photo.id !== photoToRemove.id));
        setPhotoToRemove(null);
    };

    const handleEditPhoto = (photo: AttachedPhoto) => {
        if (photo.media_asset_id) void editor.open(photo.media_asset_id);
    };

    /**
     * Only photos not yet on the change order: it copies files on save, so an
     * attached photo holds its own copy the library asset cannot reach.
     */
    const canEditPhoto = (photo: AttachedPhoto): boolean =>
        photo.photo_id === undefined && Boolean(photo.media_asset_id);

    /**
     * "Save as new copy" returns a fresh asset the change order can carry;
     * "Overwrite" returns the same one, so only its tile needs refreshing.
     */
    const handleAssetAnnotated = (saved: MediaAsset) => {
        const index = photos.findIndex(
            (photo) => canEditPhoto(photo) && photoKey(photo) === saved.id
        );

        if (index === -1) {
            addPhotos([mediaAssetToPhoto(saved)], remainingSlots);
            return;
        }

        const refreshed = mediaAssetToPhoto(saved);
        onChange(
            photos.map((photo, i) =>
                i === index
                    ? { ...photo, url: refreshed.url, size_bytes: refreshed.size_bytes }
                    : photo
            )
        );
    };

    // Drag and drop handlers
    const handleDragEnter = (e: React.DragEvent<HTMLDivElement>) => {
        if (disabled) return;
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current += 1;
        if (hasMediaOrFileDrop(e.dataTransfer.types)) {
            setIsDragOver(true);
        }
    };

    const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
        if (disabled) return;
        e.preventDefault();
        e.stopPropagation();
        if (hasMediaOrFileDrop(e.dataTransfer.types)) {
            e.dataTransfer.dropEffect = 'copy';
        }
    };

    const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current -= 1;
        if (dragCounter.current <= 0) {
            dragCounter.current = 0;
            setIsDragOver(false);
        }
    };

    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        if (disabled) return;
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current = 0;
        setIsDragOver(false);

        // 1. Check for media assets dragged from the open Media Library sidebar
        const droppedAssets = parseDroppedMediaAssets(e.dataTransfer);
        if (droppedAssets.length > 0) {
            if (room === 0) {
                toast.error(`Photo limit reached — a change order can include at most ${photoLimit} photos.`);
                return;
            }

            const added = addPhotos(
                droppedAssets.map((asset) => ({
                    id: `asset-${asset.id}`,
                    media_asset_id: asset.id,
                    original_name: asset.original_name,
                    url: mediaAssetFileUrl(asset.id, null, asset.url),
                })),
                room
            );

            if (added === 0) {
                toast.info('Dropped photo(s) are already attached.');
                return;
            }

            toast.success(`Added ${added} photo(s) from media library.`);
            warnIfTruncated(added, droppedAssets.length);
            return;
        }

        // 2. Check for files dragged from user's machine
        const droppedFiles = Array.from(e.dataTransfer.files ?? []).filter(
            (f) => f.type.startsWith('image/') || /\.(jpe?g|png|webp|heic)$/i.test(f.name)
        );

        if (droppedFiles.length > 0) {
            void handleFiles(droppedFiles);
        }
    };

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <label className="text-xs font-bold text-foreground flex items-center gap-1.5">
                        <span>Supporting Photos & Documentation</span>
                        <span className="text-[11px] font-normal text-muted-foreground">
                            (Optional)
                        </span>
                        <span
                            className={cn(
                                'ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold tabular-nums',
                                isAtLimit
                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                    : 'bg-primary/10 text-primary'
                            )}
                        >
                            {photos.length} / {photoLimit}
                        </span>
                    </label>
                    <p className="text-[11px] text-muted-foreground mt-0.5">
                        Attach up to {photoLimit} site photos or drawings. Upload directly from your machine or
                        select from the project media library.
                    </p>
                    {isAtLimit && (
                        <p className="text-[11px] font-medium text-amber-600 dark:text-amber-400 mt-0.5">
                            Limit reached — remove a photo to add another.
                        </p>
                    )}
                </div>

                {!disabled && (
                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={handleFilesChosen}
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={progress !== null || room === 0}
                            onClick={() => fileInputRef.current?.click()}
                            className="h-8 gap-1.5 text-xs cursor-pointer"
                        >
                            <Upload className="size-3.5" /> Upload from device
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={progress !== null || room === 0}
                            onClick={() => setPickerOpen(true)}
                            className="h-8 gap-1.5 text-xs cursor-pointer border-indigo-500/30 bg-indigo-500/5 text-indigo-700 hover:bg-indigo-500/10 hover:text-indigo-800 dark:border-indigo-400/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20"
                        >
                            <Images className="size-3.5 text-indigo-600 dark:text-indigo-400" /> From library
                        </Button>
                    </div>
                )}
            </div>

            {/* Upload Progress Indicator */}
            {progress !== null && (
                <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3.5 py-2 text-xs text-muted-foreground shadow-2xs animate-pulse">
                    <Loader2 className="size-3.5 shrink-0 animate-spin text-primary" />
                    <span>
                        Uploading {progress.done + 1}/{progress.total} — {progress.label} ({progress.pct}%)
                    </span>
                </div>
            )}

            {/* Picked files — annotate with the pencil, then upload */}
            <MediaStagingPanel
                staging={staging}
                onUpload={() => void uploadStaged()}
                isUploading={progress !== null}
                uploadNoun="photo"
                boxed
            />

            <ChangeOrderPhotoDropzone
                photos={photos}
                disabled={disabled}
                isDragOver={isDragOver}
                onDragEnter={handleDragEnter}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                onRemove={setPhotoToRemove}
                onEdit={handleEditPhoto}
                canEditPhoto={canEditPhoto}
                onBrowse={() => fileInputRef.current?.click()}
                onOpenLibrary={() => setPickerOpen(true)}
            />

            <ConfirmDialog
                open={photoToRemove !== null}
                onOpenChange={(open) => {
                    if (!open) setPhotoToRemove(null);
                }}
                title="Remove photo"
                description={`Remove "${photoToRemove?.original_name ?? ''}" from this change order? The original stays in the project media library.`}
                confirmLabel="Remove"
                variant="destructive"
                onConfirm={confirmRemove}
            />

            {/* Canvas editor for photos that are already library assets */}
            <MediaCanvasEditorDialog
                asset={editor.asset}
                isOpen={editor.isOpen}
                onClose={editor.close}
                onSaved={handleAssetAnnotated}
            />

            {/* Media Library Sidebar Drawer Modal */}
            <MediaPickerModal
                isOpen={pickerOpen}
                onClose={() => setPickerOpen(false)}
                projectId={projectId}
                projectName={projectName}
                availableSlots={room}
                onAssignAssets={(assets) => {
                    handleAssignFromLibrary(assets);
                }}
            />
        </div>
    );
}
