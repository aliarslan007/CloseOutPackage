import * as React from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { playMessageSentSound } from '@/lib/notification-sound';
import type {
    EntityFeedbackNoteItem,
    EntityFeedbackRoundItem,
    PhotoActionOptions,
    SaveAnnotationPayload,
} from '../types/entity-feedback.types';
import type { FeedbackThread } from '../utils/build-feedback-threads';
import {
    buildAnnotationFormData,
    buildResolveFormData,
    resolveNoteStoreRoute,
} from '../utils/feedback-actions';

interface UseEntityFeedbackNoteActionsOptions {
    round: EntityFeedbackRoundItem | null;
    token?: string | null;
    setIsSubmitting: (value: boolean) => void;
}

/**
 * Everything that acts on messages - send, correct, delete, answer - or on one
 * conversation as a whole (withdraw an issue), as opposed to the round's
 * lifecycle (submit / acknowledge / accept), which stays in useEntityFeedback.
 * Split out purely to keep each file small enough to read at once.
 */
export function useEntityFeedbackNoteActions({
    round,
    token = null,
    setIsSubmitting,
}: Readonly<UseEntityFeedbackNoteActionsOptions>) {
    const saveAnnotationNote = React.useCallback(
        async (payload: SaveAnnotationPayload) => {
            setIsSubmitting(true);
            const formData = buildAnnotationFormData(payload);
            const targetRoute = resolveNoteStoreRoute(token, round);

            router.post(targetRoute, formData, {
                preserveScroll: true,
                onSuccess: () => playMessageSentSound(),
                onError: (errors) => {
                    const firstError = Object.values(errors ?? {})[0];
                    toast.error(typeof firstError === 'string' ? firstError : 'Failed to send message.');
                },
                onFinish: () => setIsSubmitting(false),
            });
        },
        [round, token, setIsSubmitting]
    );

    const editNote = React.useCallback(
        (note: EntityFeedbackNoteItem, body: string) => {
            setIsSubmitting(true);
            router.patch(
                route('entity-feedback.notes.update', { note: note.id }),
                { body },
                {
                    preserveScroll: true,
                    onError: () => toast.error('Failed to update your message.'),
                    onFinish: () => setIsSubmitting(false),
                }
            );
        },
        [setIsSubmitting]
    );

    const deleteNote = React.useCallback(
        (note: EntityFeedbackNoteItem) => {
            setIsSubmitting(true);
            router.delete(route('entity-feedback.notes.destroy', { note: note.id }), {
                preserveScroll: true,
                onError: () => toast.error('Failed to delete note.'),
                onFinish: () => setIsSubmitting(false),
            });
        },
        [setIsSubmitting]
    );

    const resolveNote = React.useCallback(
        (
            note: EntityFeedbackNoteItem,
            responseText: string,
            replacementFile?: File | null,
            options?: PhotoActionOptions
        ) => {
            setIsSubmitting(true);
            const formData = buildResolveFormData(responseText, replacementFile, options);

            router.post(route('entity-feedback.notes.resolve', { note: note.id }), formData, {
                preserveScroll: true,
                onSuccess: () => toast.success('Response saved successfully.'),
                onError: () => toast.error('Failed to save response.'),
                onFinish: () => setIsSubmitting(false),
            });
        },
        [setIsSubmitting]
    );

    // One issue off the list, the rest of the review untouched. The messages the
    // client is reading make up the issue, so it hands over their ids and the
    // server only ever stamps notes that belong to this round.
    const withdrawIssue = React.useCallback(
        (thread: FeedbackThread) => {
            if (!round) return;
            setIsSubmitting(true);
            router.post(
                route('entity-feedback.rounds.withdraw', { round: round.id }),
                { note_ids: thread.notes.map((note) => note.id) },
                {
                    preserveScroll: true,
                    onSuccess: () => toast.success('Issue withdrawn from this review.'),
                    onError: () => toast.error('Could not withdraw this issue.'),
                    onFinish: () => setIsSubmitting(false),
                }
            );
        },
        [round, setIsSubmitting]
    );

    return { saveAnnotationNote, editNote, deleteNote, resolveNote, withdrawIssue };
}
