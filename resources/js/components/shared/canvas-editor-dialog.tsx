import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SowCanvasEditor } from '@/features/sow/components/editor/sow-canvas-editor';
import { useSowEditorStore } from '@/features/sow/stores/sow-editor.store';
import {
    parseLayer,
    type ApiAnnotationLayer,
} from '@/features/sow/utils/annotation-serializer';
import { X } from 'lucide-react';
import type Konva from 'konva';
import * as React from 'react';

export interface CanvasEditorDialogProps {
    open: boolean;
    onClose: () => void;
    /** Dialog heading, e.g. "Mark & Annotate: site-photo.jpg". */
    title: string;
    /** Small pill shown next to the title. */
    badge?: string;
    imageUrl: string;
    /** Server-stored layer to hydrate the editor with, if any. */
    initialLayer?: ApiAnnotationLayer | Record<string, unknown> | null;
    /** Remounts the canvas and re-hydrates the layer whenever this changes. */
    resetKey?: string | number;
    /** True while a save runs — locks every action and the close button. */
    busy?: boolean;
    /** Buttons rendered beside the title, before the close button. */
    headerActions?: React.ReactNode;
    /** UI pinned under the canvas (note bar, prompts, …). */
    footer?: React.ReactNode;
    onStageReady?: (stage: Konva.Stage | null) => void;
}

/**
 * The full-screen canvas dialog every annotation flow shares: header with
 * host-supplied actions, the Konva editor, and an optional footer.
 *
 * It owns only the editor plumbing (hydrating the store from a stored layer and
 * handing the stage back up); saving stays with the host, which knows whether
 * the result is a feedback note, a new media asset, or an edited upload.
 */
export function CanvasEditorDialog({
    open,
    onClose,
    title,
    badge,
    imageUrl,
    initialLayer = null,
    resetKey,
    busy = false,
    headerActions,
    footer,
    onStageReady,
}: Readonly<CanvasEditorDialogProps>) {
    const editor = useSowEditorStore();
    const setLayer = useSowEditorStore((s) => s.setLayer);
    const setSelectedId = useSowEditorStore((s) => s.setSelectedId);

    // Read through a ref: re-hydrating must happen when a *different* image
    // opens, never just because the parent re-rendered (that would wipe edits).
    const initialLayerRef = React.useRef(initialLayer);
    initialLayerRef.current = initialLayer;

    React.useEffect(() => {
        if (!open) return;
        const stored = initialLayerRef.current;
        setLayer(stored ? parseLayer(stored as ApiAnnotationLayer) : { baseWidth: 1200, nodes: [] });
        setSelectedId(null);
    }, [open, resetKey, setLayer, setSelectedId]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && !busy && onClose()}>
            <DialogContent className="max-w-[96vw] w-[1400px] h-[92vh] p-0 gap-0 flex flex-col overflow-hidden bg-background border-border [&>button:last-child]:hidden">
                <DialogHeader className="h-14 px-4 border-b border-border flex flex-row items-center justify-between shrink-0">
                    <div className="flex items-center gap-2.5 truncate max-w-[45%]">
                        <DialogTitle className="text-sm font-semibold truncate">
                            {title}
                        </DialogTitle>
                        {badge && (
                            <span className="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium whitespace-nowrap">
                                {badge}
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {headerActions}

                        {headerActions && <div className="h-4 w-px bg-border mx-1" />}

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            disabled={busy}
                            onClick={onClose}
                        >
                            <X className="size-4" />
                            <span className="sr-only">Close</span>
                        </Button>
                    </div>
                </DialogHeader>

                <div className="flex-1 min-h-0 flex flex-col relative bg-muted/20">
                    <div className="flex-1 min-h-0 relative p-3">
                        <SowCanvasEditor
                            key={resetKey}
                            imageId={resetKey ?? title}
                            imageUrl={imageUrl}
                            editor={editor}
                            fill
                            onStageReady={onStageReady}
                        />
                    </div>

                    {footer}
                </div>
            </DialogContent>
        </Dialog>
    );
}
