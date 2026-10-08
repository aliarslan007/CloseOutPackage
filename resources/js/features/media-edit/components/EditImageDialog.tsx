import { CanvasEditorDialog } from '@/components/shared/canvas-editor-dialog';
import { useCanvasExport } from '@/components/shared/use-canvas-export';
import { Button } from '@/components/ui/button';
import { CopyPlus, Loader2, Save } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { StageEditMode, StagedMediaItem } from '../types';
import { annotatedFileName, blobToFile } from '../utils/staged-file';

export interface EditImageDialogProps {
    /** The staged image being annotated, or null when the dialog is closed. */
    source: StagedMediaItem | null;
    isOpen: boolean;
    onClose: () => void;
    onApply: (mode: StageEditMode, editedFile: File) => void;
}

/**
 * Annotate a picked image before it is uploaded. The uploader chooses whether
 * the annotated result replaces the original file or rides along beside it.
 */
export function EditImageDialog({
    source,
    isOpen,
    onClose,
    onApply,
}: Readonly<EditImageDialogProps>) {
    const { setStage, exportBlob, isExporting } = useCanvasExport();
    const [applyingMode, setApplyingMode] = React.useState<StageEditMode | null>(null);

    if (!source) {
        return null;
    }

    const isBusy = applyingMode !== null || isExporting;

    const apply = async (mode: StageEditMode) => {
        setApplyingMode(mode);
        try {
            const blob = await exportBlob({ pixelRatio: 2 });
            onApply(mode, blobToFile(blob, annotatedFileName(source.file.name)));
            onClose();
        } catch (err) {
            toast.error(
                err instanceof Error ? err.message : 'Failed to export the edited image.'
            );
        } finally {
            setApplyingMode(null);
        }
    };

    return (
        <CanvasEditorDialog
            open={isOpen}
            onClose={onClose}
            title={`Mark & Annotate: ${source.file.name}`}
            badge="Before upload"
            imageUrl={source.previewUrl}
            resetKey={source.id}
            busy={isBusy}
            onStageReady={setStage}
            headerActions={
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={isBusy}
                        onClick={() => apply('copy')}
                        className="h-8 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                        title="Upload the annotated image as well as the untouched original."
                    >
                        {applyingMode === 'copy' ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <CopyPlus className="size-3.5" />
                        )}
                        <span>{applyingMode === 'copy' ? 'Saving…' : 'Save as separate copy'}</span>
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        disabled={isBusy}
                        onClick={() => apply('replace')}
                        className="h-8 gap-1.5 px-3 text-xs font-semibold shadow-xs cursor-pointer"
                        title="Upload only the annotated image; the untouched original is discarded."
                    >
                        {applyingMode === 'replace' ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <Save className="size-3.5" />
                        )}
                        <span>{applyingMode === 'replace' ? 'Saving…' : 'Replace original'}</span>
                    </Button>
                </>
            }
        />
    );
}
