import { Button } from '@/components/ui/button';
import { CanvasEditorDialog } from '@/components/shared/canvas-editor-dialog';
import { useCanvasExport } from '@/components/shared/use-canvas-export';
import { Textarea } from '@/components/ui/textarea';
import { useSowEditorStore } from '@/features/sow/stores/sow-editor.store';
import { serializeLayer, type ApiAnnotationLayer } from '@/features/sow/utils/annotation-serializer';
import { Check, Loader2, MessageSquare, Trash2 } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { FeedbackActionSelector, type FeedbackActionType } from './FeedbackActionSelector';

export type { FeedbackActionType };

export interface FeedbackCanvasDialogProps {
    imageUrl: string;
    imageTitle?: string;
    existingNoteText?: string;
    existingAnnotationLayer?: ApiAnnotationLayer | Record<string, unknown> | null;
    isOpen: boolean;
    isSaving?: boolean;
    onClose: () => void;
    onDelete?: () => Promise<void> | void;
    onSave: (data: {
        blob: Blob;
        layerJson: string;
        body: string;
        requestedAction?: FeedbackActionType;
    }) => Promise<void> | void;
}

export function FeedbackCanvasDialog({
    imageUrl,
    imageTitle = 'Photo Review',
    existingNoteText = '',
    existingAnnotationLayer = null,
    isOpen,
    isSaving = false,
    onClose,
    onDelete,
    onSave,
}: Readonly<FeedbackCanvasDialogProps>) {
    const layer = useSowEditorStore((s) => s.layer);
    const { setStage, exportBlob, isExporting } = useCanvasExport();
    const [body, setBody] = React.useState(existingNoteText);
    const [requestedAction, setRequestedAction] =
        React.useState<FeedbackActionType>('replace_photo');

    React.useEffect(() => {
        if (isOpen) setBody(existingNoteText);
    }, [isOpen, existingNoteText]);

    if (!imageUrl) {
        return null;
    }

    const isBusy = isSaving || isExporting;

    const handleSave = async () => {
        const trimmedBody = body.trim();
        if (!trimmedBody) {
            toast.error('Please enter a note or question explaining your query.');
            return;
        }

        try {
            const blob = await exportBlob({ pixelRatio: 2 });
            const layerJson = JSON.stringify(serializeLayer(layer));
            await onSave({ blob, layerJson, body: trimmedBody, requestedAction });
            onClose();
        } catch (err) {
            const message =
                err instanceof Error ? err.message : 'Failed to export annotated image.';
            toast.error(message);
        }
    };

    return (
        <CanvasEditorDialog
            open={isOpen}
            onClose={onClose}
            title={`Mark & Annotate: ${imageTitle}`}
            badge="Review Tool"
            imageUrl={imageUrl}
            initialLayer={existingAnnotationLayer}
            resetKey={imageUrl}
            busy={isBusy}
            onStageReady={setStage}
            headerActions={
                <>
                    {Boolean((existingNoteText || existingAnnotationLayer) && onDelete) && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={isBusy}
                            onClick={async () => {
                                await onDelete?.();
                                onClose();
                            }}
                            className="h-8 gap-1.5 px-2.5 text-xs text-destructive hover:bg-destructive/10 border-destructive/30 cursor-pointer"
                            title="Delete this note"
                        >
                            <Trash2 className="size-3.5" />
                            <span>Delete Note</span>
                        </Button>
                    )}

                    <Button
                        type="button"
                        size="sm"
                        disabled={isBusy}
                        onClick={handleSave}
                        className="h-8 gap-1.5 px-3.5 text-xs font-semibold shadow-xs cursor-pointer"
                    >
                        {isBusy ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <Check className="size-3.5" />
                        )}
                        <span>{isBusy ? 'Saving…' : 'Save Annotation & Note'}</span>
                    </Button>
                </>
            }
            footer={
                <div className="shrink-0 border-t border-border bg-background/95 backdrop-blur-sm px-4 py-2.5 space-y-2">
                    <FeedbackActionSelector
                        value={requestedAction}
                        onChange={setRequestedAction}
                        disabled={isBusy}
                    />

                    <div className="relative">
                        <MessageSquare className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
                        <Textarea
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                            placeholder="Describe your question or change request for the manager (e.g., 'Please check the grounding wire routing highlighted in red.')..."
                            className="min-h-[64px] h-16 max-h-[140px] resize-y bg-muted/30 pl-9 pr-3 py-2.5 text-sm leading-relaxed focus-visible:ring-1"
                            disabled={isBusy}
                            aria-label="Annotation note"
                        />
                    </div>
                </div>
            }
        />
    );
}
