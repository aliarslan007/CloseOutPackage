import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { MediaPickerModal } from '@/features/media-library';
import type { MediaAsset } from '@/features/media-library/types/media-library.types';
import {
    PACKAGE_SITE_NOTES_IMAGE_LIMIT,
    PACKAGE_SITE_NOTES_MAX_FILE_MB,
    type PackageSiteNoteImage,
} from '@/hooks/use-checklist-auto-save';
import { cn } from '@/lib/utils';
import {
    preparePhotoForUpload,
    rejectedPhotoMessage,
    SUPPORTED_PHOTO_TYPES,
} from '@/services/compression.service';
import { router } from '@inertiajs/react';
import { ImagePlus, Images, Loader2, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface PackageSiteNotesImagesProps {
    packageId: number | string;
    images: PackageSiteNoteImage[];
    canEdit: boolean;
    /** Project scope for the media library picker. */
    projectId?: number | null;
    disabled?: boolean;
}

/** Inertia field errors arrive keyed like "images.0"; the field prefix carries no meaning to users. */
function firstFriendlyError(errors: Record<string, string>): string | null {
    const values = Object.values(errors);
    return values.length > 0 ? (values[0] ?? null) : null;
}

/**
 * Images sub-section of the Additional Site Notes card: grid preview, file
 * upload, Media Library pull and drag-drop. Kept separate from the notes text
 * area so each stays under the component size limit and single-responsibility.
 * Upload flow is unchanged (Inertia POST to packages.notes-images.store);
 * files are client-compressed first, exactly like the package photo buckets.
 */
export function PackageSiteNotesImages({
    packageId,
    images,
    canEdit,
    projectId,
    disabled = false,
}: Readonly<PackageSiteNotesImagesProps>) {
    const [uploading, setUploading] = React.useState(false);
    const [isDragging, setIsDragging] = React.useState(false);
    const [pickerOpen, setPickerOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<PackageSiteNoteImage | null>(null);
    const [deleting, setDeleting] = React.useState(false);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const dragCounter = React.useRef(0);

    const slots = Math.max(0, PACKAGE_SITE_NOTES_IMAGE_LIMIT - images.length);
    const isFull = slots === 0;

    const uploadFiles = React.useCallback(
        (files: File[]) => {
            if (files.length === 0) return;

            if (isFull) {
                toast.warning(
                    `These notes can hold ${PACKAGE_SITE_NOTES_IMAGE_LIMIT} images in total, and they are all filled. Remove one before adding another.`
                );
                return;
            }

            const formData = new FormData();
            files.slice(0, slots).forEach((file) => formData.append('images[]', file));

            setUploading(true);
            router.post(route('packages.notes-images.store', { package: packageId }), formData, {
                preserveScroll: true,
                preserveState: true,
                only: ['package'],
                onError: (errors) => {
                    toast.error(firstFriendlyError(errors) ?? 'Could not upload the image. Please try again.');
                },
                onFinish: () => setUploading(false),
            });
        },
        [isFull, packageId, slots]
    );

    /** Same client-side compression gate as the package photo buckets. */
    const handleFiles = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const selected = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (selected.length === 0) return;

        const accepted: File[] = [];
        for (const file of selected.slice(0, slots)) {
            const prepared = await preparePhotoForUpload(file, {
                maxMb: PACKAGE_SITE_NOTES_MAX_FILE_MB,
                allowedTypes: SUPPORTED_PHOTO_TYPES,
            });
            if (prepared.ok) {
                accepted.push(prepared.file);
            } else {
                toast.error(rejectedPhotoMessage(file, prepared.reason, { maxMb: PACKAGE_SITE_NOTES_MAX_FILE_MB }));
            }
        }

        uploadFiles(accepted);
    };

    const attachFromLibrary = async (assets: MediaAsset[]) => {
        if (assets.length === 0) return;

        try {
            await router.post(
                route('packages.notes-images.library', { package: packageId }),
                { media_asset_ids: assets.map((a) => a.id) },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['package'],
                    onError: (errors) => {
                        toast.error(
                            firstFriendlyError(errors) ?? 'Could not add the selected image(s). Please try again.'
                        );
                    },
                }
            );
            setPickerOpen(false);
        } catch {
            toast.error('Could not add the selected image(s). Please try again.');
        }
    };

    const handleDeleteImage = () => {
        if (!deleteTarget) return;

        setDeleting(true);
        router.delete(
            route('packages.notes-images.destroy', {
                package: packageId,
                media: deleteTarget.id,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                only: ['package'],
                onSuccess: () => {
                    setDeleteTarget(null);
                    setDeleting(false);
                },
                onError: () => setDeleting(false),
            }
        );
    };

    const handleDrop = (event: React.DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        dragCounter.current = 0;
        setIsDragging(false);
        if (disabled || uploading) return;

        const files = Array.from(event.dataTransfer.files ?? []).filter(
            (f) => f.type.startsWith('image/') || /\.(jpe?g|png|webp|heic)$/i.test(f.name)
        );
        void handleFiles({ target: { files } } as unknown as React.ChangeEvent<HTMLInputElement>);
    };

    const hasImages = images.length > 0;

    return (
        <div className="space-y-3">
            {hasImages && (
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                    {images.map((image) => (
                        <div
                            key={image.id}
                            className="group relative aspect-square overflow-hidden rounded-lg border border-border/60 bg-muted/20"
                        >
                            <a
                                href={image.url || image.thumb_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={`Open ${image.file_name ?? 'image'} in a new tab`}
                                title="Open full size in a new tab"
                                className="block size-full cursor-zoom-in"
                            >
                                <img
                                    src={image.thumb_url || image.url}
                                    alt={image.file_name || 'Site note image'}
                                    loading="lazy"
                                    className="size-full object-cover transition-transform group-hover:scale-105"
                                />
                            </a>
                            {canEdit && (
                                <button
                                    type="button"
                                    onClick={() => setDeleteTarget(image)}
                                    aria-label={`Remove ${image.file_name ?? 'image'}`}
                                    title="Remove image"
                                    className="absolute right-1 top-1 z-10 cursor-pointer rounded-md bg-background/90 p-1.5 text-destructive opacity-0 shadow-sm transition-opacity hover:bg-background focus-visible:opacity-100 group-hover:opacity-100"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {canEdit && (
                <>
                    <div
                        role="button"
                        tabIndex={0}
                        aria-label="Drag and drop images here, or use the buttons below"
                        onDragEnter={(e) => {
                            e.preventDefault();
                            dragCounter.current += 1;
                            if (!disabled && !uploading) setIsDragging(true);
                        }}
                        onDragOver={(e) => e.preventDefault()}
                        onDragLeave={() => {
                            dragCounter.current -= 1;
                            if (dragCounter.current <= 0) {
                                dragCounter.current = 0;
                                setIsDragging(false);
                            }
                        }}
                        onDrop={handleDrop}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') inputRef.current?.click();
                        }}
                        className={cn(
                            'rounded-lg border border-dashed border-border p-3 text-center transition-colors outline-none',
                            'focus-visible:ring-2 focus-visible:ring-ring',
                            isDragging && 'border-indigo-500/60 bg-indigo-500/5',
                            (disabled || uploading) && 'pointer-events-none opacity-60'
                        )}
                    >
                        {uploading ? (
                            <span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" /> Adding images…
                            </span>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                Drag &amp; drop images here, or add them below
                            </span>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Indigo so the add action stands apart from the neutral outline controls. */}
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => inputRef.current?.click()}
                            disabled={disabled || isFull}
                            title={
                                isFull
                                    ? `Remove an image to free a slot (${PACKAGE_SITE_NOTES_IMAGE_LIMIT} max).`
                                    : undefined
                            }
                            className="h-8 cursor-pointer gap-1.5 bg-indigo-600 px-3 text-xs font-semibold text-white shadow-xs hover:bg-indigo-700"
                        >
                            <ImagePlus className="size-3.5" /> Add images
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setPickerOpen(true)}
                            disabled={disabled || isFull}
                            title={
                                isFull
                                    ? `Remove an image to free a slot (${PACKAGE_SITE_NOTES_IMAGE_LIMIT} max).`
                                    : undefined
                            }
                            className="h-8 cursor-pointer gap-1.5 px-3 text-xs font-semibold"
                        >
                            <Images className="size-3.5" /> From library
                        </Button>
                        <span className="text-[11px] tabular-nums text-muted-foreground">
                            {images.length} / {PACKAGE_SITE_NOTES_IMAGE_LIMIT}
                        </span>
                        <span className="text-[11px] text-muted-foreground/80">
                            · JPG, PNG, WebP or HEIC · up to {PACKAGE_SITE_NOTES_MAX_FILE_MB} MB each
                            (larger images are optimized automatically)
                        </span>
                    </div>

                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/heic"
                        multiple
                        onChange={(e) => void handleFiles(e)}
                        className="hidden"
                        aria-label="Add images to the site notes"
                    />

                    <MediaPickerModal
                        isOpen={pickerOpen}
                        onClose={() => setPickerOpen(false)}
                        projectId={projectId ?? undefined}
                        availableSlots={slots}
                        onAssignAssets={attachFromLibrary}
                        submitLabel="Add to Site Notes"
                    />
                </>
            )}

            {canEdit && (
                <ConfirmDialog
                    open={Boolean(deleteTarget)}
                    onOpenChange={(next) => {
                        if (!next && !deleting) setDeleteTarget(null);
                    }}
                    title="Remove image"
                    description={`Remove "${deleteTarget?.file_name ?? 'this image'}" from the site notes?`}
                    confirmLabel="Remove"
                    variant="destructive"
                    loading={deleting}
                    onConfirm={handleDeleteImage}
                />
            )}
        </div>
    );
}
