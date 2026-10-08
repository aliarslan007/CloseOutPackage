import { clampOpacity, clampStrokeWidth } from '../../utils/annotation-defaults';
import type { EditorTool } from '../../types/annotation.types';
import { useSowEditorStore } from '../../stores/sow-editor.store';
import { SowColorPicker } from './sow-color-picker';
import { cn } from '@/lib/utils';
import {
    ArrowRight,
    CircleDot,
    Eraser,
    Hand,
    MousePointer2,
    Slash,
    Square,
} from 'lucide-react';

const TOOLS: Array<{ id: EditorTool; label: string; icon: React.ReactNode }> = [
    { id: 'select', label: 'Select / Move (V)', icon: <MousePointer2 className="size-4" /> },
    { id: 'pan', label: 'Pan (H)', icon: <Hand className="size-4" /> },
    { id: 'box', label: 'Callout Box (R)', icon: <Square className="size-4" /> },
    { id: 'arrow', label: 'Arrow (A)', icon: <ArrowRight className="size-4" /> },
    { id: 'dashed_line', label: 'Dashed Path (L)', icon: <Slash className="size-4" /> },
    { id: 'dot', label: 'Dot / Pin (D)', icon: <CircleDot className="size-4" /> },
    { id: 'eraser', label: 'Eraser (E)', icon: <Eraser className="size-4" /> },
];

const INACTIVE_TOOL_CLASS = 'text-muted-foreground hover:bg-muted hover:text-foreground';
const TOOL_SLIDER_CLASS = 'h-8 w-3 cursor-pointer [writing-mode:vertical-lr] [direction:rtl] accent-primary';

/**
 * §13 — Professional single-column toolbar docked left:
 * Cleanly aligned tool selector, color swatches, and vertical stroke & opacity
 * controls matching the height of the center render box.
 */
export function SowToolbar({ className }: { className?: string } = {}) {
    const activeTool = useSowEditorStore((state) => state.activeTool);
    const setTool = useSowEditorStore((state) => state.setTool);
    const activeStroke = useSowEditorStore((state) => state.activeStroke);
    const setActiveStroke = useSowEditorStore((state) => state.setActiveStroke);
    const activeOpacity = useSowEditorStore((state) => state.activeOpacity);
    const setActiveOpacity = useSowEditorStore((state) => state.setActiveOpacity);

    return (
        <div className={`flex w-14 shrink-0 flex-col items-center gap-1 select-none rounded-lg border border-border bg-background p-1.5 py-2 shadow-xs overflow-hidden ${className ?? ''}`}>
            {/* Tool buttons */}
            <div className="flex flex-col items-center gap-0.5">
                {TOOLS.map((tool) => (
                    <button
                        key={tool.id}
                        type="button"
                        title={tool.label}
                        aria-label={tool.label}
                        aria-pressed={activeTool === tool.id}
                        onClick={() => setTool(tool.id)}
                        className={cn(
                            'flex size-7 items-center justify-center rounded-md transition-colors cursor-pointer',
                            activeTool === tool.id
                                ? 'bg-primary text-primary-foreground shadow-xs'
                                : INACTIVE_TOOL_CLASS
                        )}
                    >
                        {tool.icon}
                    </button>
                ))}
            </div>

            <div className="my-0.5 h-px w-8 bg-border" />

            {/* Color swatches — seed new shapes, and recolor the current selection */}
            <SowColorPicker />

            <div className="my-0.5 h-px w-8 bg-border" />

            {/* Stroke width */}
            <div className="flex flex-col items-center gap-0.5">
                <button
                    type="button"
                    title={`Stroke width: ${activeStroke}px`}
                    aria-label={`Stroke width: ${activeStroke}px`}
                    className="flex flex-col items-center justify-center gap-0.5 rounded-md p-0.5 hover:bg-muted"
                >
                    <div
                        className="rounded-full bg-foreground"
                        style={{ width: Math.min(14, 4 + activeStroke * 1.2), height: 2.5 }}
                    />
                    <span className="text-[9px] font-semibold text-muted-foreground leading-none">{activeStroke}px</span>
                </button>
                <input
                    type="range"
                    min={1}
                    max={8}
                    step={1}
                    value={activeStroke}
                    onChange={(e) => setActiveStroke(clampStrokeWidth(Number(e.target.value)))}
                    className={TOOL_SLIDER_CLASS}
                    title={`Stroke width: ${activeStroke}px (1–8px)`}
                    aria-label={`Stroke width: ${activeStroke}px`}
                />
            </div>

            <div className="my-0.5 h-px w-8 bg-border" />

            {/* Opacity */}
            <div className="flex flex-col items-center gap-0.5">
                <button
                    type="button"
                    title={`Opacity: ${Math.round(activeOpacity * 100)}%`}
                    aria-label={`Opacity: ${Math.round(activeOpacity * 100)}%`}
                    className="flex flex-col items-center justify-center gap-0.5 rounded-md p-0.5 hover:bg-muted"
                >
                    <div
                        className="rounded-full border border-foreground"
                        style={{ width: 10, height: 10, opacity: activeOpacity }}
                    />
                    <span className="text-[9px] font-semibold text-muted-foreground leading-none">{Math.round(activeOpacity * 100)}%</span>
                </button>
                <input
                    type="range"
                    min={20}
                    max={100}
                    step={5}
                    value={Math.round(activeOpacity * 100)}
                    onChange={(e) => setActiveOpacity(clampOpacity(Number(e.target.value) / 100))}
                    className={TOOL_SLIDER_CLASS}
                    title={`Opacity: ${Math.round(activeOpacity * 100)}% (20–100%)`}
                    aria-label={`Opacity: ${Math.round(activeOpacity * 100)}%`}
                />
            </div>
        </div>
    );
}
