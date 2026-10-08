import type { ChangeOrder } from './change-order-types';

/**
 * Preconditions for sending a change order to customers.
 *
 * `SendChangeOrderAction` is the authority — it refuses the send and flashes an
 * error. These mirror its guards so the button can explain itself *before* the
 * round trip instead of failing after it. Keep the two in step.
 */
export const SEND_BLOCKERS = {
    noIncludedItems: 'Add at least one included line item before sending.',
    noRecipients: 'Add a customer recipient to this change order before sending.',
} as const;

/**
 * Why this change order cannot be sent yet, or null when it can.
 *
 * Reads `recipients_count` as well as the loaded `recipients` so callers that
 * only receive a summary payload are not wrongly reported as blocked.
 */
export function sendBlocker(changeOrder: ChangeOrder): string | null {
    const hasIncludedItem = (changeOrder.items ?? []).some((item) => item.is_included);

    if (!hasIncludedItem) {
        return SEND_BLOCKERS.noIncludedItems;
    }

    const recipientCount = changeOrder.recipients?.length ?? changeOrder.recipients_count ?? 0;

    if (recipientCount === 0) {
        return SEND_BLOCKERS.noRecipients;
    }

    return null;
}
