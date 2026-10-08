import { usePoll } from '@inertiajs/react';
import * as React from 'react';
import type { FeedbackEntityType } from '../types/entity-feedback.types';

export interface UseEntityFeedbackPollOptions {
    entityType: FeedbackEntityType;
    pollIntervalMs?: number | null;
    pollOnlyKeys?: string[];
    enabled?: boolean;
}

const DEFAULT_POLL_INTERVAL_MS = 3500;

function resolvePollKeys(entityType: FeedbackEntityType, customKeys?: string[]): string[] {
    if (customKeys && customKeys.length > 0) {
        return customKeys;
    }

    switch (entityType) {
        case 'change_order':
            return [
                'feedbackRound',
                'feedback_round',
                'feedbackRoundHistory',
                'feedback_round_history',
                'changeOrder',
                'change_order',
            ];
        case 'closeout_package':
            return [
                'feedbackRound',
                'feedback_round',
                'feedbackRoundHistory',
                'feedback_round_history',
                'package',
                'project',
            ];
        case 'issue':
            return [
                'feedbackRound',
                'feedback_round',
                'feedbackRoundHistory',
                'feedback_round_history',
                'issue',
                'issues',
            ];
        default:
            return [
                'feedbackRound',
                'feedback_round',
                'feedbackRoundHistory',
                'feedback_round_history',
            ];
    }
}

export function useEntityFeedbackPoll({
    entityType,
    pollIntervalMs = DEFAULT_POLL_INTERVAL_MS,
    pollOnlyKeys,
    enabled = true,
}: Readonly<UseEntityFeedbackPollOptions>): void {
    const keys = React.useMemo(
        () => resolvePollKeys(entityType, pollOnlyKeys),
        [entityType, pollOnlyKeys]
    );

    const interval = pollIntervalMs ?? DEFAULT_POLL_INTERVAL_MS;
    const isAutoStart = Boolean(enabled && interval > 0);

    usePoll(
        interval,
        { only: keys },
        {
            autoStart: isAutoStart,
            keepAlive: false,
        }
    );
}

export default useEntityFeedbackPoll;
