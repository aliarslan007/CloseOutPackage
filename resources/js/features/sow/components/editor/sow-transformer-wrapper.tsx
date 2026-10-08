import type { Annotation } from '../../types/annotation.types';
import { Transformer } from 'react-konva';
import type Konva from 'konva';
import * as React from 'react';

export interface SowTransformerWrapperProps {
    selectedNode: Annotation | null;
    layerRef: React.RefObject<Konva.Layer | null>;
    baseWidth: number;
    logicalHeight: number;
    onTransformEnd: (id: string, patch: Partial<Annotation>) => void;
}

/**
 * Resize handles for the selected box. Rotation is disabled in v1 — the
 * annotation data model has no rotation field, so a rotated shape would be
 * silently lost on save. Add rotation to the model if the client asks for it.
 */
export function SowTransformerWrapper({
    selectedNode,
    layerRef,
    baseWidth,
    logicalHeight,
    onTransformEnd,
}: Readonly<SowTransformerWrapperProps>) {
    const trRef = React.useRef<Konva.Transformer>(null);

    React.useEffect(() => {
        const tr = trRef.current;
        const layer = layerRef.current;
        if (!tr || !layer) return;

        const node = selectedNode ? layer.findOne(`#${selectedNode.id}`) : null;

        if (!node) {
            tr.nodes([]);
            tr.getLayer()?.batchDraw();
            return;
        }

        tr.nodes([node]);
        tr.getLayer()?.batchDraw();
    }, [selectedNode, layerRef]);

    if (!selectedNode || selectedNode.type !== 'box') {
        return null;
    }

    return (
        <Transformer
            ref={trRef}
            rotateEnabled={false}
            flipEnabled={false}
            anchorSize={8}
            borderStroke="#2563EB"
            anchorStroke="#2563EB"
            anchorFill="#FFFFFF"
            boundBoxFunc={(_oldBox, newBox) => {
                const minW = 60;
                const minH = 30;
                const minX = Math.max(0, newBox.x);
                const minY = Math.max(0, newBox.y);
                const maxAllowedW = Math.max(minW, baseWidth - minX);
                const maxAllowedH = Math.max(minH, logicalHeight - minY);

                return {
                    ...newBox,
                    x: minX,
                    y: minY,
                    width: Math.min(maxAllowedW, Math.max(minW, newBox.width)),
                    height: Math.min(maxAllowedH, Math.max(minH, newBox.height)),
                };
            }}
            onTransformEnd={(e) => {
                if (!selectedNode) return;
                const node = e.target;
                const scaleX = node.scaleX();
                const scaleY = node.scaleY();
                const width = node.width() * scaleX;
                const height = node.height() * scaleY;
                node.scaleX(1);
                node.scaleY(1);

                onTransformEnd(selectedNode.id, {
                    normX: node.x() / baseWidth,
                    normY: node.y() / baseWidth,
                    normWidth: width / baseWidth,
                    normHeight: height / baseWidth,
                } as Partial<Annotation>);
            }}
        />
    );
}
