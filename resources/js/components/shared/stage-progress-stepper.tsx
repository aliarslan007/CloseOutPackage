import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import * as React from 'react';

export type StageKey = 'pre_construction' | 'construction' | 'post_completion';

export interface StageStep {
    key: StageKey;
    label: string;
    description?: string;
}

export interface StageProgressStepperProps {
    currentStage: StageKey;
    completedStages?: StageKey[];
    onStageClick?: (stage: StageKey) => void;
    className?: string;
}

const DEFAULT_STAGES: StageStep[] = [
    {
        key: 'pre_construction',
        label: 'Pre Construction',
        description: 'Site info & initial photo capture',
    },
    {
        key: 'construction',
        label: 'Construction',
        description: 'Installation & progress photo capture',
    },
    {
        key: 'post_completion',
        label: 'Post Completion',
        description: 'Final photo completion & review',
    },
];

function getBadgeStyle(isCompleted: boolean, isCurrent: boolean): string {
    if (isCompleted) {
        return 'border-emerald-600 bg-emerald-600 text-white';
    }
    if (isCurrent) {
        return 'border-primary bg-primary text-primary-foreground shadow-sm';
    }
    return 'border-muted-foreground/30 bg-muted text-muted-foreground';
}

function getTextStyle(isCompleted: boolean, isCurrent: boolean): string {
    if (isCurrent) {
        return 'text-foreground';
    }
    if (isCompleted) {
        return 'text-emerald-600 dark:text-emerald-400';
    }
    return 'text-muted-foreground';
}

export function StageProgressStepper({
    currentStage,
    completedStages = [],
    onStageClick,
    className,
}: Readonly<StageProgressStepperProps>) {
    const stageOrder: StageKey[] = ['pre_construction', 'construction', 'post_completion'];
    const currentIndex = stageOrder.indexOf(currentStage);

    return (
        <div className={cn('w-full overflow-x-auto pb-1 scrollbar-none', className)}>
            <div className="flex items-center justify-between min-w-[500px] sm:min-w-0 py-2">
                {DEFAULT_STAGES.map((stage, idx) => {
                    const isCompleted = completedStages.includes(stage.key) || idx < currentIndex;
                    const isCurrent = stage.key === currentStage;
                    const isClickable = Boolean(onStageClick);

                    return (
                        <React.Fragment key={stage.key}>
                            <button
                                type="button"
                                disabled={!isClickable}
                                onClick={isClickable ? () => onStageClick?.(stage.key) : undefined}
                                className={cn(
                                    'flex flex-1 items-center gap-3 p-2 rounded-lg transition-colors text-left',
                                    isClickable && 'cursor-pointer hover:bg-accent/50',
                                    isCurrent && 'bg-accent/80 font-semibold'
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition-all',
                                        getBadgeStyle(isCompleted, isCurrent)
                                    )}
                                >
                                    {isCompleted ? <Check className="size-4" /> : idx + 1}
                                </span>
                                <span className="flex flex-col min-w-0">
                                    <span
                                        className={cn(
                                            'text-xs font-semibold truncate',
                                            getTextStyle(isCompleted, isCurrent)
                                        )}
                                    >
                                        {stage.label}
                                    </span>
                                    {stage.description && (
                                        <span className="text-[11px] text-muted-foreground truncate hidden md:inline">
                                            {stage.description}
                                        </span>
                                    )}
                                </span>
                            </button>
                            {idx < DEFAULT_STAGES.length - 1 && (
                                <div
                                    className={cn(
                                        'h-[2px] w-8 sm:w-12 mx-1 shrink-0 rounded-full transition-colors',
                                        idx < currentIndex ? 'bg-emerald-600' : 'bg-muted'
                                    )}
                                />
                            )}
                        </React.Fragment>
                    );
                })}
            </div>
        </div>
    );
}

export default StageProgressStepper;
