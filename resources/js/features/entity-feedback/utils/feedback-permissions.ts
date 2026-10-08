import type { EntityFeedbackRoundItem } from '../types/entity-feedback.types';

export function computeCanReply(
    round: EntityFeedbackRoundItem | null,
    isCustomer: boolean,
    isManager: boolean,
    // Technicians can't reply — kept for positional call-site compat.
    _isTechnician: boolean
): boolean {
    const hasOpen = round?.status === 'open';
    const isSubmitted = round?.status === 'submitted';
    const isResolved = round?.status === 'resolved';

    if (isCustomer) {
        return !round || hasOpen || isSubmitted || isResolved;
    }
    if (isManager) {
        return hasOpen || isSubmitted || isResolved;
    }
    // Technicians resolve the issue physically and declare done via
    // "Submit to Manager" (canSubmitToManager). They never send messages
    // in the customer <-> manager conversation, so canReply stays false.
    return false;
}

export function computePermissions(
    round: EntityFeedbackRoundItem | null,
    isCustomer: boolean,
    isManager: boolean,
    isTechnician: boolean,
    notesCount: number
) {
    const hasOpenRound = round !== null && round.status === 'open';
    const isSubmitted = round !== null && round.status === 'submitted';
    const isResolved = round !== null && round.status === 'resolved';
    const isAcknowledged = round?.manager_acknowledged_at != null;

    let canAnnotate = hasOpenRound;
    if (isCustomer) {
        canAnnotate = !round || hasOpenRound || isResolved;
    }

    const canReply = computeCanReply(round, isCustomer, isManager, isTechnician);
    const canSubmit = hasOpenRound && notesCount > 0;
    const canForwardTechnician = isManager && isSubmitted && isAcknowledged;
    const canResolve = (isManager || isTechnician) && isSubmitted;
    // No canAccept here on purpose: only the customer decides to close their
    // review, and FeedbackStatusActions owns that visibility directly, off the
    // live round status - a second flag here just drifted from it.
    const canSubmitToManager =
        isTechnician &&
        isSubmitted &&
        (round?.assigned_at != null || round?.assigned_to_user_id != null) &&
        round?.technician_submitted_at == null;
    const canAcknowledge = isManager && isSubmitted && !isAcknowledged;

    return {
        hasOpenRound,
        isSubmitted,
        isResolved,
        isAcknowledged,
        canAnnotate,
        canReply,
        canSubmit,
        canForwardTechnician,
        canResolve,
        canSubmitToManager,
        canAcknowledge,
    };
}
