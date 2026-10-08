import * as React from 'react';
import { HelpCircle, Pencil, Plus, RotateCw, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export type FeedbackActionType =
    'replace_photo' | 'add_new_photo' | 'delete_photo' | 'update_note' | 'general';

export const ACTION_OPTIONS: Array<{
    id: FeedbackActionType;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
}> = [
    { id: 'replace_photo', label: 'Replace Photo', icon: RotateCw },
    { id: 'add_new_photo', label: 'Add Photo', icon: Plus },
    { id: 'delete_photo', label: 'Remove Photo', icon: Trash2 },
    { id: 'update_note', label: 'Update Note', icon: Pencil },
    { id: 'general', label: 'General Query', icon: HelpCircle },
];

export interface FeedbackActionSelectorProps {
    value: FeedbackActionType;
    onChange: (value: FeedbackActionType) => void;
    disabled?: boolean;
}

export function FeedbackActionSelector({
    value,
    onChange,
    disabled = false,
}: Readonly<FeedbackActionSelectorProps>) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mr-1">
                Action:
            </span>
            {ACTION_OPTIONS.map((opt) => {
                const Icon = opt.icon;
                const isSelected = value === opt.id;
                return (
                    <button
                        key={opt.id}
                        type="button"
                        onClick={() => onChange(opt.id)}
                        disabled={disabled}
                        className={cn(
                            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors border cursor-pointer',
                            isSelected
                                ? 'border-primary bg-primary text-primary-foreground shadow-2xs'
                                : 'border-border/80 bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'
                        )}
                    >
                        <Icon className="size-3" />
                        <span>{opt.label}</span>
                    </button>
                );
            })}
        </div>
    );
}
