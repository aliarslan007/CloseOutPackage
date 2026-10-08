import { X } from 'lucide-react';
import { ChangeOrderPhotoOptions } from './ChangeOrderPhotoOptions';

export interface FeedbackAttachmentPreviewProps {
    fileName: string;
    sizeLabel: string;
    previewUrl: string | null;
    isLocked: boolean;
    /** Change orders let staff replace or append the photo - show those toggles. */
    showChangeOrderOptions: boolean;
    isPhotoTarget: boolean;
    replaceInChangeOrder: boolean;
    addToChangeOrderPhotos: boolean;
    onToggleReplace: (value: boolean) => void;
    onToggleAdd: (value: boolean) => void;
    onRemove: () => void;
}

/**
 * The staged-photo card shown above the reply bar once a photo is attached:
 * thumbnail, name, size, the change-order options where they apply, and a
 * remove button. Purely presentational - the composer owns the file state.
 */
export function FeedbackAttachmentPreview({
    fileName,
    sizeLabel,
    previewUrl,
    isLocked,
    showChangeOrderOptions,
    isPhotoTarget,
    replaceInChangeOrder,
    addToChangeOrderPhotos,
    onToggleReplace,
    onToggleAdd,
    onRemove,
}: Readonly<FeedbackAttachmentPreviewProps>) {
    return (
        <div className="space-y-2.5 rounded-lg border border-border bg-card p-3 shadow-2xs">
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    {previewUrl && (
                        <img
                            src={previewUrl}
                            alt="Preview"
                            className="size-10 rounded border border-border object-cover"
                        />
                    )}
                    <div className="min-w-0">
                        <p className="max-w-[16rem] truncate text-xs font-semibold text-foreground">
                            {fileName}
                        </p>
                        <p className="text-[11px] text-muted-foreground">
                            {sizeLabel} · Ready to send
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onClick={onRemove}
                    disabled={isLocked}
                    className="flex size-7 items-center justify-center rounded border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    title="Remove attached photo"
                >
                    <X className="size-3.5" />
                </button>
            </div>

            {showChangeOrderOptions && (
                <ChangeOrderPhotoOptions
                    replaceInChangeOrder={replaceInChangeOrder}
                    addToChangeOrderPhotos={addToChangeOrderPhotos}
                    isLocked={isLocked}
                    isPhotoTarget={isPhotoTarget}
                    onToggleReplace={onToggleReplace}
                    onToggleAdd={onToggleAdd}
                />
            )}
        </div>
    );
}
