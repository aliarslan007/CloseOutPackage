import * as React from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface StatsCardProps {
    title: string;
    value: string | number;
    description?: string;
    icon?: React.ReactNode;
    trend?: {
        value: string;
        positive?: boolean;
    };
    className?: string;
    /** Tailwind background class for a colored top bar (e.g. "bg-chart-3"). */
    accentClassName?: string;
    /**
     * Turns the card into a button — used where a metric also acts as a filter
     * toggle (e.g. the customer portal KPI row). Styling stays identical.
     */
    onClick?: () => void;
    /** Highlights an interactive card as the active selection. */
    selected?: boolean;
}

export function StatsCard({
    title,
    value,
    description,
    icon,
    trend,
    className,
    accentClassName,
    onClick,
    selected = false,
}: StatsCardProps) {
    const content = (
        <>
            {accentClassName && <div className={cn('h-1 w-full', accentClassName)} />}
            <CardContent className="p-6">
                <div className="flex items-center justify-between space-x-4">
                    <div className="space-y-1">
                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            {title}
                        </p>
                        <div className="flex items-baseline space-x-2">
                            <span className="text-2xl font-bold tracking-tight">{value}</span>
                            {trend && (
                                <span
                                    className={cn(
                                        'text-xs font-semibold',
                                        trend.positive ? 'text-success' : 'text-destructive'
                                    )}
                                >
                                    {trend.positive ? '+' : ''}{trend.value}
                                </span>
                            )}
                        </div>
                        {description && (
                            <p className="text-xs text-muted-foreground">{description}</p>
                        )}
                    </div>
                    {icon && (
                        <div className="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                            {icon}
                        </div>
                    )}
                </div>
            </CardContent>
        </>
    );

    if (onClick) {
        return (
            <button
                type="button"
                onClick={onClick}
                aria-pressed={selected}
                className={cn(
                    'w-full cursor-pointer overflow-hidden rounded-xl border bg-card text-left text-card-foreground shadow-sm transition-all',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
                    selected && 'border-primary/50 ring-2 ring-primary/25',
                    className
                )}
            >
                {content}
            </button>
        );
    }

    return <Card className={cn('overflow-hidden shadow-sm', className)}>{content}</Card>;
}

export default StatsCard;
