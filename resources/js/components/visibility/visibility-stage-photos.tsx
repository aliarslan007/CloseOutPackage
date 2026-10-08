import { cn } from '@/lib/utils';
import { STAGE_LABELS } from '@/components/templates/template-types';
import { VisibilityPackagePhoto } from '@/components/visibility/visibility-project-types';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { Check, Camera, Flag, MapPin, RotateCcw, X } from 'lucide-react';
import * as React from 'react';
import { useState } from 'react';

export interface VisibilityStagePhotosProps {
    stage: string;
    photos?: VisibilityPackagePhoto[];
    className?: string;
}

/** Photos shown per stage before the "more" tile takes over to the lightbox. */
const PHOTO_PREVIEW_LIMIT = 10;

type StatusMarker = { label: string; icon: React.ReactNode; chipClass: string };

const PHOTO_STATUS_MARKERS: Record<string, StatusMarker> = {
    approved: {
        label: 'Approved',
        icon: <Check className="size-2.5 stroke-[3]" />,
        chipClass: 'bg-emerald-500 text-white',
    },
    flagged: {
        label: 'Flagged as Blurry',
        icon: <Flag className="size-2.5" />,
        chipClass: 'bg-red-500 text-white',
    },
    rejected: {
        label: 'Rejected',
        icon: <X className="size-2.5" />,
        chipClass: 'bg-red-600 text-white',
    },
    replacement_requested: {
        label: 'Replacement Requested',
        icon: <RotateCcw className="size-2.5" />,
        chipClass: 'bg-amber-500 text-white',
    },
    pending: {
        label: 'Pending Review',
        icon: <Camera className="size-2.5" />,
        chipClass: 'bg-blue-500 text-white',
    },
};

function StatusMarkerDot({ status }: Readonly<{ status: string }>) {
    const Marker = PHOTO_STATUS_MARKERS[status] ?? {
        label: (status || 'pending').replace(/_/g, ' '),
        icon: null,
        chipClass: 'bg-blue-500 text-white',
    };

    return (
        <span
            title={Marker.label}
            aria-label={Marker.label}
            className={cn(
                'absolute right-1.5 top-1.5 z-10 flex size-4 items-center justify-center rounded-full border border-white/70 shadow-sm',
                Marker.chipClass
            )}
        >
            {Marker.icon}
        </span>
    );
}

export function VisibilityStagePhotos({ stage, photos = [], className }: Readonly<VisibilityStagePhotosProps>) {
    const stageLabel = STAGE_LABELS[stage as keyof typeof STAGE_LABELS] ?? stage.replace(/_/g, ' ');
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    const lightboxPhotos: LightboxPhoto[] = photos.map((photo) => {
        const categoryName = photo.category_selection?.template_picture_category?.name ?? null;
        const caption = categoryName ?? `Photo #${photo.position}`;

        return {
            id: photo.id,
            url: photo.url ?? '',
            caption,
            alt: caption,
            status: photo.status,
            reason: photo.flag_reason ?? photo.photo_approval?.reason ?? undefined,
            latitude: photo.gps_latitude,
            longitude: photo.gps_longitude,
        };
    });

    const previews = photos.slice(0, PHOTO_PREVIEW_LIMIT);
    const hiddenCount = photos.length - previews.length;

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-baseline justify-between gap-2">
                <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    {stageLabel}
                </p>
                <span className="text-[10px] font-semibold text-muted-foreground">
                    {photos.length} photo{photos.length === 1 ? '' : 's'}
                </span>
            </div>

            {photos.length === 0 ? (
                <p className="text-[11px] text-muted-foreground/80">None yet.</p>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                    {previews.map((photo, index) => (
                        <PhotoTile
                            key={photo.id}
                            photo={photo}
                            onView={() => setLightboxIndex(index)}
                        />
                    ))}
                    {hiddenCount > 0 && (
                        <button
                            type="button"
                            onClick={() => setLightboxIndex(0)}
                            className="flex aspect-[4/3] min-h-[88px] items-center justify-center rounded-md border border-border/70 bg-muted/40 text-[11px] font-semibold text-muted-foreground transition-colors hover:bg-muted/70 hover:text-foreground"
                            aria-label={`View ${hiddenCount} more photos`}
                        >
                            +{hiddenCount} more
                        </button>
                    )}
                </div>
            )}

            <PhotoLightbox
                photos={lightboxPhotos}
                index={lightboxIndex}
                onClose={() => setLightboxIndex(null)}
            />
        </div>
    );
}

function PhotoTile({
    photo,
    onView,
}: Readonly<{ photo: VisibilityPackagePhoto; onView: () => void }>) {
    const src = photo.thumb_url ?? photo.url;
    const categoryName = photo.category_selection?.template_picture_category?.name ?? null;
    const caption = categoryName ?? `Photo #${photo.position}`;
    const reason = photo.flag_reason ?? photo.photo_approval?.reason ?? null;

    return (
        <div className="group relative aspect-[4/3] min-h-[88px] overflow-hidden rounded-md border border-border/60 bg-muted/30">
            <button
                type="button"
                onClick={onView}
                title={reason ? `${caption} — ${reason}` : caption}
                aria-label={`View ${caption} full size`}
                className="block size-full cursor-zoom-in text-left"
            >
                <img
                    src={src ?? ''}
                    alt={caption}
                    loading="lazy"
                    decoding="async"
                    className="size-full object-cover transition-transform duration-200 group-hover:scale-[1.03]"
                />
            </button>

            {reason ? (
                <span className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-2 pb-1.5 pt-4 text-[10px] font-medium text-white line-clamp-2">
                    {reason}
                </span>
            ) : (
                <span className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2 pb-1.5 pt-4 text-[10px] font-medium text-white truncate opacity-0 transition-opacity group-hover:opacity-100">
                    {caption}
                </span>
            )}

            <StatusMarkerDot status={photo.status} />

            {photo.gps_latitude != null && photo.gps_longitude != null && (
                <a
                    href={`https://www.google.com/maps?q=${photo.gps_latitude},${photo.gps_longitude}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Open location on Google Maps"
                    aria-label={`Open location ${photo.gps_latitude}, ${photo.gps_longitude} on Google Maps`}
                    className="absolute bottom-1.5 right-1.5 z-10 flex size-5 items-center justify-center rounded-full bg-black/70 text-white backdrop-blur transition-colors hover:bg-black/90"
                >
                    <MapPin className="size-3 text-emerald-400" />
                </a>
            )}
        </div>
    );
}
