import type { DashedLineAnnotation } from '../../../types/annotation.types';
import { getContrastHaloColor } from '../../../utils/annotation-defaults';
import { Line } from 'react-konva';

export interface AnnotationDashedLineProps {
    node: DashedLineAnnotation;
    baseWidth: number;
    selected: boolean;
    onSelect: () => void;
    onDragEnd: (points: number[]) => void;
}

/** §6.1 — multi-segment dashed path marking telco/conduit runs with contrast halo for high visibility. */
export function AnnotationDashedLine({
    node,
    baseWidth,
    selected,
    onSelect,
    onDragEnd,
}: Readonly<AnnotationDashedLineProps>) {
    const points = node.normPoints.map((value) => value * baseWidth);
    const strokeWidth = Math.max(2.5, node.normStrokeWidth * baseWidth);
    const haloColor = getContrastHaloColor(node.strokeColor);
    const underStrokeWidth = strokeWidth + 2.5;
    const effectiveOpacity = Math.max(0.95, node.opacity);

    return (
        <>
            {/* Contrast understroke halo so dashed line is clearly visible against dark/light photos */}
            <Line
                points={points}
                stroke={haloColor}
                strokeWidth={underStrokeWidth}
                strokeScaleEnabled={false}
                dash={node.dashPattern}
                opacity={0.85}
                lineCap="round"
                lineJoin="round"
                listening={false}
            />
            {/* Primary colored dashed line */}
            <Line
                id={node.id}
                points={points}
                stroke={node.strokeColor}
                strokeWidth={strokeWidth}
                strokeScaleEnabled={false}
                dash={node.dashPattern}
                opacity={effectiveOpacity}
                shadowColor="rgba(0,0,0,0.6)"
                shadowBlur={2}
                shadowOffset={{ x: 0, y: 1 }}
                shadowOpacity={0.7}
                lineCap="round"
                lineJoin="round"
                draggable
                onPointerDown={(e: { cancelBubble: boolean }) => {
                    e.cancelBubble = true;
                    onSelect();
                }}
                onDragEnd={(e: { target: { x: () => number; y: () => number } }) => {
                    const dx = e.target.x();
                    const dy = e.target.y();
                    onDragEnd(points.map((value, i) => (i % 2 === 0 ? value + dx : value + dy)));
                }}
            />
            {selected && (
                <Line
                    points={points}
                    stroke="#2563EB"
                    strokeWidth={Math.max(1, strokeWidth * 1.5)}
                    strokeScaleEnabled={false}
                    dash={[4, 4]}
                    listening={false}
                />
            )}
        </>
    );
}
