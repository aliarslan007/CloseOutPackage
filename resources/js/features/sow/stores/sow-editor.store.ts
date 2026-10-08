import { create } from 'zustand';
import type { Annotation, AnnotationLayer, EditorTool } from '../types/annotation.types';

const HISTORY_LIMIT = 50; // §9.6 — cap undo stack at 50 snapshots

export interface SowEditorStore {
    layer: AnnotationLayer;
    selectedId: string | null;
    activeTool: EditorTool;
    activeColor: string;
    activeStroke: number; // px at baseWidth, slider 1–8
    activeOpacity: number; // 0.2 – 1.0
    isDirty: boolean;
    isSaving: boolean;
    lastSavedAt: string | null; // server-echoed annotations_updated_at (ms ISO)
    past: AnnotationLayer[];
    future: AnnotationLayer[];
    // Actions
    setLayer: (layer: AnnotationLayer) => void;
    addNode: (node: Annotation) => void;
    updateNode: (id: string, patch: Partial<Annotation>) => void;
    deleteNode: (id: string) => void;
    undo: () => void;
    redo: () => void;
    setTool: (tool: EditorTool) => void;
    setSelectedId: (id: string | null) => void;
    setActiveColor: (color: string) => void;
    setActiveStroke: (px: number) => void;
    setActiveOpacity: (opacity: number) => void;
    markSaving: () => void;
    markSaved: (serverTimestamp: string) => void;
    markSaveFailed: () => void;
    markDirty: () => void;
}

const clone = (layer: AnnotationLayer): AnnotationLayer => structuredClone(layer);

export const useSowEditorStore = create<SowEditorStore>()((set) => ({
    layer: { baseWidth: 1200, nodes: [] },
    selectedId: null,
    activeTool: 'select',
    activeColor: '#E53E3E',
    activeStroke: 4,
    activeOpacity: 1,
    isDirty: false,
    isSaving: false,
    lastSavedAt: null,
    past: [],
    future: [],

    setLayer: (layer) => set({ layer: clone(layer), selectedId: null, isDirty: false }),

    addNode: (node) =>
        set((state) => {
            const past = [...state.past, clone(state.layer)].slice(-HISTORY_LIMIT);
            return {
                layer: { ...state.layer, nodes: [...state.layer.nodes, node] },
                past,
                future: [],
                isDirty: true,
            };
        }),

    updateNode: (id, patch) =>
        set((state) => {
            const target = state.layer.nodes.find((n) => n.id === id);
            if (!target) return state;

            const past = [...state.past, clone(state.layer)].slice(-HISTORY_LIMIT);
            return {
                layer: {
                    ...state.layer,
                    nodes: state.layer.nodes.map((n) =>
                        n.id === id ? ({ ...n, ...patch } as Annotation) : n
                    ),
                },
                past,
                future: [],
                isDirty: true,
            };
        }),

    deleteNode: (id) =>
        set((state) => {
            const past = [...state.past, clone(state.layer)].slice(-HISTORY_LIMIT);
            return {
                layer: { ...state.layer, nodes: state.layer.nodes.filter((n) => n.id !== id) },
                past,
                future: [],
                isDirty: true,
                selectedId: state.selectedId === id ? null : state.selectedId,
            };
        }),

    undo: () =>
        set((state) => {
            if (state.past.length === 0) return state;
            const previous = state.past[state.past.length - 1];
            return {
                layer: clone(previous),
                past: state.past.slice(0, -1),
                future: [clone(state.layer), ...state.future].slice(0, HISTORY_LIMIT),
                isDirty: true,
            };
        }),

    redo: () =>
        set((state) => {
            if (state.future.length === 0) return state;
            const next = state.future[0];
            return {
                layer: clone(next),
                future: state.future.slice(1),
                past: [...state.past, clone(state.layer)].slice(-HISTORY_LIMIT),
                isDirty: true,
            };
        }),

    setTool: (tool) => set({ activeTool: tool, selectedId: null }),
    setSelectedId: (id) => set({ selectedId: id }),
    setActiveColor: (color) => set({ activeColor: color }),
    setActiveStroke: (px) => set({ activeStroke: px }),
    setActiveOpacity: (opacity) => set({ activeOpacity: opacity }),
    markSaving: () => set({ isSaving: true }),
    markSaved: (serverTimestamp) =>
        set({ isSaving: false, isDirty: false, lastSavedAt: serverTimestamp }),
    // A failed save must re-arm saving — otherwise isSaving sticks and blocks every later save.
    markSaveFailed: () => set({ isSaving: false, isDirty: true }),
    markDirty: () => set({ isDirty: true }),
}));
