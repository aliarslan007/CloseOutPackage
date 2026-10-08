import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Loader2, Send, Sparkles } from 'lucide-react';
import { ACTION_LABELS } from '../utils/feedback-copy';

export interface FeedbackSubmitBarProps {
    noteCount: number;
    isSubmitting?: boolean;
    onSubmit: () => Promise<void> | void;
    /** Hidden once the entity is decided and the review is closed. */
    hidden?: boolean;
}

export function FeedbackSubmitBar({
    noteCount,
    isSubmitting = false,
    onSubmit,
    hidden = false,
}: Readonly<FeedbackSubmitBarProps>) {
    if (noteCount === 0 || hidden) {
        return null;
    }

    return (
        <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-full max-w-2xl px-4 animate-in fade-in slide-in-from-bottom-3 duration-200">
            <div className="rounded-xl border border-primary/30 bg-background/95 p-3 shadow-xl backdrop-blur-md flex items-center justify-between gap-3">
                <div className="flex items-center gap-2.5 min-w-0">
                    <div className="size-8 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <Sparkles className="size-4 text-primary" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-xs font-bold text-foreground truncate">
                            {noteCount} {noteCount === 1 ? 'note' : 'notes'} ready
                        </p>
                        <p className="text-[11px] text-muted-foreground truncate">
                            Everything you marked up goes to the team in one go.
                        </p>
                    </div>
                </div>

                <Button
                    type="button"
                    size="sm"
                    onClick={onSubmit}
                    disabled={isSubmitting}
                    className="h-8 gap-1.5 px-4 text-xs font-bold shadow-sm shrink-0 cursor-pointer"
                >
                    {isSubmitting ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Send className="size-3.5" />
                    )}
                    <span>{isSubmitting ? 'Sending…' : ACTION_LABELS.submitFeedback}</span>
                </Button>
            </div>
        </div>
    );
}
