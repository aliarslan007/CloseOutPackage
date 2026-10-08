import {
    exportStageToBlob,
    type ExportCanvasOptions,
} from '@/features/media-library/utils/export-canvas';
import { useSowEditorStore } from '@/features/sow/stores/sow-editor.store';
import type Konva from 'konva';
import * as React from 'react';

/**
 * Stage plumbing for the annotation dialogs: hold the Konva stage, and export
 * it to a Blob with the transformer selection cleared so handles are never
 * baked into the pixels.
 */
export function useCanvasExport() {
    const stageRef = React.useRef<Konva.Stage | null>(null);
    const [isExporting, setIsExporting] = React.useState(false);

    const setStage = React.useCallback((stage: Konva.Stage | null) => {
        stageRef.current = stage;
    }, []);

    const exportBlob = React.useCallback(
        async (options: ExportCanvasOptions = {}): Promise<Blob> => {
            const stage = stageRef.current;
            if (!stage) {
                throw new Error('Canvas is still initializing. Please wait a moment.');
            }

            useSowEditorStore.getState().setSelectedId(null);
            setIsExporting(true);
            try {
                return await exportStageToBlob(stage, {
                    mimeType: 'image/jpeg',
                    quality: 0.95,
                    pixelRatio: 2,
                    ...options,
                });
            } finally {
                setIsExporting(false);
            }
        },
        []
    );

    return { setStage, exportBlob, isExporting };
}
