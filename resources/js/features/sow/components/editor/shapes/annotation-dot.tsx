import type { DotAnnotation } from '../../../types/annotation.types';
import { Circle } from 'react-konva';

export interface AnnotationDotProps {
    node: DotAnnotation;
    baseWidth: number;
    selected: boolean;
    onSelect: () => void;
    onDragEnd: (x: number, y: number) => void;
}

/** §6.1 — filled circle point-of-interest pin. */
export function AnnotationDot({
    node,
    baseWidth,
    selected,
    onSelect,
    onDragEnd,
}: Readonly<AnnotationDotProps>) {
    const radius = Math.max(2, node.normRadius * baseWidth);

    return (
        <>
            <Circle
                id={node.id}
                x={node.normX * baseWidth}
                y={node.normY * baseWidth}
                radius={radius}
                fill={node.fillColor}
                stroke={node.strokeColor}
                strokeWidth={Math.max(0.5, node.normStrokeWidth * baseWidth)}
                strokeScaleEnabled={false}
                opacity={node.opacity}
                draggable
                onDragEnd={(e) => onDragEnd(e.target.x(), e.target.y())}
                onPointerDown={(e) => {
                    e.cancelBubble = true;
                    onSelect();
                }}
            />
            {selected && (
                <Circle
                    x={node.normX * baseWidth}
                    y={node.normY * baseWidth}
                    radius={radius + 5}
                    stroke="#2563EB"
                    strokeWidth={1.5}
                    strokeScaleEnabled={false}
                    dash={[4, 4]}
                    listening={false}
                />
            )}
        </>
    );
}
