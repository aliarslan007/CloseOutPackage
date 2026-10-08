import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';

/**
 * Notes grouped into one conversation per image.
 *
 * A customer marking up photo 3 and then replying about photo 3 is talking to
 * the manager about photo 3 — not opening two unrelated queries. Presenting each
 * note as its own card made that invisible, so the thread is the unit of display
 * and the note is just a turn in it.
 */

export interface FeedbackThread {
    /** Stable key for the image this thread is about, or a section when there is none. */
    key: string;
    /** The photo the conversation is on, null for a section-level thread. */
    photoId: number | null;
    photoType: string | null;
    /** The marked-up image if the customer drew on it, else the original. */
    imageUrl: string | null;
    /** True when any turn in this thread carries a customer annotation. */
    isAnnotated: boolean;
    /** Turns in chronological order. */
    notes: EntityFeedbackNoteItem[];
    /** The turn that started the thread. */
    root: EntityFeedbackNoteItem;
    /** True once the manager has answered every turn. */
    isFullyAnswered: boolean;
    /** True when the customer has a turn still unanswered, and has not withdrawn it. */
    hasOpenQuestion: boolean;
    /** True when the customer said they do not want this one resolved. */
    isWithdrawn: boolean;
}

export function threadKeyFor(note: EntityFeedbackNoteItem): string {
    if (note.source_photo_id == null) {
        return `section:${note.source_photo_type ?? 'none'}:${note.feedback_round_id}`;
    }

    return `${note.source_photo_type ?? 'photo'}:${note.source_photo_id}`;
}

export function buildFeedbackThreads(notes: EntityFeedbackNoteItem[]): FeedbackThread[] {
    const byKey = new Map<string, FeedbackThread>();

    for (const note of notes) {
        const key = threadKeyFor(note);
        const existing = byKey.get(key);

        if (existing) {
            existing.notes.push(note);

            // Prefer the marked-up image: only the first turn usually has it.
            if (note.annotated_image_url) {
                existing.imageUrl = note.annotated_image_url;
                existing.isAnnotated = true;
            }

            continue;
        }

        byKey.set(key, {
            key,
            photoId: note.source_photo_id ?? null,
            photoType: note.source_photo_type ?? null,
            imageUrl: note.annotated_image_url || note.original_image_url || null,
            isAnnotated: Boolean(note.annotated_image_url),
            notes: [note],
            root: note,
            isFullyAnswered: false,
            hasOpenQuestion: false,
            isWithdrawn: false,
        });
    }

    return [...byKey.values()].map((thread) => ({
        ...thread,
        notes: [...thread.notes].sort(
            (a, b) => new Date(a.created_at ?? 0).getTime() - new Date(b.created_at ?? 0).getTime()
        ),
        isFullyAnswered: thread.notes.every((n) => n.is_resolved),
        // A withdrawn turn is not a question any more, whatever its answer state.
        hasOpenQuestion: thread.notes.some((n) => !n.is_resolved && !n.is_withdrawn),
        // Withdrawal is stamped on the whole conversation, so any turn carrying it
        // means the issue was withdrawn - and a trimmed payload (technician view)
        // still reads correctly off the turns it kept.
        isWithdrawn: thread.notes.some((n) => n.is_withdrawn),
    }));
}
