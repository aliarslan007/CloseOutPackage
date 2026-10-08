import * as React from 'react';
import { DraftStatus } from '@/hooks/use-draft';
import { Button } from '@/components/ui/button';
import { Cloud, CloudOff, RefreshCw, Save } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface DraftStatusBarProps {
    status: DraftStatus;
    lastSavedTime?: Date | null;
    onManualSave?: () => void;
    className?: string;
}

export function DraftStatusBar({
    status,
    lastSavedTime,
    onManualSave,
    className,
}: Readonly<DraftStatusBarProps>) {
    const renderStatusContent = () => {
        switch (status) {
            case 'saving':
                return (
                    <span className="flex items-center gap-1.5 text-info">
                        <RefreshCw className="size-3.5 animate-spin" />
                        Saving draft...
                    </span>
                );
            case 'saved':
                return (
                    <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <Cloud className="size-3.5" />
                        {lastSavedTime
                            ? `Draft saved at ${lastSavedTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                            : 'All changes saved'}
                    </span>
                );
            case 'offline_saved':
                return (
                    <span className="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                        <CloudOff className="size-3.5" />
                        Saved locally (offline)
                    </span>
                );
            case 'unsaved':
            default:
                return (
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <Save className="size-3.5" />
                        Unsaved changes
                    </span>
                );
        }
    };

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-3 text-xs px-3 py-1.5 rounded-lg border bg-card text-card-foreground',
                className
            )}
        >
            <div className="font-medium">{renderStatusContent()}</div>
            {onManualSave && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    onClick={onManualSave}
                    disabled={status === 'saving'}
                >
                    Save now
                </Button>
            )}
        </div>
    );
}

export default DraftStatusBar;
