import type { Annotation, AnnotationLayer as Layer } from '../../types/annotation.types';
import { AnnotationBox } from './shapes/annotation-box';
import { AnnotationArrow } from './shapes/annotation-arrow';
import { AnnotationDashedLine } from './shapes/annotation-dashed-line';
import { AnnotationDot } from './shapes/annotation-dot';
import { AnnotationTextLabel } from './shapes/annotation-text-label';

export interface SowAnnotationLayerProps {
    layer: Layer;
    selectedId: string | null;
    onSelect: (id: string) => void;
    onUpdate: (id: string, patch: Partial<Annotation>) => void;
    onEditText: (id: string) => void;
}

/** Layer 2 — annotation nodes, drawn in zIndex ASC order (§6.4, Yellow #13). */
export function SowAnnotationLayer({
    layer,
    selectedId,
    onSelect,
    onUpdate,
    onEditText,
}: Readonly<SowAnnotationLayerProps>) {
    const nodes = [...layer.nodes].sort((a, b) => a.zIndex - b.zIndex);

    return (
        <>
            {nodes.map((node) => {
                const selected = node.id === selectedId;

                switch (node.type) {
                    case 'box':
                        return (
                            <AnnotationBox
                                key={node.id}
                                node={node}
                                baseWidth={layer.baseWidth}
                                selected={selected}
                                onSelect={() => onSelect(node.id)}
                                onDoubleClick={() => onEditText(node.id)}
                                onDragEnd={(x, y) =>
                                    onUpdate(node.id, {
                                        normX: x / layer.baseWidth,
                                        normY: y / layer.baseWidth,
                                    })
                                }
                            />
                        );
                    case 'arrow':
                        return (
                            <AnnotationArrow
                                key={node.id}
                                node={node}
                                baseWidth={layer.baseWidth}
                                selected={selected}
                                onSelect={() => onSelect(node.id)}
                                onDragEnd={(points) =>
                                    onUpdate(node.id, {
                                        normPoints: points.map((value) => value / layer.baseWidth),
                                    })
                                }
                            />
                        );
                    case 'dashed_line':
                        return (
                            <AnnotationDashedLine
                                key={node.id}
                                node={node}
                                baseWidth={layer.baseWidth}
                                selected={selected}
                                onSelect={() => onSelect(node.id)}
                                onDragEnd={(points) =>
                                    onUpdate(node.id, {
                                        normPoints: points.map((value) => value / layer.baseWidth),
                                    })
                                }
                            />
                        );
                    case 'dot':
                        return (
                            <AnnotationDot
                                key={node.id}
                                node={node}
                                baseWidth={layer.baseWidth}
                                selected={selected}
                                onSelect={() => onSelect(node.id)}
                                onDragEnd={(x, y) =>
                                    onUpdate(node.id, {
                                        normX: x / layer.baseWidth,
                                        normY: y / layer.baseWidth,
                                    })
                                }
                            />
                        );
                    case 'text_label':
                        return (
                            <AnnotationTextLabel
                                key={node.id}
                                node={node}
                                baseWidth={layer.baseWidth}
                                selected={selected}
                                onSelect={() => onSelect(node.id)}
                                onDoubleClick={() => onEditText(node.id)}
                                onDragEnd={(x, y) =>
                                    onUpdate(node.id, {
                                        normX: x / layer.baseWidth,
                                        normY: y / layer.baseWidth,
                                    })
                                }
                            />
                        );
                }
            })}
        </>
    );
}
