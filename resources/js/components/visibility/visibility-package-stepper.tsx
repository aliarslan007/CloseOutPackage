import { cn } from '@/lib/utils';
import { StageKey } from '@/components/shared/stage-progress-stepper';
import { STAGE_LABELS } from '@/components/templates/template-types';
import { VisibilityPackageStage } from '@/components/visibility/visibility-project-types';
import { Check, ClipboardCheck } from 'lucide-react';
import * as React from 'react';

const STAGE_ORDER: StageKey[] = ['pre_construction', 'construction', 'post_completion'];

interface StageStatusMeta {
    label: string;
    /** Tailwind classes for the status pill. */
    pillClass: string;
    /** Tailwind classes for the connector line after this step. */
    lineClass: string;
    done: boolean;
    active: boolean;
}

const STAGE_STATUS_META: Record<string, StageStatusMeta> = {
    approved: {
        label: 'Approved',
        pillClass: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        lineClass: 'bg-emerald-500',
        done: true,
        active: false,
    },
    submitted: {
        label: 'Submitted',
        pillClass: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30',
        lineClass: 'bg-blue-500',
        done: true,
        active: false,
    },
    draft_saved: {
        label: 'Draft Saved',
        pillClass: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30',
        lineClass: 'bg-sky-500',
        done: false,
        active: true,
    },
    in_progress: {
        label: 'In Progress',
        pillClass: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
        lineClass: 'bg-amber-500',
        done: false,
        active: true,
    },
    not_started: {
        label: 'Not Started',
        pillClass: 'bg-muted text-muted-foreground border-border',
        lineClass: 'bg-muted',
        done: false,
        active: false,
    },
};

function getStageMeta(status: string): StageStatusMeta {
    return STAGE_STATUS_META[status] ?? STAGE_STATUS_META.not_started;
}

function StageNode({
    stageKey,
    status,
    index,
}: Readonly<{
    stageKey: StageKey;
    status: string;
    index: number;
}>) {
    const meta = getStageMeta(status);
    const label = STAGE_LABELS[stageKey] ?? stageKey.replace(/_/g, ' ');

    return (
        <React.Fragment>
            <div className="flex flex-col items-center gap-1.5 min-w-0 flex-1">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold transition-colors',
                        meta.done && 'border-emerald-500 bg-emerald-500 text-white',
                        meta.active && 'border-primary bg-primary/10 text-primary',
                        !meta.done && !meta.active && 'border-muted-foreground/30 bg-muted text-muted-foreground'
                    )}
                >
                    {meta.done ? <Check className="size-4" /> : index + 1}
                </span>
                <span className="text-[11px] font-semibold text-foreground text-center leading-tight">
                    {label}
                </span>
                <span
                    className={cn(
                        'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium whitespace-nowrap',
                        meta.pillClass
                    )}
                >
                    {meta.label}
                </span>
            </div>
            <div
                className={cn(
                    'h-[2px] w-6 sm:w-10 mt-2 shrink-0 rounded-full transition-colors',
                    meta.lineClass
                )}
            />
        </React.Fragment>
    );
}

function ChecklistNode({
    answered,
    total,
}: Readonly<{
    answered: number;
    total: number;
}>) {
    const complete = total > 0 && answered === total;

    return (
        <div className="flex flex-col items-center gap-1.5 min-w-0 flex-1">
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold transition-colors',
                    complete && 'border-emerald-500 bg-emerald-500 text-white',
                    !complete && 'border-muted-foreground/30 bg-muted text-muted-foreground'
                )}
            >
                <ClipboardCheck className="size-4" />
            </span>
            <span className="text-[11px] font-semibold text-foreground text-center leading-tight">
                Checklist
            </span>
            <span
                className={cn(
                    'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium whitespace-nowrap',
                    complete
                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                        : 'bg-muted text-muted-foreground border-border'
                )}
            >
                {total === 0 ? 'No Items' : `${answered}/${total} Answered`}
            </span>
        </div>
    );
}

export interface VisibilityPackageStepperProps {
    stages?: VisibilityPackageStage[];
    checklistAnswered?: number;
    checklistTotal?: number;
    className?: string;
}

export function VisibilityPackageStepper({
    stages = [],
    checklistAnswered = 0,
    checklistTotal = 0,
    className,
}: Readonly<VisibilityPackageStepperProps>) {
    const stageByKey = new Map(stages.map((stage) => [stage.stage, stage]));

    return (
        <div className={cn('w-full overflow-x-auto pb-1 scrollbar-none', className)}>
            <div className="flex items-start justify-between min-w-[480px] sm:min-w-0 py-2 gap-1.5 sm:gap-2">
                {STAGE_ORDER.map((stageKey, index) => (
                    <StageNode
                        key={stageKey}
                        stageKey={stageKey}
                        status={stageByKey.get(stageKey)?.status ?? 'not_started'}
                        index={index}
                    />
                ))}
                <ChecklistNode answered={checklistAnswered} total={checklistTotal} />
            </div>
        </div>
    );
}
