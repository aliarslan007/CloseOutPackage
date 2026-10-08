import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { type EntityFeedbackNoteItem } from '@/features/entity-feedback';
import { router } from '@inertiajs/react';
import * as React from 'react';
import { toast } from 'sonner';
import { MAX_ATTACHMENTS } from './issue-attachment-utils';
import { IssueAttachmentViewer } from './issue-attachment-viewer';
import { IssueMediaUploader } from './issue-media-uploader';
import { IssueAttachment, IssueItem } from './issue-types';

export interface IssueMediaSectionProps {
    projectId: number | string;
    issue: IssueItem;
    canEditMedia: boolean;
    onAnnotate?: (attachment: IssueAttachment) => void;
    getAttachmentNote?: (attachmentId: number) => EntityFeedbackNoteItem | null;
}

export function IssueMediaSection({
    projectId,
    issue,
    canEditMedia,
    onAnnotate,
    getAttachmentNote,
}: Readonly<IssueMediaSectionProps>) {
    const [pendingAttachmentDelete, setPendingAttachmentDelete] =
        React.useState<IssueAttachment | null>(null);
    const [deletingAttachment, setDeletingAttachment] = React.useState(false);

    const attachments = issue.attachments ?? [];
    const remainingSlots = Math.max(0, MAX_ATTACHMENTS - attachments.length);

    const handleConfirmAttachmentDelete = () => {
        if (!pendingAttachmentDelete) return;
        setDeletingAttachment(true);
        router.delete(
            route('projects.issues.attachments.destroy', [
                projectId,
                issue.hash_id || issue.id,
                pendingAttachmentDelete.id,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPendingAttachmentDelete(null);
                    toast.success('Attachment removed.');
                },
                onFinish: () => setDeletingAttachment(false),
            }
        );
    };

    if (attachments.length === 0 && !canEditMedia) {
        return null;
    }

    return (
        <div className="space-y-1.5 pt-1">
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-bold text-foreground block">
                    Report Attachments
                </span>
                <span className="text-[10px] text-muted-foreground font-normal">
                    {attachments.length} / {MAX_ATTACHMENTS} · 5MB each
                </span>
            </div>

            {attachments.length > 0 && (
                <IssueAttachmentViewer
                    attachments={attachments}
                    variant="hero"
                    onDelete={canEditMedia ? setPendingAttachmentDelete : undefined}
                    onAnnotate={onAnnotate}
                    getAttachmentNote={getAttachmentNote}
                />
            )}

            {canEditMedia && (
                <IssueMediaUploader
                    projectId={projectId}
                    issue={issue}
                    remainingSlots={remainingSlots}
                />
            )}

            {canEditMedia && (
                <ConfirmDialog
                    open={Boolean(pendingAttachmentDelete)}
                    onOpenChange={(open) => {
                        if (!open) setPendingAttachmentDelete(null);
                    }}
                    title="Remove Attachment"
                    description={`Remove "${pendingAttachmentDelete?.file_name ?? ''}" from this issue? You can add it again later.`}
                    confirmLabel="Remove"
                    variant="destructive"
                    loading={deletingAttachment}
                    onConfirm={handleConfirmAttachmentDelete}
                />
            )}
        </div>
    );
}
