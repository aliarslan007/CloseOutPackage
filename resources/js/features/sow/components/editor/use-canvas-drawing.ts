import type Konva from 'konva';
import * as React from 'react';
import {
    computeNextZoomView,
    useCanvasZoom,
    wheelZoomFactor,
} from '../../hooks/use-canvas-zoom';
import type { SowEditorStore } from '../../stores/sow-editor.store';
import type { Annotation, AnnotationShape } from '../../types/annotation.types';
import { defaultAnnotation } from '../../utils/annotation-defaults';
import { toNorm } from '../../utils/normalize-coords';
import {
    canAddBox,
    checkDashedDragCommit,
    computeArrowNode,
    computeBoxNode,
    handleDashedPointerDown,
    isArrowPreview,
    isBoxPreview,
    isDashedPreview,
    MIN_SHAPE_SIZE,
    startDrawingTool,
    type Preview,
} from './sow-drawing-utils';

export interface UseCanvasDrawingOptions {
    readOnly: boolean;
    editor: SowEditorStore;
    baseWidth: number;
    logicalHeight: number;
    containerSize: { width: number; height: number };
    editingTextId: string | null;
    view: { scale: number; offsetX: number; offsetY: number };
    setView: React.Dispatch<
        React.SetStateAction<{ scale: number; offsetX: number; offsetY: number }>
    >;
}

export function useCanvasDrawing({
    readOnly,
    editor,
    baseWidth,
    logicalHeight,
    containerSize,
    editingTextId,
    view,
    setView,
}: Readonly<UseCanvasDrawingOptions>) {
    const zoom = useCanvasZoom();
    const [preview, setPreview] = React.useState<Preview | null>(null);
    const [panStart, setPanStart] = React.useState<{
        pointerX: number;
        pointerY: number;
        offsetX: number;
        offsetY: number;
    } | null>(null);

    const logicalPoint = React.useCallback(
        (e: Konva.KonvaEventObject<PointerEvent>): { x: number; y: number } => {
            const pointer = e.target.getStage()?.getPointerPosition();
            if (!pointer) return { x: 0, y: 0 };
            return {
                x: (pointer.x - view.offsetX) / view.scale,
                y: (pointer.y - view.offsetY) / view.scale,
            };
        },
        [view]
    );

    const createShape = React.useCallback(
        (
            type: AnnotationShape,
            point: { x: number; y: number },
            extra: Partial<Annotation> = {}
        ): string => {
            const id = crypto.randomUUID();
            const node = defaultAnnotation(type, baseWidth, {
                id,
                normX: point.x / baseWidth,
                normY: point.y / baseWidth,
                color: editor.activeColor,
                strokeWidthPx: editor.activeStroke,
                opacity: editor.activeOpacity,
            });
            editor.addNode({ ...node, ...extra } as Annotation);
            editor.setSelectedId(id);
            return id;
        },
        [baseWidth, editor]
    );

    const commitDashedLine = React.useCallback(
        (points: number[]) => {
            if (points.length < 4) return;
            const id = crypto.randomUUID();
            const node = defaultAnnotation('dashed_line', baseWidth, {
                id,
                normX: points[0] / baseWidth,
                normY: points[1] / baseWidth,
                color: editor.activeColor,
                strokeWidthPx: editor.activeStroke,
                opacity: editor.activeOpacity,
            });
            editor.addNode({
                ...node,
                normPoints: points.map((v) => toNorm(v, baseWidth)),
            } as Annotation);
            editor.setSelectedId(id);
            setPreview(null);
        },
        [baseWidth, editor]
    );

    // Clean up drawing or auto-commit dashed path on tool switch
    const prevToolRef = React.useRef(editor.activeTool);
    React.useEffect(() => {
        const prevTool = prevToolRef.current;
        prevToolRef.current = editor.activeTool;

        if (prevTool === editor.activeTool) return;

        setPreview((current) => {
            if (prevTool === 'dashed_line' && isDashedPreview(current) && current.points.length >= 4) {
                commitDashedLine(current.points);
            }
            return null;
        });
        setPanStart(null);
    }, [editor.activeTool, commitDashedLine]);

    const onPointerDown = (e: Konva.KonvaEventObject<PointerEvent>) => {
        const evt = e.evt;
        if (editingTextId) return;

        zoom.addPointer(evt.pointerId, evt.clientX, evt.clientY);
        const point = logicalPoint(e);
        const tool = editor.activeTool;

        if (readOnly || tool === 'pan') {
            const pointer = e.target.getStage()?.getPointerPosition();
            setPanStart({
                pointerX: pointer?.x ?? 0,
                pointerY: pointer?.y ?? 0,
                offsetX: view.offsetX,
                offsetY: view.offsetY,
            });
            return;
        }

        // In select mode, clicking empty canvas deselects the current node
        if (tool === 'select') {
            if (e.target === e.target.getStage()) {
                editor.setSelectedId(null);
            }
            return;
        }

        // When activating any drawing tool, clear previous selection and start drawing
        editor.setSelectedId(null);
        if (zoom.pointerCount() > 1) return;

        if (tool === 'box' && !canAddBox(editor.layer.nodes)) {
            return;
        }

        if (
            tool === 'dashed_line' &&
            handleDashedPointerDown(point, preview, commitDashedLine, setPreview)
        ) {
            return;
        }

        startDrawingTool({
            tool,
            point,
            setPreview,
            createShape,
            setSelectedId: editor.setSelectedId,
        });
    };

    const onPointerMove = (e: Konva.KonvaEventObject<PointerEvent>) => {
        const evt = e.evt;
        zoom.movePointer(evt.pointerId, evt.clientX, evt.clientY);

        const gestureView = zoom.gesture(view);
        if (gestureView) {
            setView(gestureView);
            return;
        }

        if (panStart) {
            const pointer = e.target.getStage()?.getPointerPosition();
            if (!pointer) return;
            setView((prev) => ({
                ...prev,
                offsetX: panStart.offsetX + (pointer.x - panStart.pointerX),
                offsetY: panStart.offsetY + (pointer.y - panStart.pointerY),
            }));
            return;
        }

        if (readOnly || !preview) return;

        const point = logicalPoint(e);
        if (isBoxPreview(preview)) {
            setPreview({ ...preview, x1: point.x, y1: point.y });
        } else if (isArrowPreview(preview)) {
            setPreview({ ...preview, x1: point.x, y1: point.y });
        } else if (isDashedPreview(preview)) {
            setPreview({ ...preview, cursor: point });
        }
    };

    const onPointerUp = (e: Konva.KonvaEventObject<PointerEvent>) => {
        zoom.removePointer(e.evt.pointerId);

        if (panStart) {
            setPanStart(null);
            return;
        }

        if (readOnly || !preview) return;

        const point = logicalPoint(e);

        // Dashed path click-and-drag commit
        if (editor.activeTool === 'dashed_line') {
            const dragPoints = checkDashedDragCommit(preview, MIN_SHAPE_SIZE * 2);
            if (dragPoints) commitDashedLine(dragPoints);
            return;
        }

        if (isBoxPreview(preview)) {
            const boxData = computeBoxNode(
                preview,
                point,
                baseWidth,
                logicalHeight,
                MIN_SHAPE_SIZE
            );
            const id = createShape('box', boxData.point, boxData.extra);
            editor.setSelectedId(id);
            setPreview(null);
            return;
        }

        if (isArrowPreview(preview)) {
            const start = { x: preview.x0, y: preview.y0 };
            const arrowData = computeArrowNode(start, point, baseWidth, MIN_SHAPE_SIZE);
            const id = createShape('arrow', arrowData.point, arrowData.extra);
            editor.setSelectedId(id);
            setPreview(null);
            return;
        }

        setPreview(null);
    };

    const commitDashed = React.useCallback(() => {
        if (isDashedPreview(preview) && editor.activeTool === 'dashed_line') {
            commitDashedLine(preview.points);
        }
    }, [preview, editor.activeTool, commitDashedLine]);

    const onContextMenu = (e: Konva.KonvaEventObject<PointerEvent>) => {
        e.evt.preventDefault();
        if (readOnly || editor.activeTool !== 'dashed_line') return;
        if (isDashedPreview(preview) && preview.points.length >= 4) {
            commitDashedLine(preview.points);
        } else {
            setPreview(null);
        }
    };

    const onWheel = (e: Konva.KonvaEventObject<WheelEvent>) => {
        e.evt.preventDefault();
        const pointer = e.target.getStage()?.getPointerPosition() ?? {
            x: containerSize.width / 2,
            y: containerSize.height / 2,
        };
        setView((prev) => computeNextZoomView(prev, wheelZoomFactor(e.evt), pointer));
    };

    const onDblClick = () => {
        if (!readOnly && editor.activeTool === 'dashed_line') {
            commitDashed();
        }
    };

    return {
        preview,
        setPreview,
        onPointerDown,
        onPointerMove,
        onPointerUp,
        onContextMenu,
        onDblClick,
        onWheel,
        commitDashed,
    };
}
