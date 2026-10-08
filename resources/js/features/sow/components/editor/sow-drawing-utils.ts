import type { Annotation, AnnotationShape, EditorTool } from '../../types/annotation.types';
import { MAX_BOXES_PER_PHOTO } from '../../utils/annotation-defaults';
import { toNorm } from '../../utils/normalize-coords';
import { toast } from 'sonner';

export const MIN_SHAPE_SIZE = 6;
export const DEFAULT_BOX_WIDTH = 250;
export const DEFAULT_BOX_HEIGHT = 80;
export const DEFAULT_ARROW_LENGTH = 90;
export const DEFAULT_DASH_LENGTH = 100;
export const MIN_FINISH_RADIUS = 18;
export const MAX_PATH_POINTS = 32;

export interface BoxPreview {
    type: 'box';
    x0: number;
    y0: number;
    x1: number;
    y1: number;
}

export interface ArrowPreview {
    type: 'arrow';
    x0: number;
    y0: number;
    x1: number;
    y1: number;
}

export interface DashedPreview {
    type: 'dashed';
    points: number[];
    cursor?: { x: number; y: number };
}

export type Preview = BoxPreview | ArrowPreview | DashedPreview;

export const isBoxPreview = (p: Preview | null): p is BoxPreview => p?.type === 'box';
export const isArrowPreview = (p: Preview | null): p is ArrowPreview => p?.type === 'arrow';
export const isDashedPreview = (p: Preview | null): p is DashedPreview => p?.type === 'dashed';

export interface DashedPathFinishResult {
    shouldCommit: boolean;
    points: number[];
}

export function toVertices(points: number[]): Array<{ x: number; y: number }> {
    return Array.from({ length: Math.floor(points.length / 2) }, (_, i) => ({
        x: points[i * 2],
        y: points[i * 2 + 1],
    }));
}

export function checkDashedPathFinish(
    point: { x: number; y: number },
    preview: Preview | null,
    finishRadius: number = MIN_FINISH_RADIUS
): DashedPathFinishResult | null {
    if (!preview || !isDashedPreview(preview) || preview.points.length < 2) return null;

    const existing = preview.points;
    const lastX = existing.at(-2) ?? 0;
    const lastY = existing.at(-1) ?? 0;

    if (Math.hypot(point.x - lastX, point.y - lastY) <= finishRadius) {
        return { shouldCommit: existing.length >= 4, points: existing };
    }

    if (existing.length >= 6 && Math.hypot(point.x - existing[0], point.y - existing[1]) <= finishRadius) {
        return { shouldCommit: true, points: [...existing, existing[0], existing[1]] };
    }

    return null;
}

export function checkDashedDragCommit(
    preview: Preview | null,
    minDistance: number
): number[] | null {
    if (!preview || !isDashedPreview(preview) || preview.points.length !== 2 || !preview.cursor) {
        return null;
    }
    const [x0, y0] = preview.points;
    return Math.hypot(preview.cursor.x - x0, preview.cursor.y - y0) > minDistance
        ? [x0, y0, preview.cursor.x, preview.cursor.y]
        : null;
}

export function computeBoxNode(
    previewBox: BoxPreview,
    end: { x: number; y: number },
    baseWidth: number,
    logicalHeight: number,
    minSize: number = MIN_SHAPE_SIZE
): { point: { x: number; y: number }; extra: Partial<Annotation> } {
    const rawW = Math.abs(end.x - previewBox.x0);
    const rawH = Math.abs(end.y - previewBox.y0);

    const isPortrait = logicalHeight > baseWidth;
    const portraitFactor = isPortrait ? Math.min(2.0, logicalHeight / baseWidth) : 1;
    const baseFs = Math.round(26 * portraitFactor);

    if (rawW > minSize && rawH > minSize) {
        const minW = Math.round(60 * portraitFactor);
        const minH = Math.round(30 * portraitFactor);
        const boxX = Math.max(0, Math.min(previewBox.x0, end.x));
        const boxY = Math.max(0, Math.min(previewBox.y0, end.y));
        const clampedW = Math.min(baseWidth - boxX, Math.max(minW, rawW));
        const clampedH = Math.min(logicalHeight - boxY, Math.max(minH, rawH));

        return {
            point: { x: boxX, y: boxY },
            extra: {
                normWidth: toNorm(clampedW, baseWidth),
                normHeight: toNorm(clampedH, baseWidth),
                normFontSize: toNorm(baseFs, baseWidth),
            },
        };
    }

    // Single-click default: place a standard compact callout box centered at click
    const defaultW = Math.min(baseWidth - 20, Math.round(DEFAULT_BOX_WIDTH * portraitFactor));
    const defaultH = Math.min(logicalHeight - 20, Math.round(DEFAULT_BOX_HEIGHT * portraitFactor));
    const boxX = Math.max(0, Math.min(baseWidth - defaultW, previewBox.x0 - defaultW / 2));
    const boxY = Math.max(0, Math.min(logicalHeight - defaultH, previewBox.y0 - defaultH / 2));

    return {
        point: { x: boxX, y: boxY },
        extra: {
            normWidth: toNorm(defaultW, baseWidth),
            normHeight: toNorm(defaultH, baseWidth),
            normFontSize: toNorm(baseFs, baseWidth),
        },
    };
}

export function computeArrowNode(
    startPoint: { x: number; y: number },
    endPoint: { x: number; y: number },
    baseWidth: number,
    minSize: number = MIN_SHAPE_SIZE
): { point: { x: number; y: number }; extra: Partial<Annotation> } {
    const dist = Math.hypot(endPoint.x - startPoint.x, endPoint.y - startPoint.y);
    const endX = dist > minSize ? endPoint.x : Math.min(baseWidth, startPoint.x + DEFAULT_ARROW_LENGTH);
    const endY = dist > minSize ? endPoint.y : startPoint.y;

    return {
        point: { x: startPoint.x, y: startPoint.y },
        extra: {
            normPoints: [startPoint.x, startPoint.y, endX, endY].map((v) => toNorm(v, baseWidth)),
        },
    };
}

export function computeDefaultDashedLine(
    startPoint: { x: number; y: number },
    baseWidth: number
): number[] {
    const endX =
        startPoint.x + DEFAULT_DASH_LENGTH <= baseWidth
            ? startPoint.x + DEFAULT_DASH_LENGTH
            : Math.max(0, startPoint.x - DEFAULT_DASH_LENGTH);
    return [startPoint.x, startPoint.y, endX, startPoint.y];
}

export function canAddBox(nodes: Annotation[]): boolean {
    const boxCount = nodes.filter((n) => n.type === 'box').length;
    if (boxCount >= MAX_BOXES_PER_PHOTO) {
        toast.warning(`Maximum ${MAX_BOXES_PER_PHOTO} callout boxes allowed per photo to keep the site image clear.`);
        return false;
    }
    return true;
}

export function handleDashedPointerDown(
    point: { x: number; y: number },
    preview: Preview | null,
    commitDashedLine: (points: number[]) => void,
    setPreview: React.Dispatch<React.SetStateAction<Preview | null>>
): boolean {
    const finishAction = checkDashedPathFinish(point, preview, MIN_FINISH_RADIUS);
    if (!finishAction) return false;

    if (finishAction.shouldCommit) {
        commitDashedLine(finishAction.points);
    } else {
        setPreview(null);
    }
    return true;
}

export interface StartDrawingArgs {
    tool: EditorTool;
    point: { x: number; y: number };
    setPreview: React.Dispatch<React.SetStateAction<Preview | null>>;
    createShape: (
        shape: AnnotationShape,
        point: { x: number; y: number },
        extra?: Partial<Annotation>
    ) => string;
    setSelectedId: (id: string | null) => void;
}

export function startDrawingTool(args: StartDrawingArgs): void {
    const { tool, point, setPreview, createShape, setSelectedId } = args;

    if (tool === 'box') {
        setPreview({ type: 'box', x0: point.x, y0: point.y, x1: point.x, y1: point.y });
    } else if (tool === 'arrow') {
        setPreview({ type: 'arrow', x0: point.x, y0: point.y, x1: point.x, y1: point.y });
    } else if (tool === 'dashed_line') {
        setPreview((prev) => {
            const existing = prev && isDashedPreview(prev) ? prev.points : [];
            if (existing.length >= MAX_PATH_POINTS * 2) {
                return { type: 'dashed', points: existing, cursor: point };
            }
            return { type: 'dashed', points: [...existing, point.x, point.y], cursor: point };
        });
    } else if (tool === 'dot') {
        const id = createShape('dot', point);
        setSelectedId(id);
    } else if (tool === 'text_label') {
        const id = createShape('text_label', point);
        setSelectedId(id);
    }
}
