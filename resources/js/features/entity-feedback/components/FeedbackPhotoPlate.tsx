import { ACTION_LABELS } from '../utils/feedback-copy';
import { ChevronLeft, ChevronRight, MapPin } from 'lucide-react';
import * as React from 'react';

/**
 * The photo(s) a conversation is about, height-clamped so a tall portrait
 * upload can never push the discussion or the reply box off screen. One image
 * shows at a time; when the thread carries several, side arrows page through
 * and a corner counter says where we are. Clicking the photo itself opens it
 * full size in a new tab - no separate button for the obvious action.
 */

export interface FeedbackPhotoPlateProps {
    /** Every image in the thread, in order. Empty array renders nothing. */
    images: string[];
    altText: string;
    /** Present only when the viewer can scroll the outer grid to this photo. */
    onJumpToPhoto?: () => void;
}

export function FeedbackPhotoPlate({
    images,
    altText,
    onJumpToPhoto,
}: Readonly<FeedbackPhotoPlateProps>) {
    const [index, setIndex] = React.useState(0);
    const total = images.length;
    if (total === 0) {
        return null;
    }
    const safe = Math.min(index, total - 1);
    const url = images[safe];
    const step = (delta: number) => setIndex((safe + delta + total) % total);

    return (
        <figure className="space-y-1.5">
            <div className="relative overflow-hidden rounded-lg border border-border/70 bg-muted/60 p-1.5">
                <a href={url} target="_blank" rel="noreferrer" title="Open photo full size">
                    <img
                        src={url}
                        alt={altText}
                        loading="lazy"
                        decoding="async"
                        className="mx-auto block max-h-80 w-full cursor-zoom-in object-contain sm:max-h-96"
                    />
                </a>

                {total > 1 && (
                    <>
                        <button
                            type="button"
                            aria-label="Previous photo"
                            onClick={() => step(-1)}
                            className="absolute left-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        >
                            <ChevronLeft className="size-5" />
                        </button>
                        <button
                            type="button"
                            aria-label="Next photo"
                            onClick={() => step(1)}
                            className="absolute right-2 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                        >
                            <ChevronRight className="size-5" />
                        </button>
                        <span className="absolute bottom-2 right-2 rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-white">
                            {safe + 1} / {total}
                        </span>
                    </>
                )}
            </div>

            <figcaption className="flex flex-wrap items-center gap-2">
                {onJumpToPhoto && (
                    <button
                        type="button"
                        onClick={onJumpToPhoto}
                        className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-primary/40 bg-primary/10 px-3 text-xs font-semibold text-primary transition-colors hover:bg-primary/20"
                    >
                        <MapPin className="size-3.5" />
                        <span>{ACTION_LABELS.showInGrid}</span>
                    </button>
                )}
            </figcaption>
        </figure>
    );
}
