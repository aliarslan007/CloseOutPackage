import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { StatusBadge } from '@/components/shared/status-badge';
import { GpsBadge } from '@/components/shared/gps-badge';
import { Button } from '@/components/ui/button';
import { AlertTriangle, RotateCcw, Eye, Check, X, Flag } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PhotoReasonNote } from './photo-reason-note';

export interface PhotoData {
    id: number;
    url: string;
    thumbnailUrl?: string;
    categoryName?: string;
    status: 'pending' | 'approved' | 'flagged' | 'rejected' | string;
    notes?: string;
    latitude?: number | null;
    longitude?: number | null;
    takenAt?: string | null;
    rejectionReason?: string;
}

export interface PhotoCardProps {
    photo: PhotoData;
    showGps?: boolean;
    onView?: (photo: PhotoData) => void;
    onApprove?: (photo: PhotoData) => void;
    onReject?: (photo: PhotoData) => void;
    onFlag?: (photo: PhotoData) => void;
    onRequestReplacement?: (photo: PhotoData) => void;
    className?: string;
}

export function PhotoCard({
    photo,
    showGps = false,
    onView,
    onApprove,
    onReject,
    onFlag,
    onRequestReplacement,
    className,
}: PhotoCardProps) {
    if (!photo) {
        return null;
    }

    const isRejected = photo.status === 'rejected';
    const isFlagged = photo.status === 'flagged';
    const isApproved = photo.status === 'approved';

    return (
        <div
            className={cn(
                'group relative rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden flex flex-col transition-all',
                isRejected && 'border-destructive/60 ring-1 ring-destructive/30',
                isFlagged && 'border-amber-500/60 ring-1 ring-amber-500/30',
                isApproved && 'border-emerald-500/60',
                className
            )}
        >
            {/* Thumbnail Image Container */}
            <div className="relative aspect-[4/3] w-full h-44 sm:h-48 shrink-0 bg-muted overflow-hidden">
                <img
                    src={photo.thumbnailUrl ?? photo.url}
                    alt={photo.categoryName ?? 'Construction photo'}
                    className="size-full object-cover transition-transform group-hover:scale-105"
                    loading="lazy"

                />

                {/* Overlays */}
                <div className="absolute top-2 left-2 flex flex-wrap gap-1">
                    <StatusBadge status={photo.status} />
                    {isFlagged && (
                        <Badge variant="warning" className="gap-1">
                            <AlertTriangle className="size-3" /> Blurry
                        </Badge>
                    )}
                    {isRejected && (
                        <Badge variant="destructive" className="gap-1">
                            <RotateCcw className="size-3" /> Replacement Requested
                        </Badge>
                    )}
                </div>

                {/* GPS Badge (Role Permitted, clickable) */}
                {showGps && photo.latitude != null && photo.longitude != null && (
                    <div className="absolute bottom-2 left-2">
                        <GpsBadge latitude={photo.latitude} longitude={photo.longitude} />
                    </div>
                )}

                {/* View Overlay Button */}
                {onView && (
                    <button
                        type="button"
                        onClick={() => onView(photo)}
                        className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white"
                        aria-label="View photo full size"
                    >
                        <Eye className="size-8" />
                    </button>
                )}
            </div>

            {/* Content & Actions */}
            <div className="p-3 flex flex-col justify-between flex-1 gap-2">
                <div>
                    {photo.categoryName && (
                        <p className="text-xs font-bold text-foreground truncate">{photo.categoryName}</p>
                    )}
                    {photo.notes && (
                        <p className="text-xs text-muted-foreground line-clamp-2 mt-0.5" title={photo.notes}>
                            {photo.notes}
                        </p>
                    )}
                    <PhotoReasonNote reason={photo.rejectionReason} className="mt-1" />
                </div>

                {/* Action Toolbar */}
                {(onApprove || onReject || onFlag || onRequestReplacement) && (
                    <div className="flex items-center gap-1 pt-2 border-t">
                        {onApprove && (
                            <Button
                                size="sm"
                                variant={isApproved ? 'success' : 'outline'}
                                className="h-7 flex-1 px-2 text-xs"
                                onClick={() => onApprove(photo)}
                            >
                                <Check className="size-3 mr-1" /> Approve
                            </Button>
                        )}
                        {onFlag && (
                            <Button
                                size="sm"
                                variant={isFlagged ? 'warning' : 'outline'}
                                className="h-7 px-2 text-xs"
                                onClick={() => onFlag(photo)}
                                title="Flag blurry photo"
                            >
                                <Flag className="size-3" />
                            </Button>
                        )}
                        {onReject && (
                            <Button
                                size="sm"
                                variant={isRejected ? 'destructive' : 'outline'}
                                className="h-7 px-2 text-xs text-destructive"
                                onClick={() => onReject(photo)}
                                title="Reject & request replacement"
                            >
                                <X className="size-3" />
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

export default PhotoCard;
