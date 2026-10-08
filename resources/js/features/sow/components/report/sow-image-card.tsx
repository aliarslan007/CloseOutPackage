import { cn } from '@/lib/utils';
import { Crown, Eye, EyeOff, GripVertical, Loader2, Trash2 } from 'lucide-react';
import type { SowAnnotatedImage } from '../../types/sow.types';
import { SowCompositeStatusBadge } from './sow-composite-status-badge';

export interface SowImageCardProps {
    image: SowAnnotatedImage;
    compositeStatus?: SowAnnotatedImage['composite_status'];
    canUpdate: boolean;
    canDelete: boolean;
    isBusy?: boolean;
    selected?: boolean;
    /** Reveals a checkbox for multi-select (group board); the strip leaves it off. */
    selectable?: boolean;
    onSelectToggle?: (imageId: number) => void;
    onOpen?: (imageId: number) => void;
    onSetCover?: (imageId: number) => void;
    onToggleInclude?: (imageId: number, includeInPdf: boolean) => void;
    onRemove?: (imageId: number) => void;
    draggable?: boolean;
    isDragging?: boolean;
    isDropTarget?: boolean;
    onDragStart?: () => void;
    onDragOver?: () => void;
    onDrop?: () => void;
    onDragEnd?: () => void;
}

export function sowImageUrl(image: SowAnnotatedImage): string {
    return image.media_asset?.thumb_url || image.media_asset?.url || '';
}

/**
 * One photo thumbnail with its cover badge, composite status, PDF toggle and
 * remove action. Shared by the flat strip and the grouped board so both stay in
 * step.
 */
export function SowImageCard({
    image,
    compositeStatus,
    canUpdate,
    canDelete,
    isBusy = false,
    selected = false,
    selectable = false,
    onSelectToggle,
    onOpen,
    onSetCover,
    onToggleInclude,
    onRemove,
    draggable = false,
    isDragging = false,
    isDropTarget = false,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}: Readonly<SowImageCardProps>) {
    const status = compositeStatus ?? image.composite_status;
    const url = sowImageUrl(image);
    const preview = url ? (
        <img
            src={url}
            alt={image.page_title ?? image.media_asset?.original_filename ?? `Photo ${image.sort_order}`}
            className="h-24 w-full object-cover"
        />
    ) : (
        <span className="flex h-24 w-full items-center justify-center gap-1.5 text-xs text-muted-foreground">
            {image.media_asset?.status === 'processing' && <Loader2 className="size-3.5 animate-spin" />}
            {image.media_asset?.status === 'processing' ? 'Processing…' : 'No preview'}
        </span>
    );

    return (
        <div
            draggable={draggable}
            onDragStart={(e) => {
                // Keep a card drag from bubbling up and starting a group drag.
                e.stopPropagation();
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(image.id));
                e.dataTransfer.setData('application/x-sow-image-id', String(image.id));
                onDragStart?.();
            }}
            onDragOver={(e) => {
                if (!draggable) return;
                e.preventDefault();
                // Otherwise the enclosing section would treat this as a group drop.
                e.stopPropagation();
                onDragOver?.();
            }}
            onDrop={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onDrop?.();
            }}
            onDragEnd={(e) => {
                e.stopPropagation();
                onDragEnd?.();
            }}
            className={cn(
                'group relative w-32 shrink-0 overflow-hidden rounded-lg border border-border bg-muted',
                (image.is_cover || selected) && 'border-primary ring-1 ring-primary',
                isDropTarget && 'ring-2 ring-primary/60',
                !image.include_in_pdf && 'opacity-60',
                isDragging && 'opacity-40'
            )}
        >
            <button
                type="button"
                aria-pressed={selected}
                aria-label={image.page_title ?? image.media_asset?.original_filename ?? `Photo ${image.sort_order}`}
                onClick={() => (onSelectToggle ? onSelectToggle(image.id) : onOpen?.(image.id))}
                className="block h-24 w-full cursor-pointer p-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
                {preview}
            </button>

            <div className="pointer-events-none absolute left-1 top-1 flex flex-col items-start gap-1">
                {selectable && (
                    <span
                        className={cn(
                            'flex size-4 items-center justify-center rounded border text-[10px] font-bold',
                            selected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background/90 text-transparent'
                        )}
                    >
                        ✓
                    </span>
                )}
                {image.is_cover && (
                    <span className="inline-flex items-center gap-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground">
                        <Crown className="size-2.5" />
                        COVER
                    </span>
                )}
                {canUpdate && <SowCompositeStatusBadge status={status} />}
            </div>

            {(canUpdate || canDelete) && (
                <>
                    {draggable && (
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
                                    onSetCover?.(image.id);
                                }}
                                className="rounded p-1 text-amber-300 transition-colors hover:bg-white/15 hover:text-amber-200 disabled:opacity-50"
                            >
                                <Crown className="size-3.5" />
                            </button>
                        )}
                        {canUpdate && (
                            <button
                                type="button"
                                title={image.include_in_pdf ? 'Exclude from PDF' : 'Include in PDF'}
                                disabled={isBusy}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onToggleInclude?.(image.id, image.include_in_pdf);
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
                                    onRemove?.(image.id);
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
}
