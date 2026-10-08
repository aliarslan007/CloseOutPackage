import * as React from 'react';
import { Wrench } from 'lucide-react';
import type { EntityFeedbackRoundItem } from '../types/entity-feedback.types';

export interface EmptyRoundBannerProps {
    round: EntityFeedbackRoundItem;
    isCustomer?: boolean;
    /** Field instructions are internal - only staff eyes may see them. */
    isStaff?: boolean;
}

/**
 * Shown while a review has nothing to read yet: the customer is still
 * collecting notes, or the round's notes are withheld. One honest sentence,
 * no lifecycle diagram - the status header covers the rest of the section.
 */
export function EmptyRoundBanner({
    round,
    isCustomer = false,
    isStaff = false,
}: Readonly<EmptyRoundBannerProps>) {
    const subtitle = isCustomer
        ? 'This review is open. Tap any photo to mark up what you see — your notes reach the team as soon as you send them.'
        : `Review ${round.round_number} is open. The customer's notes and marked-up photos will appear here the moment they are sent.`;

    return (
        <div className="space-y-3 rounded-xl border border-border/70 bg-card/50 p-4">
            <div className="space-y-0.5">
                <h3 className="text-sm font-bold tracking-tight text-foreground">
                    Customer Review
                </h3>
                <p className="text-xs text-muted-foreground">{subtitle}</p>
            </div>

            {isStaff && round.manager_instructions && (
                <div className="rounded-lg border-l-2 border-primary/50 bg-primary/5 py-2 pl-3 text-xs">
                    <p className="flex items-center gap-1.5 font-bold text-primary">
                        <Wrench className="size-3.5" /> Instructions for the field team
                    </p>
                    <p className="mt-0.5 whitespace-pre-wrap text-foreground/90">
                        {round.manager_instructions}
                    </p>
                </div>
            )}
        </div>
    );
}
