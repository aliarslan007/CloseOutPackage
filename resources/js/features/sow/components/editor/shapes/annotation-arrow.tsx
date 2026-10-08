import type { ArrowAnnotation } from '../../../types/annotation.types';
import { getContrastHaloColor } from '../../../utils/annotation-defaults';
import { Arrow as KonvaArrow, Line } from 'react-konva';

export interface AnnotationArrowProps {
    node: ArrowAnnotation;
    baseWidth: number;
    selected: boolean;
    onSelect: () => void;
    /** Stage-unit points after a drag; parent re-normalizes. */
    onDragEnd: (points: number[]) => void;
}

function OpenArrowhead({
    tip,
    angle,
    length,
    stroke,
    strokeWidth,
    opacity,
}: {
    tip: { x: number; y: number };
    angle: number;
    length: number;
    stroke: string;
    strokeWidth: number;
    opacity: number;
}) {
    const spread = 0.42; // ≈24°
    const back1 = angle + Math.PI - spread;
    const back2 = angle + Math.PI + spread;

    return (
        <>
            <Line
                points={[
                    tip.x,
                    tip.y,
                    tip.x + Math.cos(back1) * length,
                    tip.y + Math.sin(back1) * length,
                ]}
                stroke={stroke}
                strokeWidth={strokeWidth}
                strokeScaleEnabled={false}
                opacity={opacity}
                listening={false}
            />
            <Line
                points={[
                    tip.x,
                    tip.y,
                    tip.x + Math.cos(back2) * length,
                    tip.y + Math.sin(back2) * length,
                ]}
                stroke={stroke}
                strokeWidth={strokeWidth}
                strokeScaleEnabled={false}
                opacity={opacity}
                listening={false}
            />
        </>
    );
}

/**
 * Solid or dashed arrow pointing from a callout to a physical site element.
 * Position comes from normPoints only (no normX/normY).
 */
export function AnnotationArrow({
    node,
    baseWidth,
    selected,
    onSelect,
    onDragEnd,
}: Readonly<AnnotationArrowProps>) {
    const points = node.normPoints.map((value) => value * baseWidth);
    const strokeWidth = Math.max(2.5, node.normStrokeWidth * baseWidth);
    const shaft = { x: points[0], y: points[1] };
    const tip = { x: points[points.length - 2], y: points[points.length - 1] };
    const angle = Math.atan2(tip.y - shaft.y, tip.x - shaft.x);
    const haloColor = getContrastHaloColor(node.strokeColor);
    const underStrokeWidth = strokeWidth + 2.5;
    const effectiveOpacity = Math.max(0.95, node.opacity);

    const lineProps = {
        id: node.id,
        points,
        stroke: node.strokeColor,
        strokeWidth,
        strokeScaleEnabled: false,
        opacity: effectiveOpacity,
        dash: node.dashed ? [8, 6] : undefined,
        draggable: true,
        shadowColor: 'rgba(0,0,0,0.7)',
        shadowBlur: 2,
        shadowOffset: { x: 0, y: 1 },
        shadowOpacity: 0.8,
        onPointerDown: (e: { cancelBubble: boolean }) => {
            e.cancelBubble = true;
            onSelect();
        },
        onDragEnd: (e: { target: { x: () => number; y: () => number } }) => {
            const dx = e.target.x();
            const dy = e.target.y();
            onDragEnd(points.map((value, i) => (i % 2 === 0 ? value + dx : value + dy)));
        },
    };

    return (
        <>
            {node.arrowheadStyle === 'open' ? (
                <>
                    {/* Contrast understroke halo so arrow is visible against dark or light surfaces */}
                    <Line
                        points={points}
                        stroke={haloColor}
                        strokeWidth={underStrokeWidth}
                        strokeScaleEnabled={false}
                        opacity={0.85}
                        dash={node.dashed ? [8, 6] : undefined}
                        listening={false}
                    />
                    <OpenArrowhead
                        tip={tip}
                        angle={angle}
                        length={Math.max(14, strokeWidth * 5 + 2)}
                        stroke={haloColor}
                        strokeWidth={underStrokeWidth}
                        opacity={0.85}
                    />
                    {/* Primary arrow */}
                    <Line {...lineProps} />
                    <OpenArrowhead
                        tip={tip}
                        angle={angle}
                        length={Math.max(12, strokeWidth * 5)}
                        stroke={node.strokeColor}
                        strokeWidth={strokeWidth}
                        opacity={effectiveOpacity}
                    />
                </>
            ) : (
                <>
                    {/* Contrast understroke halo so arrow pops against dark or light surfaces */}
                    <KonvaArrow
                        points={points}
                        stroke={haloColor}
                        fill={haloColor}
                        strokeWidth={underStrokeWidth}
                        strokeScaleEnabled={false}
                        opacity={0.85}
                        dash={node.dashed ? [8, 6] : undefined}
                        pointerLength={Math.max(14, strokeWidth * 5 + 2)}
                        pointerWidth={Math.max(10, strokeWidth * 3.5 + 2)}
                        pointerAtBeginning={false}
                        listening={false}
                    />
                    {/* Primary arrow */}
                    <KonvaArrow
                        {...lineProps}
                        pointerLength={Math.max(12, strokeWidth * 5)}
                        pointerWidth={Math.max(8, strokeWidth * 3.5)}
                        pointerAtBeginning={false}
                    />
                </>
            )}
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
