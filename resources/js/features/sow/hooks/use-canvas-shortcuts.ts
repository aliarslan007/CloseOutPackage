import * as React from 'react';
import type { EditorTool } from '../types/annotation.types';
import type { SowEditorApi } from './use-sow-editor';

const SHAPE_TOOL_KEYS: Record<string, EditorTool> = {
    v: 'select',
    r: 'box',
    a: 'arrow',
    l: 'dashed_line',
    d: 'dot',
    e: 'eraser',
};

const isTypingTarget = (target: EventTarget | null): boolean => {
    const el = target as HTMLElement | null;
    return (
        el !== null && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable)
    );
};

export interface UseCanvasShortcutsOptions {
    readOnly: boolean;
    editor: SowEditorApi;
    hasPreview: boolean;
    isBoxPreview: boolean;
    editingTextId: string | null;
    onClearEditingText: () => void;
    onClearPreview: () => void;
    onCommitDashed: () => void;
}

export function useCanvasShortcuts({
    readOnly,
    editor,
    hasPreview,
    isBoxPreview,
    editingTextId,
    onClearEditingText,
    onClearPreview,
    onCommitDashed,
}: UseCanvasShortcutsOptions) {
    React.useEffect(() => {
        if (readOnly) return;

        const handleUndoRedo = (e: KeyboardEvent): boolean => {
            if (!(e.ctrlKey || e.metaKey)) return false;
            const key = e.key.toLowerCase();
            if (key === 'z') {
                e.preventDefault();
                if (e.shiftKey) editor.redo();
                else editor.undo();
                return true;
            }
            if (key === 'y') {
                e.preventDefault();
                editor.redo();
                return true;
            }
            return false;
        };

        const handleDelete = (e: KeyboardEvent): boolean => {
            if ((e.key !== 'Delete' && e.key !== 'Backspace') || !editor.selectedId) return false;
            e.preventDefault();
            editor.deleteNode(editor.selectedId);
            editor.setSelectedId(null);
            return true;
        };

        const handleEscape = (): void => {
            if (editingTextId) onClearEditingText();
            else if (hasPreview) onClearPreview();
            else editor.setSelectedId(null);
        };

        const onKeyDown = (e: KeyboardEvent) => {
            if (isTypingTarget(e.target)) return;
            if (handleUndoRedo(e)) return;

            const tool = SHAPE_TOOL_KEYS[e.key.toLowerCase()];
            if (tool) {
                editor.setTool(tool);
                return;
            }
            if (handleDelete(e)) return;
            if (e.key === 'Escape') handleEscape();
            if (
                e.key === 'Enter' &&
                editor.activeTool === 'dashed_line' &&
                hasPreview &&
                !isBoxPreview
            ) {
                onCommitDashed();
            }
        };

        globalThis.addEventListener('keydown', onKeyDown);
        return () => globalThis.removeEventListener('keydown', onKeyDown);
    }, [
        readOnly,
        editor,
        hasPreview,
        isBoxPreview,
        editingTextId,
        onClearEditingText,
        onClearPreview,
        onCommitDashed,
    ]);
}
