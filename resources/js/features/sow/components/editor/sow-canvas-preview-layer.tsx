import { Arrow, Circle, Layer, Line, Rect } from 'react-konva';
import type { EditorTool } from '../../types/annotation.types';
import {
    isArrowPreview,
    isBoxPreview,
    isDashedPreview,
    toVertices,
    type DashedPreview,
    type Preview,
} from './sow-drawing-utils';

const PREVIEW_STROKE = '#2563EB';
const PREVIEW_DASH = [8, 6];

/**
 * In-progress dashed path: the vertices committed so far, plus a rubber band
 * from the last vertex to the pointer so the next segment is always visible.
 */
export function DashedPathPreview({
    preview,
    strokeWidth,
}: Readonly<{ preview: DashedPreview; strokeWidth: number }>) {
    const { points, cursor } = preview;
    const lastVertex = points.slice(-2);
    const rubberBand =
        cursor && lastVertex.length === 2
            ? [lastVertex[0], lastVertex[1], cursor.x, cursor.y]
            : null;
    const vertices = toVertices(points);

    return (
        <Layer listening={false}>
            {points.length >= 4 && (
                <Line
                    points={points}
                    stroke={PREVIEW_STROKE}
                    strokeWidth={strokeWidth}
                    strokeScaleEnabled={false}
                    dash={PREVIEW_DASH}
                    lineCap="round"
                    lineJoin="round"
                    shadowColor="rgba(0,0,0,0.5)"
                    shadowBlur={2}
                />
            )}
            {rubberBand && (
                <Line
                    points={rubberBand}
                    stroke={PREVIEW_STROKE}
                    strokeWidth={strokeWidth}
                    strokeScaleEnabled={false}
                    dash={PREVIEW_DASH}
                    opacity={0.65}
                    lineCap="round"
                    shadowColor="rgba(0,0,0,0.5)"
                    shadowBlur={2}
                />
            )}
            {vertices.map((vertex, index) => {
                const isLast = index === vertices.length - 1;
                return (
                    <Circle
                        key={`${vertex.x}:${vertex.y}:${index}`}
                        x={vertex.x}
                        y={vertex.y}
                        radius={isLast ? 6 : 4}
                        fill={isLast ? '#FFFFFF' : PREVIEW_STROKE}
                        stroke={PREVIEW_STROKE}
                        strokeWidth={isLast ? 2.5 : 1}
                        shadowColor="rgba(0,0,0,0.5)"
                        shadowBlur={2}
                    />
                );
            })}
            {cursor && (
                <Circle
                    x={cursor.x}
                    y={cursor.y}
                    radius={3}
                    fill={PREVIEW_STROKE}
                    opacity={0.7}
                />
            )}
        </Layer>
    );
}

export interface SowCanvasPreviewLayerProps {
    preview: Preview | null;
    strokeWidth: number;
    activeTool: EditorTool;
}

export function SowCanvasPreviewLayer({
    preview,
    strokeWidth,
    activeTool,
}: Readonly<SowCanvasPreviewLayerProps>) {
    if (!preview) return null;

    if (isBoxPreview(preview)) {
        return (
            <Layer listening={false}>
                <Rect
                    x={Math.min(preview.x0, preview.x1)}
                    y={Math.min(preview.y0, preview.y1)}
                    width={Math.abs(preview.x1 - preview.x0)}
                    height={Math.abs(preview.y1 - preview.y0)}
                    stroke={PREVIEW_STROKE}
                    strokeWidth={strokeWidth}
                    strokeScaleEnabled={false}
                    dash={[6, 4]}
                    shadowColor="rgba(0,0,0,0.5)"
                    shadowBlur={2}
                />
            </Layer>
        );
    }

    if (isArrowPreview(preview)) {
        return (
            <Layer listening={false}>
                <Arrow
                    points={[preview.x0, preview.y0, preview.x1, preview.y1]}
                    stroke={PREVIEW_STROKE}
                    fill={PREVIEW_STROKE}
                    strokeWidth={strokeWidth}
                    strokeScaleEnabled={false}
                    pointerLength={10}
                    pointerWidth={10}
                    dash={[6, 4]}
                    shadowColor="rgba(0,0,0,0.5)"
                    shadowBlur={2}
                />
            </Layer>
        );
    }

    if (activeTool === 'dashed_line' && isDashedPreview(preview)) {
        return <DashedPathPreview preview={preview} strokeWidth={strokeWidth} />;
    }

    return null;
}
