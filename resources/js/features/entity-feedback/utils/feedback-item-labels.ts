import type { FeedbackThread } from './build-feedback-threads';

/**
 * Naming for what a conversation is about, plus the issue numbering that makes a
 * review containing several conversations readable at a glance. Split out of
 * feedback-copy.ts so each module stays small enough to hold in your head.
 */

/** Target types that mean "a whole section, not one image". */
export const SECTION_TARGET_TYPES = ['section', 'category'] as const;

export function isSectionThread(photoId: number | null, photoType?: string | null): boolean {
    if (photoId == null) {
        return true;
    }
    return (SECTION_TARGET_TYPES as readonly string[]).includes(photoType ?? '');
}

export function photoItemLabel(isAnnotated: boolean): string {
    return isAnnotated ? 'Marked-up photo' : 'Photo';
}

export function sectionItemLabel(categoryName?: string | null): string {
    return categoryName ? `Section: ${categoryName}` : 'General feedback';
}

/**
 * Name for a section/category thread.
 *
 * Legacy rows quote the same category id under either type - the portal saves a
 * whole-section note as 'section' with a category id - so try the stated type
 * first, then the other container spelling, before falling back to generic copy.
 * Keys are `${type}:${id}` because template sections, template categories and
 * category selections share overlapping numbers (category 2 "Grounding" vs
 * section 2 "Construction / Installation").
 */
function itemDisplayName(
    itemNamesByKey: Record<string, string> | undefined,
    photoType: string | null | undefined,
    photoId: number | null
): string | undefined {
    if (photoId == null) {
        return undefined;
    }
    for (const type of [photoType, 'category', 'section']) {
        const name = type ? itemNamesByKey?.[`${type}:${photoId}`] : undefined;
        if (name) {
            return name;
        }
    }
    return undefined;
}

/** Human name for a conversation's subject, e.g. "Marked-up photo". */
export function threadItemLabel(
    thread: Pick<FeedbackThread, 'photoId' | 'photoType' | 'isAnnotated'>,
    itemNamesByKey?: Record<string, string>
): string {
    if (isSectionThread(thread.photoId, thread.photoType)) {
        const name = itemDisplayName(itemNamesByKey, thread.photoType, thread.photoId);
        return sectionItemLabel(name ?? null);
    }
    // A photo thread names the section its image sits in - "Marked-up photo ·
    // Grounding" - so two photo issues raised in one review are tellable apart.
    // Exact `photo:` key only: a photo id and a category id are different things.
    const where = thread.photoId == null ? undefined : itemNamesByKey?.[`photo:${thread.photoId}`];
    return where
        ? `${photoItemLabel(thread.isAnnotated)} · ${where}`
        : photoItemLabel(thread.isAnnotated);
}

/** Header count so staff see the size of the job before opening anything. */
export function issueCountLabel(openCount: number, total: number): string {
    if (total === 0) {
        return '';
    }
    if (openCount === 0) {
        // Covers both endings of an issue: answered, or withdrawn by the customer.
        return 'Nothing left to resolve';
    }
    const noun = total === 1 ? 'issue' : 'issues';
    return openCount === total
        ? `${total} ${noun} to resolve`
        : `${openCount} of ${total} ${noun} to resolve`;
}
