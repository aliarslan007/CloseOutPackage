import type {
    EntityFeedbackRoundItem,
    FeedbackEntityType,
    FeedbackRoundStatus,
} from '../types/entity-feedback.types';

/**
 * Single source of truth for every user-facing string in the review & feedback
 * section. Components must not hard-code status text, role badges, or action
 * labels — they read them from here. Adding a new reviewed entity (SOW, daily
 * report, audit, invoice...) means adding one entry to ENTITY_COPY, never
 * touching JSX.
 */

/** Who is looking at the section; drives perspective-specific wording. */
export type FeedbackViewerRole = 'customer' | 'manager' | 'technician';

/** Visual/semantic tone for status chrome. Keep in sync with Tailwind usage. */
export type StatusTone = 'needs-you' | 'waiting' | 'working' | 'done';

/** The noun we show instead of the internal "round" / "thread" jargon. */
export const REVIEW_NOUN = 'Review';

/** Shared "the ball is with you" line (kept as a constant for lint hygiene). */
const WAITING_ON_YOU = 'Waiting on you';

export const ROLE_LABELS = {
    customer: 'Customer',
    manager: 'Project Manager',
    technician: 'Technician',
} as const satisfies Record<FeedbackViewerRole, string>;

/** Maps Spatie role names (backend) to layman badges (frontend). */
const SPATIE_ROLE_LABELS: Record<string, string> = {
    field_supervisor: ROLE_LABELS.technician,
    manager: ROLE_LABELS.manager,
    admin: 'Admin',
    customer: ROLE_LABELS.customer,
};

export function roleBadgeLabel(roleName?: string | null): string {
    if (!roleName) {
        return 'Team';
    }
    return SPATIE_ROLE_LABELS[roleName] ?? roleName.replaceAll(/[_-]/g, ' ');
}

/* ------------------------------------------------------------------ */
/* Thread item naming + issue numbering live in ./feedback-item-labels.ts */
/* and are re-exported here so every consumer keeps importing from one   */
/* copy module.                                                          */
/* ------------------------------------------------------------------ */

export {
    issueCountLabel,
    isSectionThread,
    photoItemLabel,
    SECTION_TARGET_TYPES,
    sectionItemLabel,
    threadItemLabel,
} from './feedback-item-labels';

/* ------------------------------------------------------------------ */
/* Action labels — one natural phrase per workflow step.               */
/* ------------------------------------------------------------------ */

export const ACTION_LABELS = {
    /** Optional convenience; replying auto-acknowledges server-side. */
    acknowledge: 'Mark as under review',
    sendFixesToCustomer: 'Send fixes to customer',
    assignTech: 'Assign to field tech',
    reassignTech: 'Re-assign to field tech',
    techDone: 'Done — send to manager',
    approve: 'Approve & close review',
    showInGrid: 'Show in photo grid',
    openReview: 'Start review',
    submitFeedback: 'Send notes to team',
    reply: 'Send reply',
    attachPhoto: 'Attach photo',
} as const;

/* ------------------------------------------------------------------ */
/* Message-level chips.                                                */
/* ------------------------------------------------------------------ */

export const NOTE_STATUS_LABELS = {
    open: 'Waiting for answer',
    resolved: 'Answered',
    withdrawn: 'Withdrawn by customer',
} as const;

export const REQUESTED_ACTION_LABELS: Record<string, string> = {
    replace_photo: 'Replace photo',
    add_new_photo: 'Add a photo',
    delete_photo: 'Remove photo',
    update_note: 'Update note',
    general: 'Question',
};

export function requestedActionLabel(action?: string | null): string | null {
    if (!action) {
        return null;
    }
    return REQUESTED_ACTION_LABELS[action] ?? action.replaceAll(/_/g, ' ');
}

/* ------------------------------------------------------------------ */
/* Progress: three plain steps every role can read.                   */
/* ------------------------------------------------------------------ */

export function reviewProgressSteps(viewer: FeedbackViewerRole): readonly string[] {
    return viewer === 'customer'
        ? ['Feedback sent', 'Team is working on it', 'Ready for your OK']
        : ['Feedback received', 'Fixes in progress', 'Ready to send back'];
}

/** Index into the three steps above, derived purely from round data. */
export function progressStepFor(round: EntityFeedbackRoundItem): number {
    if (round.status === 'accepted') {
        return 3;
    }

    if (round.status === 'resolved') {
        return 2;
    }

    if (round.status === 'open') {
        return 0;
    }

    // Submitted: past step 0 once someone started (acknowledged or assigned).
    const started =
        round.manager_acknowledged_at != null ||
        round.assigned_at != null ||
        round.assigned_to_user_id != null ||
        round.technician_submitted_at != null;

    return started ? 1 : 0;
}

/* ------------------------------------------------------------------ */
/* Status narrative: one headline + the ball is with whom.            */
/* ------------------------------------------------------------------ */

export interface ReviewStatusCopy {
    headline: string;
    tone: StatusTone;
    /** Who must act next, in layman terms. Empty string = nobody. */
    waitingOn: string;
}

function submittedStatusForManager(round: EntityFeedbackRoundItem): ReviewStatusCopy {
    if (round.technician_submitted_at != null) {
        return {
            headline: 'Tech fixes are in — review them, then send back to the customer',
            tone: 'needs-you',
            waitingOn: WAITING_ON_YOU,
        };
    }

    if (round.assigned_at != null || round.assigned_to_user_id != null) {
        return {
            headline: 'Field rework assigned — the technician is on it',
            tone: 'working',
            waitingOn: 'Waiting on the technician',
        };
    }

    if (round.manager_acknowledged_at != null) {
        return {
            headline: "You're on it — reply to each note, then send the fixes",
            tone: 'working',
            waitingOn: WAITING_ON_YOU,
        };
    }

    return {
        headline: 'The customer left notes — start replying whenever you are ready',
        tone: 'needs-you',
        waitingOn: 'Waiting on you',
    };
}

function submittedStatusForTechnician(round: EntityFeedbackRoundItem): ReviewStatusCopy {
    if (round.technician_submitted_at != null) {
        return {
            headline: 'Fixes sent to the manager — you are all set',
            tone: 'waiting',
            waitingOn: 'Waiting on the manager',
        };
    }

    if (round.assigned_at != null || round.assigned_to_user_id != null) {
        return {
            headline: 'You have field work to do — fix the notes and attach photos',
            tone: 'needs-you',
            waitingOn: WAITING_ON_YOU,
        };
    }

    return {
        headline: 'Customer notes are in — the manager is reviewing them',
        tone: 'working',
        waitingOn: 'Waiting on the manager',
    };
}

function submittedStatusForCustomer(round: EntityFeedbackRoundItem): ReviewStatusCopy {
    if (round.technician_submitted_at != null) {
        return {
            headline: 'Our team is putting the finishing touches on your notes',
            tone: 'working',
            waitingOn: 'Almost ready for you',
        };
    }

    // Field work can start via assignment alone - the manager never has to
    // click "acknowledge" first, so this state is real and common.
    if (round.assigned_at != null || round.assigned_to_user_id != null) {
        return {
            headline: "We're on it — our field team is working through your notes",
            tone: 'working',
            waitingOn: 'The team has the ball',
        };
    }

    if (round.manager_acknowledged_at != null) {
        return {
            headline: 'We got your notes and are working on them',
            tone: 'working',
            waitingOn: 'The team has the ball',
        };
    }

    return {
        headline: 'Your notes were sent to the team',
        tone: 'waiting',
        waitingOn: 'The team will pick them up',
    };
}

function submittedStatusCopy(
    round: EntityFeedbackRoundItem,
    viewer: FeedbackViewerRole
): ReviewStatusCopy {
    if (viewer === 'manager') {
        return submittedStatusForManager(round);
    }
    if (viewer === 'technician') {
        return submittedStatusForTechnician(round);
    }
    return submittedStatusForCustomer(round);
}

/** Plain-language status for the unified header. Pure derivation, no state. */
export function reviewStatusCopy(
    round: EntityFeedbackRoundItem,
    viewer: FeedbackViewerRole
): ReviewStatusCopy {
    const status: FeedbackRoundStatus = round.status;

    if (status === 'accepted') {
        return {
            headline:
                viewer === 'customer'
                    ? 'Approved — thank you, this review is complete'
                    : 'The customer approved everything — review is closed',
            tone: 'done',
            waitingOn: '',
        };
    }

    if (status === 'resolved') {
        return viewer === 'customer'
            ? {
                  headline: "We've addressed your notes — take a look and approve",
                  tone: 'needs-you',
                  waitingOn: WAITING_ON_YOU,
              }
            : {
                  headline: 'Fixes sent — the customer is checking them over',
                  tone: 'waiting',
                  waitingOn: 'Waiting on the customer',
              };
    }

    if (status === 'open') {
        return viewer === 'customer'
            ? {
                  headline: 'Add your notes — tap any photo to mark up what you see',
                  tone: 'needs-you',
                  waitingOn: WAITING_ON_YOU,
              }
            : {
                  headline: 'The customer is still collecting their notes',
                  tone: 'waiting',
                  waitingOn: 'Waiting on the customer',
              };
    }

    return submittedStatusCopy(round, viewer);
}

/* ------------------------------------------------------------------ */
/* Past-review chips (history list).                                  */
/* ------------------------------------------------------------------ */

export function historyStatusLabel(status: FeedbackRoundStatus): string {
    switch (status) {
        case 'open':
            return 'Draft';
        case 'submitted':
            return 'In progress';
        case 'resolved':
            return 'Answered';
        case 'accepted':
            return 'Completed';
    }
}

export function historyStatusTone(status: FeedbackRoundStatus): StatusTone {
    return status === 'accepted' || status === 'resolved' ? 'done' : 'working';
}

/* ------------------------------------------------------------------ */
/* Entity-specific wording. Future reviewed entities = one new entry.  */
/* ------------------------------------------------------------------ */

export interface EntityCopy {
    /** What the reviewer of this entity is called. */
    reviewerName: string;
    /** Friendly name of the thing under review, used in empty/entry states. */
    subjectNoun: string;
}

const DEFAULT_ENTITY_COPY: EntityCopy = { reviewerName: 'customer', subjectNoun: 'work' };

const ENTITY_COPY: Partial<Record<FeedbackEntityType, EntityCopy>> = {
    change_order: { reviewerName: 'customer', subjectNoun: 'change order' },
    issue: { reviewerName: 'customer', subjectNoun: 'issue fix' },
    closeout_package: { reviewerName: 'customer', subjectNoun: 'close-out package' },
};

export function entityCopy(entityType?: FeedbackEntityType | null): EntityCopy {
    if (entityType == null) {
        return DEFAULT_ENTITY_COPY;
    }
    return ENTITY_COPY[entityType] ?? DEFAULT_ENTITY_COPY;
}
