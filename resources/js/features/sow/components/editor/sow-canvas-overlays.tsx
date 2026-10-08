import { Button } from '@/components/ui/button';
import { Check, Maximize, Minus, Plus, Slash, Trash2 } from 'lucide-react';
import type { EditorTool } from '../../types/annotation.types';
import { isDashedPreview, type Preview } from './sow-drawing-utils';

export function SowCanvasZoomControls({
    onZoomIn,
    onZoomOut,
    onFit,
}: Readonly<{
    onZoomIn: () => void;
    onZoomOut: () => void;
    onFit: () => void;
}>) {
    return (
        <div className="absolute bottom-3 right-3 flex items-center gap-1 rounded-lg border border-border bg-background/95 p-1 shadow-sm">
            <button
                type="button"
                title="Zoom out"
                onClick={onZoomOut}
                className="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer"
            >
                <Minus className="size-3.5" />
            </button>
            <button
                type="button"
                title="Zoom in"
                onClick={onZoomIn}
                className="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer"
            >
                <Plus className="size-3.5" />
            </button>
            <div className="h-4 w-px bg-border" />
            <button
                type="button"
                title="Fit to screen"
                onClick={onFit}
                className="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer"
            >
                <Maximize className="size-3.5" />
            </button>
        </div>
    );
}

export function SowCanvasToolHints({
    activeTool,
    preview,
    onCommitDashed,
}: Readonly<{
    activeTool: EditorTool;
    preview: Preview | null;
    onCommitDashed: () => void;
}>) {
    if (activeTool === 'eraser') {
        return (
            <div className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-md bg-background/95 px-2 py-1 text-xs text-muted-foreground shadow-sm">
                <Trash2 className="size-3.5" />
                Click a shape to erase
            </div>
        );
    }

    if (activeTool === 'dashed_line') {
        const hasPoints = isDashedPreview(preview) && preview.points.length >= 4;
        return (
            <div className="absolute left-3 top-3 z-10 flex items-center gap-2 rounded-md border border-sky-400/50 bg-slate-900/95 px-3 py-1.5 text-xs font-medium text-white shadow-md backdrop-blur-xs">
                <Slash className="size-3.5 text-sky-400" />
                <span>Click to draw points · Double-click or click Done to finish</span>
                {hasPoints && (
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={onCommitDashed}
                        className="ml-2 h-5 px-2 text-[10px] font-bold text-white bg-emerald-600 hover:bg-emerald-700 cursor-pointer"
                    >
                        Finish Path
                    </Button>
                )}
            </div>
        );
    }

    return null;
}

export function SowDashedPathDoneButton({
    activeTool,
    preview,
    onCommit,
}: Readonly<{
    activeTool: EditorTool;
    preview: Preview | null;
    onCommit: () => void;
}>) {
    if (activeTool !== 'dashed_line' || !isDashedPreview(preview) || preview.points.length < 4) {
        return null;
    }

    return (
        <div className="absolute bottom-3 left-3 z-10">
            <Button
                size="sm"
                onClick={onCommit}
                className="gap-1.5 shadow-sm bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer"
            >
                <Check className="size-3.5" />
                Done — finish path
            </Button>
        </div>
    );
}
