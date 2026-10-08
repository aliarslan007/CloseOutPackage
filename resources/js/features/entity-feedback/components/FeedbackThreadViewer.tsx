import { cn } from '@/lib/utils';
import * as React from 'react';
import { FolderTree } from 'lucide-react';
import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';
import { buildFeedbackThreads, type FeedbackThread } from '../utils/build-feedback-threads';
import { isSectionThread, issueCountLabel, threadItemLabel } from '../utils/feedback-copy';
import { FeedbackPhotoPlate } from './FeedbackPhotoPlate';
import { FeedbackThreadItemRow } from './FeedbackThreadItemRow';

/**
 * Item-first accordion: one row per conversation ("Marked-up photo",
 * "Section: Roof"), and the open one shows its photo and dialogue directly
 * below the row. One column, one click to read everything - no side-by-side
 * panels and no unlabeled thumbnail puzzle.
 */

export interface FeedbackThreadViewerProps {
    notes: EntityFeedbackNoteItem[];
    activeThreadKey: string | null;
    onActiveThreadChange: (key: string | null) => void;
    onJumpToPhoto?: (photoId: number, photoType?: string | null) => void;
    renderThread: (thread: FeedbackThread) => React.ReactNode;
    itemNamesByKey?: Record<string, string>;
}

function SectionThreadCallout({
    thread,
    itemNamesByKey,
}: Readonly<{
    thread: FeedbackThread;
    itemNamesByKey?: Record<string, string>;
}>) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-border/70 bg-muted/40 p-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <FolderTree className="size-4 text-primary" />
            </span>
            <div className="min-w-0">
                <p className="text-sm font-semibold text-foreground">
                    {threadItemLabel(thread, itemNamesByKey)}
                </p>
                <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                    This feedback is about the whole section, not a single photo. Read the
                    conversation and reply below.
                </p>
            </div>
        </div>
    );
}

function OpenedThreadBody({
    thread,
    onJumpToPhoto,
    itemNamesByKey,
    renderThread,
}: Readonly<{
    thread: FeedbackThread;
    onJumpToPhoto?: (photoId: number, photoType?: string | null) => void;
    itemNamesByKey?: Record<string, string>;
    renderThread: (thread: FeedbackThread) => React.ReactNode;
}>) {
    const canJump = onJumpToPhoto != null && thread.photoId != null;
    const isSection = isSectionThread(thread.photoId, thread.photoType);
    const showCallout = thread.imageUrl === null && isSection;
    const jumpHandler =
        canJump && !isSection
            ? () => onJumpToPhoto?.(thread.photoId!, thread.photoType)
            : undefined;
    // The plate is about ONE thing: the photo(s) the customer submitted as
    // feedback - their markup, or the original they marked up. Replacement
    // photos are staff answers and already live inside the chat bubbles, so
    // showing them here would duplicate the conversation. Deduped, in order.
    const images = [
        ...new Set(
            thread.notes
                .map((note) => note.annotated_image_url || note.original_image_url)
                .filter((url): url is string => Boolean(url))
        ),
    ];

    return (
        <div className="space-y-4 p-3 sm:p-4">
            {showCallout && (
                <SectionThreadCallout thread={thread} itemNamesByKey={itemNamesByKey} />
            )}

            {images.length > 0 && (
                <FeedbackPhotoPlate
                    key={thread.key}
                    images={images}
                    altText={`Photo for: ${threadItemLabel(thread, itemNamesByKey)}`}
                    onJumpToPhoto={jumpHandler}
                />
            )}

            {renderThread(thread)}
        </div>
    );
}

export const FeedbackThreadViewer = React.memo(function FeedbackThreadViewer({
    notes,
    activeThreadKey,
    onActiveThreadChange,
    onJumpToPhoto,
    renderThread,
    itemNamesByKey,
}: Readonly<FeedbackThreadViewerProps>) {
    // Oldest issue first: "Issue 1" is always the first thing raised, so the
    // numbers do not shuffle when a reply arrives or the page refetches.
    const threads = React.useMemo(
        () =>
            buildFeedbackThreads(notes).sort(
                (a, b) =>
                    new Date(a.root.created_at ?? 0).getTime() -
                    new Date(b.root.created_at ?? 0).getTime()
            ),
        [notes]
    );

    // One row open at a time. Nothing picked yet? Show the first so the page is
    // never blank - but only until the reader has clicked, so collapsing the
    // first issue is not silently undone by this default.
    const hasInteracted = React.useRef(false);

    if (threads.length === 0) {
        return null;
    }

    const openCount = threads.filter((thread) => thread.hasOpenQuestion).length;

    const active =
        threads.find((t) => t.key === activeThreadKey) ??
        (hasInteracted.current ? null : threads[0]);

    const handleToggle = (key: string) => {
        hasInteracted.current = true;
        // Clicking the open row closes it; clicking another opens that one.
        onActiveThreadChange(active?.key === key ? null : key);
        // Let the expand commit, then bring the tapped row back into view so
        // its answer is visible without hunting down a long list.
        window.requestAnimationFrame(() => {
            document
                .getElementById(`feedback-item-${CSS.escape(key)}`)
                ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    };

    return (
        <div className="space-y-2.5">
            {/* More than one conversation in a review is the confusing case: say
                up front how many issues there are and how many are still open. */}
            {threads.length > 1 && (
                <p className="px-1 text-xs font-semibold text-muted-foreground">
                    {issueCountLabel(openCount, threads.length)}
                </p>
            )}
            {threads.map((thread, index) => {
                const isOpen = active?.key === thread.key;

                return (
                    <section
                        key={thread.key}
                        id={`feedback-item-${thread.key}`}
                        className={cn(
                            'overflow-hidden rounded-xl border bg-card transition-colors',
                            isOpen
                                ? 'border-primary/40 shadow-sm ring-1 ring-primary/20'
                                : 'border-border/60'
                        )}
                    >
                        <FeedbackThreadItemRow
                            thread={thread}
                            index={index}
                            isOpen={isOpen}
                            onToggle={() => handleToggle(thread.key)}
                            itemNamesByKey={itemNamesByKey}
                        />

                        {isOpen && (
                            <div className="border-t border-border/50">
                                <OpenedThreadBody
                                    thread={thread}
                                    onJumpToPhoto={onJumpToPhoto}
                                    itemNamesByKey={itemNamesByKey}
                                    renderThread={renderThread}
                                />
                            </div>
                        )}
                    </section>
                );
            })}
        </div>
    );
});
