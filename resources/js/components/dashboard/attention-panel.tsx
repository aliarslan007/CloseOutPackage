import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertOctagon, AlertTriangle, CheckCircle2, ChevronRight, Info, Sparkles } from 'lucide-react';
import { cn } from '@/lib/utils';

export type AttentionTone = 'danger' | 'warning' | 'info' | 'success';

export interface AttentionItem {
    key: string;
    label: string;
    count: number;
    href: string;
    tone: AttentionTone;
}

const toneStyles: Record<
    AttentionTone,
    { icon: React.ReactNode; rowHover: string; iconBox: string }
> = {
    danger: {
        icon: <AlertOctagon className="size-4" />,
        rowHover: 'hover:bg-destructive/5',
        iconBox: 'bg-destructive/10 text-destructive',
    },
    warning: {
        icon: <AlertTriangle className="size-4" />,
        rowHover: 'hover:bg-amber-500/5',
        iconBox: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    },
    info: {
        icon: <Info className="size-4" />,
        rowHover: 'hover:bg-primary/5',
        iconBox: 'bg-primary/10 text-primary',
    },
    success: {
        icon: <CheckCircle2 className="size-4" />,
        rowHover: 'hover:bg-emerald-500/5',
        iconBox: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    },
};

export function AttentionPanel({ items }: { items: AttentionItem[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <Sparkles className="size-4 text-primary" />
                    Needs your attention
                </CardTitle>
                <CardDescription>Things that are waiting on someone right now.</CardDescription>
            </CardHeader>
            <CardContent className="p-2">
                <ul className="divide-y divide-border/60">
                    {items.map((item) => {
                        const style = toneStyles[item.tone];
                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'group flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition-colors',
                                        style.rowHover
                                    )}
                                >
                                    <span className="flex min-w-0 items-center gap-3">
                                        <span
                                            className={cn(
                                                'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                style.iconBox
                                            )}
                                        >
                                            {style.icon}
                                        </span>
                                        <span className="truncate text-sm font-medium">
                                            {item.label}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1.5">
                                        <span className="text-lg font-bold tabular-nums">
                                            {item.count}
                                        </span>
                                        <ChevronRight className="size-4 text-muted-foreground transition-colors group-hover:text-foreground" />
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </CardContent>
        </Card>
    );
}

export default AttentionPanel;
