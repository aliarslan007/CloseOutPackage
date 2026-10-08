import type { BoxAnnotation } from '../../../types/annotation.types';
import { sizeToPixel } from '../../../utils/normalize-coords';
import { Rect, Text } from 'react-konva';

export interface AnnotationBoxProps {
    node: BoxAnnotation;
    baseWidth: number;
    selected: boolean;
    onSelect: () => void;
    onDoubleClick: () => void;
    onDragEnd: (x: number, y: number) => void;
}

/** Callout box with optional multi-line text inside (strokeScaleEnabled=false). */
export function AnnotationBox({
    node,
    baseWidth,
    selected,
    onSelect,
    onDoubleClick,
    onDragEnd,
}: Readonly<AnnotationBoxProps>) {
    const x = node.normX * baseWidth;
    const y = node.normY * baseWidth;
    const width = Math.max(1, node.normWidth * baseWidth);
    const height = Math.max(1, node.normHeight * baseWidth);
    const strokeWidth = Math.max(0.5, node.normStrokeWidth * baseWidth);
    const fontSize = Math.max(22, sizeToPixel(node.normFontSize, baseWidth));

    const hasText = (node.text ?? '').trim() !== '';

    return (
        <>
            <Rect
                id={node.id}
                x={x}
                y={y}
                width={width}
                height={height}
                stroke={node.strokeColor}
                strokeWidth={strokeWidth}
                strokeScaleEnabled={false}
                fill="#FFFFFF"
                opacity={Math.max(0.95, node.opacity)}
                cornerRadius={node.borderRadius}
                dash={node.dashed ? [8, 6] : undefined}
                draggable
                onDragEnd={(e) => onDragEnd(e.target.x(), e.target.y())}
                onPointerDown={(e) => {
                    e.cancelBubble = true;
                    onSelect();
                }}
                onDblClick={(e) => {
                    e.cancelBubble = true;
                    onDoubleClick();
                }}
            />
            {hasText && (
                <Text
                    x={x + strokeWidth + 6}
                    y={y + strokeWidth + 4}
                    width={Math.max(10, width - (strokeWidth + 6) * 2)}
                    height={Math.max(10, height - (strokeWidth + 4) * 2)}
                    text={node.text ?? ''}
                    fontSize={fontSize}
                    fontFamily="Arial, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
                    fontStyle="bold"
                    fill="#000000"
                    wrap="word"
                    ellipsis={false}
                    lineHeight={1.28}
                    align="center"
                    verticalAlign="middle"
                    opacity={1}
                    listening={false}
                />
            )}
            {selected && (
                <Rect
                    x={x}
                    y={y}
                    width={width}
                    height={height}
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
