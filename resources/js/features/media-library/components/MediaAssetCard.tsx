import { Button } from '@/components/ui/button';
import { canDeleteMediaAsset, usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2, Clock, Eye, ImageOff, Loader2, MapPin, Paintbrush, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { MediaAsset } from '../types/media-library.types';
import { formatFileSize } from '../utils/format-file-size';
import { getUploaderInitials, getUploaderRoleName } from '../utils/format-uploader';

export interface MediaAssetCardProps {
    asset: MediaAsset;
    isSelected?: boolean;
    isSelectable?: boolean;
    isDense?: boolean;
    onSelect?: (asset: MediaAsset) => void;
    onView?: (asset: MediaAsset) => void;
    onDelete?: (asset: MediaAsset) => void;
    onAnnotate?: (asset: MediaAsset) => void;
}

function formatAssetDate(dateString?: string | null): string {
    if (!dateString) return '';
    try {
        const d = new Date(dateString);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return '';
    }
}

const BADGE_DENSE_CLASS = 'px-2 py-0.5 text-[10px]';
const BADGE_INSPECT_CLASS = 'px-2.5 py-1 text-xs font-semibold';

function MediaCardImageContent({
    asset,
    imageError,
    onImageError,
}: {
    readonly asset: MediaAsset;
    readonly imageError: boolean;
    readonly onImageError: () => void;
}) {
    if (asset.status === 'processing') {
        return (
            <div className="flex h-full w-full flex-col items-center justify-center text-primary p-3 text-center bg-muted/30">
                <Loader2 className="h-6 w-6 mb-1.5 animate-spin text-primary" />
                <span className="text-xs font-medium text-muted-foreground">Generating preview...</span>
            </div>
        );
    }

    // Rejected uploads (e.g. duplicates) have no stored file — say why on the
    // tile itself instead of rendering an img that can only 404.
    if (asset.status === 'failed') {
        return (
            <div className="flex h-full w-full flex-col items-center justify-center bg-destructive/5 p-3 text-center">
                <AlertCircle className="h-6 w-6 mb-1.5 text-destructive/70" />
                <span className="text-xs font-semibold text-destructive">Upload rejected</span>
                {asset.validation_error && (
                    <span className="mt-0.5 line-clamp-2 text-[10px] leading-tight text-muted-foreground">
                        {asset.validation_error}
                    </span>
                )}
            </div>
        );
    }

    if (imageError) {
        return (
            <div className="flex h-full w-full flex-col items-center justify-center text-muted-foreground p-3 text-center">
                <ImageOff className="h-6 w-6 mb-1 opacity-50" />
                <span className="text-xs">Preview unavailable</span>
            </div>
        );
    }

    const imageUrl = asset.thumb_url || asset.url || (asset.id ? route('media-library.file', { asset: asset.id }) : '');

    return (
        <img
            src={imageUrl}
            alt={asset.original_filename}
            loading="lazy"
            onError={onImageError}
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-103"
        />
    );
}

function MediaCardBadges({
    asset,
    isSelected,
    isDense,
}: {
    readonly asset: MediaAsset;
    readonly isSelected: boolean;
    readonly isDense: boolean;
}) {
    const badgeTextClass = isDense ? BADGE_DENSE_CLASS : BADGE_INSPECT_CLASS;
    const iconSizeClass = isDense ? 'h-3 w-3' : 'h-3.5 w-3.5';
    const tag = asset.project?.name || asset.market?.name;
    const hasGps = asset.gps_latitude !== null && asset.gps_longitude !== null;
    const isProcessing = asset.status === 'processing';
    const isFailed = asset.status === 'failed';
    const hasAnnotations = Boolean(asset.annotation_layer?.nodes && asset.annotation_layer.nodes.length > 0);

    return (
        <>
            {/* Top Left: Project Tag or Selection Checkmark */}
            <div className={cn('absolute z-10 flex items-center gap-1.5 max-w-[70%]', isDense ? 'top-2 left-2' : 'top-3 left-3')}>
                {isSelected && (
                    <div className={cn('flex items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xs shrink-0', isDense ? 'h-6 w-6' : 'h-7 w-7')}>
                        <CheckCircle2 className={cn(isDense ? 'h-4 w-4' : 'h-4.5 w-4.5')} />
                    </div>
                )}
                {!isSelected && tag && (
                    <span
                        className={cn('truncate rounded-md bg-black/65 backdrop-blur-xs font-medium text-white shadow-xs', badgeTextClass)}
                        title={tag}
                    >
                        {tag}
                    </span>
                )}
            </div>

            {/* Top Right: Status / GPS / Annotated Badges */}
            <div className={cn('absolute z-10 flex items-center gap-1.5', isDense ? 'top-2 right-2' : 'top-3 right-3')}>
                {hasAnnotations && (
                    <span
                        className={cn('flex items-center gap-1 rounded-md bg-black/65 backdrop-blur-xs font-medium text-amber-300 shadow-xs', badgeTextClass)}
                        title={`Contains ${asset.annotation_layer?.nodes?.length} re-editable annotations`}
                    >
                        <Paintbrush className={iconSizeClass} />
                        Annotated
                    </span>
                )}

                {hasGps && (
                    <span
                        className={cn('flex items-center gap-1 rounded-md bg-black/65 backdrop-blur-xs font-medium text-emerald-400 shadow-xs', badgeTextClass)}
                        title={`GPS: ${asset.gps_latitude?.toFixed(4)}, ${asset.gps_longitude?.toFixed(4)}`}
                    >
                        <MapPin className={iconSizeClass} />
                        GPS
                    </span>
                )}

                {isProcessing && (
                    <span className={cn('flex items-center gap-1 rounded-full bg-amber-500/90 backdrop-blur-xs font-medium text-white shadow-xs', isDense ? BADGE_DENSE_CLASS : 'px-2.5 py-1 text-xs')}>
                        <Clock className={cn('animate-spin', isDense ? 'h-2.5 w-2.5' : 'h-3 w-3')} />
                        Processing
                    </span>
                )}

                {isFailed && (
                    <span className={cn('flex items-center gap-1 rounded-full bg-destructive/90 backdrop-blur-xs font-medium text-white shadow-xs', isDense ? BADGE_DENSE_CLASS : 'px-2.5 py-1 text-xs')} title={asset.validation_error ?? undefined}>
                        <AlertCircle className={cn(isDense ? 'h-2.5 w-2.5' : 'h-3 w-3')} />
                        Failed
                    </span>
                )}
            </div>
        </>
    );
}

function MediaCardHoverActions({
    asset,
    isDense,
    onAnnotate,
    onView,
    onDelete,
}: {
    readonly asset: MediaAsset;
    readonly isDense: boolean;
    readonly onAnnotate?: (asset: MediaAsset) => void;
    readonly onView?: (asset: MediaAsset) => void;
    readonly onDelete?: (asset: MediaAsset) => void;
}) {
    if (!onAnnotate && !onView && !onDelete) {
        return null;
    }

    const actionBtnClass = cn(
        'gap-1.5 rounded-full shadow-md hover:scale-105 font-semibold cursor-pointer',
        isDense ? 'h-8 px-3 text-xs' : 'h-9 px-4 text-xs',
    );

    return (
        <div className="absolute inset-0 z-20 flex items-center justify-center gap-2.5 bg-black/45 opacity-0 backdrop-blur-[2px] transition-opacity group-hover:opacity-100">
            {onAnnotate && (
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className={actionBtnClass}
                    onClick={(e) => {
                        e.stopPropagation();
                        onAnnotate(asset);
                    }}
                    title="Edit in Canvas"
                    aria-label={`Annotate ${asset.original_filename}`}
                >
                    <Paintbrush className="h-3.5 w-3.5" /> Edit
                </Button>
            )}

            {onView && (
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className={actionBtnClass}
                    onClick={(e) => {
                        e.stopPropagation();
                        onView(asset);
                    }}
                    title="Inspect full image and details"
                    aria-label={`Inspect ${asset.original_filename}`}
                >
                    <Eye className="h-3.5 w-3.5" /> Inspect
                </Button>
            )}

            {onDelete && (
                <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    className={cn(
                        'rounded-full shadow-md hover:scale-105 cursor-pointer',
                        isDense ? 'h-8 w-8' : 'h-9 w-9',
                    )}
                    onClick={(e) => {
                        e.stopPropagation();
                        onDelete(asset);
                    }}
                    title="Delete photo"
                    aria-label={`Delete ${asset.original_filename}`}
                >
                    <Trash2 className={cn(isDense ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
                </Button>
            )}
        </div>
    );
}

function MediaCardFooter({
    asset,
    isDense,
    formattedDate,
}: {
    readonly asset: MediaAsset;
    readonly isDense: boolean;
    readonly formattedDate: string;
}) {
    const uploader = asset.owner ?? asset.user ?? null;
    const roleName = getUploaderRoleName(uploader);
    const initials = getUploaderInitials(uploader?.name);

    return (
        <div className={cn(isDense ? 'p-2.5 space-y-1' : 'p-3.5 sm:p-4 space-y-1.5')}>
            <div className="flex items-center justify-between gap-2">
                <p className={cn('truncate font-semibold text-foreground', isDense ? 'text-xs' : 'text-sm')} title={asset.original_filename}>
                    {asset.original_filename}
                </p>
                <span className={cn('flex-shrink-0 tabular-nums font-medium text-muted-foreground', isDense ? 'text-[10px]' : 'text-xs')}>
                    {formatFileSize(asset.file_size_bytes)}
                </span>
            </div>

            <div className={cn('flex items-center justify-between gap-1 text-muted-foreground', isDense ? 'text-[10px]' : 'text-xs')}>
                <span className="truncate" title={formattedDate}>
                    {formattedDate || 'Date not recorded'}
                </span>
                {uploader && (
                    <div
                        className="flex items-center gap-1.5 shrink-0 text-muted-foreground hover:text-foreground transition-colors cursor-help"
                        title={`Uploaded by ${uploader.name} (${roleName})`}
                    >
                        {uploader.profile_photo_url ? (
                            <img
                                src={uploader.profile_photo_url}
                                alt={uploader.name}
                                className={cn('rounded-full object-cover shrink-0 border border-border', isDense ? 'h-4 w-4' : 'h-5 w-5')}
                            />
                        ) : (
                            <span className={cn('flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-bold text-primary', isDense ? 'h-4 w-4 text-[8px]' : 'h-5 w-5 text-[9px]')}>
                                {initials}
                            </span>
                        )}
                        <span className={cn('truncate font-medium', isDense ? 'max-w-[80px]' : 'max-w-[120px]')}>
                            {uploader.name.split(' ')[0]}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}

export function MediaAssetCard({
    asset,
    isSelected = false,
    isSelectable = false,
    isDense = false,
    onSelect,
    onView,
    onDelete,
    onAnnotate,
}: Readonly<MediaAssetCardProps>) {
    const [imageError, setImageError] = useState(false);
    const { user } = usePermissions();

    useEffect(() => {
        setImageError(false);
    }, [asset.thumb_url, asset.url, asset.status]);

    const handleClick = useCallback(() => {
        if (isSelectable && onSelect) {
            onSelect(asset);
        } else if (onView) {
            onView(asset);
        }
    }, [isSelectable, onSelect, onView, asset]);

    const formattedDate = formatAssetDate(asset.captured_at || asset.created_at);

    return (
        <div
            onClick={handleClick}
            className={cn(
                'group relative flex flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xs transition-all hover:shadow-md cursor-pointer select-none',
                isSelected && 'ring-2 ring-primary ring-offset-2 border-primary',
            )}
        >
            {/* Image Container with native 4:3 camera aspect ratio in inspection mode */}
            <div className={cn('relative w-full overflow-hidden bg-muted/40 flex items-center justify-center', isDense ? 'aspect-square' : 'aspect-[4/3]')}>
                <MediaCardImageContent
                    asset={asset}
                    imageError={imageError}
                    onImageError={() => setImageError(true)}
                />

                <MediaCardBadges
                    asset={asset}
                    isSelected={isSelected}
                    isDense={isDense}
                />

                <MediaCardHoverActions
                    asset={asset}
                    isDense={isDense}
                    onAnnotate={onAnnotate}
                    onView={onView}
                    onDelete={canDeleteMediaAsset(user, asset) ? onDelete : undefined}
                />
            </div>

            <MediaCardFooter
                asset={asset}
                isDense={isDense}
                formattedDate={formattedDate}
            />
        </div>
    );
}
