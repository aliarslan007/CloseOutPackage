import { canDeleteMediaAsset, usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { Check, GripVertical, ImageOff, Trash2 } from 'lucide-react';
import type { MediaAsset } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';
import { getUploaderRoleName } from '../utils/format-uploader';

export interface MediaPickerItemProps {
    asset: MediaAsset;
    isSelected: boolean;
    isDisabled: boolean;
    selectedIds?: Set<number>;
    onToggle: (asset: MediaAsset) => void;
    /** Offered only where delete is a valid action; visibility is still gated per asset. */
    onDelete?: (asset: MediaAsset) => void;
}

export function MediaPickerItem({
    asset,
    isSelected,
    isDisabled,
    selectedIds,
    onToggle,
    onDelete,
}: Readonly<MediaPickerItemProps>) {
    const { user } = usePermissions();
    const imageUrl = asset.thumb_url || asset.url || (asset.id ? route('media-library.file', { asset: asset.id }) : '');
    const uploader = asset.owner ?? asset.user ?? null;
    const roleName = getUploaderRoleName(uploader);
    // Same project tag the Media Library page renders, so a photo picked
    // across projects reads the same way in both surfaces.
    const projectTag = asset.project?.name || asset.market?.name;

    const handleDragStart = (e: React.DragEvent<HTMLButtonElement>) => {
        e.dataTransfer.setData('text/x-media-asset-id', String(asset.id));

        if (selectedIds && selectedIds.has(asset.id) && selectedIds.size > 1) {
            e.dataTransfer.setData('text/x-media-asset-ids', JSON.stringify(Array.from(selectedIds)));
        }

        e.dataTransfer.setData(
            'application/json',
            JSON.stringify({
                id: asset.id,
                filename: asset.original_filename,
                url: imageUrl,
            })
        );

        e.dataTransfer.effectAllowed = 'copy';
    };

    return (
        <div className="group relative">
            <button
                type="button"
                draggable
                onDragStart={handleDragStart}
                onClick={() => {
                    if (!isDisabled || isSelected) {
                        onToggle(asset);
                    }
                }}
                aria-pressed={isSelected}
                aria-label={`${isSelected ? 'Deselect' : 'Select'} ${asset.original_filename}`}
                title="Click to select, or drag directly onto any category"
                className={cn(
                    'flex w-full flex-col overflow-hidden rounded-lg border bg-card text-left text-card-foreground shadow-2xs transition-all select-none cursor-grab active:cursor-grabbing focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
                    // Indigo, not `primary`: primary is near-black, which vanishes
                    // against dark photos in a grid of thousands.
                    isSelected ? 'ring-2 ring-indigo-500 border-indigo-500' : 'border-border',
                    isDisabled && !isSelected
                        ? 'opacity-40'
                        : 'hover:border-indigo-500/70 hover:shadow-xs'
                )}
            >
                <div className="relative aspect-square w-full overflow-hidden bg-muted/40">
                    {imageUrl ? (
                        <img
                            src={imageUrl}
                            alt={asset.original_filename}
                            loading="lazy"
                            className="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                            <ImageOff className="h-5 w-5 opacity-40" />
                        </div>
                    )}

                    {!isSelected && projectTag && (
                        <span
                            className="absolute top-1.5 left-1.5 z-10 max-w-[70%] truncate rounded-md bg-black/65 px-2 py-0.5 text-[10px] font-medium text-white shadow-xs"
                            title={projectTag}
                        >
                            {projectTag}
                        </span>
                    )}

                    {isSelected && (
                        <>
                            {/* Ring alone is easy to miss at thumbnail size, so the
                                whole tile is washed and badged — scannable at a glance. */}
                            <div className="absolute inset-0 z-10 bg-indigo-600/25" aria-hidden="true" />
                            <div className="absolute top-1.5 left-1.5 z-20 flex size-6 items-center justify-center rounded-full bg-indigo-600 text-white shadow-md ring-2 ring-white">
                                <Check className="size-4" strokeWidth={3} />
                            </div>
                        </>
                    )}
                </div>

                <div className="p-2 space-y-0.5">
                    <p className="truncate text-xs font-medium text-foreground" title={asset.original_filename}>
                        {asset.original_filename}
                    </p>
                    <div className="flex items-center justify-between text-[10px] text-muted-foreground">
                        <span>{formatFileSize(asset.file_size_bytes)}</span>
                        {uploader && (
                            <span
                                className="truncate max-w-[80px] font-medium"
                                title={`Uploaded by ${uploader.name} (${roleName})`}
                            >
                                {uploader.name.split(' ')[0]}
                            </span>
                        )}
                    </div>
                </div>
            </button>

            {/* Overlay controls sit outside the card <button> — nested buttons
                are invalid HTML — and share one row so the trash never covers the grip. */}
            <div className="pointer-events-none absolute top-1.5 right-1.5 z-20 flex items-center gap-1">
                <span
                    className="flex size-5 items-center justify-center rounded-md bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100 shadow-xs"
                    aria-hidden="true"
                >
                    <GripVertical className="size-3.5" />
                </span>
                {onDelete && canDeleteMediaAsset(user, asset) && (
                    <button
                        type="button"
                        onClick={() => onDelete(asset)}
                        aria-label={`Delete ${asset.original_filename}`}
                        title="Delete photo"
                        className="pointer-events-none group-hover:pointer-events-auto flex size-6 items-center justify-center rounded-full bg-black/70 text-red-400 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-red-600 hover:text-white shadow-xs cursor-pointer"
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
