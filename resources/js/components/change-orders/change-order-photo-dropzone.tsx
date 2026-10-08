import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/features/media-library';
import { cn } from '@/lib/utils';
import { ImagePlus, Images, Pencil, Trash2, Upload } from 'lucide-react';
import * as React from 'react';
import { dropzoneContainerClass, type AttachedPhoto } from './change-order-photo-helpers';

export interface ChangeOrderPhotoDropzoneProps {
    photos: AttachedPhoto[];
    disabled: boolean;
    isDragOver: boolean;
    onDragEnter: (event: React.DragEvent<HTMLDivElement>) => void;
    onDragOver: (event: React.DragEvent<HTMLDivElement>) => void;
    onDragLeave: (event: React.DragEvent<HTMLDivElement>) => void;
    onDrop: (event: React.DragEvent<HTMLDivElement>) => void;
    /** Staff-only remove affordance; the host owns the confirm dialog. */
    onRemove: (photo: AttachedPhoto) => void;
    /** Staff-only annotate affordance for photos already in the library. */
    onEdit?: (photo: AttachedPhoto) => void;
    /**
     * Per-photo gate for the pencil. A change order copies each file when it is
     * saved, so a photo already on the order holds its own copy that editing the
     * library asset would not reach.
     */
    canEditPhoto?: (photo: AttachedPhoto) => boolean;
    onBrowse: () => void;
    onOpenLibrary: () => void;
}

/** Drop zone plus thumbnails — presentational only; the parent owns the state. */
export function ChangeOrderPhotoDropzone({
    photos,
    disabled,
    isDragOver,
    onDragEnter,
    onDragOver,
    onDragLeave,
    onDrop,
    onRemove,
    onEdit,
    canEditPhoto,
    onBrowse,
    onOpenLibrary,
}: Readonly<ChangeOrderPhotoDropzoneProps>) {
    return (
            <div
                onDragEnter={onDragEnter}
                onDragOver={onDragOver}
                onDragLeave={onDragLeave}
                onDrop={onDrop}
                className={cn(
                    'relative rounded-xl border transition-all duration-200',
                    dropzoneContainerClass(isDragOver, photos.length > 0)
                )}
            >
                {photos.length === 0 ? (
                    <div className="flex flex-col items-center justify-center space-y-2 py-3">
                        <div className="flex size-10 items-center justify-center rounded-full bg-muted border border-border text-muted-foreground">
                            <ImagePlus className="size-5" />
                        </div>
                        <div className="space-y-1">
                            <p className="text-xs font-semibold text-foreground">
                                {isDragOver ? 'Drop photos here' : 'No photos attached yet'}
                            </p>
                            <p className="text-[11px] text-muted-foreground max-w-sm mx-auto">
                                Drag & drop image files from your computer, or drag photos directly from the Media Library sidebar.
                            </p>
                        </div>
                        {!disabled && (
                            <div className="pt-2 flex items-center gap-2 justify-center">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    onClick={onBrowse}
                                    className="h-7 text-xs gap-1.5"
                                >
                                    <Upload className="size-3" /> Browse files
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={onOpenLibrary}
                                    className="h-7 text-xs gap-1.5"
                                >
                                    <Images className="size-3 text-indigo-500" /> Open library
                                </Button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {isDragOver && (
                            <div className="rounded-lg border-2 border-dashed border-primary bg-primary/10 p-4 text-center text-xs font-semibold text-primary">
                                Drop to add photos to this change order
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4">
                            {photos.map((photo) => (
                                <div
                                    key={photo.id}
                                    className="group relative block overflow-hidden rounded-lg border border-border bg-card text-left shadow-2xs transition-all hover:border-primary/60"
                                >
                                    <div className="aspect-square w-full bg-muted/40 overflow-hidden relative">
                                        <img
                                            src={photo.url}
                                            alt={photo.original_name}
                                            loading="lazy"
                                            className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                            onError={(e) => {
                                                // If URL failed, hide the broken icon and show fallback container
                                                const target = e.currentTarget;
                                                target.style.display = 'none';
                                                const fallback = target.nextElementSibling as HTMLElement | null;
                                                if (fallback) {
                                                    fallback.classList.remove('hidden');
                                                    fallback.classList.add('flex');
                                                }
                                            }}
                                        />
                                        <div className="hidden h-full w-full flex-col items-center justify-center bg-muted/60 p-2 text-center text-muted-foreground">
                                            <ImagePlus className="size-6 opacity-40 mb-1" />
                                            <span className="text-[10px] truncate max-w-full font-medium">{photo.original_name}</span>
                                        </div>
                                        {!disabled && (
                                            <div className="absolute top-1.5 right-1.5 z-10 flex items-center gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                                                {onEdit && (!canEditPhoto || canEditPhoto(photo)) && (
                                                    <button
                                                        type="button"
                                                        onClick={() => onEdit(photo)}
                                                        aria-label={`Edit ${photo.original_name}`}
                                                        title="Mark & annotate this photo"
                                                        className="rounded-md bg-black/75 p-1.5 text-white transition-colors hover:bg-primary focus-visible:opacity-100 cursor-pointer shadow-sm"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </button>
                                                )}
                                                <button
                                                    type="button"
                                                    onClick={() => onRemove(photo)}
                                                    aria-label={`Remove ${photo.original_name}`}
                                                    title="Remove this photo"
                                                    className="rounded-md bg-black/75 p-1.5 text-white transition-colors hover:bg-destructive focus-visible:opacity-100 cursor-pointer shadow-sm"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between gap-1 p-2 bg-card">
                                        <span
                                            className="min-w-0 truncate text-[11px] font-medium text-foreground"
                                            title={photo.original_name}
                                        >
                                            {photo.original_name}
                                        </span>
                                        {photo.size_bytes !== undefined && photo.size_bytes > 0 && (
                                            <span className="shrink-0 text-[10px] text-muted-foreground tabular-nums">
                                                {formatFileSize(photo.size_bytes)}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
    );
}
