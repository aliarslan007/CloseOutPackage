import type { SowEditorApi } from '../../hooks/use-sow-editor';
import { Redo2, Undo2 } from 'lucide-react';

const HISTORY_BUTTON_CLASS =
    'flex size-7 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none disabled:opacity-40';

/**
 * §13.7 — Compact segmented undo/redo control. Lives in the photo header row
 * (centered, beside the photo name and Save) instead of the left toolbar.
 */
export function SowUndoRedoControls({
    editor,
    visible,
    hasImage,
}: Readonly<{ editor: SowEditorApi; visible: boolean; hasImage: boolean }>) {
    if (!visible || !hasImage) return null;

    return (
        <div className="inline-flex items-center rounded-full border border-border bg-card p-0.5 shadow-sm">
            <button
                type="button"
                title="Undo (Ctrl+Z)"
                aria-label="Undo"
                disabled={editor.past.length === 0}
                onClick={editor.undo}
                className={HISTORY_BUTTON_CLASS}
            >
                <Undo2 className="size-3.5" />
            </button>
            <span aria-hidden className="mx-0.5 h-4 w-px bg-border" />
            <button
                type="button"
                title="Redo (Ctrl+Y)"
                aria-label="Redo"
                disabled={editor.future.length === 0}
                onClick={editor.redo}
                className={HISTORY_BUTTON_CLASS}
            >
                <Redo2 className="size-3.5" />
            </button>
        </div>
    );
}
