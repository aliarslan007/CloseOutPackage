import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Check, FileText, Loader2, Pencil, Plus, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

export const MAX_PHOTO_NOTE_LENGTH = 500;

export interface PhotoNoteSectionProps {
    packageId: number | string;
    photoId: number;
    initialNotes?: string | null;
    isLocked: boolean;
    onNoteUpdated?: (photoId: number, notes: string | null) => void;
    className?: string;
}

export function PhotoNoteSection({
    packageId,
    photoId,
    initialNotes = null,
    isLocked,
    onNoteUpdated,
    className,
}: Readonly<PhotoNoteSectionProps>) {
    const [notes, setNotes] = useState<string | null>(initialNotes ?? null);
    const [isEditing, setIsEditing] = useState(false);
    const [draftText, setDraftText] = useState(initialNotes ?? '');
    const [isSaving, setIsSaving] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        setNotes(initialNotes ?? null);
        if (!isEditing) {
            setDraftText(initialNotes ?? '');
        }
    }, [initialNotes, isEditing]);

    useEffect(() => {
        if (isEditing && textareaRef.current) {
            textareaRef.current.focus();
            const len = textareaRef.current.value.length;
            textareaRef.current.setSelectionRange(len, len);
        }
    }, [isEditing]);

    const handleSave = (targetText: string) => {
        const trimmed = targetText.trim();
        const payloadNotes = trimmed.length > 0 ? trimmed : null;

        setIsSaving(true);
        router.patch(
            route('photos.notes.update', { package: packageId, photo: photoId }),
            { notes: payloadNotes },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setNotes(payloadNotes);
                    setDraftText(payloadNotes ?? '');
                    setIsEditing(false);
                    onNoteUpdated?.(photoId, payloadNotes);
                    toast.success(payloadNotes ? 'Photo note saved.' : 'Photo note removed.');
                },
                onError: (errors) => {
                    const message = typeof errors?.notes === 'string' ? errors.notes : 'Failed to save note.';
                    toast.error(message);
                },
                onFinish: () => {
                    setIsSaving(false);
                },
            }
        );
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            handleSave(draftText);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setDraftText(notes ?? '');
            setIsEditing(false);
        }
    };

    if (isEditing && !isLocked) {
        const remainingChars = MAX_PHOTO_NOTE_LENGTH - draftText.length;
        const isNearLimit = remainingChars <= 50;

        return (
            <div className={cn('rounded-lg border bg-card p-2 space-y-1.5 shadow-sm text-left', className)}>
                <div className="flex items-center justify-between">
                    <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                        <FileText className="size-3 text-primary" /> Photo Note
                    </span>
                    <span
                        className={cn(
                            'text-[10px] tabular-nums',
                            isNearLimit ? 'text-amber-500 font-semibold' : 'text-muted-foreground'
                        )}
                    >
                        {draftText.length}/{MAX_PHOTO_NOTE_LENGTH}
                    </span>
                </div>

                <Textarea
                    ref={textareaRef}
                    value={draftText}
                    onChange={(e) => setDraftText(e.target.value.slice(0, MAX_PHOTO_NOTE_LENGTH))}
                    onKeyDown={handleKeyDown}
                    placeholder="Describe what this photo shows (e.g. equipment label, elevation)..."
                    rows={2}
                    className="text-xs resize-none min-h-[50px] p-1.5 focus-visible:ring-1"
                    disabled={isSaving}
                />

                <div className="flex items-center justify-between gap-1 pt-0.5">
                    {notes ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => handleSave('')}
                            disabled={isSaving}
                            className="h-6 px-1.5 text-[10px] text-destructive hover:text-destructive hover:bg-destructive/10"
                            title="Remove this note"
                        >
                            <Trash2 className="size-3 mr-1" />
                            Remove
                        </Button>
                    ) : (
                        <div />
                    )}

                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setDraftText(notes ?? '');
                                setIsEditing(false);
                            }}
                            disabled={isSaving}
                            className="h-6 px-2 text-[10px]"
                        >
                            <X className="size-3 mr-0.5" />
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => handleSave(draftText)}
                            disabled={isSaving}
                            className="h-6 px-2 text-[10px]"
                        >
                            {isSaving ? (
                                <Loader2 className="size-3 animate-spin mr-1" />
                            ) : (
                                <Check className="size-3 mr-1" />
                            )}
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    if (notes) {
        return (
            <div
                className={cn(
                    'group/note rounded-md bg-muted/40 border border-border/70 p-2 text-left transition-colors hover:bg-muted/60',
                    className
                )}
            >
                <div className="flex items-center justify-between gap-1 mb-0.5">
                    <span className="inline-flex items-center gap-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        <FileText className="size-3 text-primary shrink-0" /> Photo Note
                    </span>
                    {!isLocked && (
                        <button
                            type="button"
                            onClick={() => {
                                setDraftText(notes);
                                setIsEditing(true);
                            }}
                            className="text-muted-foreground hover:text-foreground opacity-60 group-hover/note:opacity-100 transition-opacity p-0.5 rounded hover:bg-background/80"
                            title="Edit note"
                            aria-label="Edit photo note"
                        >
                            <Pencil className="size-3" />
                        </button>
                    )}
                </div>
                <p
                    className="text-[11px] text-foreground/90 leading-relaxed whitespace-pre-wrap break-words line-clamp-3"
                    title={notes}
                >
                    {notes}
                </p>
            </div>
        );
    }

    if (isLocked) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => {
                setDraftText('');
                setIsEditing(true);
            }}
            className={cn(
                'inline-flex items-center justify-center gap-1 w-full py-1 px-2 rounded-md border border-dashed border-border/70 bg-background/50 hover:bg-muted/50 text-[11px] font-medium text-muted-foreground hover:text-foreground transition-all duration-150 text-left',
                className
            )}
            title="Add a note to this photo"
        >
            <Plus className="size-3 shrink-0" />
            <span>Add Note</span>
        </button>
    );
}

export default PhotoNoteSection;
