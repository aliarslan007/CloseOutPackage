import * as React from 'react';

export interface UseAutoScrollToBottomOptions {
    /** Identity of the current conversation (thread.key). Changing it re-pins to newest. */
    scrollKey: string;
    /** Number of turns; an increase means a new message just arrived. */
    itemCount: number;
    /** Changes when a message the reader just wrote lands. Scrolling to your own
     *  message is never an interruption, so it follows even from the top of a
     *  long backlog. Pass null (the default) for anything else. */
    forceKey?: string | number | null;
    /** Distance from the bottom (px) within which the reader counts as following. */
    nearBottomThresholdPx?: number;
}

/**
 * Keeps a scrollable message list pinned to the newest turn without hijacking a
 * reader who scrolled up through history.
 *
 * - First mount and any conversation switch: jump straight to the newest turn.
 * - A new message on the open conversation: follow only if the reader was already
 *   near the bottom; otherwise leave their scroll exactly where it is.
 * - A message the reader just sent: always followed, wherever they had scrolled.
 * - Honors prefers-reduced-motion (instant instead of smooth).
 *
 * "Was near the bottom" is sampled while rendering - i.e. before React inserts
 * the new bubble - because a photo reply is far taller than the follow threshold,
 * and measuring after the insert would read a follower at the bottom as someone
 * who had scrolled away.
 *
 * Returns a ref to attach to the scrolling container.
 */
export function useAutoScrollToBottom<T extends HTMLElement>({
    scrollKey,
    itemCount,
    forceKey = null,
    nearBottomThresholdPx = 120,
}: UseAutoScrollToBottomOptions) {
    const ref = React.useRef<T | null>(null);
    const pinnedKey = React.useRef<string | null>(null);
    const seenForceKey = React.useRef<string | number | null>(forceKey);
    const followingRef = React.useRef(true);

    // Render-phase read of the *previous* DOM: scrollHeight here still excludes
    // the message being added in this commit.
    if (ref.current) {
        const el = ref.current;
        followingRef.current =
            el.scrollHeight - el.scrollTop - el.clientHeight <= nearBottomThresholdPx;
    }

    React.useLayoutEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }

        const switchingConversation = pinnedKey.current !== scrollKey;
        pinnedKey.current = scrollKey;

        // A newly sent message must come into view even when the reader was
        // scrolled up; someone else's reply must not yank them.
        const sentMessageLanded = forceKey !== null && forceKey !== seenForceKey.current;
        seenForceKey.current = forceKey;

        if (!switchingConversation && !sentMessageLanded && !followingRef.current) {
            // Reader scrolled up to re-read an older turn — leave them alone.
            return;
        }

        const reduceMotion =
            window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
        // Jump instantly when opening or switching a conversation - animating
        // the whole backlog every click is a slideshow, not a chat. Smooth only
        // for a new message arriving while the reader is already following.
        el.scrollTo({
            top: el.scrollHeight,
            behavior:
                switchingConversation || sentMessageLanded || reduceMotion ? 'auto' : 'smooth',
        });
    }, [scrollKey, itemCount, forceKey, nearBottomThresholdPx]);

    return ref;
}
