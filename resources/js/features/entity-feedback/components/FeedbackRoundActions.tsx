import { Button } from '@/components/ui/button';
import { CheckCircle2, Eye, Loader2, Send, Wrench } from 'lucide-react';
import * as React from 'react';
import type { EntityFeedbackRoundItem } from '../types/entity-feedback.types';
import { ACTION_LABELS } from '../utils/feedback-copy';

/**
 * Round-level actions, in the Customer Review heading row:
 * - FeedbackStatusActions asks the one question the current state has
 *   (acknowledge / done / approve).
 * - FeedbackManagerPhotoActions is the manager's turn once acknowledged:
 *   assign a technician and send all fixes back to the customer.
 * Both act on the whole review, so both sit in the header - never inside a
 * single issue thread. Visibility rules are unchanged; only the places moved.
 */

interface StatusActionsProps {
    round: EntityFeedbackRoundItem;
    isManager?: boolean;
    isCustomer?: boolean;
    isTechnician?: boolean;
    isSubmitting?: boolean;
    onAcknowledge?: () => void;
    onAcceptRound?: () => void;
    onSubmitToManager?: () => void;
}

export function FeedbackStatusActions({
    round,
    isManager = false,
    isCustomer = false,
    isTechnician = false,
    isSubmitting = false,
    onAcknowledge,
    onAcceptRound,
    onSubmitToManager,
}: Readonly<StatusActionsProps>) {
    const isSubmitted = round.status === 'submitted';
    const isAcknowledged = Boolean(round.manager_acknowledged_at);

    const showAcknowledge = isManager && isSubmitted && !isAcknowledged && Boolean(onAcknowledge);
    const showTechnicianControls =
        isTechnician &&
        isSubmitted &&
        (round.assigned_at != null || round.assigned_to_user_id != null);
    // The customer can approve and close their review any time it is with the
    // team - once submitted, through the manager's replies, and after fixes are
    // sent - not only once the manager marks it resolved. The server's accept
    // rule just refuses an already-accepted round, so the offer stays valid.
    const showCustomerControls = isCustomer && (isSubmitted || round.status === 'resolved');

    if (!showAcknowledge && !showTechnicianControls && !showCustomerControls) {
        return null;
    }

    return (
        <div className="flex shrink-0 flex-wrap items-center gap-2">
            {showAcknowledge && (
                <Button
                    type="button"
                    size="sm"
                    disabled={isSubmitting}
                    onClick={onAcknowledge}
                    className="h-8 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-xs bg-primary text-primary-foreground hover:bg-primary/90 active:scale-[0.98] transition-all"
                >
                    <Eye className="size-3.5" />
                    <span>{ACTION_LABELS.acknowledge}</span>
                </Button>
            )}

            {showTechnicianControls &&
                (round.technician_submitted_at ? (
                    <div className="inline-flex items-center gap-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 shadow-xs">
                        <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <span>Fixes sent to the manager · Waiting on review</span>
                    </div>
                ) : (
                    onSubmitToManager && (
                        <Button
                            type="button"
                            size="sm"
                            disabled={isSubmitting}
                            onClick={onSubmitToManager}
                            className="h-8 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-xs bg-primary text-primary-foreground hover:bg-primary/90 active:scale-[0.98] transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {isSubmitting ? (
                                <Loader2 className="size-3.5 animate-spin" />
                            ) : (
                                <Send className="size-3.5" />
                            )}
                            <span>{isSubmitting ? 'Sending…' : ACTION_LABELS.techDone}</span>
                        </Button>
                    )
                ))}

            {showCustomerControls && onAcceptRound && (
                <Button
                    type="button"
                    size="sm"
                    disabled={isSubmitting}
                    onClick={onAcceptRound}
                    className="h-8 gap-1.5 rounded-lg px-3 text-xs font-semibold bg-emerald-600 text-white shadow-xs hover:bg-emerald-700 active:scale-[0.98] transition-all"
                >
                    <CheckCircle2 className="size-3.5" />
                    <span>{ACTION_LABELS.approve}</span>
                </Button>
            )}
        </div>
    );
}

interface ManagerPhotoActionsProps {
    round: EntityFeedbackRoundItem;
    isManager?: boolean;
    canDelegate?: boolean;
    isSubmitting?: boolean;
    onOpenForwardDialog?: () => void;
    onResolveAll?: (generalResponse?: string) => void;
}

export function FeedbackManagerPhotoActions({
    round,
    isManager = false,
    canDelegate = true,
    isSubmitting = false,
    onOpenForwardDialog,
    onResolveAll,
}: Readonly<ManagerPhotoActionsProps>) {
    // Assigning and sending fixes need an acknowledged, submitted round -
    // same gate the old bottom bar used.
    if (!isManager || round.status !== 'submitted' || !round.manager_acknowledged_at) {
        return null;
    }

    const reassignLabel =
        round.assigned_at || round.assigned_to_user_id
            ? ACTION_LABELS.reassignTech
            : ACTION_LABELS.assignTech;

    return (
        <>
            {canDelegate && onOpenForwardDialog && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={isSubmitting}
                    onClick={onOpenForwardDialog}
                    className="h-8 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-xs active:scale-[0.98] transition-all"
                >
                    <Wrench className="size-3.5 text-primary" />
                    <span>{reassignLabel}</span>
                </Button>
            )}

            {onResolveAll && (
                <Button
                    type="button"
                    size="sm"
                    disabled={isSubmitting}
                    onClick={() => onResolveAll()}
                    title="Send your answers to the customer for approval"
                    className="h-8 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-xs bg-emerald-600 text-white hover:bg-emerald-700 active:scale-[0.98] transition-all"
                >
                    <CheckCircle2 className="size-3.5" />
                    <span>{ACTION_LABELS.sendFixesToCustomer}</span>
                </Button>
            )}
        </>
    );
}
