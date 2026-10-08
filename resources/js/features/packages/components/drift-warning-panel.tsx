import { AlertTriangle } from 'lucide-react';
import type { DriftConflict } from '../types/drift.types';

interface DriftWarningPanelProps {
    readonly conflicts: readonly DriftConflict[];
    readonly onScrollToCategory: (stageId: number, categoryId: number) => void;
}

/**
 * Compact status strip for template drift that blocks submission. Renders a
 * single row (wraps on narrow screens) rather than a panel, so it never pushes
 * the builder below the fold. Each chip jumps to its category, where the
 * per-category "over limit" badge carries the detail.
 */
export function DriftWarningPanel({
    conflicts,
    onScrollToCategory,
}: Readonly<DriftWarningPanelProps>) {
    if (conflicts.length === 0) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 rounded-lg border border-warning/40 bg-warning/10 px-3 py-1.5"
        >
            <span className="inline-flex shrink-0 items-center gap-1.5">
                <AlertTriangle className="size-4 shrink-0 text-warning" />
                <span className="text-xs font-semibold text-foreground">
                    Template changes require your attention
                </span>
            </span>

            <span className="hidden h-4 w-px shrink-0 bg-warning/40 sm:block" aria-hidden />

            {conflicts.map((conflict) => (
                <DriftConflictChip
                    key={`${conflict.stageId}:${conflict.categoryId}`}
                    conflict={conflict}
                    onScrollToCategory={onScrollToCategory}
                />
            ))}
        </div>
    );
}

function DriftConflictChip({
    conflict,
    onScrollToCategory,
}: Readonly<{
    conflict: DriftConflict;
    onScrollToCategory: (stageId: number, categoryId: number) => void;
}>) {
    const excess = Math.max(0, conflict.currentPhotoCount - conflict.newValue);

    return (
        <button
            type="button"
            onClick={() => onScrollToCategory(conflict.stageId, conflict.categoryId)}
            title={`${conflict.stageName} › ${conflict.categoryName}: ${conflict.currentPhotoCount} uploaded, limit ${conflict.newValue}. Remove ${excess} photo${excess === 1 ? '' : 's'}.`}
            className="inline-flex max-w-full items-center gap-1.5 rounded-md border border-warning/40 bg-card px-2 py-0.5 text-[11px] font-medium transition-colors hover:border-warning hover:bg-warning/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <span className="truncate text-muted-foreground">{conflict.stageName}</span>
            <span className="truncate font-semibold text-foreground">{conflict.categoryName}</span>
            <span className="shrink-0 font-mono font-semibold tabular-nums text-foreground">
                {conflict.currentPhotoCount}/{conflict.newValue}
            </span>
        </button>
    );
}
