import * as React from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import type {
    EntityFeedbackNoteItem,
    EntityFeedbackRoundItem,
    FeedbackEntityType,
} from '../types/entity-feedback.types';
import { postRoundAction } from '../utils/feedback-actions';
import { computePermissions } from '../utils/feedback-permissions';
import { useEntityFeedbackNoteActions } from './use-entity-feedback-note-actions';
import { useEntityFeedbackPoll } from './use-entity-feedback-poll';

export interface UseEntityFeedbackOptions {
    entityType: FeedbackEntityType;
    entityId: number;
    round: EntityFeedbackRoundItem | null;
    token?: string | null;
    isManager?: boolean;
    isCustomer?: boolean;
    isTechnician?: boolean;
    pollIntervalMs?: number | null;
    pollOnlyKeys?: string[];
    enablePolling?: boolean;
}

export function useEntityFeedback({
    entityType,
    entityId,
    round,
    token = null,
    isManager = false,
    isCustomer = false,
    isTechnician = false,
    pollIntervalMs,
    pollOnlyKeys,
    enablePolling = true,
}: Readonly<UseEntityFeedbackOptions>) {
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    /*
     * Poll until the round is accepted — the only terminal state.
     *
     * Resolved is NOT the end. When the manager resolves the thread the customer
     * still has to accept it or push back (a note on a resolved round reopens
     * it), so stopping at `resolved` would hide the resolution and the pending
     * action until a manual refresh. Gating on `accepted` alone is what makes the
     * customer's real-time reply on the package page work.
     *
     * With no round at all there is nothing to watch, so that also stops. When a
     * round is opened — by a customer or a manager — the prop arrives and polling
     * resumes on its own.
     *
     * `enablePolling` stays as the hard kill switch for read-only surfaces.
     *
     * Once the customer withdraws every issue, the round is inert: staff cannot
     * answer or resolve a withdrawn thread, and no new activity can arrive until
     * the customer writes again (which re-renders and resumes polling on its own).
     * So there is nothing left to watch.
     */
    const roundNotes = round?.notes ?? [];
    const everyIssueWithdrawn = roundNotes.length > 0 && roundNotes.every((n) => n.is_withdrawn);
    const shouldPoll = round !== null && round.status !== 'accepted' && !everyIssueWithdrawn;

    useEntityFeedbackPoll({
        entityType,
        pollIntervalMs,
        pollOnlyKeys,
        enabled: enablePolling && shouldPoll,
    });

    const notes = React.useMemo(() => round?.notes ?? [], [round]);
    // Still awaiting an answer: a withdrawn issue is off the list, so it is not
    // open work for anybody.
    const unresolvedNotes = React.useMemo(
        () => notes.filter((n) => !n.is_resolved && !n.is_withdrawn),
        [notes]
    );

    const perms = React.useMemo(
        () => computePermissions(round, isCustomer, isManager, isTechnician, notes.length),
        [round, isCustomer, isManager, isTechnician, notes.length]
    );

    const findNoteForPhoto = React.useCallback(
        (photoId?: number | null, photoUrl?: string | null): EntityFeedbackNoteItem | null => {
            if (!photoId && !photoUrl) return null;
            return (
                notes.find(
                    (n) =>
                        (photoId && n.source_photo_id === photoId) ||
                        (photoUrl && n.original_image_url === photoUrl)
                ) ?? null
            );
        },
        [notes]
    );

    const { saveAnnotationNote, editNote, deleteNote, resolveNote, withdrawIssue } =
        useEntityFeedbackNoteActions({
            round,
            token,
            setIsSubmitting,
        });

    // Erasing a whole past review (round + every message and photo in it) is
    // admin-only, decided by the backend policy - this hook just calls the route
    // and lets the server refuse anybody else.
    const deleteRound = React.useCallback((round: EntityFeedbackRoundItem) => {
        setIsSubmitting(true);
        router.delete(route('entity-feedback.rounds.delete', { round: round.id }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Review deleted.'),
            onError: () => toast.error('Failed to delete review.'),
            onFinish: () => setIsSubmitting(false),
        });
    }, []);

    const submitRound = React.useCallback(() => {
        if (!round) return;
        setIsSubmitting(true);
        const routeName = token
            ? 'change-orders.token.feedback.submit'
            : 'entity-feedback.rounds.submit';
        const params = token ? { token, round: round.id } : { round: round.id };
        postRoundAction(
            routeName,
            params,
            {},
            'Failed to submit feedback.',
            () => setIsSubmitting(false),
            'Feedback submitted successfully.'
        );
    }, [round, token]);

    const forwardToTechnician = React.useCallback(
        async (data: { assignedToUserId: number | null; instructions: string }) => {
            if (!round) return;
            setIsSubmitting(true);
            postRoundAction(
                'entity-feedback.rounds.forward-technician',
                round.id,
                data,
                'Failed to assign feedback.',
                () => setIsSubmitting(false),
                'Feedback assigned to field technician.'
            );
        },
        [round]
    );

    const submitToManager = React.useCallback(() => {
        if (!round) return;
        setIsSubmitting(true);
        postRoundAction(
            'entity-feedback.rounds.submit-manager',
            round.id,
            {},
            'Failed to submit revisions to manager.',
            () => setIsSubmitting(false),
            'Revisions submitted to manager for review.'
        );
    }, [round]);

    const resolveAll = React.useCallback(
        (generalResponse?: string) => {
            if (!round) return;
            setIsSubmitting(true);
            postRoundAction(
                'entity-feedback.rounds.resolve-all',
                round.id,
                { general_response: generalResponse },
                'Please ensure all notes have a response.',
                () => setIsSubmitting(false),
                'Feedback resolved and sent to customer.'
            );
        },
        [round]
    );

    const openRound = React.useCallback(
        (managerInstructions?: string) => {
            setIsSubmitting(true);
            postRoundAction(
                'entity-feedback.rounds.open',
                {},
                {
                    entity_type: entityType,
                    entity_id: entityId,
                    manager_instructions: managerInstructions ?? null,
                },
                'Could not open the review round.',
                () => setIsSubmitting(false),
                'Review round opened.'
            );
        },
        [entityType, entityId]
    );

    const acceptRound = React.useCallback(() => {
        if (!round) return;
        setIsSubmitting(true);
        const routeName = token
            ? 'change-orders.token.feedback.accept'
            : 'entity-feedback.rounds.accept';
        const params = token ? { token, round: round.id } : { round: round.id };
        postRoundAction(
            routeName,
            params,
            {},
            'Failed to accept feedback.',
            () => setIsSubmitting(false),
            'Feedback accepted and closed.'
        );
    }, [round, token]);

    const acknowledge = React.useCallback(() => {
        if (!round) return;
        setIsSubmitting(true);
        postRoundAction(
            'entity-feedback.rounds.acknowledge',
            round.id,
            {},
            'Could not acknowledge this feedback.',
            () => setIsSubmitting(false),
            'Feedback acknowledged.'
        );
    }, [round]);

    return {
        activeRound: round,
        isAcknowledged: perms.isAcknowledged,
        canAcknowledge: perms.canAcknowledge,
        canSubmitToManager: perms.canSubmitToManager,
        acknowledge,
        notes,
        unresolvedNotes,
        isSubmitting,
        hasOpenRound: perms.hasOpenRound,
        isSubmitted: perms.isSubmitted,
        isResolved: perms.isResolved,
        canAnnotate: perms.canAnnotate,
        canReply: perms.canReply,
        canSubmit: perms.canSubmit,
        canForwardTechnician: perms.canForwardTechnician,
        canResolve: perms.canResolve,
        findNoteForPhoto,
        saveAnnotationNote,
        editNote,
        deleteNote,
        deleteRound,
        withdrawIssue,
        submitRound,
        forwardToTechnician,
        submitToManager,
        resolveNote,
        resolveAll,
        acceptRound,
        openRound,
    };
}
