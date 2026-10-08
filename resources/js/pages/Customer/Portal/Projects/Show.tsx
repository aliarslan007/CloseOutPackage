import {
    EntityFeedbackSection,
    FeedbackCanvasDialog,
    FeedbackSubmitBar,
    useEntityFeedback,
} from '@/features/entity-feedback';
import { EmptyCard } from '@/components/shared/empty-card';
import { DocumentsHubCard } from '@/features/project-documents';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { Building2, Camera, CheckCircle2, ClipboardCheck, Wrench } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { CustomerChecklistTab } from './components/CustomerChecklistTab';
import { CustomerPackagesCard } from './components/CustomerPackagesCard';
import { CustomerPhotoNoteDialog } from './components/CustomerPhotoNoteDialog';
import { CustomerPortalHeaderActions } from './components/CustomerPortalHeaderActions';
import { useCustomerProjectHeaderMeta } from './components/CustomerProjectHeaderMeta';
import { CustomerStagePhotos } from './components/CustomerStagePhotos';
import { CustomerStageTabs, CustomerTabKey, StageTabItem } from './components/CustomerStageTabs';
import { CustomerPackageItem, CustomerPhotoData, CustomerProjectShowProps } from './types';
import { organizeStagePhotos } from './utils';

const PORTAL_INDEX_ROUTE = 'portal.index';

export function CustomerProjectShow() {
    useFlashToasts();

    const { project, projectDocuments } = usePage<CustomerProjectShowProps>().props;
    const [activeTab, setActiveTab] = useState<CustomerTabKey>('pre_construction');

    const packages = useMemo(() => {
        if (!project) return [];
        return project.packages ?? project.approved_packages ?? [];
    }, [project]);

    const [selectedPackageKey, setSelectedPackageKey] = useState<string>(() => {
        return new URLSearchParams(globalThis.location.search).get('package') ?? '';
    });

    const activePackage = useMemo(() => {
        const requested = packages.find(
            (p) => (p.hash_id ?? String(p.id)) === selectedPackageKey && !p.locked
        );
        return requested ?? packages.find((p) => !p.locked) ?? null;
    }, [packages, selectedPackageKey]);

    const selectPackage = (pkg: CustomerPackageItem) => {
        const key = pkg.hash_id ?? String(pkg.id);
        setSelectedPackageKey(key);

        const params = new URLSearchParams(globalThis.location.search);
        params.set('package', key);
        globalThis.history.replaceState(null, '', `${globalThis.location.pathname}?${params}`);
    };

    const feedbackRound = activePackage?.feedback_round ?? activePackage?.feedbackRound ?? null;
    const feedbackRoundHistory =
        activePackage?.feedbackRoundHistory ?? activePackage?.feedback_round_history ?? [];

    const feedback = useEntityFeedback({
        entityType: 'closeout_package',
        entityId: activePackage?.id ?? 0,
        round: feedbackRound,
        isCustomer: true,
    });

    const [photoForCanvas, setPhotoForCanvas] = useState<CustomerPhotoData | null>(null);

    // A category has no image to mark up, so it keeps the plain text dialog —
    // saved through the same feedback engine, with no annotation attached.
    const [categoryNoteTarget, setCategoryNoteTarget] = useState<{
        id: number;
        title: string;
    } | null>(null);

    const handleSaveAnnotation = async (data: {
        blob: Blob;
        layerJson: string;
        body: string;
        requestedAction?: string;
    }) => {
        if (!photoForCanvas || !activePackage) return;

        await feedback.saveAnnotationNote({
            entityType: 'closeout_package',
            entityId: activePackage.id,
            originalImageUrl: photoForCanvas.url || photoForCanvas.photo_url || null,
            body: data.body,
            sourcePhotoId: photoForCanvas.id,
            sourcePhotoType: 'photo',
            annotatedImageBlob: data.blob,
            annotationLayerJson: data.layerJson,
            requestedAction: data.requestedAction,
        });
    };
    const { headerTags, headerSubtitle } = useCustomerProjectHeaderMeta(project);

    const allPhotos = useMemo(() => activePackage?.photos ?? [], [activePackage]);
    const stageGroups = useMemo(() => organizeStagePhotos(allPhotos), [allPhotos]);

    // Keyed `${type}:${id}`: template category ids and selection ids overlap with
    // template section ids, and a bare number key shows one as the other.
    const itemNamesByKey = useMemo(() => {
        const map: Record<string, string> = {};
        allPhotos.forEach((photo) => {
            const catName =
                photo.category_selection?.user_heading ||
                photo.category_selection?.template_picture_category?.name ||
                photo.category;
            const tplId =
                photo.category_selection?.template_picture_category?.id ??
                photo.category_selection?.template_picture_category_id;
            const selId = photo.category_selection?.id ?? photo.package_category_selection_id;

            if (tplId != null && catName) {
                map[`category:${tplId}`] = catName;
            }
            if (selId != null && catName) {
                map[`category:${selId}`] = catName;
            }
            // Photo threads read "Marked-up photo · Grounding" instead of a bare
            // "Marked-up photo" when a review holds images from several sections.
            if (photo.id != null && catName) {
                map[`photo:${photo.id}`] = catName;
            }
        });
        return map;
    }, [allPhotos]);

    const checklistResponses = useMemo(() => {
        return activePackage?.checklist_responses ?? activePackage?.checklistResponses ?? [];
    }, [activePackage]);

    const adminNotes = useMemo(() => {
        return (
            activePackage?.notes ??
            project?.notes ??
            activePackage?.site_info?.notes ??
            activePackage?.siteInfo?.notes ??
            null
        );
    }, [activePackage, project]);

    const counts = useMemo(() => {
        const preGroup = stageGroups.find((g) => g.key === 'pre_construction');
        const constGroup = stageGroups.find((g) => g.key === 'construction');
        const postGroup = stageGroups.find((g) => g.key === 'post_completion');

        let preSum = 0;
        preGroup?.categories.forEach((list) => {
            preSum += list.length;
        });
        let constSum = 0;
        constGroup?.categories.forEach((list) => {
            constSum += list.length;
        });
        let postSum = 0;
        postGroup?.categories.forEach((list) => {
            postSum += list.length;
        });

        return {
            pre_construction: preSum,
            construction: constSum,
            post_completion: postSum,
            checklist: checklistResponses.length,
        };
    }, [stageGroups, checklistResponses]);

    if (!project) {
        return (
            <AppLayout
                title="Project Details"
                breadcrumb={[
                    { label: 'Customer Portal', href: route(PORTAL_INDEX_ROUTE) },
                    { label: 'Project' },
                ]}
            >
                <Head title="Project Details" />
                <EmptyCard
                    title="Project unavailable"
                    description="The project details could not be loaded or you do not have permission to view them."
                    icon={<Building2 className="size-10 text-muted-foreground" />}
                />
            </AppLayout>
        );
    }

    const handleRequestPhotoNote = () => {
        // Reached only from a keyboard/AT path on a photo; the canvas is the
        // photo affordance, so send focus there instead of a second dialog.
        setPhotoForCanvas(null);
    };

    const handleRequestCategoryNote = (catName: string, catId?: number) => {
        if (catId == null) return;
        setCategoryNoteTarget({ id: catId, title: catName });
    };

    const findCategoryNote = (categoryId: number) =>
        feedback.notes.find((note) => {
            const isCategoryOrSection =
                note.source_photo_type === 'category' || note.source_photo_type === 'section';
            if (!isCategoryOrSection || note.source_photo_id == null) {
                return false;
            }
            if (note.source_photo_id === categoryId) {
                return true;
            }
            const noteCatName = itemNamesByKey[`category:${note.source_photo_id}`];
            const targetCatName = itemNamesByKey[`category:${categoryId}`];
            return Boolean(noteCatName && targetCatName && noteCatName === targetCatName);
        }) ?? null;

    const categoryNote = categoryNoteTarget ? findCategoryNote(categoryNoteTarget.id) : null;

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
        if (!activePackage) return;

        await feedback.saveAnnotationNote({
            entityType: 'closeout_package',
            entityId: activePackage.id,
            originalImageUrl: null,
            body,
            sourcePhotoId: photoId,
            sourcePhotoType: photoType === null ? 'section' : photoType,
            replacementImageFile: replacementFile,
        });
    };

    const handleSaveCategoryNote = async (body: string) => {
        if (!categoryNoteTarget || !activePackage) return;

        await feedback.saveAnnotationNote({
            entityType: 'closeout_package',
            entityId: activePackage.id,
            originalImageUrl: null,
            body,
            sourcePhotoId: categoryNoteTarget.id,
            sourcePhotoType: 'category',
        });
    };

    const tabs: StageTabItem[] = [
        {
            id: 'pre_construction',
            label: 'Pre Construction',
            icon: Camera,
            count: counts.pre_construction,
        },
        {
            id: 'construction',
            label: 'Construction / Installation',
            icon: Wrench,
            count: counts.construction,
        },
        {
            id: 'post_completion',
            label: 'Post Completion',
            icon: CheckCircle2,
            count: counts.post_completion,
        },
        {
            id: 'checklist',
            label: 'Checklist & Notes',
            icon: ClipboardCheck,
            count: counts.checklist,
        },
    ];

    return (
        <AppLayout
            title={project.name}
            breadcrumb={[
                { label: 'Customer Portal', href: route(PORTAL_INDEX_ROUTE) },
                {
                    label: project.name,
                    href: route('projects.show', project.hash_id || project.id),
                },
                { label: 'Close-Out Packages' },
            ]}
            headerTags={headerTags}
            headerSubtitle={headerSubtitle}
            headerActions={
                <CustomerPortalHeaderActions project={project} />
            }
        >
            <Head title={`Deliverables - ${project.name}`} />

            <div className="space-y-4 text-xs pb-16">
                {/* 1. All Packages — pick one to view its deliverables below */}
                <CustomerPackagesCard
                    packages={packages}
                    activePackageKey={
                        activePackage ? (activePackage.hash_id ?? String(activePackage.id)) : null
                    }
                    onSelect={selectPackage}
                />

                {/* Project documents — read-only for customers. */}
                <DocumentsHubCard
                    projectKey={project.hash_id || project.id}
                    documents={projectDocuments ?? {}}
                    canManage={false}
                />

                {/* 2. Stage Tab Navigation */}
                <CustomerStageTabs tabs={tabs} activeTab={activeTab} onSelectTab={setActiveTab} />

                {/* 3. Stage Content Area */}
                <div className="pt-1">
                    {activeTab === 'checklist' ? (
                        <CustomerChecklistTab
                            responses={checklistResponses}
                            adminNotes={adminNotes}
                            noteImages={activePackage?.site_note_images ?? []}
                        />
                    ) : (
                        <CustomerStagePhotos
                            allApprovedPhotos={allPhotos}
                            stageGroups={stageGroups}
                            activeStageKey={activeTab}
                            canAddNote={feedback.canAnnotate}
                            getPhotoNote={(photoId) => feedback.findNoteForPhoto(photoId)}
                            getCategoryNote={findCategoryNote}
                            onRequestPhotoNote={handleRequestPhotoNote}
                            onRequestCategoryNote={handleRequestCategoryNote}
                            onAnnotate={setPhotoForCanvas}
                        />
                    )}
                </div>
            </div>

            {/* Single feedback engine — same annotate canvas as change orders */}
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
                canReply={Boolean(activePackage)}
                onWithdrawIssue={feedback.withdrawIssue}
                itemNamesByKey={itemNamesByKey}
            />

            {categoryNoteTarget && (
                <CustomerPhotoNoteDialog
                    target={{
                        type: 'category',
                        id: categoryNoteTarget.id,
                        title: categoryNoteTarget.title,
                        subtitle: 'Category Section Change Request',
                        existingNote: categoryNote,
                    }}
                    open
                    submitting={feedback.isSubmitting}
                    onClose={() => setCategoryNoteTarget(null)}
                    onSave={handleSaveCategoryNote}
                />
            )}

            <FeedbackSubmitBar
                noteCount={feedback.hasOpenRound ? feedback.notes.length : 0}
                isSubmitting={feedback.isSubmitting}
                onSubmit={feedback.submitRound}
            />

            {photoForCanvas && (
                <FeedbackCanvasDialog
                    imageUrl={photoForCanvas.url || photoForCanvas.photo_url || ''}
                    imageTitle={photoForCanvas.category || 'Site Photo'}
                    existingNoteText={feedback.findNoteForPhoto(photoForCanvas.id)?.body ?? ''}
                    existingAnnotationLayer={
                        feedback.findNoteForPhoto(photoForCanvas.id)?.annotation_layer
                    }
                    isOpen={Boolean(photoForCanvas)}
                    isSaving={feedback.isSubmitting}
                    onClose={() => setPhotoForCanvas(null)}
                    onDelete={
                        photoForCanvas && feedback.findNoteForPhoto(photoForCanvas.id)
                            ? () => {
                                  const note = feedback.findNoteForPhoto(photoForCanvas.id);
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

export default CustomerProjectShow;
