import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Crown, Eye, EyeOff, GripVertical, ImagePlus, Images, Loader2, Trash2, Upload } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { sowImagesApi } from '../../api/sow-images.api';
import { useCompositeStatuses } from '../../hooks/use-composite-statuses';
import { useSowReports } from '../../hooks/use-sow-reports';
import type { SowAnnotatedImage, SowReport } from '../../types/sow.types';
import { sowErrorMessage as errorMessage } from '../../utils/sow-error';
import { SowCompositeStatusBadge } from './sow-composite-status-badge';
import { SowImageDeleteDialog } from './sow-image-delete-dialog';

export interface SowImageStripProps {
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

function imageUrl(image: NonNullable<SowReport['annotated_images']>[number]): string {
    return image.media_asset?.thumb_url || image.media_asset?.url || '';
}

/** Thumbnail strip: Cover badge, composite status, PDF toggle, delete, drag-to-reorder. */
export function SowImageStrip({
    report,
    canUpdate,
    maxImages = 20,
    selectedImageId,
    onSelect,
    onImportClick,
    onUploadClick,
    isUploading,
    uploadStatus = null,
}: Readonly<SowImageStripProps>) {
    const { refresh } = useSowReports();
    const images = report.annotated_images ?? [];
    const atLimit = images.length >= maxImages;
    const [pendingId, setPendingId] = React.useState<number | null>(null);
    const [pendingDelete, setPendingDelete] = React.useState<SowAnnotatedImage | null>(null);
    const [draggedId, setDraggedId] = React.useState<number | null>(null);
    const [overId, setOverId] = React.useState<number | null>(null);

    const { statuses, settled } = useCompositeStatuses(
        report.id,
        images.map((image) => image.id)
    );

    // Refresh props once after any pending/processing composites settle (§9.14).
    const sawActive = React.useRef(false);
    React.useEffect(() => {
        const values = Object.values(statuses);
        if (values.some((s) => s === 'pending' || s === 'processing')) {
            sawActive.current = true;
        }
        if (settled && sawActive.current) {
            sawActive.current = false;
            refresh();
        }
    }, [settled, statuses, refresh]);

    const run = async (
        id: number,
        action: () => Promise<unknown>,
        success: string,
        failure: string
    ) => {
        setPendingId(id);
        try {
            await action();
            toast.success(success);
            refresh();
        } catch (err) {
            toast.error(errorMessage(err, failure));
        } finally {
            setPendingId(null);
        }
    };

    const setCover = (imageId: number) =>
        run(
            imageId,
            () => sowImagesApi.update(report.id, imageId, { is_cover: true }),
            'Cover photo set.',
            'Failed to set cover photo.'
        );

    const toggleInclude = (imageId: number, includeInPdf: boolean) =>
        run(
            imageId,
            () => sowImagesApi.update(report.id, imageId, { include_in_pdf: !includeInPdf }),
            includeInPdf ? 'Removed from PDF.' : 'Included in PDF.',
            'Failed to update PDF inclusion.'
        );

    const onDrop = () => {
        if (draggedId === null || overId === null || draggedId === overId) {
            setDraggedId(null);
            setOverId(null);
            return;
        }

        const fromIndex = images.findIndex((image) => image.id === draggedId);
        const toIndex = images.findIndex((image) => image.id === overId);
        if (fromIndex < 0 || toIndex < 0) {
            setDraggedId(null);
            setOverId(null);
            return;
        }

        const reordered = [...images];
        const [moved] = reordered.splice(fromIndex, 1);
        reordered.splice(toIndex, 0, moved);

        const order = reordered.map((image, index) => ({ id: image.id, sort_order: index }));
        setPendingId(draggedId);
        sowImagesApi
            .reorder(report.id, order)
            .then(() => {
                toast.success('Photo order saved.');
                refresh();
            })
            .catch((err) => toast.error(errorMessage(err, 'Failed to reorder photos.')))
            .finally(() => {
                setPendingId(null);
                setDraggedId(null);
                setOverId(null);
            });
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Photos{' '}
                    <span className="font-normal text-muted-foreground">
                        ({images.length} / {maxImages})
                    </span>
                </h3>
                {canUpdate && (
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="default"
                            className="gap-1.5"
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
                            className="gap-1.5"
                            disabled={atLimit}
                            onClick={onImportClick}
                        >
                            <ImagePlus className="size-3.5" />
                            Import from Media Library
                        </Button>
                    </div>
                )}
            </div>

            {images.length === 0 ? (
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
            ) : (
                <div className="flex gap-3 overflow-x-auto pb-2">
                    {images.map((image) => {
                        const isBusy = pendingId === image.id;
                        const compositeStatus = statuses[image.id] ?? image.composite_status;
                        // Server-computed remove rule (ownership + report status).
                        const canDelete = image.can_delete ?? canUpdate;
                        const thumbUrl = imageUrl(image);
                        const isProcessing = image.media_asset?.status === 'processing';
                        const preview = thumbUrl ? (
                            <img
                                src={thumbUrl}
                                alt={
                                    image.page_title ??
                                    image.media_asset?.original_filename ??
                                    `Photo ${image.sort_order}`
                                }
                                className="h-24 w-full object-cover"
                            />
                        ) : (
                            <span className="flex h-24 w-full items-center justify-center gap-1.5 text-xs text-muted-foreground">
                                {isProcessing && <Loader2 className="size-3.5 animate-spin" />}
                                {isProcessing ? 'Processing…' : 'No preview'}
                            </span>
                        );

                        return (
                            <div
                                key={image.id}
                                draggable={canUpdate}
                                onDragStart={() => setDraggedId(image.id)}
                                onDragOver={(e) => {
                                    if (!canUpdate) return;
                                    e.preventDefault();
                                    setOverId(image.id);
                                }}
                                onDrop={onDrop}
                                onDragEnd={() => {
                                    setDraggedId(null);
                                    setOverId(null);
                                }}
                                className={cn(
                                    'group relative w-32 shrink-0 overflow-hidden rounded-lg border border-border bg-muted',
                                    (image.is_cover || selectedImageId === image.id) &&
                                    'border-primary ring-1 ring-primary',
                                    overId === image.id &&
                                    draggedId !== null &&
                                    draggedId !== image.id &&
                                    'ring-2 ring-primary/60',
                                    !image.include_in_pdf && 'opacity-60',
                                    draggedId === image.id && 'opacity-40'
                                )}
                            >
                                <button
                                    type="button"
                                    aria-pressed={selectedImageId === image.id}
                                    aria-label={
                                        image.page_title ??
                                        image.media_asset?.original_filename ??
                                        `Photo ${image.sort_order}`
                                    }
                                    onClick={() => onSelect(image.id)}
                                    className="block h-24 w-full cursor-pointer p-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                >
                                    {preview}
                                </button>

                                <div className="pointer-events-none absolute left-1 top-1 flex flex-col items-start gap-1">
                                    {image.is_cover && (
                                        <span className="inline-flex items-center gap-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                                            <Crown className="size-2.5" />
                                            COVER
                                        </span>
                                    )}
                                    {canUpdate && (
                                        <SowCompositeStatusBadge status={compositeStatus} />
                                    )}
                                </div>

                                {(canUpdate || canDelete) && (
                                    <>
                                        {canUpdate && (
                                            <span className="pointer-events-none absolute right-1 top-1 rounded bg-black/60 p-0.5 text-white/80">
                                                <GripVertical className="size-3.5" />
                                            </span>
                                        )}
                                        <div
                                            className={cn(
                                                'absolute inset-x-0 bottom-0 z-10 flex items-center gap-0.5 bg-black/60 p-1 backdrop-blur-sm',
                                                canUpdate ? 'justify-between' : 'justify-end'
                                            )}
                                        >
                                            {canUpdate && !image.is_cover && (
                                                <button
                                                    type="button"
                                                    title="Set as cover photo"
                                                    disabled={isBusy}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setCover(image.id);
                                                    }}
                                                    className="rounded p-1 text-amber-300 transition-colors hover:bg-white/15 hover:text-amber-200 disabled:opacity-50"
                                                >
                                                    <Crown className="size-3.5" />
                                                </button>
                                            )}
                                            {canUpdate && (
                                                <button
                                                    type="button"
                                                    title={
                                                        image.include_in_pdf
                                                            ? 'Exclude from PDF'
                                                            : 'Include in PDF'
                                                    }
                                                    disabled={isBusy}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        toggleInclude(
                                                            image.id,
                                                            image.include_in_pdf
                                                        );
                                                    }}
                                                    className={cn(
                                                        'rounded p-1 transition-colors hover:bg-white/15 disabled:opacity-50',
                                                        image.include_in_pdf
                                                            ? 'text-emerald-300 hover:text-emerald-200'
                                                            : 'text-red-300 hover:text-red-200'
                                                    )}
                                                >
                                                    {image.include_in_pdf ? (
                                                        <Eye className="size-3.5" />
                                                    ) : (
                                                        <EyeOff className="size-3.5" />
                                                    )}
                                                </button>
                                            )}
                                            {canDelete && (
                                                <button
                                                    type="button"
                                                    title="Remove photo"
                                                    disabled={isBusy}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setPendingDelete(image);
                                                    }}
                                                    className="rounded p-1 text-red-300 transition-colors hover:bg-white/15 hover:text-red-200 disabled:opacity-50"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    </>
                                )}

                                {isBusy && (
                                    <span className="absolute inset-0 flex items-center justify-center bg-background/50 text-foreground">
                                        <Loader2 className="size-4 animate-spin" />
                                    </span>
                                )}
                            </div>
                        );
                    })}
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
