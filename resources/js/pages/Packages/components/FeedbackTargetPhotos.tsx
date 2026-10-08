import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { StatusBadge } from '@/components/shared/status-badge';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { preparePhotoForUpload, rejectedPhotoMessage } from '@/services/compression.service';
import { router } from '@inertiajs/react';
import { FileText, ImageOff, Loader2, MessageSquarePlus, Pencil, Plus, RotateCcw, ZoomIn } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface FeedbackTargetPhoto {
    id: number;
    url?: string | null;
    thumb_url?: string | null;
    original_url?: string | null;
    notes?: string | null;
    status?: string | null;
    is_approved?: boolean;
    can_replace?: boolean;
    can_update_notes?: boolean;
    position?: number | null;
}

interface FeedbackTargetPhotosProps {
    photos: FeedbackTargetPhoto[];
    packageId: number | string;
    /** Optional heading shown above the grid. */
    label?: string;
    categorySelectionId?: number | null;
    stageType?: string | null;
    canAddPhoto?: boolean;
}

function PhotoNoteRow({
    photo,
    canEdit,
    onEdit,
}: {
    photo: FeedbackTargetPhoto;
    canEdit: boolean;
    onEdit: (photo: FeedbackTargetPhoto) => void;
}) {
    if (photo.notes) {
        return (
            <div className="flex items-start justify-between gap-1 border-t border-border/50 bg-muted/20 px-2 py-1">
                <p
                    className="line-clamp-2 flex-1 break-words text-[10px] text-muted-foreground [overflow-wrap:anywhere]"
                    title={photo.notes}
                >
                    <span className="font-semibold text-foreground mr-1">Note:</span>
                    {photo.notes}
                </p>
                {canEdit && (
                    <button
                        type="button"
                        onClick={() => onEdit(photo)}
                        className="shrink-0 rounded p-0.5 text-muted-foreground transition-colors hover:text-foreground"
                        title="Edit photo note"
                    >
                        <Pencil className="size-2.5" />
                    </button>
                )}
            </div>
        );
    }

    if (canEdit) {
        return (
            <div className="border-t border-border/50 px-2 py-1">
                <button
                    type="button"
                    onClick={() => onEdit(photo)}
                    className="inline-flex items-center gap-1 text-[10px] text-muted-foreground transition-colors hover:text-primary"
                    title="Add a note to this photo (visible to customer upon revision)"
                >
                    <MessageSquarePlus className="size-2.5" /> Add Photo Note
                </button>
            </div>
        );
    }

    return null;
}

/**
 * Shows the photo(s) a customer change request is about, each with a
 * capture/upload replace action so the manager can swap in the corrected image
 * without leaving the feedback screen.
 */
export function FeedbackTargetPhotos({
    photos,
    packageId,
    label,
    categorySelectionId,
    stageType,
    canAddPhoto = false,
}: Readonly<FeedbackTargetPhotosProps>) {
    const [replacingId, setReplacingId] = React.useState<number | null>(null);
    const [uploadingSectionPhoto, setUploadingSectionPhoto] = React.useState(false);
    const [editingPhoto, setEditingPhoto] = React.useState<FeedbackTargetPhoto | null>(null);
    const [noteText, setNoteText] = React.useState('');
    const [savingNote, setSavingNote] = React.useState(false);
    const [lightboxIndex, setLightboxIndex] = React.useState<number | null>(null);

    const handleReplace = async (photo: FeedbackTargetPhoto, rawFile: File) => {
        const toastId = `replace-${photo.id}`;
        toast.loading('Compressing photo…', { id: toastId });

        const prepared = await preparePhotoForUpload(rawFile, { maxMb: 20 });

        if (!prepared.ok) {
            toast.error(rejectedPhotoMessage(rawFile, prepared.reason, { maxMb: 20 }), { id: toastId });
            return;
        }

        const formData = new FormData();
        formData.append('photo', prepared.file);
        formData.append('photo_id', String(photo.id));

        setReplacingId(photo.id);
        toast.loading('Replacing photo…', { id: toastId });

        router.post(
            route('photos.replace', { package: packageId, photo: photo.id }),
            formData,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flashError = (page.props as { flash?: { error?: string | null } })?.flash?.error;
                    if (flashError) {
                        toast.error(flashError, { id: toastId });
                    } else {
                        toast.success('Photo replaced and auto-approved.', { id: toastId });
                    }
                },
                onError: (errors: Record<string, string>) => {
                    const firstError =
                        errors?.photo ||
                        errors?.photo_id ||
                        errors?.message ||
                        errors?.error ||
                        (typeof errors === 'object' && errors !== null ? Object.values(errors)[0] : null);

                    toast.error(
                        firstError || 'Replace failed. Please check the image and try again.',
                        { id: toastId }
                    );
                },
                onFinish: () => setReplacingId(null),
            }
        );
    };

    const handleAddPhoto = async (rawFile: File) => {
        if (!categorySelectionId) {
            return;
        }

        const toastId = `add-photo-${categorySelectionId}`;
        toast.loading('Compressing photo…', { id: toastId });

        const prepared = await preparePhotoForUpload(rawFile, { maxMb: 20 });
        if (!prepared.ok) {
            toast.error(rejectedPhotoMessage(rawFile, prepared.reason, { maxMb: 20 }), { id: toastId });
            return;
        }

        const nextPosition = photos.length > 0
            ? Math.max(...photos.map((p) => p.position || 0)) + 1
            : 1;

        const formData = new FormData();
        formData.append('photo', prepared.file);
        formData.append('category_selection_id', String(categorySelectionId));
        formData.append('position', String(nextPosition));

        setUploadingSectionPhoto(true);
        toast.loading('Uploading photo to section…', { id: toastId });

        const stage = stageType || 'construction';

        router.post(
            route('photos.store', { package: packageId, stage }),
            formData,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flashError = (page.props as { flash?: { error?: string | null } })?.flash?.error;
                    if (flashError) {
                        toast.error(flashError, { id: toastId });
                    } else {
                        toast.success('Photo added to section and auto-approved.', { id: toastId });
                    }
                },
                onError: (errors: Record<string, string>) => {
                    const firstError =
                        errors?.photo ||
                        errors?.category_selection_id ||
                        errors?.message ||
                        errors?.error ||
                        (typeof errors === 'object' && errors !== null ? Object.values(errors)[0] : null);

                    toast.error(
                        typeof firstError === 'string' ? firstError : 'Upload failed. Please try again.',
                        { id: toastId }
                    );
                },
                onFinish: () => setUploadingSectionPhoto(false),
            }
        );
    };

    const handleSaveNote = () => {
        if (!editingPhoto) {
            return;
        }

        setSavingNote(true);
        router.patch(
            route('photos.notes.update', { package: packageId, photo: editingPhoto.id }),
            { notes: noteText.trim() || undefined },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(noteText.trim() ? 'Photo note saved.' : 'Photo note removed.');
                    setEditingPhoto(null);
                },
                onError: (errors: Record<string, string>) => {
                    const firstError =
                        errors?.notes ||
                        errors?.message ||
                        (typeof errors === 'object' && errors !== null ? Object.values(errors)[0] : null);

                    toast.error(
                        typeof firstError === 'string' ? firstError : 'Failed to save photo note.'
                    );
                },
                onFinish: () => setSavingNote(false),
            }
        );
    };

    const lightboxPhotos: LightboxPhoto[] = React.useMemo(() => {
        return photos.map((p) => ({
            id: p.id,
            url: p.original_url || p.url || p.thumb_url || '',
            notes: p.notes,
            status: p.status || undefined,
        }));
    }, [photos]);

    const showAddTile = Boolean(canAddPhoto && categorySelectionId);

    if (photos.length === 0 && !showAddTile) {
        return null;
    }

    return (
        <div className="space-y-2">
            {label && (
                <div className="flex items-center justify-between">
                    <p className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {label}
                    </p>
                    {photos.length > 0 && (
                        <span className="text-[10px] text-muted-foreground">
                            Click image to inspect
                        </span>
                    )}
                </div>
            )}
            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                {photos.map((photo, idx) => {
                    const isReplacing = replacingId === photo.id;
                    const src = photo.thumb_url || photo.url || photo.original_url || '';
                    const canEditNote = photo.can_update_notes !== false;

                    return (
                        <div
                            key={photo.id}
                            className="group relative flex flex-col overflow-hidden rounded-lg border border-border/70 bg-card shadow-2xs transition-all hover:border-border"
                        >
                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => setLightboxIndex(idx)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        setLightboxIndex(idx);
                                    }
                                }}
                                className="relative aspect-square w-full cursor-pointer bg-muted/30 overflow-hidden"
                                title="Click to view full photo"
                            >
                                {src ? (
                                    <img
                                        src={src}
                                        alt="Customer change request target"
                                        className="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                        <ImageOff className="size-5" />
                                    </div>
                                )}

                                <div className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/20 flex items-center justify-center">
                                    <ZoomIn className="size-5 text-white opacity-0 transition-opacity group-hover:opacity-100 drop-shadow-md" />
                                </div>

                                {photo.status && (
                                    <span className="absolute left-1.5 top-1.5 z-10 pointer-events-none">
                                        <StatusBadge status={photo.status} />
                                    </span>
                                )}
                            </div>

                            {/* Photo note row */}
                            <PhotoNoteRow
                                photo={photo}
                                canEdit={canEditNote}
                                onEdit={(targetPhoto) => {
                                    setEditingPhoto(targetPhoto);
                                    setNoteText(targetPhoto.notes || '');
                                }}
                            />

                            {photo.can_replace && (
                                <label
                                    className={cn(
                                        'flex cursor-pointer items-center justify-center gap-1.5 border-t border-border/60 bg-card px-2 py-1.5 text-[11px] font-semibold text-primary transition-colors hover:bg-primary/5 active:scale-98',
                                        isReplacing && 'pointer-events-none opacity-60'
                                    )}
                                    title="Capture or upload the corrected photo to replace this one."
                                >
                                    {isReplacing ? (
                                        <Loader2 className="size-3 animate-spin" />
                                    ) : (
                                        <RotateCcw className="size-3" />
                                    )}
                                    {isReplacing ? 'Replacing…' : 'Replace'}
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/heic"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) {
                                                void handleReplace(photo, file);
                                            }
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                        </div>
                    );
                })}

                {/* Add Photo tile for section-level requests */}
                {showAddTile && (
                    <label
                        className={cn(
                            'flex flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-border/80 bg-muted/10 p-3 text-center cursor-pointer transition-all hover:border-primary/60 hover:bg-primary/5 aspect-square active:scale-98',
                            uploadingSectionPhoto && 'pointer-events-none opacity-60'
                        )}
                        title="Add an additional photo to this section as requested by customer."
                    >
                        {uploadingSectionPhoto ? (
                            <Loader2 className="size-5 animate-spin text-primary" />
                        ) : (
                            <div className="flex size-7 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Plus className="size-4" />
                            </div>
                        )}
                        <span className="text-[11px] font-semibold text-foreground">
                            {uploadingSectionPhoto ? 'Uploading…' : 'Add Photo'}
                        </span>
                        <span className="text-[9px] text-muted-foreground">To section</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/heic"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    void handleAddPhoto(file);
                                }
                                e.target.value = '';
                            }}
                        />
                    </label>
                )}
            </div>

            {/* Lightbox for inspecting photos */}
            {lightboxIndex !== null && (
                <PhotoLightbox
                    photos={lightboxPhotos}
                    index={lightboxIndex}
                    onClose={() => setLightboxIndex(null)}
                />
            )}

            {/* Note Editor Modal */}
            <Dialog open={editingPhoto !== null} onOpenChange={(open) => !open && setEditingPhoto(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-sm font-bold">
                            <FileText className="size-4 text-primary" /> Photo Note
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            This note is saved with the photo and will be visible to the customer when revisions are sent.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2 py-2">
                        <Textarea
                            value={noteText}
                            onChange={(e) => setNoteText(e.target.value)}
                            placeholder="Enter note or explanation for this photo (e.g. Corrected bracket alignment, tightened grounding lugs)..."
                            maxLength={500}
                            rows={3}
                            className="text-xs"
                            autoFocus
                        />
                        <p className="text-right text-[10px] text-muted-foreground">
                            {noteText.length}/500
                        </p>
                    </div>
                    <DialogFooter className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs"
                            onClick={() => setEditingPhoto(null)}
                            disabled={savingNote}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            className="h-8 text-xs font-semibold"
                            onClick={handleSaveNote}
                            disabled={savingNote}
                        >
                            {savingNote ? 'Saving…' : 'Save Note'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

export default FeedbackTargetPhotos;
