import { Textarea } from '@/components/ui/textarea';
import { PackageSiteNotesImages } from '@/components/packages/package-site-notes-images';
import {
    PACKAGE_SITE_NOTES_MAX_LENGTH,
    type PackageSiteNoteImage,
} from '@/hooks/use-checklist-auto-save';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Loader2, StickyNote } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface PackageSiteNotesProps {
    packageId: number | string;
    notes: string;
    images: PackageSiteNoteImage[];
    canEdit?: boolean;
    /** Project scope for the media library picker. */
    projectId?: number | null;
    className?: string;
}

/**
 * The single site-notes section at the bottom of the package checklist.
 * Text auto-saves (debounced); images live in PackageSiteNotesImages.
 * Notes alone are enough for this section to render — images are optional.
 */
export function PackageSiteNotes({
    packageId,
    notes: initialNotes,
    images,
    canEdit = false,
    projectId,
    className,
}: Readonly<PackageSiteNotesProps>) {
    const [notes, setNotes] = React.useState(initialNotes ?? '');
    const [saving, setSaving] = React.useState(false);
    const debounceTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const notesRef = React.useRef(notes);
    notesRef.current = notes;

    React.useEffect(() => {
        setNotes(initialNotes ?? '');
    }, [initialNotes, packageId]);

    React.useEffect(() => {
        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    const saveNotes = React.useCallback(
        (text: string) => {
            setSaving(true);
            router.put(
                route('packages.notes.update', { package: packageId }),
                { notes: text.trim() === '' ? null : text },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['package'],
                    onError: () => toast.error('Could not save site notes.'),
                    onFinish: () => setSaving(false),
                }
            );
        },
        [packageId]
    );

    const handleNotesChange = (text: string) => {
        setNotes(text.slice(0, PACKAGE_SITE_NOTES_MAX_LENGTH));
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }
        debounceTimerRef.current = setTimeout(() => saveNotes(text), 800);
    };

    const handleBlur = () => {
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
            saveNotes(notesRef.current);
        }
    };

    const hasNotes = notes.trim() !== '';
    const hasImages = images.length > 0;

    if (!canEdit && !hasNotes && !hasImages) {
        return null;
    }

    return (
        <div className={cn('rounded-xl border bg-card overflow-hidden', className)}>
            <div className="flex items-center gap-2 border-b border-border/70 bg-muted/30 px-4 py-3">
                <StickyNote className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <h3 className="text-sm font-bold text-foreground">Additional Site Notes</h3>
                {saving && <Loader2 className="size-3.5 animate-spin text-muted-foreground" />}
                {!canEdit && (
                    <span className="ml-auto text-[11px] font-medium text-muted-foreground">
                        Read only
                    </span>
                )}
            </div>

            <div className="space-y-3 p-4">
                {canEdit ? (
                    <>
                        {/* 20k characters: tall by default so reading never happens through a thin scroll strip. */}
                        <Textarea
                            value={notes}
                            onChange={(event) => handleNotesChange(event.target.value)}
                            onBlur={handleBlur}
                            maxLength={PACKAGE_SITE_NOTES_MAX_LENGTH}
                            placeholder="Add any extra site notes for this package (optional)…"
                            className="min-h-[320px] text-sm leading-relaxed"
                            aria-label="Additional site notes"
                        />
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] tabular-nums text-muted-foreground">
                                {notes.length} / {PACKAGE_SITE_NOTES_MAX_LENGTH}
                            </span>
                            <span className="text-[10px] text-muted-foreground">
                                Saves automatically
                            </span>
                        </div>
                    </>
                ) : (
                    hasNotes && (
                        <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-foreground/90">
                            {notes}
                        </p>
                    )
                )}

                <PackageSiteNotesImages
                    packageId={packageId}
                    images={images}
                    canEdit={canEdit}
                    projectId={projectId}
                />
            </div>
        </div>
    );
}
