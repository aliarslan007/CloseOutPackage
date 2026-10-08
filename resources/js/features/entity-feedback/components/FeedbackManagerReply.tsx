import { Badge } from '@/components/ui/badge';
import { Lock } from 'lucide-react';
import * as React from 'react';
import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';
import {
    authorAvatarUrl,
    authorRoleLabel,
    FeedbackMessage,
    MessagePhotoLink,
} from './FeedbackMessage';

export interface FeedbackManagerReplyProps {
    note: EntityFeedbackNoteItem;
    showSentState?: boolean;
    /** Answers always come from the staff side of the chat. */
    side?: 'left' | 'right';
}

function sentState(note: EntityFeedbackNoteItem) {
    if (note.response_sent_at) {
        return (
            <Badge
                variant="outline"
                title="A published reply is locked so the customer's copy stays accurate."
                className="h-5 gap-1 rounded px-1.5 text-[10px] font-semibold text-muted-foreground"
            >
                <Lock className="size-2.5" /> Sent to customer
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="h-5 gap-1 rounded border-amber-600/30 bg-amber-600/10 px-1.5 text-[10px] font-semibold text-amber-700 dark:border-amber-500/30 dark:text-amber-300"
        >
            Draft — not sent
        </Badge>
    );
}

export const FeedbackManagerReply = React.memo(function FeedbackManagerReply({
    note,
    showSentState = false,
    side = 'left',
}: Readonly<FeedbackManagerReplyProps>) {
    if (!note.manager_response) {
        return null;
    }

    const answerer = note.resolved_by_user;
    const answeredAt = note.resolved_at ?? note.response_sent_at;
    // Name the person being answered. "Replying to Jane" removes any doubt about
    // who wrote what without the reader decoding a colour or an avatar.
    const replyingTo = note.author?.name;

    return (
        <FeedbackMessage
            author={{
                name: answerer?.name ?? 'Project Manager',
                avatarUrl: authorAvatarUrl(answerer),
            }}
            roleLabel={authorRoleLabel(answerer, 'Project Manager')}
            verb={replyingTo ? `Replying to ${replyingTo}` : 'Reply'}
            side={side}
            body={note.manager_response}
            createdAt={answeredAt}
            delivered={Boolean(answeredAt)}
            status={showSentState ? sentState(note) : undefined}
        >
            {note.replacement_image_url && (
                <MessagePhotoLink
                    url={note.replacement_image_url}
                    title="Updated Photo"
                    subtitle="Provided by manager"
                    alt="Updated"
                />
            )}
        </FeedbackMessage>
    );
});
