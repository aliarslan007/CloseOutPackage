import * as React from 'react';
import { UserAvatar } from '@/components/shared/user-avatar';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Wrench } from 'lucide-react';

export interface ManagerInstructionEntry {
    id: string;
    timestamp?: string;
    authorName?: string;
    body: string;
}

interface HeaderMeta {
    timestamp?: string;
    authorName?: string;
}

function parseHeaderMeta(headerContent: string): HeaderMeta {
    const trimmed = headerContent.trim();
    if (!trimmed.includes(' - ')) {
        return { timestamp: trimmed };
    }

    const [timestampPart, ...authorParts] = trimmed.split(' - ');
    return {
        timestamp: timestampPart.trim(),
        authorName: authorParts.join(' - ').trim(),
    };
}

function pushCompletedEntry(
    entry: ManagerInstructionEntry | null,
    entries: ManagerInstructionEntry[]
): void {
    if (!entry) return;
    const body = entry.body.trim();
    if (body) {
        entries.push({ ...entry, body });
    }
}

export function parseManagerInstructions(rawText?: string | null): ManagerInstructionEntry[] {
    if (!rawText?.trim()) {
        return [];
    }

    const lines = rawText.trim().split('\n');
    const entries: ManagerInstructionEntry[] = [];
    let currentEntry: ManagerInstructionEntry | null = null;
    let index = 0;

    for (const line of lines) {
        const headerMatch = line.match(/^\[(.*?)\]:$/);
        if (headerMatch) {
            pushCompletedEntry(currentEntry, entries);
            const { timestamp, authorName } = parseHeaderMeta(headerMatch[1]);
            currentEntry = {
                id: `inst-${index++}`,
                timestamp,
                authorName,
                body: '',
            };
            continue;
        }

        if (currentEntry) {
            currentEntry.body = currentEntry.body ? `${currentEntry.body}\n${line}` : line;
        } else {
            currentEntry = {
                id: `inst-${index++}`,
                body: line,
            };
        }
    }

    pushCompletedEntry(currentEntry, entries);
    return entries;
}

export interface ManagerInstructionsCardProps {
    instructions?: string | null;
    className?: string;
}

export function ManagerInstructionsCard({
    instructions,
    className,
}: Readonly<ManagerInstructionsCardProps>) {
    const entries = React.useMemo(() => parseManagerInstructions(instructions), [instructions]);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'rounded-xl border-2 border-amber-300 bg-amber-50 p-4 space-y-3 dark:border-amber-700/50 dark:bg-amber-950/20',
                className
            )}
        >
            <div className="flex items-center justify-between gap-2 border-b border-amber-200 pb-2 dark:border-amber-800/40">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm">
                        <Wrench className="size-4" />
                    </span>
                    <span className="text-sm font-bold tracking-tight text-amber-900 dark:text-amber-100">
                        Instructions for the field team
                    </span>
                </div>
                <Badge
                    variant="outline"
                    className="border-amber-300 bg-amber-100 text-amber-800 text-[10px] font-semibold dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
                >
                    {entries.length} {entries.length === 1 ? 'Instruction' : 'Instructions'}
                </Badge>
            </div>

            <div className="max-h-52 overflow-y-auto space-y-2.5 pr-1 scrollbar-thin">
                {entries.map((entry) => (
                    <div
                        key={entry.id}
                        className="rounded-lg border border-amber-200/70 bg-white/80 p-3 text-sm space-y-2 shadow-2xs dark:border-amber-800/30 dark:bg-card/70"
                    >
                        {(entry.authorName || entry.timestamp) && (
                            <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground border-b border-border/50 pb-1">
                                {entry.authorName && (
                                    <div className="flex items-center gap-1.5">
                                        <UserAvatar
                                            name={entry.authorName}
                                            className="size-4 text-[8px]"
                                        />
                                        <span className="font-semibold text-foreground">
                                            {entry.authorName}
                                        </span>
                                    </div>
                                )}
                                {entry.timestamp && (
                                    <span className="text-muted-foreground/80">
                                        · {entry.timestamp}
                                    </span>
                                )}
                            </div>
                        )}
                        <p className="whitespace-pre-wrap leading-relaxed text-foreground [overflow-wrap:anywhere]">
                            {entry.body}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}
