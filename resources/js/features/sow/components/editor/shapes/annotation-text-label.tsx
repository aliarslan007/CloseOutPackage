import type { TextLabelAnnotation } from '../../../types/annotation.types';
import { sizeToPixel } from '../../../utils/normalize-coords';
import { Group, Rect, Text } from 'react-konva';
import type Konva from 'konva';
import * as React from 'react';

export interface AnnotationTextLabelProps {
    node: TextLabelAnnotation;
    baseWidth: number;
    selected: boolean;
    onSelect: () => void;
    onDoubleClick: () => void;
    onDragEnd: (x: number, y: number) => void;
}

const PLACEHOLDER_LABEL = 'Double-click to type text';
const EDIT_HINT_LABEL = 'Double-click to edit';
const DEFAULT_FONT_FAMILY = 'Geist, system-ui, sans-serif';
const BACKDROP_DARK_COLOR = 'rgba(15, 23, 42, 0.92)';
const BACKDROP_SEMI_DARK = 'rgba(15, 23, 42, 0.65)';
const ACCENT_CYAN_COLOR = '#38BDF8';

/** §6.1 — standalone floating text callout with high-contrast visibility on all photo backgrounds. */
export function AnnotationTextLabel({
    node,
    baseWidth,
    selected,
    onSelect,
    onDoubleClick,
    onDragEnd,
}: Readonly<AnnotationTextLabelProps>) {
    const fontSize = sizeToPixel(node.normFontSize, baseWidth);
    const textRef = React.useRef<Konva.Text>(null);
    const [textSize, setTextSize] = React.useState<{ width: number; height: number } | null>(null);

    const rawText = node.text ?? '';
    const isEmpty = rawText.trim() === '';
    const displayText = isEmpty ? PLACEHOLDER_LABEL : rawText;

    React.useEffect(() => {
        const text = textRef.current;
        if (!text) return;
        setTextSize({
            width: text.width(),
            height: text.height(),
        });
    }, [displayText, fontSize]);

    const padX = 8;
    const padY = 5;
    const computedWidth = Math.max(isEmpty ? 170 : 40, textSize?.width ?? (isEmpty ? 170 : 40));
    const computedHeight = Math.max(isEmpty ? 22 : 16, textSize?.height ?? fontSize);
    const boxWidth = computedWidth + padX * 2;
    const boxHeight = computedHeight + padY * 2;

    const strokeColor = selected || isEmpty ? ACCENT_CYAN_COLOR : 'rgba(255, 255, 255, 0.3)';
    const fillColor = isEmpty ? BACKDROP_DARK_COLOR : BACKDROP_SEMI_DARK;

    return (
        <Group
            id={node.id}
            x={node.normX * baseWidth}
            y={node.normY * baseWidth}
            draggable
            opacity={node.opacity}
            onDragEnd={(e) => {
                onDragEnd(e.currentTarget.x(), e.currentTarget.y());
            }}
            onPointerDown={(e) => {
                e.cancelBubble = true;
                onSelect();
            }}
            onDblClick={(e) => {
                e.cancelBubble = true;
                onDoubleClick();
            }}
        >
            {/* Backdrop pill: ensures text is 100% visible against any photo background */}
            <Rect
                x={-padX}
                y={-padY}
                width={boxWidth}
                height={boxHeight}
                fill={fillColor}
                stroke={strokeColor}
                strokeWidth={selected ? 2 : 1.5}
                dash={isEmpty || selected ? [4, 3] : undefined}
                cornerRadius={5}
                shadowColor="rgba(0, 0, 0, 0.75)"
                shadowBlur={6}
                shadowOffsetY={2}
            />

            {/* Text label */}
            <Text
                ref={textRef}
                x={0}
                y={0}
                text={displayText}
                fontSize={fontSize}
                fontFamily={DEFAULT_FONT_FAMILY}
                fontStyle={isEmpty ? 'italic bold' : node.fontWeight}
                fill={isEmpty ? ACCENT_CYAN_COLOR : node.textColor}
                shadowColor="rgba(0,0,0,0.85)"
                shadowBlur={Math.max(2, fontSize / 6)}
                shadowOffsetY={1}
                listening={false}
            />

            {/* Instruction tag shown when selected: tells the user how to edit */}
            {selected && (
                <Group y={boxHeight - padY + 4} listening={false}>
                    <Rect
                        x={-padX}
                        width={124}
                        height={18}
                        fill={BACKDROP_DARK_COLOR}
                        stroke={ACCENT_CYAN_COLOR}
                        strokeWidth={1}
                        cornerRadius={3}
                        shadowColor="rgba(0, 0, 0, 0.5)"
                        shadowBlur={4}
                    />
                    <Text
                        x={-padX + 6}
                        y={3}
                        text={EDIT_HINT_LABEL}
                        fontSize={10}
                        fontFamily={DEFAULT_FONT_FAMILY}
                        fontStyle="bold"
                        fill="#E0F2FE"
                    />
                </Group>
            )}
        </Group>
    );
}
