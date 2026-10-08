import { BLACK_COLOR, COLOR_SWATCHES, PURE_YELLOW_COLOR, WHITE_COLOR } from '../../utils/annotation-defaults';
import { useSowEditorStore } from '../../stores/sow-editor.store';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

const SWATCH_CLASS =
    'flex size-5.5 items-center justify-center rounded-full border-2 transition-transform hover:scale-110 cursor-pointer';

/** White and bright colors need a darker outline when inactive to stay visible. */
function swatchBorderClass(isActive: boolean, isWhite: boolean): string {
    if (isActive) return 'border-primary ring-2 ring-primary/40';
    return isWhite ? 'border-foreground/30' : 'border-border';
}

/**
 * §13 — Color swatches. Picking a color seeds the next shape and, when a shape
 * is already selected, recolors it immediately — previously a swatch clicked
 * beside a selected shape silently did nothing.
 */
export function SowColorPicker() {
    const activeColor = useSowEditorStore((state) => state.activeColor);
    const setActiveColor = useSowEditorStore((state) => state.setActiveColor);
    const selectedId = useSowEditorStore((state) => state.selectedId);
    const selectedType = useSowEditorStore(
        (state) => state.layer.nodes.find((node) => node.id === state.selectedId)?.type
    );
    const updateNode = useSowEditorStore((state) => state.updateNode);

    const chooseColor = (color: string) => {
        setActiveColor(color);
        if (!selectedId || !selectedType) return;

        if (selectedType === 'text_label') {
            updateNode(selectedId, { textColor: color });
            return;
        }
        if (selectedType === 'box') {
            updateNode(selectedId, {
                strokeColor: color,
                fillColor: WHITE_COLOR,
                textColor: BLACK_COLOR,
            });
            return;
        }
        updateNode(selectedId, { strokeColor: color, fillColor: color });
    };

    return (
        <div className="flex flex-col items-center gap-1 py-0.5">
            <span className="text-[9px] font-semibold uppercase leading-none text-muted-foreground">
                Color
            </span>
            <div className="grid grid-cols-2 gap-1.5">
                {COLOR_SWATCHES.map((color) => {
                    const isActive = activeColor === color;
                    const isWhite = color === WHITE_COLOR;
                    const isLight = isWhite || color === PURE_YELLOW_COLOR;
                    return (
                        <button
                            key={color}
                            type="button"
                            title={
                                selectedId
                                    ? `Apply ${color} to the selected shape`
                                    : `Color for new shapes: ${color}`
                            }
                            aria-label={`Color ${color}`}
                            aria-pressed={isActive}
                            onClick={() => chooseColor(color)}
                            style={{ backgroundColor: color }}
                            className={cn(SWATCH_CLASS, swatchBorderClass(isActive, isWhite))}
                        >
                            {isActive && (
                                <Check
                                    className={cn(
                                        'size-3',
                                        isLight ? 'text-foreground' : 'text-white'
                                    )}
                                    strokeWidth={3}
                                />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
