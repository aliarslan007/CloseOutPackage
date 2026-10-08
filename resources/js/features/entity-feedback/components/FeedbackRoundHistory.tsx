import { Badge } from '@/components/ui/badge';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { ChevronDown, History, MessageSquare, Trash2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import * as React from 'react';
import type { EntityFeedbackRoundItem } from '../types/entity-feedback.types';
import { historyStatusLabel, historyStatusTone, REVIEW_NOUN } from '../utils/feedback-copy';
import { FeedbackThreadPanel } from './FeedbackThreadPanel';
import { FeedbackThreadViewer } from './FeedbackThreadViewer';

/**
 * The record of every review round on this item, once the review is closed.
 *
 * An approved change order has no live round, so without this the customer loses
 * the whole conversation. It opens closed: the rounds are listed, nothing is
 * expanded, and one click opens that round's images and dialogue. A closed
 * review should feel like an archive you consult, not a form you fill in.
 */

export interface FeedbackRoundHistoryProps {
    rounds: EntityFeedbackRoundItem[];
    /** Admin-only. Omitted (or the round's `can_delete_round` is false) means no trash icon. */
    onDeleteRound?: (round: EntityFeedbackRoundItem) => void;
    /** `${type}:${id}` -> section name, so archived threads are named too. */
    itemNamesByKey?: Record<string, string>;
}

function formatWhen(value?: string | null): string {
    if (value == null || value === '') {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

export function FeedbackRoundHistory({
    rounds,
    onDeleteRound,
    itemNamesByKey,
}: Readonly<FeedbackRoundHistoryProps>) {
    // Nothing expanded by default: the archive is opt-in.
    const [openRoundId, setOpenRoundId] = React.useState<number | null>(null);
    const [activeThreadKey, setActiveThreadKey] = React.useState<string | null>(null);
    // The round queued for the shared confirm dialog, or null when closed.
    const [pendingDelete, setPendingDelete] = React.useState<EntityFeedbackRoundItem | null>(null);

    if (rounds.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 rounded-xl border border-border/70 bg-card/50 p-4">
            <div className="flex items-center gap-2">
                <span className="flex size-7 items-center justify-center rounded-md bg-muted">
                    <History className="size-3.5 text-muted-foreground" />
                </span>
                <div>
                    <h3 className="text-sm font-bold text-foreground">Past reviews</h3>
                    <p className="text-xs text-muted-foreground">
                        Finished rounds kept as an archive - the review in progress is always the
                        one above. {rounds.length} {rounds.length === 1 ? 'round' : 'rounds'} here;
                        open one to read it.
                    </p>
                </div>
            </div>

            <div className="divide-y divide-border/60 overflow-hidden rounded-lg border border-border/70">
                {rounds.map((round) => {
                    const isOpen = openRoundId === round.id;
                    const notes = round.notes ?? [];
                    const statusLabel = historyStatusLabel(round.status);
                    const isDone = historyStatusTone(round.status) === 'done';
                    const canDelete = round.can_delete_round && onDeleteRound;

                    return (
                        <div key={round.id}>
                            <div className="flex items-center gap-1 pr-1.5">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setOpenRoundId(isOpen ? null : round.id);
                                        setActiveThreadKey(null);
                                    }}
                                    aria-expanded={isOpen}
                                    className="flex min-w-0 flex-1 items-center gap-3 px-3.5 py-3 text-left transition-colors hover:bg-muted/40"
                                >
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold tabular-nums text-primary">
                                        {round.round_number}
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-semibold text-foreground">
                                                {REVIEW_NOUN} {round.round_number}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'h-5 gap-1 px-1.5 text-[10px] font-bold',
                                                    isDone
                                                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                        : 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                )}
                                            >
                                                {statusLabel}
                                            </Badge>
                                            <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
                                                <MessageSquare className="size-3" />
                                                {notes.length}{' '}
                                                {notes.length === 1 ? 'message' : 'messages'}
                                            </span>
                                        </span>
                                        <span className="mt-0.5 block text-[11px] text-muted-foreground">
                                            {formatWhen(round.created_at)}
                                        </span>
                                    </span>

                                    <ChevronDown
                                        className={`size-4 shrink-0 text-muted-foreground transition-transform ${
                                            isOpen ? 'rotate-180' : ''
                                        }`}
                                    />
                                </button>

                                {/* Always visible, never hover-gated - a delete that only
                                    appears on a mouseover is undiscoverable on a tablet,
                                    and this is admin-only anyway, so it can sit in the
                                    row without cluttering it for everyone else. */}
                                {canDelete && (
                                    <button
                                        type="button"
                                        aria-label={`Delete ${REVIEW_NOUN.toLowerCase()} ${round.round_number}`}
                                        title="Delete this past review"
                                        onClick={() => setPendingDelete(round)}
                                        className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-destructive/40"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                            </div>

                            {isOpen && (
                                <div className="space-y-4 border-t border-border/60 bg-muted/10 px-3.5 py-4">
                                    {notes.length > 0 ? (
                                        <FeedbackThreadViewer
                                            notes={notes}
                                            activeThreadKey={activeThreadKey}
                                            onActiveThreadChange={setActiveThreadKey}
                                            itemNamesByKey={itemNamesByKey}
                                            renderThread={(thread) => (
                                                <FeedbackThreadPanel thread={thread} isCustomer />
                                            )}
                                        />
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            No messages were left in this round.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* One shared confirm dialog for the whole archive, not one per row. */}
            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setPendingDelete(null);
                }}
                title="Delete past review"
                description={
                    pendingDelete
                        ? `Delete ${REVIEW_NOUN.toLowerCase()} ${pendingDelete.round_number}? This removes the whole conversation and every photo in it. This cannot be undone.`
                        : ''
                }
                confirmLabel="Delete"
                variant="destructive"
                onConfirm={() => {
                    if (pendingDelete) {
                        onDeleteRound?.(pendingDelete);
                    }
                    setPendingDelete(null);
                }}
            />
        </div>
    );
}
