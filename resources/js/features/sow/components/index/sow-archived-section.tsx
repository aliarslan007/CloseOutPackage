import type { SowReport } from '@/features/sow/types/sow.types';
import { cn } from '@/lib/utils';
import { Archive, ChevronDown } from 'lucide-react';
import * as React from 'react';
import { SowCard } from './sow-card';

export interface SowArchivedSectionProps {
    reports: SowReport[];
    className?: string;
}

export function SowArchivedSection({ reports, className }: Readonly<SowArchivedSectionProps>) {
    const [isOpen, setIsOpen] = React.useState(false);

    if (reports.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Archived SOW Reports"
            className={cn(
                'rounded-sm border border-border/70 bg-card/60 shadow-2xs transition-colors',
                className
            )}
        >
            <button
                type="button"
                onClick={() => setIsOpen((prev) => !prev)}
                className="flex w-full cursor-pointer items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/40 rounded-sm"
                aria-expanded={isOpen}
            >
                <div className="flex items-center gap-2.5">
                    <div className="flex size-7 items-center justify-center rounded-sm bg-muted text-muted-foreground">
                        <Archive className="size-3.5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-semibold text-foreground">Archived SOWs</span>
                            <span className="rounded-full bg-muted px-1.5 py-0.2 font-mono text-[10px] font-medium text-muted-foreground tabular-nums">
                                {reports.length}
                            </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                            Read-only records stored for historical compliance
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-1.5 text-xs text-muted-foreground font-medium">
                    <span>{isOpen ? 'Collapse' : 'Expand'}</span>
                    <ChevronDown
                        className={cn(
                            'size-4 text-muted-foreground transition-transform duration-200',
                            isOpen && 'rotate-180'
                        )}
                    />
                </div>
            </button>

            {isOpen && (
                <div className="border-t border-border/50 p-3 pt-3.5">
                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                        {reports.map((report) => (
                            <SowCard key={report.id} report={report} className="rounded-sm" />
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}
