import type { ChangeOrderPhoto } from '@/components/change-orders/change-order-types';
import { cn } from '@/lib/utils';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { ChevronLeft, ChevronRight, ExternalLink, X } from 'lucide-react';
import * as React from 'react';

export interface ChangeOrderPhotoViewerProps {
    photos: ChangeOrderPhoto[];
    /** Index of the open photo, or null when the viewer is closed. */
    index: number | null;
    onIndexChange: (index: number | null) => void;
}

const navButtonClass =
    'flex size-10 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white backdrop-blur-sm transition-colors hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 cursor-pointer disabled:opacity-40';

/**
 * Full-screen viewer for change-order photos.
 *
 * Built on the Radix dialog primitive so focus trapping, Escape-to-close and
 * screen-reader labelling come for free. Arrow keys move between photos and
 * wrap around at both ends.
 */
export function ChangeOrderPhotoViewer({
    photos,
    index,
    onIndexChange,
}: Readonly<ChangeOrderPhotoViewerProps>) {
    const total = photos.length;
    const photo = index === null ? undefined : photos[index];
    const isOpen = photo !== undefined;

    const go = React.useCallback(
        (delta: number) => {
            if (index === null || total === 0) return;
            onIndexChange((index + delta + total) % total);
        },
        [index, total, onIndexChange]
    );

    return (
        <DialogPrimitive.Root
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) onIndexChange(null);
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/90 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0" />
                <DialogPrimitive.Content
                    aria-describedby={undefined}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            go(1);
                        }
                        if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            go(-1);
                        }
                    }}
                    className="fixed inset-0 z-50 flex flex-col outline-none"
                >
                    <DialogPrimitive.Title className="sr-only">
                        Supporting photo viewer
                    </DialogPrimitive.Title>

                    {/* Toolbar */}
                    <div className="flex shrink-0 items-start justify-between gap-4 p-4">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-white">
                                {photo?.original_name}
                            </p>
                            <p className="mt-0.5 text-[11px] tabular-nums text-white/60">
                                {(index ?? 0) + 1} of {total}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {photo && (
                                <a
                                    href={photo.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={navButtonClass}
                                    aria-label="Open photo in a new tab"
                                    title="Open in new tab"
                                >
                                    <ExternalLink className="size-4" />
                                </a>
                            )}
                            <DialogPrimitive.Close className={navButtonClass} aria-label="Close viewer">
                                <X className="size-4" />
                            </DialogPrimitive.Close>
                        </div>
                    </div>

                    {/* Stage */}
                    <div className="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-6 sm:px-16">
                        {photo && (
                            <img
                                src={photo.url}
                                alt={photo.original_name}
                                className="max-h-full max-w-full rounded-lg object-contain shadow-2xl"
                            />
                        )}

                        {total > 1 && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => go(-1)}
                                    className={cn('absolute left-2 sm:left-5', navButtonClass)}
                                    aria-label="Previous photo"
                                >
                                    <ChevronLeft className="size-5" />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => go(1)}
                                    className={cn('absolute right-2 sm:right-5', navButtonClass)}
                                    aria-label="Next photo"
                                >
                                    <ChevronRight className="size-5" />
                                </button>
                            </>
                        )}
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

export default ChangeOrderPhotoViewer;
