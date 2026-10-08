import { Button } from '@/components/ui/button';
import { CanvasEditorDialog } from '@/components/shared/canvas-editor-dialog';
import { useCanvasExport } from '@/components/shared/use-canvas-export';
import { useSowEditorStore } from '@/features/sow/stores/sow-editor.store';
import { serializeLayer } from '@/features/sow/utils/annotation-serializer';
import { CopyPlus, Loader2, Save } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { mediaLibraryApi } from '../api/media-library.api';
import type { MediaAsset } from '../types/media-library.types';

export interface MediaCanvasEditorDialogProps {
    asset: MediaAsset | null;
    isOpen: boolean;
    onClose: () => void;
    onSaved?: (savedAsset: MediaAsset) => void;
    /**
     * Hide Overwrite for hosts that hold their own copy of the file (a change
     * order does), where overwriting the library asset would not reach them.
     */
    allowOverwrite?: boolean;
}

export function MediaCanvasEditorDialog({
    asset,
    isOpen,
    onClose,
    onSaved,
    allowOverwrite = true,
}: Readonly<MediaCanvasEditorDialogProps>) {
    const layer = useSowEditorStore((s) => s.layer);
    const { setStage, exportBlob, isExporting } = useCanvasExport();
    const [savingMode, setSavingMode] = React.useState<'copy' | 'overwrite' | null>(null);
    const isBusy = savingMode !== null || isExporting;

    if (!asset) {
        return null;
    }

    // Always prefer the unannotated clean source image as the canvas background
    const fileUrl =
        asset.source_image_url ||
        asset.url ||
        (asset.id ? route('media-library.file', { asset: asset.id }) : '');

    const handleSave = async (saveMode: 'copy' | 'overwrite' = 'copy') => {
        setSavingMode(saveMode);

        try {
            const blob = await exportBlob({ pixelRatio: 2 });
            const savedAsset = await mediaLibraryApi.annotateMediaAsset(
                asset.id,
                blob,
                saveMode,
                serializeLayer(layer)
            );

            toast.success(
                saveMode === 'copy'
                    ? 'Annotated copy saved to Media Library.'
                    : 'Original asset updated and overwritten successfully.'
            );

            onSaved?.(savedAsset);
            onClose();
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to save annotated image.';
            toast.error(message);
        } finally {
            setSavingMode(null);
        }
    };

    return (
        <CanvasEditorDialog
            open={isOpen}
            onClose={onClose}
            title={`Annotate: ${asset.original_filename}`}
            badge="Canvas Editor"
            imageUrl={fileUrl}
            initialLayer={asset.annotation_layer}
            resetKey={asset.id}
            busy={isBusy}
            onStageReady={setStage}
            headerActions={
                <>
                    {/* Overwrite replaces the original — styled quiet so the recommended action leads. */}
                    {allowOverwrite && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={isBusy}
                            onClick={() => handleSave('overwrite')}
                            className="h-8 gap-1.5 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                            title="Overwrite and update the original asset directly. Prefer Save as New Copy to keep the original."
                        >
                            {savingMode === 'overwrite' ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Save className="size-3.5" />
                            )}
                            <span>{savingMode === 'overwrite' ? 'Overwriting…' : 'Overwrite'}</span>
                        </Button>
                    )}

                    {/* Save as New Copy — the primary action. */}
                    <Button
                        type="button"
                        size="sm"
                        disabled={isBusy}
                        onClick={() => handleSave('copy')}
                        className="h-8 gap-1.5 px-3 text-xs font-semibold shadow-xs cursor-pointer"
                        title="Create a new annotated asset while preserving the original (recommended)."
                    >
                        {savingMode === 'copy' ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <CopyPlus className="size-3.5" />
                        )}
                        <span>{savingMode === 'copy' ? 'Saving…' : 'Save as New Copy'}</span>
                    </Button>
                </>
            }
        />
    );
}
