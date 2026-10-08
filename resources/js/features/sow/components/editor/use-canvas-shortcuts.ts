import * as React from 'react';
import type { SowEditorStore } from '../../stores/sow-editor.store';
import type { EditorTool } from '../../types/annotation.types';
import type { Preview } from './sow-drawing-utils';

export interface UseCanvasShortcutsOptions {
    readOnly: boolean;
    preview: Preview | null;
    setPreview: (p: Preview | null) => void;
    editor: SowEditorStore;
    commitDashed: () => void;
}

const TOOL_KEYS: Record<string, EditorTool> = {
    v: 'select',
    r: 'box',
    a: 'arrow',
    l: 'dashed_line',
    d: 'dot',
    e: 'eraser',
    h: 'pan',
};

export function useCanvasShortcuts({
    readOnly,
    preview,
    setPreview,
    editor,
    commitDashed,
}: Readonly<UseCanvasShortcutsOptions>) {
    React.useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if (
                readOnly ||
                e.target instanceof HTMLInputElement ||
                e.target instanceof HTMLTextAreaElement
            ) {
                return;
            }

            if (e.key === 'Escape') {
                if (preview) setPreview(null);
                else editor.setSelectedId(null);
                return;
            }

            if (e.key === 'Enter' && editor.activeTool === 'dashed_line') {
                commitDashed();
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                e.preventDefault();
                if (e.shiftKey) editor.redo();
                else editor.undo();
                return;
            }

            const mapped = TOOL_KEYS[e.key.toLowerCase()];
            if (mapped) editor.setTool(mapped);
        };

        globalThis.addEventListener('keydown', onKeyDown);
        return () => globalThis.removeEventListener('keydown', onKeyDown);
    }, [readOnly, preview, setPreview, editor, commitDashed]);
}
