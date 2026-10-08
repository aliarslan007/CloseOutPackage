import * as React from 'react';
import { Trash2 } from 'lucide-react';
import { formatFileSize } from '@/features/media-library';
import { FeedbackImageHoverOverlay, type EntityFeedbackNoteItem } from '@/features/entity-feedback';
import type { ChangeOrderPhoto } from './change-order-types';

export interface ChangeOrderPhotoGridProps {
    photos: ChangeOrderPhoto[];
    /** Token page (no-auth) viewers: appended to every photo URL. */
    token?: string;
    /** Staff-only remove affordance; host owns the confirm dialog. */
    onDelete?: (photo: ChangeOrderPhoto) => void;
    /** Allows user to open canvas annotation tool on this photo */
    onAnnotate?: (photo: ChangeOrderPhoto) => void;
    getPhotoNote?: (photoId: number) => EntityFeedbackNoteItem | null;
}

/**
 * Read-only photo grid shared by the staff details tab, the customer portal
 * and the no-auth token page. Photos open full-size in a new tab.
 */
export const ChangeOrderPhotoGrid = React.memo(function ChangeOrderPhotoGrid({
    photos,
    token,
    onDelete,
    onAnnotate,
    getPhotoNote,
}: Readonly<ChangeOrderPhotoGridProps>) {
    return (
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 [content-visibility:auto] [contain-intrinsic-size:0_260px]">
            {photos.map((photo) => {
                const href = token ? `${photo.url}?token=${encodeURIComponent(token)}` : photo.url;

                return (
                    <a
                        key={photo.id}
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="group relative block overflow-hidden rounded-lg border border-border bg-muted/30 text-left transition-colors hover:border-primary/60 [contain:layout_style]"
                        title={`Open ${photo.original_name}`}
                    >
                        <div className="aspect-square w-full overflow-hidden bg-muted/40">
                            <img
                                src={href}
                                alt={photo.original_name}
                                loading="lazy"
                                decoding="async"
                                className="size-full object-cover transform-gpu transition-transform duration-200 group-hover:scale-[1.03]"
                            />
                        </div>
                        {onAnnotate && (
                            <FeedbackImageHoverOverlay
                                note={getPhotoNote?.(photo.id)}
                                onAnnotate={() => onAnnotate(photo)}
                            />
                        )}
                        {onDelete && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    onDelete(photo);
                                }}
                                aria-label={`Remove ${photo.original_name}`}
                                title="Remove this photo"
                                className="absolute top-1.5 right-1.5 z-10 rounded-md bg-black/70 p-1.5 text-white opacity-0 transition-all hover:bg-red-600 focus-visible:opacity-100 group-hover:opacity-100 cursor-pointer"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        )}
                        <div className="flex items-center justify-between gap-1 p-1.5">
                            <span className="min-w-0 truncate text-[11px] font-medium text-foreground">
                                {photo.original_name}
                            </span>
                            <span className="shrink-0 text-[10px] text-muted-foreground tabular-nums">
                                {formatFileSize(photo.size_bytes)}
                            </span>
                        </div>
                    </a>
                );
            })}
        </div>
    );
});

export default ChangeOrderPhotoGrid;
