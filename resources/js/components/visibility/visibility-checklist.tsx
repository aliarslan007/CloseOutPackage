import { cn } from '@/lib/utils';
import { VisibilityChecklistItem } from '@/components/visibility/visibility-project-types';
import { CheckCircle2, ClipboardCheck, MinusCircle, XCircle } from 'lucide-react';
import * as React from 'react';

export interface VisibilityChecklistProps {
    items?: VisibilityChecklistItem[];
    className?: string;
}

export function VisibilityChecklist({ items = [], className }: Readonly<VisibilityChecklistProps>) {
    if (items.length === 0) {
        return null;
    }

    const answered = items.filter((item) => item.answer !== null).length;
    const complete = answered === items.length;

    return (
        <div className={cn('space-y-2', className)}>
            <div className="flex items-center justify-between gap-2">
                <p className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    <ClipboardCheck className="size-3.5" />
                    Checklist
                </p>
                <span
                    className={cn(
                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold',
                        complete
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                    )}
                >
                    {complete ? 'Complete' : `${answered}/${items.length} answered`}
                </span>
            </div>

            <div className="divide-y divide-border/60 rounded-md border border-border/70 overflow-hidden bg-card/40">
                {items.map((item) => (
                    <ChecklistRow key={item.id} item={item} />
                ))}
            </div>
        </div>
    );
}

function ChecklistRow({ item }: Readonly<{ item: VisibilityChecklistItem }>) {
    const { answer, explain, quantity } = item;
    const label = item.checklist_item?.label ?? `Item #${item.id}`;

    const iconClass = cn(
        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
        answer === true && 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        answer === false && 'bg-red-500/15 text-red-600 dark:text-red-400',
        answer === null && 'bg-muted text-muted-foreground'
    );
    const answerPillClass = cn(
        'shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-semibold',
        answer === true && 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        answer === false && 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400',
        answer === null && 'border-border bg-muted text-muted-foreground'
    );

    let answerIcon = <MinusCircle className="size-3.5" />;
    let answerLabel = 'Pending';

    if (answer === true) {
        answerIcon = <CheckCircle2 className="size-3.5" />;
        answerLabel = 'Yes';
    } else if (answer === false) {
        answerIcon = <XCircle className="size-3.5" />;
        answerLabel = 'No';
    }

    return (
        <div className="flex items-start gap-3 px-3 py-2">
            <span className={iconClass}>{answerIcon}</span>
            <div className="min-w-0 flex-1">
                <p className="text-[11px] leading-snug text-foreground">{label}</p>
                {answer === null && (
                    <p className="text-[10px] text-muted-foreground">Not answered yet</p>
                )}
                {answer === false && explain && (
                    <p className="mt-0.5 text-[10px] leading-snug text-red-600/90 dark:text-red-400/90">
                        {explain}
                    </p>
                )}
                {!item.is_na && answer !== null && quantity != null && (
                    <p className="mt-0.5 text-[10px] leading-snug text-muted-foreground">
                        Quantity: {quantity}
                    </p>
                )}
            </div>
            <span className={answerPillClass}>{answerLabel}</span>
        </div>
    );
}
