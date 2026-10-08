import type { SowReport } from '../../types/sow.types';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, Undo2 } from 'lucide-react';
import * as React from 'react';

export interface SowReopenBannerProps {
    report: SowReport;
    className?: string;
}

export function SowReopenBanner({ report, className }: Readonly<SowReopenBannerProps>) {
    const [expanded, setExpanded] = React.useState(false);

    if (!report.reopen_notes && !report.reopened_at) {
        return null;
    }

    if (report.status === 'published' || report.status === 'archived') {
        return null;
    }

    const managerName = report.reopened_by_user?.name;
    const formattedDate = report.reopened_at
        ? new Date(report.reopened_at).toLocaleDateString(undefined, {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          })
        : null;

    const hasLongNotes = Boolean(report.reopen_notes && report.reopen_notes.length > 180);

    return (
        <div
            className={cn(
                'rounded-lg border border-border/80 bg-muted/40 px-3.5 py-2 text-xs transition-colors',
                className
            )}
        >
            <div className="flex items-start gap-2.5">
                <Undo2 className="size-3.5 shrink-0 text-primary mt-0.5" />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span className="font-semibold text-foreground">
                            Report Reopened for Corrections
                        </span>
                        {(formattedDate || managerName) && (
                            <span className="text-[11px] text-muted-foreground">
                                · {formattedDate}
                                {managerName ? ` by ${managerName}` : ''}
                            </span>
                        )}
                    </div>
                    {report.reopen_notes && (
                        <div className="mt-0.5">
                            <p
                                className={cn(
                                    'text-foreground/90 leading-snug break-words',
                                    !expanded && 'line-clamp-2'
                                )}
                            >
                                <span className="font-medium text-foreground">Instructions: </span>
                                {report.reopen_notes}
                            </p>
                            {hasLongNotes && (
                                <button
                                    type="button"
                                    onClick={() => setExpanded((prev) => !prev)}
                                    className="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline mt-1 cursor-pointer"
                                >
                                    <span>{expanded ? 'Show less' : 'Show full instructions'}</span>
                                    {expanded ? (
                                        <ChevronUp className="size-3" />
                                    ) : (
                                        <ChevronDown className="size-3" />
                                    )}
                                </button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
