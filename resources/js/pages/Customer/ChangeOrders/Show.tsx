import { ChangeOrderPhotoGrid } from '@/components/change-orders/change-order-photo-grid';
import { ChangeOrderStatusBadge } from '@/components/change-orders/change-order-status-badge';
import { CustomerCategorizedItemsTable } from '@/components/change-orders/customer-categorized-items-table';
import { CustomerChangeOrderHeaderStrip } from '@/components/change-orders/customer-change-order-header-strip';
import {
    ChangeOrder,
    ChangeOrderPhoto,
    CustomerDecision,
} from '@/components/change-orders/change-order-types';
import { Button } from '@/components/ui/button';
import {
    EntityFeedbackSection,
    FeedbackCanvasDialog,
    FeedbackSubmitBar,
    useEntityFeedback,
    type EntityFeedbackRoundItem,
} from '@/features/entity-feedback';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head, router } from '@inertiajs/react';
import * as React from 'react';
import { toast } from 'sonner';

export interface CustomerChangeOrderShowProps extends PageProps {
    project: {
        id: number;
        hash_id?: string;
        name: string;
    };
    changeOrder: ChangeOrder;
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
}

export function CustomerChangeOrderShow({
    project,
    changeOrder,
    feedbackRound = null,
    feedbackRoundHistory = [],
}: Readonly<CustomerChangeOrderShowProps>) {
    const projectIdentifier = project.hash_id || project.id;
    // Mirrors FeedbackEntityResolver::entityIsDecided. Once the customer has
    // ruled, the review loop is closed: there is nothing left to negotiate.
    const isDecided =
        changeOrder.status === 'approved' ||
        changeOrder.status === 'partially_approved' ||
        changeOrder.status === 'rejected';

    const items = React.useMemo(() => {
        return (changeOrder.items ?? []).filter((i) => i.is_included);
    }, [changeOrder.items]);

    const totalAbsorbed = React.useMemo(() => {
        return items
            .filter((i) => Boolean(i.cost_absorbed_by_solucomp))
            .reduce((sum, i) => sum + (parseFloat(String(i.amount)) || 0), 0);
    }, [items]);

    // Decision state per item
    const [decisions, setDecisions] = React.useState<
        Record<number, { decision: CustomerDecision; note?: string }>
    >(() => {
        const init: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                init[item.id] = {
                    decision: item.customer_decision ?? 'pending',
                    note: item.customer_decision_note ?? '',
                };
            }
        });
        return init;
    });

    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [activePhotoForCanvas, setActivePhotoForCanvas] = React.useState<ChangeOrderPhoto | null>(
        null
    );

    /**
     * A reply continues the conversation on the image it was written about, so
     * the note carries that image's id and joins its thread.
     */
    const handleThreadReply = async (
        photoId: number | null,
        photoType: string | null,
        body: string,
        replacementFile?: File | null
    ) => {
        await feedback.saveAnnotationNote({
            entityType: 'change_order',
            entityId: changeOrder.id,
            originalImageUrl: null,
            body,
            sourcePhotoId: photoId,
            sourcePhotoType: photoType === null ? 'section' : photoType,
            replacementImageFile: replacementFile,
        });
    };

    const feedback = useEntityFeedback({
        entityType: 'change_order',
        entityId: changeOrder.id,
        round: feedbackRound,
        isCustomer: true,
    });

    const handleSaveAnnotation = async (data: {
        blob: Blob;
        layerJson: string;
        body: string;
        requestedAction?: string;
    }) => {
        if (!activePhotoForCanvas) return;
        await feedback.saveAnnotationNote({
            entityType: 'change_order',
            entityId: changeOrder.id,
            originalImageUrl: activePhotoForCanvas.url,
            body: data.body,
            sourcePhotoId: activePhotoForCanvas.id,
            sourcePhotoType: 'change_order_photo',
            annotatedImageBlob: data.blob,
            annotationLayerJson: data.layerJson,
            requestedAction: data.requestedAction,
        });
    };

    const setItemDecision = (itemId: number, decision: CustomerDecision) => {
        if (isDecided) return;
        setDecisions((prev) => ({
            ...prev,
            [itemId]: {
                ...prev[itemId],
                decision,
            },
        }));
    };

    const handleApproveAll = () => {
        if (isDecided) return;
        const updated: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                updated[item.id] = {
                    ...decisions[item.id],
                    decision: 'approved',
                };
            }
        });
        setDecisions(updated);
        toast.success('Marked all items as Approved.');
    };

    const handleRejectAll = () => {
        if (isDecided) return;
        const updated: Record<number, { decision: CustomerDecision; note?: string }> = {};
        items.forEach((item) => {
            if (item.id) {
                updated[item.id] = {
                    ...decisions[item.id],
                    decision: 'rejected',
                };
            }
        });
        setDecisions(updated);
        toast.info('Marked all items as Rejected.');
    };

    const hasPending = Object.values(decisions).some((d) => d.decision === 'pending');

    const handleSubmit = () => {
        if (isDecided) return;
        if (hasPending) {
            toast.error('Please make a decision (Approve or Reject) on all line items.');
            return;
        }

        setIsSubmitting(true);
        const payload = {
            decisions: Object.entries(decisions).map(([itemId, val]) => ({
                item_id: Number.parseInt(itemId, 10),
                decision: val.decision,
                note: val.note || null,
            })),
        };

        router.post(
            route('customer.change-orders.decide', [projectIdentifier, changeOrder.id]),
            payload,
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => {
                    toast.success('Your decisions have been submitted successfully.');
                },
            }
        );
    };

    return (
        <AppLayout
            title={changeOrder.title}
            breadcrumb={[
                { label: 'Customer Portal', href: route('portal.index') },
                { label: project.name, href: route('projects.show', projectIdentifier) },
                { label: 'Change Orders', href: route('change-orders.index') },
                { label: changeOrder.title },
            ]}
            headerActions={<ChangeOrderStatusBadge status={changeOrder.status} />}
        >
            <Head title={`Change Order - ${changeOrder.title}`} />

            <div className="space-y-4 text-xs">
                {/* 4-Column Metadata Header Strip */}
                <CustomerChangeOrderHeaderStrip
                    changeOrder={changeOrder}
                    totalAbsorbed={totalAbsorbed}
                />

                {changeOrder.description && (
                    <div className="rounded-xl border border-border/80 bg-card p-4 sm:p-5 shadow-xs">
                        <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            Description / Cover Note
                        </h3>
                        <div className="mt-2 max-h-80 overflow-y-auto overscroll-contain p-3.5 rounded-lg bg-muted/20 border border-border/70 text-foreground text-xs whitespace-pre-wrap break-words [overflow-wrap:anywhere] leading-relaxed">
                            {changeOrder.description}
                        </div>
                    </div>
                )}

                {/* Categorized Items Table (Billed vs Cost Absorbed by Solucomp) */}
                <CustomerCategorizedItemsTable
                    items={items}
                    decisions={decisions}
                    isDecided={isDecided}
                    onDecisionChange={setItemDecision}
                    onApproveAll={handleApproveAll}
                    onRejectAll={handleRejectAll}
                />

                {/* Bottom Decision Submission Card */}
                {!isDecided && (
                    <div className="flex items-center justify-between gap-4 rounded-xl border border-border/80 bg-card p-4 sm:p-5 shadow-xs flex-wrap">
                        <div>
                            <h4 className="text-xs font-bold text-foreground uppercase tracking-wider">
                                Ready to submit your decisions?
                            </h4>
                            <p className="text-[11px] text-muted-foreground mt-0.5">
                                Please review every line item above, decide on each one, then
                                submit.
                            </p>
                        </div>
                        <div className="flex items-center gap-3 ml-auto">
                            <Button
                                type="button"
                                size="sm"
                                onClick={handleSubmit}
                                disabled={isSubmitting}
                                className="rounded-lg text-xs font-bold px-6 py-2 bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs cursor-pointer"
                            >
                                {isSubmitting ? 'Submitting...' : 'Submit Decisions'}
                            </Button>
                        </div>
                    </div>
                )}

                {(changeOrder.photos?.length ?? 0) > 0 && (
                    <div className="rounded-xl border border-border/80 bg-card shadow-xs p-4 sm:p-5 space-y-3 [content-visibility:auto] [contain-intrinsic-size:0_320px]">
                        <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            Photos ({changeOrder.photos?.length ?? 0})
                        </h3>
                        <ChangeOrderPhotoGrid
                            photos={changeOrder.photos ?? []}
                            onAnnotate={
                                isDecided ? undefined : (photo) => setActivePhotoForCanvas(photo)
                            }
                            getPhotoNote={(photoId) => feedback.findNoteForPhoto(photoId)}
                        />

                        {isDecided && (
                            <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-[11px] text-muted-foreground">
                                You have decided on this change order, so the review is closed. The
                                conversation below is kept for your records.
                            </p>
                        )}
                    </div>
                )}

                <div className="[content-visibility:auto] [contain-intrinsic-size:0_450px]">
                    <EntityFeedbackSection
                        round={feedback.activeRound}
                        history={feedbackRoundHistory}
                        isCustomer
                        onDeleteNote={feedback.deleteNote}
                        onDeleteRound={feedback.deleteRound}
                        onEditNote={feedback.editNote}
                        onAcceptRound={feedback.acceptRound}
                        isAcknowledged={feedback.isAcknowledged}
                        onReply={handleThreadReply}
                        canReply={!isDecided}
                        onWithdrawIssue={isDecided ? undefined : feedback.withdrawIssue}
                    />
                </div>
            </div>

            <FeedbackSubmitBar
                noteCount={feedback.hasOpenRound ? feedback.notes.length : 0}
                isSubmitting={feedback.isSubmitting}
                onSubmit={feedback.submitRound}
                hidden={isDecided}
            />

            {activePhotoForCanvas && (
                <FeedbackCanvasDialog
                    imageUrl={activePhotoForCanvas.url}
                    imageTitle={activePhotoForCanvas.original_name}
                    existingNoteText={
                        feedback.findNoteForPhoto(activePhotoForCanvas.id)?.body ?? ''
                    }
                    existingAnnotationLayer={
                        feedback.findNoteForPhoto(activePhotoForCanvas.id)?.annotation_layer
                    }
                    isOpen={Boolean(activePhotoForCanvas)}
                    isSaving={feedback.isSubmitting}
                    onClose={() => setActivePhotoForCanvas(null)}
                    onDelete={
                        activePhotoForCanvas && feedback.findNoteForPhoto(activePhotoForCanvas.id)
                            ? () => {
                                  const note = feedback.findNoteForPhoto(activePhotoForCanvas.id);
                                  if (note) feedback.deleteNote(note);
                              }
                            : undefined
                    }
                    onSave={handleSaveAnnotation}
                />
            )}
        </AppLayout>
    );
}

export default CustomerChangeOrderShow;
