import * as React from 'react';
import { Pencil, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';

export interface FeedbackImageHoverOverlayProps {
    note?: EntityFeedbackNoteItem | null;
    canAnnotate?: boolean;
    onAnnotate: () => void;
    className?: string;
}

export function FeedbackImageHoverOverlay({
    note,
    canAnnotate = true,
    onAnnotate,
    className,
}: Readonly<FeedbackImageHoverOverlayProps>) {
    if (!canAnnotate && !note) {
        return null;
    }

    // The customer dropped this issue: showing "Mark / Annotate" or "Edit
    // Annotation" on its photo/section invites the very change they asked to
    // cancel. The thread is gone from the list, so its tools go too. Reopening
    // happens by replying in the thread, not by re-annotating the picture.
    if (note?.is_withdrawn) {
        return null;
    }

    const hasExistingNote = Boolean(note);

    return (
        <div
            className={cn(
                'absolute inset-0 z-20 flex flex-col items-center justify-center p-2',
                'bg-black/40 backdrop-blur-[2px] transition-all duration-200',
                hasExistingNote ? 'opacity-90 hover:opacity-100' : 'opacity-0 hover:opacity-100',
                className
            )}
        >
            {hasExistingNote && (
                <div className="absolute top-2 right-2 inline-flex items-center gap-1 rounded-full bg-amber-500/90 px-2 py-0.5 text-[9px] font-bold text-amber-950 shadow-xs">
                    <Sparkles className="size-2.5" />
                    <span>Review Note Attached</span>
                </div>
            )}

            {canAnnotate && (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={(e) => {
                        // This button lives inside an <a target="_blank"> card on the
                        // change-order grid. stopPropagation alone leaves the native
                        // click on the anchor, so the browser opens the raw image in a
                        // new tab *and* the dialog — prevent both.
                        e.preventDefault();
                        e.stopPropagation();
                        onAnnotate();
                    }}
                    className="h-7 gap-1.5 px-3 text-xs font-semibold bg-background/90 hover:bg-background text-foreground shadow-md transition-transform hover:scale-105 cursor-pointer"
                >
                    <Pencil className="size-3 text-primary" />
                    <span>{hasExistingNote ? 'Edit Annotation / Query' : 'Mark / Annotate'}</span>
                </Button>
            )}
        </div>
    );
}
