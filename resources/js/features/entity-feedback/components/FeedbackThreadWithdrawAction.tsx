import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Undo2 } from 'lucide-react';
import * as React from 'react';

/**
 * The customer's "I don't want that problem resolved" button: it takes ONE issue
 * off the list and leaves the rest of the review alone. Nothing disappears - the
 * conversation stays readable for everyone and is labelled withdrawn, so the
 * manager knows why the work stopped rather than wondering.
 *
 * It sits under the conversation, never inside the accordion row, because that
 * row is a button itself and a button cannot live inside a button.
 */

export interface FeedbackThreadWithdrawActionProps {
    /** Names the issue in the confirmation, e.g. "Section: Grounding". */
    targetLabel: string;
    onWithdraw: () => void;
    isSubmitting?: boolean;
}

export function FeedbackThreadWithdrawAction({
    targetLabel,
    onWithdraw,
    isSubmitting = false,
}: Readonly<FeedbackThreadWithdrawActionProps>) {
    const [confirming, setConfirming] = React.useState(false);

    return (
        <>
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={() => setConfirming(true)}
                    disabled={isSubmitting}
                    className="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/40 disabled:opacity-50"
                >
                    <Undo2 className="size-3.5" aria-hidden />
                    Withdraw this issue
                </button>
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Withdraw this issue?"
                description={`“${targetLabel}” goes off the list, so nobody works on it. The rest of your review stays open and the conversation is kept for your records. Changed your mind? Reply in this thread and it goes back on.`}
                confirmLabel="Withdraw this issue"
                cancelLabel="Keep it"
                onConfirm={() => {
                    setConfirming(false);
                    onWithdraw();
                }}
            />
        </>
    );
}
