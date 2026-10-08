import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/features/media-library';
import { cn } from '@/lib/utils';
import { FileVideo, Loader2, Pencil, Upload, X } from 'lucide-react';
import type { MediaStaging } from '../hooks/use-media-staging';
import { EditImageDialog } from './EditImageDialog';

export interface MediaStagingPanelProps {
    staging: MediaStaging;
    /** Set false on surfaces where the viewer may pick but not mark up. */
    editable?: boolean;
    /** Adds the review row — a hint plus Clear and Upload actions. */
    onUpload?: () => void;
    isUploading?: boolean;
    /** Singular noun for the upload count, e.g. "photo" or "file". */
    uploadNoun?: string;
    /** Draws the panel as its own bordered card. */
    boxed?: boolean;
}

/**
 * Drop-in review panel for picked files: thumbnails, per-image annotate and
 * remove, an optional Upload action, and the canvas dialog. Videos are listed
 * but not editable — the canvas only draws on stills.
 */
export function MediaStagingPanel({
    staging,
    editable = true,
    onUpload,
    isUploading = false,
    uploadNoun = 'photo',
    boxed = false,
}: Readonly<MediaStagingPanelProps>) {
    const { items } = staging;

    if (items.length === 0) {
        return null;
    }

    const count = items.length;

    return (
        <div className={cn('space-y-2.5', boxed && 'rounded-lg border border-border bg-muted/20 p-3')}>
            {onUpload && (
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-[11px] text-muted-foreground">
                        Review before uploading — annotate an image with the pencil, then upload.
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={isUploading}
                            onClick={staging.reset}
                            className="h-8 text-xs cursor-pointer"
                        >
                            Clear
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={isUploading}
                            onClick={onUpload}
                            className="h-8 gap-1.5 text-xs cursor-pointer"
                        >
                            {isUploading ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Upload className="size-3.5" />
                            )}
                            <span>
                                Upload {count} {uploadNoun}
                                {count === 1 ? '' : 's'}
                            </span>
                        </Button>
                    </div>
                </div>
            )}

            <ul className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="group relative overflow-hidden rounded-lg border border-border bg-muted/30"
                    >
                        <div className="aspect-square w-full overflow-hidden bg-muted/40">
                            {item.isVideo ? (
                                <div className="flex size-full flex-col items-center justify-center gap-1 text-muted-foreground">
                                    <FileVideo className="size-6" />
                                    <span className="text-[10px] font-medium">Video</span>
                                </div>
                            ) : (
                                <img
                                    src={item.previewUrl}
                                    alt={item.file.name}
                                    loading="lazy"
                                    decoding="async"
                                    className="size-full object-cover"
                                />
                            )}
                        </div>

                        {item.derivedFromId && (
                            <span className="absolute top-1.5 left-1.5 rounded-md bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground">
                                Annotated
                            </span>
                        )}

                        <div className="absolute top-1.5 right-1.5 flex items-center gap-1">
                            {editable && !item.isVideo && (
                                <button
                                    type="button"
                                    onClick={() => staging.openEditor(item.id)}
                                    aria-label={`Edit ${item.file.name}`}
                                    title="Mark & annotate this image"
                                    className="rounded-md bg-black/70 p-1.5 text-white transition-colors hover:bg-primary cursor-pointer"
                                >
                                    <Pencil className="size-3.5" />
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => staging.remove(item.id)}
                                aria-label={`Remove ${item.file.name}`}
                                title="Remove this file"
                                className="rounded-md bg-black/70 p-1.5 text-white transition-colors hover:bg-red-600 cursor-pointer"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>

                        <div className="flex items-center justify-between gap-1 p-1.5">
                            <span className="min-w-0 truncate text-[11px] font-medium text-foreground">
                                {item.file.name}
                            </span>
                            <span className="shrink-0 text-[10px] text-muted-foreground tabular-nums">
                                {formatFileSize(item.file.size)}
                            </span>
                        </div>
                    </li>
                ))}
            </ul>

            {editable && (
                <EditImageDialog
                    source={staging.editingItem}
                    isOpen={staging.editingItem !== null}
                    onClose={staging.closeEditor}
                    onApply={staging.applyEdit}
                />
            )}
        </div>
    );
}

export default MediaStagingPanel;
