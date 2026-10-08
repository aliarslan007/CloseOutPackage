import * as React from 'react';
import type {
    EntityFeedbackNoteItem,
    EntityFeedbackRoundItem,
    PhotoActionOptions,
} from '../types/entity-feedback.types';
import type { FeedbackViewerRole } from '../utils/feedback-copy';
import { EmptyRoundBanner } from './EmptyRoundBanner';
import { FeedbackManagerPhotoActions, FeedbackStatusActions } from './FeedbackRoundActions';
import { FeedbackRoundHistory } from './FeedbackRoundHistory';
import { FeedbackStatusHeader } from './FeedbackStatusHeader';
import { FeedbackThreadPanel } from './FeedbackThreadPanel';
import { FeedbackThreadViewer } from './FeedbackThreadViewer';
import { ForwardTechnicianDialog, type TechnicianOption } from './ForwardTechnicianDialog';
import { ManagerInstructionsCard } from './ManagerInstructionsCard';
import { OpenRoundPrompt } from './OpenRoundPrompt';
import type { FeedbackThread } from '../utils/build-feedback-threads';

/** Who is looking at this section; drives plain-language status copy. */
function viewerRoleFor(isCustomer: boolean, isTechnician: boolean): FeedbackViewerRole {
    if (isCustomer) {
        return 'customer';
    }
    return isTechnician ? 'technician' : 'manager';
}

export interface EntityFeedbackSectionProps {
    round: EntityFeedbackRoundItem | null;
    history?: EntityFeedbackRoundItem[];
    isManager?: boolean;
    isCustomer?: boolean;
    isTechnician?: boolean;
    technicians?: TechnicianOption[];
    onDeleteNote?: (note: EntityFeedbackNoteItem) => void;
    /** Admin-only: erase a whole past review round and everything in it. */
    onDeleteRound?: (round: EntityFeedbackRoundItem) => void;
    /** Accepted for callers; message editing was removed - replies correct the record. */
    onEditNote?: (note: EntityFeedbackNoteItem, body: string) => Promise<void> | void;
    /** Accepted for callers; staff answers now go through the thread composer. */
    onResolveNote?: (
        note: EntityFeedbackNoteItem,
        responseText: string,
        replacementFile?: File | null,
        options?: PhotoActionOptions
    ) => Promise<void> | void;
    onResolveAll?: (generalResponse?: string) => void;
    onForwardTechnician?: (data: {
        assignedToUserId: number | null;
        instructions: string;
    }) => Promise<void> | void;
    onAcceptRound?: () => void;
    onOpenRound?: (managerInstructions?: string) => void;
    isOpeningRound?: boolean;
    isSubmitting?: boolean;
    onSubmitToManager?: () => void;
    onReply?: (
        photoId: number | null,
        photoType: string | null,
        body: string,
        replacementFile?: File | null,
        options?: PhotoActionOptions
    ) => Promise<void> | void;
    canReply?: boolean;
    /**
     * Customer only: take one issue off the list without touching the rest of the
     * review. Passed only where the customer is authenticated against the round -
     * the emailed-token review has no such endpoint, so leaving it out keeps the
     * button off that surface instead of offering a dead click.
     */
    onWithdrawIssue?: (thread: FeedbackThread) => void;
    onAcknowledge?: () => void;
    canAcknowledge?: boolean;
    /** Accepted for callers; the status header derives this from the round. */
    isAcknowledged?: boolean;
    onJumpToPhoto?: (photoId: number, photoType?: string | null) => void;
    itemNamesByKey?: Record<string, string>;
}

export function EntityFeedbackSection({
    round,
    history = [],
    isManager = false,
    isCustomer = false,
    isTechnician = false,
    technicians = [],
    onDeleteNote,
    onDeleteRound,
    onResolveAll,
    onForwardTechnician,
    onAcceptRound,
    onOpenRound,
    isOpeningRound = false,
    isSubmitting = false,
    onSubmitToManager,
    onAcknowledge,
    onReply,
    canReply = false,
    onWithdrawIssue,
    onJumpToPhoto,
    itemNamesByKey,
}: Readonly<EntityFeedbackSectionProps>) {
    const [forwardDialogOpen, setForwardDialogOpen] = React.useState(false);
    const [isSendingThreadReply, setIsSendingThreadReply] = React.useState(false);
    const [activeThreadKey, setActiveThreadKey] = React.useState<string | null>(null);

    const isStaff = isManager || isTechnician;
    const viewerRole = viewerRoleFor(isCustomer, isTechnician);
    // Past reviews are the manager <-> customer archive - a field technician has
    // no reason to read closed rounds, so the whole history stays hidden for them.
    const canSeeHistory = !isTechnician;

    // The live conversation is already trimmed on the server (FeedbackRoundPresenter)
    // so a technician never receives the manager <-> customer thread - nothing to
    // filter here. Only past rounds, which stay in the payload, are hidden below.
    const notes = round?.notes ?? [];

    if (round === null) {
        if (isManager && onOpenRound) {
            return (
                <div className="space-y-4">
                    <OpenRoundPrompt onOpen={() => onOpenRound?.()} busy={isOpeningRound} />
                    {history.length > 0 && (
                        <FeedbackRoundHistory
                            rounds={history}
                            onDeleteRound={onDeleteRound}
                            itemNamesByKey={itemNamesByKey}
                        />
                    )}
                </div>
            );
        }
        return history.length > 0 && canSeeHistory ? (
            <FeedbackRoundHistory
                rounds={history}
                onDeleteRound={onDeleteRound}
                itemNamesByKey={itemNamesByKey}
            />
        ) : null;
    }

    const isOpen = round.status === 'open';
    // The backend sends every round in history, live one included - the active
    // review belongs solely at the top, never inside "Past reviews".
    const priorRounds = history.filter((h) => h.id !== round.id);

    if (round.notes_withheld || (isOpen && notes.length === 0)) {
        return <EmptyRoundBanner round={round} isCustomer={isCustomer} isStaff={isStaff} />;
    }

    const canDelegate = round.entity_type !== 'change_order';

    const sendThreadReply = async (
        thread: FeedbackThread,
        body: string,
        replacementFile?: File | null,
        options?: PhotoActionOptions
    ): Promise<void> => {
        if (!onReply) return;
        setIsSendingThreadReply(true);
        try {
            await onReply(thread.photoId, thread.photoType, body, replacementFile, options);
        } finally {
            setIsSendingThreadReply(false);
        }
    };

    return (
        <div className="space-y-4 rounded-2xl border-2 border-border bg-card p-4 sm:p-5 shadow-sm">
            {/* One row carries everything at-a-glance: what this is, where it
                stands, and the one thing the viewer can do about it. */}
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <h3 className="text-sm font-bold tracking-tight text-foreground">
                    Customer Review
                </h3>
                <FeedbackStatusHeader
                    round={round}
                    viewer={viewerRole}
                    className="order-last w-full sm:order-none sm:w-auto"
                />
                {/* Round-level controls live up here, not inside a single issue
                    thread: assigning a technician or sending fixes acts on the
                    whole review - every issue in it - so they read as such. */}
                <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
                    <FeedbackStatusActions
                        round={round}
                        isManager={isManager}
                        isCustomer={isCustomer}
                        isTechnician={isTechnician}
                        isSubmitting={isSubmitting}
                        onAcknowledge={onAcknowledge}
                        onAcceptRound={onAcceptRound}
                        onSubmitToManager={onSubmitToManager}
                    />
                    <FeedbackManagerPhotoActions
                        round={round}
                        isManager={isManager}
                        canDelegate={canDelegate}
                        isSubmitting={isSubmitting}
                        onOpenForwardDialog={() => setForwardDialogOpen(true)}
                        onResolveAll={onResolveAll}
                    />
                </div>
            </div>

            {/* Internal field-rework notes: manager and technician eyes only. */}
            {isStaff && round.manager_instructions && (
                <ManagerInstructionsCard instructions={round.manager_instructions} />
            )}

            <FeedbackThreadViewer
                notes={notes}
                activeThreadKey={activeThreadKey}
                onActiveThreadChange={setActiveThreadKey}
                onJumpToPhoto={onJumpToPhoto}
                itemNamesByKey={itemNamesByKey}
                renderThread={(thread) => (
                    <FeedbackThreadPanel
                        thread={thread}
                        isStaff={isStaff}
                        isCustomer={isCustomer}
                        isEditable={round.status === 'open'}
                        entityType={round.entity_type}
                        itemNamesByKey={itemNamesByKey}
                        onDeleteNote={onDeleteNote}
                        canReply={canReply}
                        canWithdraw={
                            Boolean(onWithdrawIssue) && isCustomer && round.status !== 'accepted'
                        }
                        onWithdraw={onWithdrawIssue ? () => onWithdrawIssue(thread) : undefined}
                        isSubmitting={isSendingThreadReply || isSubmitting}
                        onReply={
                            onReply
                                ? (body, file, options) =>
                                      sendThreadReply(thread, body, file, options)
                                : undefined
                        }
                    />
                )}
            />

            {canSeeHistory && priorRounds.length > 0 && (
                <div className="pt-2 border-t border-border/50">
                    <FeedbackRoundHistory
                        rounds={priorRounds}
                        onDeleteRound={onDeleteRound}
                        itemNamesByKey={itemNamesByKey}
                    />
                </div>
            )}

            {isManager && onForwardTechnician && (
                <ForwardTechnicianDialog
                    open={forwardDialogOpen}
                    technicians={technicians}
                    assignedToUserId={round.assigned_to_user_id ?? null}
                    onClose={() => setForwardDialogOpen(false)}
                    onForward={onForwardTechnician}
                />
            )}
        </div>
    );
}
