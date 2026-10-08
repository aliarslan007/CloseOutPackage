import {
    EntityFeedbackSection,
    FeedbackCanvasDialog,
    FeedbackSubmitBar,
    useEntityFeedback,
} from '@/features/entity-feedback';
import { PageProps } from '@/types/inertia';
import { usePage } from '@inertiajs/react';
import * as React from 'react';
import { IssueActionButtons } from './issue-action-buttons';
import { IssueDiscussionSection } from './issue-discussion-section';
import { IssueHeaderInfo, ProjectLocationInfo } from './issue-header-info';
import { IssueMediaSection } from './issue-media-section';
import { IssueAttachment, IssueItem, ProjectStakeholder } from './issue-types';

export interface IssueDetailCardProps {
    projectId: number | string;
    projectLocation?: ProjectLocationInfo | null;
    issue: IssueItem;
    issueNumber: number;
    canResolve: boolean;
    canComment: boolean;
    canReport?: boolean;
    onOpenReportDialog?: () => void;
    manager?: ProjectStakeholder | null;
    fieldSupervisors?: ProjectStakeholder[];
}

export function IssueDetailCard({
    projectId,
    projectLocation,
    issue,
    canResolve,
    canComment,
    canReport = false,
    onOpenReportDialog,
    manager,
    fieldSupervisors = [],
}: Readonly<IssueDetailCardProps>) {
    const { auth, allowMediaEditWhenResolved = false } = usePage<
        PageProps & { allowMediaEditWhenResolved?: boolean }
    >().props;
    const currentUserId = auth?.user?.id;
    const currentUserPhotoUrl = auth?.user?.profile_photo_url;

    const isResolved = Boolean(issue.resolved_at);
    const isAdmin = auth?.user?.roles?.includes('admin') ?? false;
    const isManager = auth?.user?.roles?.includes('manager') ?? false;
    const isFieldSupervisor = auth?.user?.roles?.includes('field_supervisor') ?? false;
    const isCustomer = auth?.user?.roles?.includes('customer') ?? false;
    const isCreator = Boolean(
        currentUserId &&
        (issue.reporter?.id === currentUserId || issue.reported_by === currentUserId)
    );
    const canDelete = isAdmin || (isCreator && isManager);
    const canManageMedia =
        !isCustomer && (isAdmin || isManager || (isFieldSupervisor && isCreator));
    const canEditMedia = canManageMedia && (!isResolved || allowMediaEditWhenResolved);

    const [activeAttachmentForCanvas, setActiveAttachmentForCanvas] =
        React.useState<IssueAttachment | null>(null);

    const feedback = useEntityFeedback({
        entityType: 'issue',
        entityId: issue.id,
        round: issue.feedback_round ?? null,
        isCustomer,
    });

    const technicianOptions = React.useMemo(() => {
        return (fieldSupervisors ?? []).map((t) => ({
            id: t.id,
            name: t.name,
            email: t.email,
        }));
    }, [fieldSupervisors]);

    const handleSaveAnnotation = async (data: {
        blob: Blob;
        layerJson: string;
        body: string;
        requestedAction?: string;
    }) => {
        if (!activeAttachmentForCanvas) return;
        const mediaUrl =
            activeAttachmentForCanvas.url ||
            activeAttachmentForCanvas.original_url ||
            activeAttachmentForCanvas.preview_url ||
            '';
        await feedback.saveAnnotationNote({
            entityType: 'issue',
            entityId: issue.id,
            originalImageUrl: mediaUrl,
            body: data.body,
            requestedAction: data.requestedAction,
            sourcePhotoId: activeAttachmentForCanvas.id,
            sourcePhotoType: 'issue_attachment',
            annotatedImageBlob: data.blob,
            annotationLayerJson: data.layerJson,
        });
        setActiveAttachmentForCanvas(null);
    };

    const activeNote = activeAttachmentForCanvas
        ? feedback.findNoteForPhoto(activeAttachmentForCanvas.id)
        : null;

    const handleThreadReply = async (
        photoId: number | null,
        photoType: string | null,
        body: string,
        replacementFile?: File | null
    ) => {
        await feedback.saveAnnotationNote({
            entityType: 'issue',
            entityId: issue.id,
            originalImageUrl: null,
            body,
            sourcePhotoId: photoId,
            sourcePhotoType: photoType === null ? 'section' : photoType,
            replacementImageFile: replacementFile,
        });
    };

    return (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 w-full items-start">
            {/* LEFT COLUMN: ISSUE DETAILS */}
            <div className="lg:col-span-5 xl:col-span-4 space-y-1.5">
                <span className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase px-1 block">
                    ISSUE DETAILS
                </span>

                <div className="rounded-md border border-border/80 bg-card p-5 sm:p-6 shadow-2xs space-y-4">
                    <IssueHeaderInfo
                        issue={issue}
                        projectLocation={projectLocation}
                        isResolved={isResolved}
                    />

                    <IssueMediaSection
                        projectId={projectId}
                        issue={issue}
                        canEditMedia={canEditMedia}
                        onAnnotate={
                            isCustomer && !isResolved
                                ? (att) => setActiveAttachmentForCanvas(att)
                                : undefined
                        }
                        getAttachmentNote={(id) => feedback.findNoteForPhoto(id)}
                    />

                    <EntityFeedbackSection
                        round={feedback.activeRound}
                        isManager={isManager || isAdmin}
                        isCustomer={isCustomer}
                        isTechnician={isFieldSupervisor}
                        technicians={technicianOptions}
                        onDeleteNote={feedback.deleteNote}
                        onDeleteRound={feedback.deleteRound}
                        onEditNote={isCustomer ? feedback.editNote : undefined}
                        onResolveNote={feedback.resolveNote}
                        onResolveAll={feedback.resolveAll}
                        onForwardTechnician={feedback.forwardToTechnician}
                        onAcceptRound={feedback.acceptRound}
                        onReply={handleThreadReply}
                        canReply={!isResolved && feedback.canReply}
                        onWithdrawIssue={feedback.withdrawIssue}
                    />

                    <IssueActionButtons
                        projectId={projectId}
                        issue={issue}
                        isResolved={isResolved}
                        canResolve={canResolve}
                        canDelete={canDelete}
                        canReport={canReport}
                        onOpenReportDialog={onOpenReportDialog}
                    />
                </div>
            </div>

            {/* RIGHT COLUMN: DISCUSSION FEED */}
            <div className="lg:col-span-7 xl:col-span-8 space-y-1.5">
                <span className="text-[11px] font-bold tracking-wider text-muted-foreground uppercase px-1 block">
                    DISCUSSION FEED
                </span>

                <IssueDiscussionSection
                    projectId={projectId}
                    issue={issue}
                    canComment={canComment}
                    currentUserId={currentUserId}
                    currentUserPhotoUrl={currentUserPhotoUrl}
                    currentUserName={auth?.user?.name}
                    manager={manager}
                    fieldSupervisors={fieldSupervisors}
                />
            </div>

            {/* Customer Sticky Submission Bar */}
            {isCustomer && (
                <FeedbackSubmitBar
                    noteCount={feedback.hasOpenRound ? feedback.notes.length : 0}
                    isSubmitting={feedback.isSubmitting}
                    onSubmit={feedback.submitRound}
                />
            )}

            {/* Image Annotation Canvas Dialog */}
            {activeAttachmentForCanvas && (
                <FeedbackCanvasDialog
                    imageUrl={
                        activeAttachmentForCanvas.url ||
                        activeAttachmentForCanvas.original_url ||
                        activeAttachmentForCanvas.preview_url ||
                        ''
                    }
                    imageTitle={activeAttachmentForCanvas.file_name}
                    existingNoteText={activeNote?.body ?? ''}
                    existingAnnotationLayer={activeNote?.annotation_layer}
                    isOpen={Boolean(activeAttachmentForCanvas)}
                    isSaving={feedback.isSubmitting}
                    onClose={() => setActiveAttachmentForCanvas(null)}
                    onDelete={activeNote ? () => feedback.deleteNote(activeNote) : undefined}
                    onSave={handleSaveAnnotation}
                />
            )}
        </div>
    );
}
export type { ProjectLocationInfo };
