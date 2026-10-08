import { PageProps } from '@/types/inertia';
import { usePage } from '@inertiajs/react';
import * as React from 'react';
import type { EntityFeedbackNoteItem, PhotoActionOptions } from '../types/entity-feedback.types';
import type { FeedbackThread } from '../utils/build-feedback-threads';
import { DEFAULT_FEEDBACK_MESSAGE_LIMIT } from '../utils/feedback-limits';
import { threadItemLabel } from '../utils/feedback-copy';
import { useAutoScrollToBottom } from '../hooks/use-auto-scroll-to-bottom';
import { FeedbackThreadComposer } from './FeedbackThreadComposer';
import { FeedbackThreadMessageItem } from './FeedbackThreadMessageItem';
import { FeedbackThreadWithdrawAction } from './FeedbackThreadWithdrawAction';

/**
 * The conversation for one item, as a plain chronological feed - newest turn
 * last, like any chat. Staff answers read as normal messages; no diagram-style
 * nesting that makes a layman hunt for "which reply belongs to which note".
 */

export interface FeedbackThreadPanelProps {
    thread: FeedbackThread;
    maxLength?: number;
    isStaff?: boolean;
    isCustomer?: boolean;
    isEditable?: boolean;
    entityType?: string;
    itemNamesByKey?: Record<string, string>;
    onDeleteNote?: (note: EntityFeedbackNoteItem) => void;
    onReply?: (
        body: string,
        replacementFile?: File | null,
        options?: PhotoActionOptions
    ) => Promise<void> | void;
    canReply?: boolean;
    isSubmitting?: boolean;
    /** Customer only: offer "Withdraw this issue" on a live thread. */
    canWithdraw?: boolean;
    onWithdraw?: () => void;
}

/**
 * Newest page first; older turns revealed on demand so a pathological thread
 * never mounts all its bubbles at once. ponytail: windowing, not virtualizing -
 * reach for @tanstack/react-virtual only if scroll must stay smooth across
 * thousands of mounted rows.
 */
const EARLIER_PAGE_SIZE = 50;

export const FeedbackThreadPanel = React.memo(function FeedbackThreadPanel({
    thread,
    maxLength,
    isStaff = false,
    isCustomer = false,
    isEditable = false,
    entityType,
    itemNamesByKey,
    onDeleteNote,
    onReply,
    canReply = false,
    isSubmitting = false,
    canWithdraw = false,
    onWithdraw,
}: Readonly<FeedbackThreadPanelProps>) {
    const { props: pageProps } = usePage<PageProps>();
    const currentUserId = pageProps.auth?.user?.id;
    const limit: number =
        maxLength ??
        (typeof pageProps.feedbackMessageLimit === 'number'
            ? pageProps.feedbackMessageLimit
            : DEFAULT_FEEDBACK_MESSAGE_LIMIT);

    const label = threadItemLabel(thread, itemNamesByKey);

    // Open at the newest turn and follow new replies, without yanking a reader
    // who scrolled up to re-read history - except into your own just-sent
    // message, which you always expect to land in view wherever you had scrolled.
    const newestNote = thread.notes[thread.notes.length - 1];
    const scrollRef = useAutoScrollToBottom<HTMLDivElement>({
        scrollKey: thread.key,
        itemCount: thread.notes.length,
        forceKey:
            newestNote && newestNote.created_by === (currentUserId ?? null) ? newestNote.id : null,
    });

    // Newest turns are what the reader came for; older ones sit behind a
    // "show earlier" step so the DOM never carries the whole backlog.
    const [visibleCount, setVisibleCount] = React.useState(EARLIER_PAGE_SIZE);
    const hiddenCount = Math.max(0, thread.notes.length - visibleCount);

    // A withdrawn issue is closed by the customer, so its history is read-only for
    // everybody — no deleting a message out of a conversation nobody may act on.
    const messagesEditable = isEditable && !thread.isWithdrawn;

    return (
        <div className="space-y-3">
            {/* Fixed-height message area: a long thread scrolls inside its own box
                instead of stretching the whole page. no-scrollbar hides the chrome
                but keeps the wheel/trackpad scroll; overscroll-contain stops the
                list from hijacking the page scroll once it hits an end. The
                composer below stays pinned, so the tools never scroll out of reach. */}
            <div
                ref={scrollRef}
                className="no-scrollbar max-h-[26rem] space-y-3 overflow-y-auto overscroll-contain"
            >
                {hiddenCount > 0 && (
                    <div className="flex justify-center">
                        <button
                            type="button"
                            onClick={() => setVisibleCount((c) => c + EARLIER_PAGE_SIZE)}
                            className="rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            Show earlier messages ({hiddenCount})
                        </button>
                    </div>
                )}
                <ol className="space-y-3">
                    {thread.notes.slice(hiddenCount).map((note, offset) => {
                        const index = hiddenCount + offset;
                        return (
                            <li key={note.id}>
                                <FeedbackThreadMessageItem
                                    note={note}
                                    currentUserId={currentUserId}
                                    isStaff={isStaff}
                                    viewerIsCustomer={isCustomer}
                                    grouped={
                                        thread.notes[index - 1]?.created_by === note.created_by
                                    }
                                    isEditable={messagesEditable}
                                    onDeleteNote={onDeleteNote}
                                />
                            </li>
                        );
                    })}
                </ol>
            </div>

            {/* A withdrawn issue is off the list, so the conversation is closed to
                everybody - no reply box for staff, and none for the customer
                either. The server refuses the same way, so a stale tab cannot
                write past the withdrawal. */}
            {canReply &&
                onReply &&
                (thread.isWithdrawn ? (
                    <p className="rounded-lg border border-dashed border-border bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground">
                        This issue was withdrawn, so its conversation is closed. A new review is
                        needed to raise it again.
                    </p>
                ) : (
                    <FeedbackThreadComposer
                        targetLabel={label}
                        maxLength={limit}
                        isStaff={isStaff}
                        isSubmitting={isSubmitting}
                        isPhotoTarget={thread.photoId != null}
                        entityType={entityType}
                        onReply={onReply}
                    />
                ))}

            {/* Already withdrawn says nothing more - the row above carries the
                label, and the thread is closed below. */}
            {canWithdraw && onWithdraw && !thread.isWithdrawn && (
                <FeedbackThreadWithdrawAction
                    targetLabel={label}
                    onWithdraw={onWithdraw}
                    isSubmitting={isSubmitting}
                />
            )}
        </div>
    );
});
