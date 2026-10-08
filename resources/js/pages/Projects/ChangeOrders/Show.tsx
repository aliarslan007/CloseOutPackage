import { ChangeOrderDetailsCard } from '@/components/change-orders/change-order-details-card';
import { ChangeOrderHeaderActions } from '@/components/change-orders/change-order-header-actions';
import { ChangeOrderItemsTable } from '@/components/change-orders/change-order-items-table';
import { ChangeOrderPhotoSection } from '@/components/change-orders/change-order-photo-section';
import { ChangeOrderSummaryCards } from '@/components/change-orders/change-order-summary-cards';
import { ChangeOrderTimeline } from '@/components/change-orders/change-order-timeline';
import { ChangeOrder } from '@/components/change-orders/change-order-types';
import {
    EntityFeedbackSection,
    useEntityFeedback,
    type EntityFeedbackRoundItem,
    type PhotoActionOptions,
    type TechnicianOption,
} from '@/features/entity-feedback';
import AppLayout from '@/layouts/app-layout';
import { PageProps } from '@/types/inertia';
import { Head } from '@inertiajs/react';
import * as React from 'react';

export interface ChangeOrdersShowProps extends PageProps {
    project: {
        id: number;
        hash_id?: string;
        name: string;
    };
    changeOrder: ChangeOrder;
    /** Backend-enforced photo cap for a single change order. */
    photoLimit?: number;
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
    technicians?: TechnicianOption[];
}

export function ChangeOrdersShow({
    project,
    changeOrder,
    photoLimit,
    feedbackRound = null,
    feedbackRoundHistory = [],
    technicians = [],
}: Readonly<ChangeOrdersShowProps>) {
    const projectIdentifier = project.hash_id || project.id;
    const isDraft = changeOrder.status === 'draft';
    const creatorName = changeOrder.creator?.name ?? 'Project Manager';
    const creatorAvatar = changeOrder.creator?.profile_photo_url;
    const items = React.useMemo(() => changeOrder.items ?? [], [changeOrder.items]);
    const photoCount = changeOrder.photos?.length ?? 0;

    const feedback = useEntityFeedback({
        entityType: 'change_order',
        entityId: changeOrder.id,
        round: feedbackRound,
        isManager: true,
    });

    const totalIncludedAmount = React.useMemo(() => {
        return items.reduce((sum, item) => {
            if (!item.is_included || item.cost_absorbed_by_solucomp) return sum;
            const val =
                typeof item.amount === 'number'
                    ? item.amount
                    : Number.parseFloat(String(item.amount)) || 0;
            return sum + val;
        }, 0);
    }, [items]);

    const includedItemsCount = React.useMemo(() => {
        return items.filter((item) => item.is_included && !item.cost_absorbed_by_solucomp).length;
    }, [items]);

    const isDecided = changeOrder.status === 'approved' || changeOrder.status === 'rejected';

    const handleThreadReply = React.useCallback(
        async (
            photoId: number | null,
            photoType: string | null,
            body: string,
            replacementFile?: File | null,
            options?: PhotoActionOptions
        ) => {
            await feedback.saveAnnotationNote({
                entityType: 'change_order',
                entityId: changeOrder.id,
                originalImageUrl: null,
                body,
                sourcePhotoId: photoId,
                sourcePhotoType: photoType === null ? 'section' : photoType,
                replacementImageFile: replacementFile,
                replaceInChangeOrder: options?.replaceInChangeOrder,
                addToChangeOrderPhotos: options?.addToChangeOrderPhotos,
            });
        },
        [changeOrder.id, feedback]
    );

    return (
        <AppLayout
            title={changeOrder.title}
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                { label: project.name, href: route('projects.show', projectIdentifier) },
                {
                    label: 'Change Orders',
                    href: route('projects.change-orders.index', projectIdentifier),
                },
                { label: changeOrder.title },
            ]}
            headerActions={
                <ChangeOrderHeaderActions
                    projectIdentifier={projectIdentifier}
                    changeOrder={changeOrder}
                />
            }
        >
            <Head title={`Change Order: ${changeOrder.title} - ${project.name}`} />

            <div className="space-y-4">
                <ChangeOrderSummaryCards
                    project={project}
                    changeOrder={changeOrder}
                    creatorName={creatorName}
                    creatorAvatar={creatorAvatar}
                    isDraft={isDraft}
                />

                {/* Items, details, and activity are intentionally visible together for quick review. */}
                <div className="space-y-5">
                    <section aria-labelledby="items-heading" className="space-y-2">
                        <div className="flex items-end justify-between gap-3 px-1">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
                                    Scope of work
                                </p>
                                <h2
                                    id="items-heading"
                                    className="mt-1 text-lg font-bold tracking-tight text-foreground"
                                >
                                    Item details
                                </h2>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {includedItemsCount} of {items.length} included
                            </span>
                        </div>
                        <ChangeOrderItemsTable
                            items={items}
                            includedItemsCount={includedItemsCount}
                            totalIncludedAmount={totalIncludedAmount}
                        />
                    </section>

                    <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)] gap-5">
                        <section
                            aria-labelledby="details-heading"
                            className="space-y-2 flex flex-col"
                        >
                            <div className="px-1">
                                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
                                    Context & ownership
                                </p>
                                <h2
                                    id="details-heading"
                                    className="mt-1 text-lg font-bold tracking-tight text-foreground"
                                >
                                    Change order details
                                </h2>
                            </div>
                            <div className="flex-1 flex">
                                <ChangeOrderDetailsCard changeOrder={changeOrder} />
                            </div>
                        </section>

                        <section
                            aria-labelledby="timeline-heading"
                            className="space-y-2 flex flex-col"
                        >
                            <div className="px-1">
                                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
                                    Live record
                                </p>
                                <h2
                                    id="timeline-heading"
                                    className="mt-1 text-lg font-bold tracking-tight text-foreground"
                                >
                                    Activity history
                                </h2>
                            </div>
                            <div className="flex-1 flex">
                                <ChangeOrderTimeline
                                    changeOrder={changeOrder}
                                    creatorName={creatorName}
                                />
                            </div>
                        </section>
                    </div>

                    {/* Photos live below both cards: a large gallery would otherwise
                        stretch the cover-note card past the activity history. */}
                    <section
                        aria-labelledby="photos-heading"
                        className="space-y-2 [content-visibility:auto] [contain-intrinsic-size:0_320px]"
                    >
                        <div className="flex items-end justify-between gap-3 px-1">
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
                                    Site evidence
                                </p>
                                <h2
                                    id="photos-heading"
                                    className="mt-1 text-lg font-bold tracking-tight text-foreground"
                                >
                                    Supporting photos
                                </h2>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {photoCount === 0 ? 'None attached' : `${photoCount} attached`}
                            </span>
                        </div>
                        <ChangeOrderPhotoSection
                            changeOrder={changeOrder}
                            projectName={project.name}
                            photoLimit={photoLimit}
                        />
                    </section>

                    <section
                        aria-label="Customer review and annotations"
                        className="[content-visibility:auto] [contain-intrinsic-size:0_450px]"
                    >
                        <EntityFeedbackSection
                            round={feedback.activeRound}
                            history={feedbackRoundHistory}
                            isManager
                            technicians={technicians}
                            onDeleteNote={feedback.deleteNote}
                            onDeleteRound={feedback.deleteRound}
                            onEditNote={feedback.editNote}
                            onResolveNote={feedback.resolveNote}
                            onResolveAll={feedback.resolveAll}
                            onForwardTechnician={feedback.forwardToTechnician}
                            onAcknowledge={feedback.acknowledge}
                            canAcknowledge={feedback.canAcknowledge}
                            isAcknowledged={feedback.isAcknowledged}
                            onReply={handleThreadReply}
                            canReply={!isDecided && feedback.canReply}
                        />
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

export default ChangeOrdersShow;
