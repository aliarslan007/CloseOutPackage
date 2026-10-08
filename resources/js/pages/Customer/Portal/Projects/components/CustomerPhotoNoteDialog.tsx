import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle2, MessageSquare, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { EntityFeedbackNoteItem } from '@/features/entity-feedback';

/** What the note is attached to: a photo, or a whole category section. */
export interface NoteDialogTarget {
    type: 'photo' | 'category';
    id: number;
    title: string;
    subtitle?: string;
    photoUrl?: string | null;
    existingNote?: EntityFeedbackNoteItem | null;
}

interface CustomerPhotoNoteDialogProps {
    target: NoteDialogTarget | null;
    open: boolean;
    submitting: boolean;
    onClose: () => void;
    onSave: (body: string) => void;
    onDelete?: (note: EntityFeedbackNoteItem) => void;
}

function getSaveButtonLabel(submitting: boolean, hasExistingNote: boolean): string {
    if (submitting) {
        return 'Saving…';
    }
    if (hasExistingNote) {
        return 'Update Note';
    }
    return 'Save Note';
}

export function CustomerPhotoNoteDialog({
    target,
    open,
    submitting,
    onClose,
    onSave,
    onDelete,
}: Readonly<CustomerPhotoNoteDialogProps>) {
    const [body, setBody] = useState('');
    const seededForId = useRef<number | null>(null);

    // Reseed only when the note being edited actually changes.
    //
    // The parent passes `target` as an inline object literal, so a new reference
    // arrives on every render — and the feedback poll re-renders this page every
    // few seconds. Keying the seed on that reference made this effect re-run on
    // each poll and replace the customer's in-progress text with a reset, so
    // they could not type for more than a few seconds. Comparing the target's
    // id keeps switching notes working while leaving typing alone.
    useEffect(() => {
        const id = target?.id ?? null;

        if (seededForId.current === id) {
            return;
        }

        seededForId.current = id;
        setBody(target?.existingNote?.body ?? '');
    }, [target]);

    if (!target) return null;

    const hasExistingNote = Boolean(target.existingNote);
    const isResolved = Boolean(
        target.existingNote?.is_resolved ||
        target.existingNote?.resolved_at ||
        target.existingNote?.manager_response
    );
    const managerResponse = target.existingNote?.manager_response;

    const handleSave = () => {
        const trimmed = body.trim();
        if (!trimmed) return;
        onSave(trimmed);
    };

    return (
        <Dialog open={open} onOpenChange={(val) => !val && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="space-y-1.5">
                    <DialogTitle className="flex items-center gap-2 text-base font-bold">
                        {hasExistingNote ? 'Edit Feedback Note' : 'Request Change / Leave Note'}
                    </DialogTitle>
                    <DialogDescription className="text-xs text-muted-foreground">
                        {target.subtitle ??
                            'Describe what you see — your note goes straight to the project manager.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-1">
                    {/* Visual target preview */}
                    <div className="flex items-center gap-3 rounded-lg border border-border/70 bg-muted/40 p-2.5">
                        {target.photoUrl ? (
                            <img
                                src={target.photoUrl}
                                alt={target.title}
                                className="size-14 rounded-md object-cover border border-border/80 shrink-0"
                            />
                        ) : (
                            <div className="size-10 rounded-md bg-primary/10 flex items-center justify-center shrink-0">
                                <MessageSquare className="size-5 text-primary" />
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <h4 className="text-xs font-bold text-foreground break-words [overflow-wrap:anywhere] line-clamp-2">
                                {target.title}
                            </h4>
                            <p className="text-[11px] text-muted-foreground capitalize">
                                Target: {target.type}
                            </p>
                        </div>
                    </div>

                    {/* Manager response if resolved */}
                    {managerResponse && (
                        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 text-xs space-y-1">
                            <p className="font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                                <CheckCircle2 className="size-3.5" /> Project Manager Response
                            </p>
                            <p className="text-foreground/90 break-words [overflow-wrap:anywhere] whitespace-pre-wrap">
                                {managerResponse}
                            </p>
                        </div>
                    )}

                    {/* Feedback textarea */}
                    <div className="space-y-1.5">
                        <label
                            htmlFor="customer-note-body"
                            className="text-xs font-semibold text-foreground"
                        >
                            Your Notes & Instructions:
                        </label>
                        <Textarea
                            id="customer-note-body"
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            placeholder="e.g., Mount angle is blurry; please retake closer to grounding lug or clarify mounting height."
                            rows={4}
                            disabled={submitting || isResolved}
                            className="text-xs resize-none"
                        />
                    </div>
                </div>

                <DialogFooter className="flex items-center justify-between gap-2 sm:justify-between">
                    <div>
                        {hasExistingNote && onDelete && !isResolved && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={submitting}
                                onClick={() => target.existingNote && onDelete(target.existingNote)}
                                className="h-8 text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/30 gap-1.5"
                            >
                                <Trash2 className="size-3.5" /> Remove Note
                            </Button>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClose}
                            disabled={submitting}
                            className="h-8 text-xs"
                        >
                            Cancel
                        </Button>
                        {!isResolved && (
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleSave}
                                disabled={submitting || !body.trim()}
                                className="h-8 text-xs font-bold gap-1.5 bg-amber-600 hover:bg-amber-700 text-white"
                            >
                                {getSaveButtonLabel(submitting, hasExistingNote)}
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default CustomerPhotoNoteDialog;
