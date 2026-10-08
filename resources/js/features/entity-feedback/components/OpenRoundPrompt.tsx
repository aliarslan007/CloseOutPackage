import { Button } from '@/components/ui/button';
import { Loader2, Unlock } from 'lucide-react';
import * as React from 'react';
import { ACTION_LABELS } from '../utils/feedback-copy';

export interface OpenRoundPromptProps {
    onOpen: (managerInstructions?: string) => void;
    busy?: boolean;
}

/**
 * The one place a manager opens a round from, when none exists yet.
 *
 * `onOpen` takes the optional instructions argument on purpose: wiring it
 * straight to onClick would hand it the MouseEvent, and Inertia would try to
 * serialise a cyclic event object and blow the stack.
 */
export function OpenRoundPrompt({ onOpen, busy = false }: Readonly<OpenRoundPromptProps>) {
    return (
        <div className="rounded-xl border border-dashed border-border bg-card/40 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-0.5">
                    <h3 className="text-sm font-bold tracking-tight text-foreground">
                        Customer Review
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Start a review so the customer can mark up photos and send you their notes.
                    </p>
                </div>
                <Button
                    size="sm"
                    className="h-8 gap-1.5 text-xs font-bold shadow-2xs"
                    disabled={busy}
                    onClick={() => onOpen()}
                >
                    {busy ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Unlock className="size-3.5" />
                    )}
                    <span>{ACTION_LABELS.openReview}</span>
                </Button>
            </div>
        </div>
    );
}
