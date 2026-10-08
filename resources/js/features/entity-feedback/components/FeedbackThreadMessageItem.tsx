import * as React from 'react';
import type { EntityFeedbackNoteItem } from '../types/entity-feedback.types';
import { requestedActionLabel } from '../utils/feedback-copy';
import {
    authorAvatarUrl,
    authorRoleLabel,
    FeedbackMessage,
    MessagePhotoLink,
} from './FeedbackMessage';
import { FeedbackManagerReply } from './FeedbackManagerReply';

export interface FeedbackThreadMessageItemProps {
    note: EntityFeedbackNoteItem;
    currentUserId?: number;
    isStaff?: boolean;
    /** Whose side of the chat the reader sits on - drives bubble alignment. */
    viewerIsCustomer?: boolean;
    /** Same speaker as the previous message - suppresses the repeated header. */
    grouped?: boolean;
    isEditable?: boolean;
    onDeleteNote?: (note: EntityFeedbackNoteItem) => void;
}

function getAttachmentSubtitle(tone: string): string {
    if (tone === 'technician') {
        return 'Photo from your technician';
    }
    if (tone === 'manager') {
        return 'Photo from your project manager';
    }
    return 'Attached photo';
}

export function deriveTone(roleName?: string | null): 'customer' | 'manager' | 'technician' {
    if (roleName === 'field_supervisor') return 'technician';
    if (roleName === 'manager' || roleName === 'admin') return 'manager';
    return 'customer';
}

/**
 * One chat message in a thread. Editing was removed by design: a corrected
 * note is a new reply in the chat, not a silent rewrite of history.
 */
export const FeedbackThreadMessageItem = React.memo(function FeedbackThreadMessageItem({
    note,
    isStaff = false,
    viewerIsCustomer = false,
    grouped = false,
}: Readonly<FeedbackThreadMessageItemProps>) {
    const tone = deriveTone(note.author?.role_name);
    const fromViewerSide = viewerIsCustomer === (tone === 'customer');

    return (
        <div className="w-full">
            <FeedbackMessage
                author={{
                    name: note.author?.name ?? (tone === 'customer' ? 'Customer' : 'Team member'),
                    avatarUrl: authorAvatarUrl(note.author),
                }}
                roleLabel={authorRoleLabel(note.author, tone === 'customer' ? 'Customer' : 'Team')}
                body={note.body}
                verb={requestedActionLabel(note.requested_action) ?? undefined}
                editedAt={note.edited_at}
                createdAt={note.created_at}
                side={fromViewerSide ? 'right' : 'left'}
                grouped={grouped}
                delivered={Boolean(note.created_at)}
            >
                {note.annotated_image_url && (
                    <MessagePhotoLink
                        url={note.annotated_image_url}
                        title="Your markup"
                        subtitle="Click to view the marked-up photo"
                        alt="Annotated"
                    />
                )}
                {note.replacement_image_url && !note.manager_response && (
                    <MessagePhotoLink
                        url={note.replacement_image_url}
                        title="Replacement photo"
                        subtitle={getAttachmentSubtitle(tone)}
                        alt="Replacement"
                    />
                )}
            </FeedbackMessage>

            {note.manager_response && (
                <div className="mt-2">
                    <FeedbackManagerReply
                        note={note}
                        showSentState={isStaff}
                        side={viewerIsCustomer ? 'left' : 'right'}
                    />
                </div>
            )}
        </div>
    );
});
