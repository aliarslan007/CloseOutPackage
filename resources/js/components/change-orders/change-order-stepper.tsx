import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import * as React from 'react';

export interface StepItem {
    id: number;
    title: string;
    description?: string;
}

export const CHANGE_ORDER_STEPS: StepItem[] = [
    { id: 1, title: 'Details' },
    { id: 2, title: 'Line Items' },
    { id: 3, title: 'Verizon Customers' },
    { id: 4, title: 'Review & Save' },
];

export interface ChangeOrderStepperProps {
    currentStep: number;
    onStepClick?: (step: number) => void;
    completedSteps?: number[];
    className?: string;
}

export function ChangeOrderStepper({
    currentStep,
    onStepClick,
    completedSteps = [],
    className,
}: Readonly<ChangeOrderStepperProps>) {
    return (
        <nav aria-label="Progress" className={cn('w-full', className)}>
            <ol className="relative flex flex-col space-y-6 sm:space-y-8">
                {CHANGE_ORDER_STEPS.map((step, index) => {
                    const isCurrent = currentStep === step.id;
                    const isCompleted = completedSteps.includes(step.id);
                    const isClickable = onStepClick !== undefined && (isCompleted || isCurrent || step.id <= currentStep);
                    const isLast = index === CHANGE_ORDER_STEPS.length - 1;

                    return (
                        <li key={step.id} className="relative flex items-start group">
                            {/* Vertical Line connecting steps */}
                            {!isLast && (
                                <div
                                    className={cn(
                                        'absolute left-4.5 top-9 -bottom-6 w-0.5 transition-colors',
                                        isCompleted ? 'bg-primary/80' : 'bg-border'
                                    )}
                                    aria-hidden="true"
                                />
                            )}

                            <button
                                type="button"
                                disabled={!isClickable}
                                onClick={() => isClickable && onStepClick?.(step.id)}
                                className={cn(
                                    'flex items-center gap-3.5 text-left w-full transition-all text-sm font-medium',
                                    isClickable ? 'cursor-pointer' : 'cursor-default'
                                )}
                            >
                                {/* Step Circle Indicator */}
                                <div
                                    className={cn(
                                        'size-9 rounded-full flex items-center justify-center text-xs font-bold transition-all shrink-0',
                                        isCurrent &&
                                            'bg-foreground text-background shadow-xs ring-4 ring-foreground/10',
                                        !isCurrent &&
                                            isCompleted &&
                                            'bg-primary text-primary-foreground',
                                        !isCurrent &&
                                            !isCompleted &&
                                            'border-2 border-border bg-background text-muted-foreground'
                                    )}
                                >
                                    {isCompleted && !isCurrent ? (
                                        <Check className="size-4 stroke-[3]" />
                                    ) : (
                                        <span>{step.id}</span>
                                    )}
                                </div>

                                {/* Step Title */}
                                <div className="flex flex-col">
                                    <span
                                        className={cn(
                                            'transition-colors',
                                            isCurrent && 'font-bold text-foreground',
                                            !isCurrent && isCompleted && 'text-foreground font-medium',
                                            !isCurrent && !isCompleted && 'text-muted-foreground'
                                        )}
                                    >
                                        {step.title}
                                    </span>
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
