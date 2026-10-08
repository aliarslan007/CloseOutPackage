import { cn } from '@/lib/utils';
import { PlusCircle, RefreshCw } from 'lucide-react';
import * as React from 'react';

export interface ChangeOrderPhotoOptionsProps {
    replaceInChangeOrder: boolean;
    addToChangeOrderPhotos: boolean;
    isLocked: boolean;
    isPhotoTarget: boolean;
    onToggleReplace: (val: boolean) => void;
    onToggleAdd: (val: boolean) => void;
}

export const ChangeOrderPhotoOptions = React.memo(function ChangeOrderPhotoOptions({
    replaceInChangeOrder,
    addToChangeOrderPhotos,
    isLocked,
    isPhotoTarget: _isPhotoTarget,
    onToggleReplace,
    onToggleAdd,
}: Readonly<ChangeOrderPhotoOptionsProps>) {
    return (
        <div className="pt-2 border-t border-border/50 grid gap-2 sm:grid-cols-2 text-xs">
            <label
                className={cn(
                    'flex items-start gap-2 rounded border p-2.5 cursor-pointer transition-all',
                    replaceInChangeOrder
                        ? 'border-foreground/40 bg-muted/40 text-foreground'
                        : 'border-border/80 hover:border-border text-muted-foreground'
                )}
            >
                <input
                    type="checkbox"
                    checked={replaceInChangeOrder}
                    onChange={(e) => {
                        onToggleReplace(e.target.checked);
                        if (e.target.checked) onToggleAdd(false);
                    }}
                    disabled={isLocked}
                    className="mt-0.5 size-3.5 rounded border-border text-primary focus:ring-primary/30"
                />
                <div className="space-y-0.5">
                    <span className="flex items-center gap-1 font-semibold text-[11px] text-foreground">
                        <RefreshCw className="size-3 text-primary" />
                        Replace in Change Order
                    </span>
                    <p className="text-[10px] text-muted-foreground leading-tight">
                        Updates the site evidence photo directly.
                    </p>
                </div>
            </label>

            <label
                className={cn(
                    'flex items-start gap-2 rounded border p-2.5 cursor-pointer transition-all',
                    addToChangeOrderPhotos
                        ? 'border-foreground/40 bg-muted/40 text-foreground'
                        : 'border-border/80 hover:border-border text-muted-foreground'
                )}
            >
                <input
                    type="checkbox"
                    checked={addToChangeOrderPhotos}
                    onChange={(e) => {
                        onToggleAdd(e.target.checked);
                        if (e.target.checked) onToggleReplace(false);
                    }}
                    disabled={isLocked}
                    className="mt-0.5 size-3.5 rounded border-border text-primary focus:ring-primary/30"
                />
                <div className="space-y-0.5">
                    <span className="flex items-center gap-1 font-semibold text-[11px] text-foreground">
                        <PlusCircle className="size-3 text-primary" />
                        Add to Supporting Photos
                    </span>
                    <p className="text-[10px] text-muted-foreground leading-tight">
                        Appends as new photo in gallery (max 20).
                    </p>
                </div>
            </label>
        </div>
    );
});
