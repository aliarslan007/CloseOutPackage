import { cn } from '@/lib/utils';
import { Bell, CheckCircle2, Headset, Hourglass } from 'lucide-react';
import * as React from 'react';
import type { EntityFeedbackRoundItem } from '../types/entity-feedback.types';
import {
    progressStepFor,
    reviewProgressSteps,
    reviewStatusCopy,
    type FeedbackViewerRole,
    type StatusTone,
} from '../utils/feedback-copy';

/**
 * "Where is this review and who has the ball" - one compact inline strip:
 * tone icon, three progress segments, and the current step's plain-language
 * label. It lives in the Customer Review heading row so the state reads at a
 * glance instead of in a card that pushes the conversation down. The full
 * headline and waiting-on text ride along as a tooltip.
 */

export interface FeedbackStatusHeaderProps {
    round: EntityFeedbackRoundItem;
    viewer: FeedbackViewerRole;
    className?: string;
}

const TONE_STYLES: Record<StatusTone, { icon: string; bar: string; Icon: typeof CheckCircle2 }> = {
    'needs-you': {
        icon: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
        bar: 'bg-amber-500',
        Icon: Bell,
    },
    working: {
        icon: 'bg-primary/15 text-primary',
        bar: 'bg-primary',
        Icon: Headset,
    },
    waiting: {
        icon: 'bg-muted text-muted-foreground',
        bar: 'bg-muted-foreground/40',
        Icon: Hourglass,
    },
    done: {
        icon: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        bar: 'bg-emerald-500',
        Icon: CheckCircle2,
    },
};

export function FeedbackStatusHeader({
    round,
    viewer,
    className,
}: Readonly<FeedbackStatusHeaderProps>) {
    const status = reviewStatusCopy(round, viewer);
    const steps = reviewProgressSteps(viewer);
    const current = Math.min(progressStepFor(round), steps.length - 1);
    const styles = TONE_STYLES[status.tone];
    const ToneIcon = styles.Icon;

    const tooltip =
        status.waitingOn === '' ? status.headline : `${status.headline} · ${status.waitingOn}`;

    return (
        <div
            role="status"
            aria-atomic
            title={tooltip}
            className={cn('flex min-w-0 flex-1 items-center gap-2', className)}
        >
            <span
                className={cn(
                    'flex size-6 shrink-0 items-center justify-center rounded-full',
                    styles.icon
                )}
            >
                <ToneIcon className="size-3.5" />
            </span>

            <ol
                className="flex shrink-0 items-center gap-1"
                aria-label={`Review progress, step ${current + 1} of ${steps.length}`}
            >
                {steps.map((label, index) => (
                    <li
                        key={label}
                        aria-hidden
                        className={cn(
                            'h-1.5 w-7 rounded-full transition-colors sm:w-9',
                            index <= current ? styles.bar : 'bg-border'
                        )}
                    />
                ))}
            </ol>

            <p className="truncate text-xs font-semibold text-muted-foreground">{steps[current]}</p>
        </div>
    );
}
