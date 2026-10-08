import { cn } from '@/lib/utils';
import { Ban, CheckCircle2, ChevronDown, FolderTree } from 'lucide-react';
import * as React from 'react';
import type { FeedbackThread } from '../utils/build-feedback-threads';
import {
    isSectionThread,
    NOTE_STATUS_LABELS,
    requestedActionLabel,
    threadItemLabel,
} from '../utils/feedback-copy';

/**
 * One clickable row per conversation in the item-first accordion:
 * a human name, and how many messages sit inside. No thumbnail - the
 * photo is shown full-size the moment the row opens, twice is noise.
 * The row is the navigation; the conversation opens below it.
 */

export interface FeedbackThreadItemRowProps {
    thread: FeedbackThread;
    /** Position in the review, shown as "Issue 1", "Issue 2", so a review with
     *  several conversations is countable at a glance. */
    index?: number;
    isOpen: boolean;
    onToggle: () => void;
    itemNamesByKey?: Record<string, string>;
}

/**
 * Plain-language state of the whole conversation. A withdrawn issue reads
 * quieter than an answered one on purpose: the customer dropped the ask, nobody
 * completed it, and the row stays as the record of why.
 */
function threadStatus(thread: FeedbackThread): { label: string; icon: 'ban' | 'check' | null } {
    if (thread.isWithdrawn) {
        return { label: NOTE_STATUS_LABELS.withdrawn, icon: 'ban' };
    }
    if (thread.isFullyAnswered) {
        return { label: NOTE_STATUS_LABELS.resolved, icon: 'check' };
    }

    return { label: NOTE_STATUS_LABELS.open, icon: null };
}

/**
 * Colour for an issue's leading number chip. Each state reads at a glance and
 * the open issue is filled solid, so several issues in one review never blur
 * into a single block.
 */
function chipTone(isOpen: boolean, isWithdrawn: boolean, isResolved: boolean): string {
    if (isOpen) return 'bg-primary text-primary-foreground';
    if (isWithdrawn) return 'bg-muted text-muted-foreground';
    if (isResolved) return 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400';
    return 'bg-primary/10 text-primary';
}

export const FeedbackThreadItemRow = React.memo(function FeedbackThreadItemRow({
    thread,
    index,
    isOpen,
    onToggle,
    itemNamesByKey,
}: Readonly<FeedbackThreadItemRowProps>) {
    const label = threadItemLabel(thread, itemNamesByKey);
    const isSection = isSectionThread(thread.photoId, thread.photoType);
    // The customer took this one off the list: the row goes quiet and stops
    // counting as work, while the history underneath stays readable.
    const isWithdrawn = thread.isWithdrawn;
    const status = threadStatus(thread);
    // Root note's ask, e.g. "Replace photo", so closed rows still teach the
    // difference between three look-alike photo threads.
    const actionHint = requestedActionLabel(thread.root.requested_action);
    const messageCount = thread.notes.length;

    // The leading chip carries each issue's identity and state colour, so two
    // issues in one review never blur into a single block.
    const chipClass = cn(
        'flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-colors',
        chipTone(isOpen, isWithdrawn, status.icon === 'check')
    );

    return (
        <button
            type="button"
            onClick={onToggle}
            aria-expanded={isOpen}
            className={cn(
                'flex w-full items-center gap-3 px-3 py-3 text-left transition-colors sm:px-4',
                isOpen ? 'bg-primary/5' : 'hover:bg-muted/20'
            )}
        >
            {/* Numbered when the row sits in a live review; the archived
                (un-numbered) rows keep their plain section icon. */}
            {typeof index === 'number' && <span className={chipClass}>{index + 1}</span>}
            {typeof index !== 'number' && isSection && (
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border/70 bg-muted/50">
                    <FolderTree className="size-4 text-primary" />
                </span>
            )}

            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block truncate text-sm font-semibold',
                        isWithdrawn ? 'text-muted-foreground' : 'text-foreground'
                    )}
                >
                    {label}
                </span>
                <span className="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                    {status.icon === 'ban' && <Ban className="size-3.5" aria-hidden />}
                    {status.icon === 'check' && (
                        <CheckCircle2
                            className="size-3.5 text-emerald-600 dark:text-emerald-400"
                            aria-hidden
                        />
                    )}
                    <span>{status.label}</span>
                    <span aria-hidden>·</span>
                    <span>
                        {messageCount} {messageCount === 1 ? 'message' : 'messages'}
                    </span>
                    {actionHint && (
                        <>
                            <span aria-hidden>·</span>
                            <span>{actionHint}</span>
                        </>
                    )}
                </span>
            </span>

            <ChevronDown
                aria-hidden
                className={cn(
                    'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                    isOpen && 'rotate-180'
                )}
            />
        </button>
    );
});
