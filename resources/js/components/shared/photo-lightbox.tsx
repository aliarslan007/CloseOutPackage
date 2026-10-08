import { GpsBadge } from '@/components/shared/gps-badge';
import { cn } from '@/lib/utils';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    Loader,
    RotateCcw,
    X,
    ZoomIn,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

export interface LightboxPhoto {
    id: number | string;
    url: string;
    alt?: string;
    caption?: string;
    status?: string;
    reason?: string;
    notes?: string | null;
    latitude?: number | null;
    longitude?: number | null;
}

interface PhotoLightboxProps {
    photos: LightboxPhoto[];
    index: number | null;
    onClose: () => void;
}

function getStatusLabel(status?: string): string {
    if (status === 'flagged') {
        return 'Blurry';
    }
    if (status === 'replacement_requested') {
        return 'Replacement Requested';
    }
    if (status === 'approved') {
        return 'Approved';
    }
    if (status === 'rejected') {
        return 'Rejected';
    }
    return 'Pending';
}

function getStatusBadgeClass(status?: string): string {
    if (status === 'approved') {
        return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40';
    }
    if (status === 'flagged') {
        return 'bg-red-500/15 text-red-300 border-red-500/40';
    }
    if (status === 'rejected' || status === 'replacement_requested') {
        return 'bg-amber-500/15 text-amber-300 border-amber-500/40';
    }
    return 'bg-white/10 text-zinc-300 border-white/15';
}

function getStatusIcon(status?: string): ReactNode {
    if (status === 'replacement_requested' || status === 'rejected') {
        return <RotateCcw className="size-3" />;
    }
    if (status === 'flagged') {
        return <AlertTriangle className="size-3" />;
    }
    return null;
}

function clampIndex(index: number, length: number): number {
    if (length <= 0) {
        return 0;
    }
    return Math.min(length - 1, Math.max(0, index));
}

/**
 * Enterprise full-screen photo lightbox. Opens a large zoomable preview of a
 * single photo with keyboard navigation (←/→/Esc), status + reason context,
 * GPS link, and a download action. Replaces opening photos in a new browser tab.
 */
export function PhotoLightbox({ photos, index, onClose }: Readonly<PhotoLightboxProps>) {
    const open = index !== null;
    const [current, setCurrent] = useState(() => clampIndex(index ?? 0, photos.length));

    // Keep internal index in sync when a new photo is clicked, and clamp it
    // if the photos list ever shrinks while the lightbox is open.
    useEffect(() => {
        if (index !== null) {
            setCurrent(clampIndex(index, photos.length));
        }
    }, [index, photos.length]);

    // Read the latest index from a ref inside the keyboard handler so the
    // window listener stays mounted for the whole time the lightbox is open.
    const currentRef = useRef(current);
    useEffect(() => {
        currentRef.current = current;
    }, [current]);

    const goPrev = useCallback(() => {
        setCurrent((prev) => clampIndex(prev - 1, photos.length));
    }, [photos.length]);

    const goNext = useCallback(() => {
        setCurrent((prev) => clampIndex(prev + 1, photos.length));
    }, [photos.length]);

    // Keyboard navigation while the lightbox is open.
    useEffect(() => {
        if (!open) {
            return;
        }
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goPrev();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                goNext();
            }
        };
        globalThis.addEventListener('keydown', onKeyDown);
        return () => globalThis.removeEventListener('keydown', onKeyDown);
    }, [open, goPrev, goNext]);

    const photo = photos[current];
    const hasMultiple = photos.length > 1;
    const canPrev = hasMultiple && current > 0;
    const canNext = hasMultiple && current < photos.length - 1;
    const isApproved = photo?.status === 'approved';

    return (
        <DialogPrimitive.Root
            open={open}
            onOpenChange={(isOpen) => {
                if (!isOpen) {
                    onClose();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/85 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
                <DialogPrimitive.Content
                    aria-label="Photo viewer"
                    aria-describedby={undefined}
                    className={cn(
                        'fixed left-1/2 top-1/2 z-50 flex w-[min(94vw,1100px)] max-h-[92vh] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-white/10 bg-zinc-950 text-white shadow-2xl outline-none',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95'
                    )}
                >
                    {/* Header: context + actions */}
                    <div className="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3 sm:px-5">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <span
                                className={cn(
                                    'inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                    getStatusBadgeClass(photo?.status)
                                )}
                            >
                                {getStatusIcon(photo?.status)}
                                {getStatusLabel(photo?.status)}
                            </span>
                            <p className="truncate text-sm font-semibold text-zinc-100">
                                {photo?.caption ?? 'Photo'}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-1.5">
                            {hasMultiple && (
                                <span className="mr-1 hidden rounded-full bg-white/10 px-2.5 py-1 font-mono text-[11px] font-semibold tabular-nums text-zinc-300 sm:inline-flex">
                                    {current + 1} / {photos.length}
                                </span>
                            )}
                            <a
                                href={photo?.url}
                                download
                                aria-label="Download photo"
                                title="Download photo"
                                className="flex size-9 items-center justify-center rounded-lg bg-white/10 text-zinc-200 transition-colors hover:bg-white/20 hover:text-white"
                            >
                                <Download className="size-4" />
                            </a>
                            <DialogPrimitive.Close
                                aria-label="Close photo viewer"
                                title="Close (Esc)"
                                className="flex size-9 items-center justify-center rounded-lg bg-white/10 text-zinc-200 transition-colors hover:bg-white/20 hover:text-white"
                            >
                                <X className="size-4" />
                            </DialogPrimitive.Close>
                        </div>
                    </div>

                    {/* Image stage */}
                    <div className="relative flex min-h-[320px] flex-1 items-center justify-center overflow-hidden bg-black/60 p-3 sm:p-5">
                        {photo ? (
                            <img
                                key={photo.id}
                                src={photo.url}
                                alt={photo.alt ?? photo.caption ?? 'Photo preview'}
                                className="max-h-[70vh] max-w-full rounded-lg object-contain shadow-2xl"
                            />
                        ) : (
                            <Loader className="size-8 animate-spin text-zinc-500" />
                        )}

                        {/* Prev / Next */}
                        {canPrev && (
                            <button
                                type="button"
                                onClick={goPrev}
                                aria-label="Previous photo"
                                title="Previous (←)"
                                className="absolute left-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white shadow-lg backdrop-blur transition-all hover:bg-black/80 hover:scale-105 sm:left-4"
                            >
                                <ChevronLeft className="size-6" />
                            </button>
                        )}
                        {canNext && (
                            <button
                                type="button"
                                onClick={goNext}
                                aria-label="Next photo"
                                title="Next (→)"
                                className="absolute right-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white shadow-lg backdrop-blur transition-all hover:bg-black/80 hover:scale-105 sm:right-4"
                            >
                                <ChevronRight className="size-6" />
                            </button>
                        )}

                        {/* Hint */}
                        <span className="pointer-events-none absolute bottom-3 left-1/2 hidden -translate-x-1/2 items-center gap-1.5 rounded-full bg-black/40 px-2.5 py-1 text-[10px] font-medium text-zinc-300 backdrop-blur md:inline-flex">
                            <ZoomIn className="size-3" />
                            Click image grid to browse · Use ← → keys
                        </span>
                    </div>

                    {/* Footer: notes + reason + GPS */}
                    {(photo?.notes || photo?.reason || photo?.latitude != null) && (
                        <div className="flex flex-col gap-2 border-t border-white/10 px-4 py-2.5 sm:px-5">
                            {photo?.notes && (
                                <div className="rounded-lg bg-white/5 border border-white/10 p-2.5 text-xs text-zinc-200 flex items-start gap-2">
                                    <FileText className="size-4 shrink-0 text-primary mt-0.5" />
                                    <div className="min-w-0 flex-1">
                                        <span className="font-bold text-white text-[11px] uppercase tracking-wider block mb-0.5">
                                            Photo Note
                                        </span>
                                        <p className="whitespace-pre-wrap text-zinc-300 leading-relaxed break-words">{photo.notes}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex flex-wrap items-center gap-2">
                                {photo.reason && (
                                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-red-500/15 px-2.5 py-1 text-[11px] font-semibold text-red-300">
                                        <AlertTriangle className="size-3 shrink-0" />
                                        {photo.reason}
                                    </span>
                                )}
                                {photo?.latitude != null && photo.longitude != null && (
                                    <GpsBadge latitude={photo.latitude} longitude={photo.longitude} />
                                )}
                                {isApproved && (
                                    <span className="ml-auto text-[11px] font-medium text-emerald-400">
                                        Approved photo
                                    </span>
                                )}
                            </div>
                        </div>
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

export default PhotoLightbox;
