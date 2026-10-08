import { cn } from '@/lib/utils';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Maximize2 } from 'lucide-react';

/**
 * Two lines hold roughly this much inside a 4-up photo card. Below it there is
 * nothing to expand, so short reasons stay plain text with no affordance.
 */
const CLAMP_THRESHOLD = 120;

export interface PhotoReasonNoteProps {
    reason: string | null | undefined;
    className?: string;
}

/**
 * Why a photo was flagged or had a replacement requested.
 *
 * The reason can run to 1,000 characters, and these cards sit in a CSS grid —
 * one long reason grew the whole row and left the photos beside it with a wall
 * of empty space. So the note holds two lines with an ellipsis and the full text
 * opens in a popover, which portals to the body and therefore escapes the
 * card's own `overflow-hidden` without changing the card's height.
 *
 * Short reasons render as plain text: a click target that opens nothing is
 * worse than no click target.
 */
export function PhotoReasonNote({ reason, className }: Readonly<PhotoReasonNoteProps>) {
    if (!reason || reason.trim() === '') {
        return null;
    }

    const isLong = reason.length > CLAMP_THRESHOLD;
    const noteClass = 'rounded bg-destructive/10 p-1.5 text-[11px] font-medium text-destructive';

    if (!isLong) {
        return (
            <p className={cn(noteClass, className)}>
                <span className="font-semibold">Reason: </span>
                {reason}
            </p>
        );
    }

    return (
        <Popover className="relative">
            {({ close }) => (
                <>
                    <PopoverButton
                        className={cn(
                            noteClass,
                            'relative w-full cursor-pointer pr-7 text-left transition-colors hover:bg-destructive/15 focus:outline-none focus-visible:ring-1 focus-visible:ring-destructive',
                            className
                        )}
                        aria-label="Read the full reason this photo was flagged"
                    >
                        <span className="line-clamp-2">
                            <span className="font-semibold">Reason: </span>
                            {reason}
                        </span>
                        {/* Absolute so the affordance costs no extra height. */}
                        <Maximize2
                            aria-hidden="true"
                            className="absolute right-1.5 top-1.5 size-3 text-destructive/60"
                        />
                    </PopoverButton>

                    <PopoverPanel
                        transition
                        anchor={{ to: 'bottom start', gap: 6 }}
                        className="z-50 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-border/80 bg-card p-3.5 shadow-xl ring-1 ring-black/5 focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
                        onClick={close}
                    >
                        <p className="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                            Why this photo was flagged
                        </p>
                        {/* The cap keeps a very long reason from becoming a very tall panel. */}
                        <p className="max-h-64 overflow-y-auto text-xs leading-relaxed text-foreground/90 [overflow-wrap:anywhere]">
                            {reason}
                        </p>
                    </PopoverPanel>
                </>
            )}
        </Popover>
    );
}
