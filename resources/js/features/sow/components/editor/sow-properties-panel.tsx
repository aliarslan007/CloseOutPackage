import { BLACK_COLOR, COLOR_SWATCHES, WHITE_COLOR, clampOpacity, clampStrokeWidth } from '../../utils/annotation-defaults';
import type { Annotation } from '../../types/annotation.types';
import { useSowEditorStore } from '../../stores/sow-editor.store';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Pencil, Trash2, Type } from 'lucide-react';

export interface SowPropertiesPanelProps {
    baseWidth: number;
    onEditText: (id: string) => void;
}

/** Right-side panel shown when a shape is selected (§13.8). */
export function SowPropertiesPanel({ baseWidth, onEditText }: Readonly<SowPropertiesPanelProps>) {
    const node = useSowEditorStore((state) =>
        state.layer.nodes.find((n) => n.id === state.selectedId)
    );
    const updateNode = useSowEditorStore((state) => state.updateNode);
    const deleteNode = useSowEditorStore((state) => state.deleteNode);
    const setSelectedId = useSowEditorStore((state) => state.setSelectedId);

    if (!node) return null;

    const strokePx = Math.round(node.normStrokeWidth * baseWidth);
    const opacity = Math.round(node.opacity * 100);

    const patch = (partial: Partial<Annotation>) => updateNode(node.id, partial);

    return (
        <div className="w-full space-y-4 rounded-lg border border-border bg-background p-3">
            <div className="flex items-center justify-between">
                <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {node.type.replace('_', ' ')}
                </h4>
                <button
                    type="button"
                    title="Delete shape"
                    onClick={() => {
                        deleteNode(node.id);
                        setSelectedId(null);
                    }}
                    className="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                >
                    <Trash2 className="size-4" />
                </button>
            </div>

            <div className="space-y-1.5">
                <p className="text-[11px] font-medium text-muted-foreground">
                    {node.type === 'box' ? 'Border color' : 'Stroke color'}
                </p>
                <div className="flex flex-wrap gap-1.5">
                    {COLOR_SWATCHES.map((color) => (
                        <button
                            key={color}
                            type="button"
                            onClick={() =>
                                patch(
                                    node.type === 'box'
                                        ? { strokeColor: color, fillColor: WHITE_COLOR, textColor: BLACK_COLOR }
                                        : { strokeColor: color }
                                )
                            }
                            className={cn(
                                'size-6 rounded-full border-2',
                                node.strokeColor === color ? 'border-primary' : 'border-border'
                            )}
                            style={{ backgroundColor: color }}
                        />
                    ))}
                </div>
            </div>

            <div className="space-y-1.5">
                <p className="text-[11px] font-medium text-muted-foreground">
                    Stroke width — {strokePx}px
                </p>
                <input
                    type="range"
                    min={1}
                    max={8}
                    step={1}
                    value={strokePx}
                    onChange={(e) =>
                        patch({
                            normStrokeWidth: clampStrokeWidth(Number(e.target.value)) / baseWidth,
                        })
                    }
                    className="w-full"
                />
            </div>

            <div className="space-y-1.5">
                <p className="text-[11px] font-medium text-muted-foreground">
                    Opacity — {opacity}%
                </p>
                <input
                    type="range"
                    min={20}
                    max={100}
                    step={5}
                    value={opacity}
                    onChange={(e) => patch({ opacity: clampOpacity(Number(e.target.value) / 100) })}
                    className="w-full"
                />
            </div>

            {(node.type === 'box' || node.type === 'text_label') && (
                <Button
                    size="sm"
                    variant="outline"
                    className="w-full gap-1.5"
                    onClick={() => onEditText(node.id)}
                >
                    <Type className="size-3.5" />
                    {(node.text ?? '').trim() === '' ? 'Add text' : 'Edit text'}
                </Button>
            )}

            {(node.type === 'box' || node.type === 'text_label') && (node.text ?? '').trim() !== '' && (
                <div className="flex items-start gap-2 rounded-md bg-muted p-2">
                    <Pencil className="mt-0.5 size-3 shrink-0 text-muted-foreground" />
                    <p className="line-clamp-3 text-xs text-muted-foreground">{node.text}</p>
                </div>
            )}
        </div>
    );
}
