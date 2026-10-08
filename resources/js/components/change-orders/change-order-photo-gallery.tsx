import { ChangeOrderPhotoViewer } from '@/components/change-orders/change-order-photo-viewer';
import type { ChangeOrderPhoto } from '@/components/change-orders/change-order-types';
import { formatFileSize } from '@/features/media-library';
import { ImageOff, Maximize2, Pencil, Trash2 } from 'lucide-react';
import * as React from 'react';

export interface ChangeOrderPhotoGalleryProps {
    photos: ChangeOrderPhoto[];
    /** Staff-only remove affordance; the host owns the confirm dialog. */
    onDelete?: (photo: ChangeOrderPhoto) => void;
    /** Staff-only annotate affordance for photos already in the library. */
    onEdit?: (photo: ChangeOrderPhoto) => void;
}

/**
 * Supporting-photo gallery for the change-order detail page.
 *
 * Tiles open the full-screen viewer. Unlike a plain thumbnail grid, the file
 * name and size sit below the tile so nothing is hidden behind hover, and the
 * remove affordance is a sibling button rather than a nested one.
 */
export const ChangeOrderPhotoGallery = React.memo(function ChangeOrderPhotoGallery({
    photos,
    onDelete,
    onEdit,
}: Readonly<ChangeOrderPhotoGalleryProps>) {
    const [openIndex, setOpenIndex] = React.useState<number | null>(null);
    // Broken streams (a deleted asset, a locked disk) should not leave a
    // silent empty tile.
    const [failedIds, setFailedIds] = React.useState<Record<number, true>>({});

    const markFailed = React.useCallback((id: number) => {
        setFailedIds((prev) => ({ ...prev, [id]: true }));
    }, []);

    return (
        <>
            <ul className="grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-3 lg:grid-cols-4 [content-visibility:auto] [contain-intrinsic-size:0_280px]">
                {photos.map((photo, index) => {
                    const isBroken = Boolean(failedIds[photo.id]);

                    return (
                        <li key={photo.id} className="group relative [contain:layout_style]">
                            <button
                                type="button"
                                onClick={() => setOpenIndex(index)}
                                aria-label={`View ${photo.original_name}, ${index + 1} of ${photos.length}`}
                                className="block w-full cursor-zoom-in overflow-hidden rounded-lg border border-border/80 bg-muted/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                            >
                                <span className="relative block aspect-[4/3] overflow-hidden bg-muted/40">
                                    {isBroken ? (
                                        <span className="flex size-full flex-col items-center justify-center gap-1 text-muted-foreground">
                                            <ImageOff className="size-5" />
                                            <span className="text-[10px]">Preview unavailable</span>
                                        </span>
                                    ) : (
                                        <>
                                            <img
                                                src={photo.url}
                                                alt={photo.original_name}
                                                loading="lazy"
                                                decoding="async"
                                                onError={() => markFailed(photo.id)}
                                                className="size-full object-cover transition-transform duration-200 group-hover:scale-[1.04] transform-gpu will-change-transform"
                                            />
                                            <span
                                                aria-hidden="true"
                                                className="absolute inset-0 flex items-center justify-center bg-black/45 opacity-0 transition-opacity duration-200 group-hover:opacity-100 group-focus-visible:opacity-100 pointer-events-none"
                                            >
                                                <Maximize2 className="size-4 text-white" />
                                            </span>
                                        </>
                                    )}
                                </span>
                            </button>

                            {(onEdit || onDelete) && (
                                <div className="absolute right-2 top-2 z-10 flex items-center gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                                    {onEdit && (
                                        <button
                                            type="button"
                                            onClick={() => onEdit(photo)}
                                            aria-label={`Edit ${photo.original_name}`}
                                            title="Mark & annotate this photo"
                                            className="rounded-md bg-black/70 p-1.5 text-white transition-colors hover:bg-primary focus-visible:opacity-100 cursor-pointer"
                                        >
                                            <Pencil className="size-3.5" />
                                        </button>
                                    )}
                                    {onDelete && (
                                        <button
                                            type="button"
                                            onClick={() => onDelete(photo)}
                                            aria-label={`Remove ${photo.original_name}`}
                                            title="Remove this photo"
                                            className="rounded-md bg-black/70 p-1.5 text-white transition-colors hover:bg-destructive focus-visible:opacity-100 cursor-pointer"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    )}
                                </div>
                            )}

                            <p
                                className="mt-1.5 truncate text-[11px] font-medium text-foreground"
                                title={photo.original_name}
                            >
                                {photo.original_name}
                            </p>
                            <p className="text-[10px] tabular-nums text-muted-foreground">
                                {formatFileSize(photo.size_bytes)}
                            </p>
                        </li>
                    );
                })}
            </ul>

            <ChangeOrderPhotoViewer
                photos={photos}
                index={openIndex}
                onIndexChange={setOpenIndex}
            />
        </>
    );
});

export default ChangeOrderPhotoGallery;
