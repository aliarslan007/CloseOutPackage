import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Camera, CheckCircle2, ShieldCheck, Wrench } from 'lucide-react';
import { cn } from '@/lib/utils';
import { StageGroup } from '../types';

interface CustomerStagePipelineProps {
    stageGroups: StageGroup[];
    totalPhotosCount: number;
}

export function CustomerStagePipeline({ stageGroups, totalPhotosCount }: Readonly<CustomerStagePipelineProps>) {
    const preCount = React.useMemo(() => {
        const group = stageGroups.find((g) => g.key === 'pre_construction');
        if (!group) return 0;
        let sum = 0;
        group.categories.forEach((list) => {
            sum += list.length;
        });
        return sum;
    }, [stageGroups]);

    const constCount = React.useMemo(() => {
        const group = stageGroups.find((g) => g.key === 'construction');
        if (!group) return 0;
        let sum = 0;
        group.categories.forEach((list) => {
            sum += list.length;
        });
        return sum;
    }, [stageGroups]);

    const postCount = React.useMemo(() => {
        const group = stageGroups.find((g) => g.key === 'post_completion');
        if (!group) return 0;
        let sum = 0;
        group.categories.forEach((list) => {
            sum += list.length;
        });
        return sum;
    }, [stageGroups]);

    const steps = [
        {
            num: 1,
            label: 'Pre Construction',
            icon: <Camera className="size-4" />,
            count: preCount,
            isDone: preCount > 0,
            activeColor: 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
        },
        {
            num: 2,
            label: 'Construction / Installation',
            icon: <Wrench className="size-4" />,
            count: constCount,
            isDone: constCount > 0,
            activeColor: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
        },
        {
            num: 3,
            label: 'Post Completion',
            icon: <CheckCircle2 className="size-4" />,
            count: postCount,
            isDone: postCount > 0,
            activeColor: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        },
    ];

    return (
        <div className="rounded-xl border border-border/80 bg-card p-3.5 shadow-2xs space-y-2 text-xs">
            <div className="flex items-center justify-between border-b border-border/60 pb-2">
                <span className="font-bold uppercase tracking-wider text-muted-foreground text-[11px] flex items-center gap-1.5">
                    <ShieldCheck className="size-3.5 text-primary" />
                    3-Stage Closeout Deliverable Status
                </span>
                <Badge variant="outline" className="font-mono text-[10px]">
                    {totalPhotosCount} Approved Deliverables Total
                </Badge>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
                {steps.map((step) => (
                    <div
                        key={step.num}
                        className={cn(
                            'rounded-lg border p-2.5 flex items-center gap-3 transition-colors',
                            step.isDone
                                ? step.activeColor
                                : 'border-border/60 bg-muted/20 text-muted-foreground'
                        )}
                    >
                        <div
                            className={cn(
                                'size-8 rounded-full flex items-center justify-center shrink-0 font-bold text-xs',
                                step.isDone ? 'bg-background shadow-xs' : 'bg-muted'
                            )}
                        >
                            {step.isDone ? <CheckCircle2 className="size-4 text-emerald-500" /> : step.num}
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="font-bold text-xs truncate">{step.label}</p>
                            <p className="text-[10px] opacity-80">
                                {step.isDone ? `${step.count} Photos Approved` : 'Pending Review'}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default CustomerStagePipeline;
