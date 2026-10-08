import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import type { SowImageGroup } from '../../types/sow.types';

export interface SowImageGroupActionBarProps {
    selectedCount: number;
    groups: SowImageGroup[];
    busy: boolean;
    onMoveTo: (groupId: number | null) => void;
    onClear: () => void;
}

/** Batch actions for the photos currently selected on the board. */
export function SowImageGroupActionBar({
    selectedCount,
    groups,
    busy,
    onMoveTo,
    onClear,
}: Readonly<SowImageGroupActionBarProps>) {
    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 px-3 py-2">
            <span className="text-xs font-medium text-foreground">{selectedCount} selected</span>
            <span className="text-xs text-muted-foreground">Move to:</span>
            {groups.map((group) => (
                <Button
                    key={group.id}
                    size="sm"
                    variant="outline"
                    className="h-7 text-xs"
                    disabled={busy}
                    onClick={() => onMoveTo(group.id)}
                >
                    {group.name}
                </Button>
            ))}
            <Button
                size="sm"
                variant="outline"
                className="h-7 text-xs"
                disabled={busy}
                onClick={() => onMoveTo(null)}
            >
                Ungrouped
            </Button>
            <Button size="sm" variant="ghost" className="ml-auto h-7 gap-1 text-xs" onClick={onClear}>
                <X className="size-3" /> Clear
            </Button>
        </div>
    );
}
