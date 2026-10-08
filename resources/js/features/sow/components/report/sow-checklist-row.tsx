import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Pencil, Trash2 } from 'lucide-react';
import * as React from 'react';
import type { SowChecklistEntry } from '../../types/sow.types';

export const SOW_CHECKLIST_EXPLANATION_LIMIT = 1000;

export interface SowChecklistRowProps {
    entry: SowChecklistEntry;
    index: number;
    canEdit: boolean;
    onSetResponse: (id: number, response: SowChecklistEntry['response']) => void;
    onSetExplanation: (id: number, explanation: string) => void;
    onEdit: (entry: SowChecklistEntry) => void;
    onDelete: (entry: SowChecklistEntry) => void;
}

const RESPONSE_OPTIONS = [
    { value: 'yes', label: 'YES' },
    { value: 'no', label: 'NO' },
    { value: 'n_a', label: 'N/A' },
] as const;

/** Read-only answer styling — the finalized checklist is presented, not edited. */
const RESPONSE_META: Record<
    NonNullable<SowChecklistEntry['response']>,
    { label: string; variant: 'success' | 'destructive' | 'secondary' }
> = {
    yes: { label: 'Yes', variant: 'success' },
    no: { label: 'No', variant: 'destructive' },
    n_a: { label: 'N/A', variant: 'secondary' },
};

export function SowChecklistRow({
    entry,
    index,
    canEdit,
    onSetResponse,
    onSetExplanation,
    onEdit,
    onDelete,
}: Readonly<SowChecklistRowProps>) {
    const explanationLength = entry.explanation?.length ?? 0;
    const isNearLimit = explanationLength > 900;

    return (
        <div className="rounded-lg border border-border/70 bg-card p-3.5 transition-colors hover:border-border">
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div className="space-y-1 min-w-0 flex-1">
                    <div className="flex items-start gap-2">
                        <span className="shrink-0 text-xs font-semibold tabular-nums text-muted-foreground mt-0.5">
                            {index + 1}.
                        </span>
                        <div className="space-y-1">
                            <p className="text-xs font-medium text-foreground leading-snug">
                                {entry.label}
                            </p>
                            <div className="flex items-center gap-1.5 flex-wrap">
                                {entry.is_required && (
                                    <Badge
                                        variant="secondary"
                                        className="text-[10px] px-1.5 py-0 font-normal"
                                    >
                                        Required
                                    </Badge>
                                )}
                                {entry.has_explain_field && (
                                    <Badge
                                        variant="outline"
                                        className="text-[10px] px-1.5 py-0 font-normal text-muted-foreground"
                                    >
                                        Explanation on No
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {canEdit ? (
                    <div className="flex items-center gap-2 self-end sm:self-start shrink-0">
                        <div className="flex gap-1 bg-muted/30 p-0.5 rounded-md border border-border/50">
                            {RESPONSE_OPTIONS.map((opt) => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() =>
                                        onSetResponse(
                                            entry.id,
                                            entry.response === opt.value ? null : opt.value
                                        )
                                    }
                                    className={cn(
                                        'h-7 px-2.5 rounded text-[11px] font-semibold transition-colors',
                                        entry.response === opt.value
                                            ? 'bg-primary text-primary-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                    )}
                                >
                                    {opt.label}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center gap-1 border-l border-border/50 pl-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 text-muted-foreground hover:text-foreground"
                                onClick={() => onEdit(entry)}
                                title="Edit question"
                            >
                                <Pencil className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 text-muted-foreground hover:text-destructive"
                                onClick={() => onDelete(entry)}
                                title="Delete question"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        </div>
                    </div>
                ) : (
                    <div className="self-end sm:self-start shrink-0">
                        {entry.response ? (
                            <Badge variant={RESPONSE_META[entry.response].variant}>
                                {RESPONSE_META[entry.response].label}
                            </Badge>
                        ) : (
                            <span className="text-[11px] text-muted-foreground">
                                Not answered
                            </span>
                        )}
                    </div>
                )}
            </div>

            {entry.response === 'no' && entry.has_explain_field && (
                <div className="mt-3 pt-2.5 border-t border-border/50 space-y-1.5">
                    {canEdit ? (
                        <>
                            <div className="flex items-center justify-between gap-2">
                                <label
                                    htmlFor={`sow-explain-${entry.id}`}
                                    className="text-[11px] font-medium text-destructive flex items-center gap-1"
                                >
                                    Explanation required for &quot;No&quot;:
                                </label>
                                <span
                                    className={cn(
                                        'text-[10px] tabular-nums',
                                        isNearLimit
                                            ? 'text-destructive font-semibold'
                                            : 'text-muted-foreground'
                                    )}
                                >
                                    {explanationLength} / {SOW_CHECKLIST_EXPLANATION_LIMIT}{' '}
                                    characters
                                </span>
                            </div>
                            <Textarea
                                id={`sow-explain-${entry.id}`}
                                value={entry.explanation ?? ''}
                                onChange={(e) =>
                                    onSetExplanation(
                                        entry.id,
                                        e.target.value.slice(0, SOW_CHECKLIST_EXPLANATION_LIMIT)
                                    )
                                }
                                maxLength={SOW_CHECKLIST_EXPLANATION_LIMIT}
                                placeholder="Explain reason for answering No (max 1000 characters)..."
                                rows={2}
                                className="text-xs resize-none"
                            />
                        </>
                    ) : (
                        <>
                            <p className="text-[11px] font-medium text-muted-foreground">
                                Explanation for &quot;No&quot;:
                            </p>
                            {entry.explanation ? (
                                <p className="text-xs leading-relaxed text-foreground whitespace-pre-wrap">
                                    {entry.explanation}
                                </p>
                            ) : (
                                <p className="text-xs italic text-muted-foreground">
                                    No explanation provided.
                                </p>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
