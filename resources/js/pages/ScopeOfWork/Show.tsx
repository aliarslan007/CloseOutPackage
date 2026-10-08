import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { sowImagesApi } from '@/features/sow/api/sow-images.api';
import { sowMediaImportApi } from '@/features/sow/api/sow-media-import.api';
import { AttachProjectDialog } from '@/features/sow/components/dialogs/attach-project-dialog';
import { SowReopenDialog } from '@/features/sow/components/dialogs/sow-reopen-dialog';
import { SowCanvasEditor } from '@/features/sow/components/editor/sow-canvas-editor';
import { SowUndoRedoControls } from '@/features/sow/components/editor/sow-undo-redo-controls';
import { SowChecklistTab } from '@/features/sow/components/report/sow-checklist-tab';
import { SowImageGroupBoard } from '@/features/sow/components/report/sow-image-group-board';
import { SowProjectDataForm } from '@/features/sow/components/report/sow-project-data-form';
import { SowReopenBanner } from '@/features/sow/components/report/sow-reopen-banner';
import { SowReportStatusBadge } from '@/features/sow/components/report/sow-report-status-badge';
import { SowReviewTab } from '@/features/sow/components/report/sow-review-tab';
import { SowSurveyDataForm } from '@/features/sow/components/report/sow-survey-data-form';
import { useSowEditor } from '@/features/sow/hooks/use-sow-editor';
import { useSowPdfGenerationWatcher } from '@/features/sow/hooks/use-sow-pdf-generation-watcher';
import { useSowPhotoUpload } from '@/features/sow/hooks/use-sow-photo-upload';
import { useSowReports } from '@/features/sow/hooks/use-sow-reports';
import type {
    SowChecklistEntry,
    SowReport,
    SowReportAbilities,
} from '@/features/sow/types/sow.types';
import { sowErrorMessage } from '@/features/sow/utils/sow-error';
import { MediaPickerModal } from '@/features/media-library';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types/inertia';
import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    CheckCircle2,
    CheckSquare,
    ClipboardList,
    Download,
    FileText,
    ImagePlus,
    Link2,
    Loader2,
    Lock,
    RotateCcw,
    Save,
    Send,
    Trash2,
    Undo2,
    Upload,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

export interface SowShowProps extends PageProps {
    report: SowReport;
    checklist: SowChecklistEntry[];
    can: SowReportAbilities;
    maxImages?: number;
    projects: Array<{ id: number; name: string; ps_number?: string | null }>;
}

type Tab = 'data' | 'photos' | 'checklist' | 'review';

const TABS: Array<{ id: Tab; label: string; icon: React.ReactNode }> = [
    { id: 'data', label: '1. Project & Survey Data', icon: <ClipboardList className="size-3.5" /> },
    { id: 'photos', label: '2. Photos & Annotations', icon: <FileText className="size-3.5" /> },
    { id: 'checklist', label: '3. Survey Checklist', icon: <CheckSquare className="size-3.5" /> },
    { id: 'review', label: '4. Submit & Finalize', icon: <Send className="size-3.5" /> },
];

function getAnnotationsSaveStatus(
    isSaving: boolean,
    isDirty: boolean,
    isSwitching = false
): string {
    if (isSwitching) return 'Saving before switching…';
    if (isSaving) return 'Saving…';
    if (isDirty) return 'Unsaved changes';
    return 'All changes saved';
}

/** "Uploading… 3/12" while a multi-file batch runs, so a 20-photo import isn't a dead spinner. */
function getUploadStatus(progress: { done: number; total: number } | null): string | null {
    if (progress === null) return null;
    return `Uploading… ${progress.done}/${progress.total}`;
}

interface SowHeaderActionsProps {
    report: SowReport;
    can: SowReportAbilities;
    isPublishing: boolean;
    isSubmitting: boolean;
    isReopening: boolean;
    isArchiving: boolean;
    isUnarchiving: boolean;
    isRegenerating: boolean;
    onPublish: () => void;
    onSubmit: () => void;
    onReopen: () => void;
    onAttach: () => void;
    onArchive: () => void;
    onUnarchive: () => void;
    onDelete: () => void;
    onRegenerate: () => void;
}

function SowHeaderActions({
    report,
    can,
    isPublishing,
    isSubmitting,
    isReopening,
    isArchiving,
    isUnarchiving,
    isRegenerating,
    onPublish,
    onSubmit,
    onReopen,
    onAttach,
    onArchive,
    onUnarchive,
    onDelete,
    onRegenerate,
}: Readonly<SowHeaderActionsProps>) {
    return (
        <div className="flex items-center gap-2 shrink-0 sm:ml-auto flex-wrap sm:flex-nowrap">
            {report.status === 'draft' && !can.update && !can.submit && (
                <span
                    className="inline-flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/60 border border-border/80 rounded-md px-2.5 py-1"
                    title="This report is in draft by the technician. You are viewing it in read-only mode until submitted for review."
                >
                    <Lock className="size-3.5 text-amber-500 shrink-0" />
                    <span>Technician draft · Read-only until submitted</span>
                </span>
            )}
            {can.publish && (
                <Button
                    size="sm"
                    disabled={isPublishing}
                    onClick={onPublish}
                    className="gap-1.5 h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                    {isPublishing ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <CheckCircle2 className="size-3.5" />
                    )}
                    Approve & Finalize
                </Button>
            )}
            {can.submit && (
                <Button
                    size="sm"
                    disabled={isSubmitting}
                    onClick={onSubmit}
                    className="gap-1.5 h-8 text-xs"
                >
                    {isSubmitting ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <Send className="size-3.5" />
                    )}
                    Submit for Review
                </Button>
            )}
            {report.status === 'published' && can.download && report.pdf_generation_status === 'ready' && (
                <Button
                    asChild
                    size="sm"
                    variant="info"
                    className="gap-1.5 h-8 text-xs font-medium"
                >
                    <a
                        href={route('sow.reports.pdf', report.id)}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Download className="size-3.5" />
                        Download PDF
                    </a>
                </Button>
            )}
            {report.status === 'published' && can.regenerate && (() => {
                const isGenerating = isRegenerating || report.pdf_generation_status === 'generating';
                let label = 'Regenerate';
                if (isGenerating) {
                    label = 'Generating…';
                } else if (report.pdf_generation_status === 'failed') {
                    label = 'Retry Generation';
                }
                return (
                    <Button
                        size="sm"
                        variant="warning"
                        disabled={isGenerating}
                        onClick={onRegenerate}
                        className="gap-1.5 h-8 text-xs"
                        title="Regenerate the PDF with latest changes"
                    >
                        <RotateCcw
                            className={cn('size-3.5', isGenerating && 'animate-spin')}
                        />
                        {label}
                    </Button>
                );
            })()}
            {(report.status === 'published' || report.status === 'in_review') && can.reopen && (
                <Button
                    size="sm"
                    variant="secondary"
                    disabled={isReopening}
                    onClick={onReopen}
                    className="gap-1.5 h-8 text-xs"
                >
                    <Undo2 className="size-3.5" />
                    Reopen
                </Button>
            )}
            {report.project_id === null && can.attach && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={onAttach}
                    className="gap-1.5 h-8 text-xs"
                >
                    <Link2 className="size-3.5" />
                    Attach to Project
                </Button>
            )}
            {report.status === 'published' && can.archive && (
                <Button
                    size="sm"
                    variant="outline"
                    disabled={isArchiving}
                    onClick={onArchive}
                    className="gap-1.5 h-8 text-xs"
                >
                    <Archive className="size-3.5" />
                    Archive
                </Button>
            )}
            {report.status === 'archived' && can.unarchive && (
                <Button
                    size="sm"
                    variant="outline"
                    disabled={isUnarchiving}
                    onClick={onUnarchive}
                    className="gap-1.5 h-8 text-xs font-medium"
                >
                    {isUnarchiving ? (
                        <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                        <ArchiveRestore className="size-3.5" />
                    )}
                    Unarchive
                </Button>
            )}
            {can.delete && (
                <Button
                    size="sm"
                    variant="destructive"
                    onClick={onDelete}
                    className="gap-1.5 h-8 text-xs"
                >
                    <Trash2 className="size-3.5" />
                    Delete
                </Button>
            )}
        </div>
    );
}

export function SowShow({ report, checklist, can, maxImages = 20, projects = [] }: Readonly<SowShowProps>) {
    useFlashToasts();
    const { refresh } = useSowReports();

    // The PDF renders in a queued job, so this page only sees the final status
    // when the `report` prop is re-fetched — poll it instead of letting the
    // buttons sit stuck on "Generating…".
    useSowPdfGenerationWatcher(report.pdf_generation_status);
    const [currentChecklist, setCurrentChecklist] = React.useState<SowChecklistEntry[]>(checklist);

    React.useEffect(() => {
        setCurrentChecklist(checklist);
    }, [checklist]);

    const [activeTab, setActiveTab] = React.useState<Tab>('data');
    const [importOpen, setImportOpen] = React.useState(false);
    const [attachOpen, setAttachOpen] = React.useState(false);
    const [deleteOpen, setDeleteOpen] = React.useState(false);
    const [isDeleting, setIsDeleting] = React.useState(false);
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    const [isPublishing, setIsPublishing] = React.useState(false);
    const [isReopening, setIsReopening] = React.useState(false);
    const [reopenDialogOpen, setReopenDialogOpen] = React.useState(false);
    const [isArchiving, setIsArchiving] = React.useState(false);
    const [isUnarchiving, setIsUnarchiving] = React.useState(false);
    const images = React.useMemo(() => report.annotated_images ?? [], [report.annotated_images]);
    const atLimit = images.length >= maxImages;
    const alreadyAttachedIds = React.useMemo(
        () => images.map((image) => image.media_asset_id),
        [images]
    );
    const {
        fileInputRef,
        progress,
        isUploading,
        browseFiles,
        chooseFiles,
    } = useSowPhotoUpload(report.id, report.project_id, Math.max(0, maxImages - images.length));
    const uploadStatus = getUploadStatus(progress);
    const [selectedImageId, setSelectedImageId] = React.useState<number | null>(
        images[0]?.id ?? null
    );

    // Keep selection valid after a refresh (e.g. the selected photo was deleted,
    // or the first import landed while selectedImageId was still null).
    const activeImage = React.useMemo(() => {
        const current = images.find((image) => image.id === selectedImageId);
        return current ?? images[0] ?? null;
    }, [images, selectedImageId]);

    React.useEffect(() => {
        if (!images.some((image) => image.id === selectedImageId)) {
            setSelectedImageId(images[0]?.id ?? null);
        }
    }, [images, selectedImageId]);

    const [pageTitle, setPageTitle] = React.useState(activeImage?.page_title ?? '');
    const [isSavingTitle, setIsSavingTitle] = React.useState(false);

    // Annotations editor state lives here so Save + status can sit in the
    // photo header row (§13.7) instead of a dedicated row above the canvas.
    // key changes per photo via the <SowCanvasEditor key=…> remount.
    const annotationsEditor = useSowEditor({
        reportId: report.id,
        imageId: activeImage?.id ?? 0,
        initialLayer: activeImage?.annotation_layer,
        initialUpdatedAt: activeImage?.annotations_updated_at ?? null,
        onSaved: refresh,
    });

    const [isSwitchingPhoto, setIsSwitchingPhoto] = React.useState(false);

    const handleSelectPhoto = async (nextId: number) => {
        if (nextId === selectedImageId || isSwitchingPhoto) return;
        if (annotationsEditor.isDirty) {
            setIsSwitchingPhoto(true);
            const saved = await annotationsEditor.save();
            setIsSwitchingPhoto(false);
            if (!saved) {
                toast.error('Could not save current photo annotations. Please try again.');
                return;
            }
            toast.success('Annotations saved.');
        }
        setSelectedImageId(nextId);
    };

    const handleTabChange = async (nextTab: Tab) => {
        if (nextTab === activeTab) return;
        if (activeTab === 'photos' && annotationsEditor.isDirty) {
            const saved = await annotationsEditor.save();
            if (!saved) {
                toast.error('Could not save photo annotations. Please try again.');
                return;
            }
            toast.success('Annotations saved.');
        }
        setActiveTab(nextTab);
    };

    const annotationsSaveStatus = getAnnotationsSaveStatus(
        annotationsEditor.isSaving,
        annotationsEditor.isDirty,
        isSwitchingPhoto
    );

    React.useEffect(() => {
        setPageTitle(activeImage?.page_title ?? '');
    }, [activeImage?.id, activeImage?.page_title]);

    const savePageTitle = async () => {
        if (!activeImage || isSavingTitle) return;
        // Blur fires after every edit — skip the round-trip when nothing changed.
        if (pageTitle.trim() === (activeImage.page_title ?? '')) return;
        setIsSavingTitle(true);
        try {
            await sowImagesApi.update(report.id, activeImage.id, {
                page_title: pageTitle.trim() || null,
            });
            toast.success('Photo heading saved.');
            refresh();
        } catch (err) {
            toast.error(sowErrorMessage(err, 'Failed to save the photo heading.'));
        } finally {
            setIsSavingTitle(false);
        }
    };

    const submitForReview = () => {
        setIsSubmitting(true);
        router.post(
            route('sow.reports.submit', { report: report.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    const publishSow = () => {
        setIsPublishing(true);
        // The render is queued: the watcher hook picks the status up.
        router.post(
            route('sow.reports.publish', { report: report.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsPublishing(false),
            }
        );
    };

    const handleReopenConfirm = (reason: string) => {
        setIsReopening(true);
        router.post(
            route('sow.reports.reopen', { report: report.id }),
            { notes: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReopenDialogOpen(false);
                    toast.success('SOW report reopened for editing. Field technician notified.');
                },
                onError: () => {
                    toast.error('Failed to reopen SOW report.');
                },
                onFinish: () => setIsReopening(false),
            }
        );
    };

    const archive = () => {
        setIsArchiving(true);
        router.post(
            route('sow.reports.archive', { report: report.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsArchiving(false),
            }
        );
    };

    const unarchive = () => {
        setIsUnarchiving(true);
        router.post(
            route('sow.reports.unarchive', { report: report.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsUnarchiving(false),
            }
        );
    };

    const [isRegenerating, setIsRegenerating] = React.useState(false);

    const regeneratePdf = () => {
        setIsRegenerating(true);
        router.post(
            route('sow.reports.pdf.regenerate', { report: report.id }),
            {},
            {
                preserveScroll: true,
                // Success copy comes from the controller's flash toast — no duplicate here.
                onError: () => {
                    toast.error('Failed to regenerate PDF.');
                },
                onFinish: () => setIsRegenerating(false),
            }
        );
    };

    const destroy = () => {
        setIsDeleting(true);
        router.delete(route('sow.reports.destroy', { report: report.id }), {
            onFinish: () => setIsDeleting(false),
        });
    };

    const imageUrl = activeImage?.media_asset?.url || null;
    const isProcessing = activeImage?.media_asset?.status === 'processing';
    const photoPlaceholder = (
        <div className="flex h-64 items-center justify-center gap-2 rounded-md border border-dashed border-border text-sm text-muted-foreground">
            {isProcessing && <Loader2 className="size-4 animate-spin" />}
            {isProcessing ? 'Processing…' : 'Photo unavailable'}
        </div>
    );

    const importFromLibrary = async (assetIds: number[]) => {
        await sowMediaImportApi.importMedia(report.id, assetIds);
        refresh();
    };

    return (
        <AppLayout
            breadcrumb={[
                { label: 'Scope of Work', href: route('sow.reports.index') },
                { label: report.site_name ?? report.title },
            ]}
        >
            <Head title={report.site_name ?? report.title} />
            <div className="space-y-3">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-x-4 gap-y-2 pb-2 border-b border-border/70">
                    <div className="flex items-start sm:items-center gap-3 min-w-0">
                        <Button asChild variant="outline" size="icon" className="size-8 shrink-0">
                            <Link href={route('sow.reports.index')} title="Back to SOW list">
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="font-heading text-lg sm:text-xl font-bold tracking-tight text-foreground leading-tight truncate">
                                    {report.site_name ?? report.title}
                                </h1>
                                <SowReportStatusBadge status={report.status} />
                                <Badge variant="outline" className="text-xs">
                                    {report.project ? (
                                        <span className="inline-flex items-center gap-1">
                                            <Link2 className="size-3" />
                                            Linked to {report.project.name}
                                        </span>
                                    ) : (
                                        'Standalone SOW'
                                    )}
                                </Badge>
                                {report.status === 'draft' && !can.update && (
                                    <Badge
                                        variant="secondary"
                                        className="text-[11px] gap-1 font-normal bg-amber-500/10 text-amber-700 border-amber-500/30 dark:text-amber-400"
                                        title="Technician draft in progress. Viewing in read-only mode until submitted for review."
                                    >
                                        <Lock className="size-3 text-amber-600 dark:text-amber-400" />
                                        Read-Only Draft
                                    </Badge>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                {report.market?.name && (
                                    <span className="font-medium text-foreground/80">
                                        {report.market.name}
                                    </span>
                                )}
                                <span>· Rev {report.revision}</span>
                            </p>
                        </div>
                    </div>

                    <SowHeaderActions
                        report={report}
                        can={can}
                        isPublishing={isPublishing}
                        isSubmitting={isSubmitting}
                        isReopening={isReopening}
                        isArchiving={isArchiving}
                        isUnarchiving={isUnarchiving}
                        isRegenerating={isRegenerating}
                        onPublish={publishSow}
                        onSubmit={submitForReview}
                        onReopen={() => setReopenDialogOpen(true)}
                        onAttach={() => setAttachOpen(true)}
                        onArchive={archive}
                        onUnarchive={unarchive}
                        onDelete={() => setDeleteOpen(true)}
                        onRegenerate={regeneratePdf}
                    />
                </div>

                <SowReopenBanner report={report} className="-mt-1" />

                <div className="flex gap-1 overflow-x-auto no-scrollbar border-b border-border">
                    {TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => void handleTabChange(tab.id)}
                            className={cn(
                                'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                                activeTab === tab.id
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            )}
                        >
                            {tab.icon}
                            {tab.label}
                        </button>
                    ))}
                </div>

                {activeTab === 'data' && (
                    <div className="space-y-4">
                        <SowProjectDataForm
                            key={`project-${report.id}-${report.updated_at}`}
                            reportId={report.id}
                            readOnly={!can.update}
                            initial={{
                                site_address: report.site_address,
                                site_city: report.site_city,
                                site_county: report.site_county,
                                latitude: report.latitude,
                                longitude: report.longitude,
                                project_description: report.project_description,
                            }}
                        />
                        <SowSurveyDataForm
                            key={`survey-${report.id}-${report.updated_at}`}
                            reportId={report.id}
                            readOnly={!can.update}
                            initial={{
                                field_survey_date: report.field_survey_date,
                                fiber_provider: report.fiber_provider,
                                provider_contact_name: report.provider_contact_name,
                                provider_contact_phone: report.provider_contact_phone,
                                provider_contact_email: report.provider_contact_email,
                                demarcation: report.demarcation,
                                ps_number: report.ps_number,
                                survey_contractor_name: report.survey_contractor_name,
                                survey_contact_name: report.survey_contact_name,
                                survey_contact_phone: report.survey_contact_phone,
                                survey_contact_email: report.survey_contact_email,
                                scope_of_work_description: report.scope_of_work_description,
                                site_access_notes: report.site_access_notes,
                            }}
                        />
                    </div>
                )}

                {activeTab === 'photos' && (
                    <div className="space-y-3">
                        {images.length === 0 ? (
                            <Card className="border-dashed">
                                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                                    <div className="rounded-full bg-muted p-3 mb-3">
                                        <ImagePlus className="size-6 text-muted-foreground" />
                                    </div>
                                    <h3 className="text-base font-semibold text-foreground">
                                        No photos added yet
                                    </h3>
                                    <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                        Import cell site photos from the central Media Library to
                                        start annotating demarcations, cabling, and equipment.
                                        Maximum {maxImages} photos.
                                    </p>
                                    {can.update && (
                                        <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                onClick={browseFiles}
                                                disabled={isUploading || atLimit}
                                                className="gap-1.5 text-xs"
                                            >
                                                {isUploading ? (
                                                    <>
                                                        <Loader2 className="size-3.5 animate-spin" />
                                                        {uploadStatus ?? 'Uploading…'}
                                                    </>
                                                ) : (
                                                    <>
                                                        <Upload className="size-3.5" />
                                                        Upload Photos
                                                    </>
                                                )}
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setImportOpen(true)}
                                                disabled={atLimit}
                                                className="gap-1.5 text-xs"
                                            >
                                                <ImagePlus className="size-3.5" />
                                                Import from Media Library
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                {activeImage && (
                                    <div className="rounded-lg border border-border bg-muted/40 p-2.5">
                                        <div className="mb-1.5 flex flex-col gap-2 lg:grid lg:grid-cols-[1fr_auto_1fr] lg:items-center lg:gap-3">
                                            {/* Left — photo name, badges, save status */}
                                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                                <span className="text-sm font-medium">
                                                    {activeImage.page_title ??
                                                        activeImage.media_asset
                                                            ?.original_filename ??
                                                        `Photo ${activeImage.sort_order}`}
                                                </span>
                                                {activeImage.is_cover && (
                                                    <Badge variant="default">Cover</Badge>
                                                )}
                                                {!activeImage.include_in_pdf && (
                                                    <Badge variant="secondary">
                                                        Excluded from PDF
                                                    </Badge>
                                                )}
                                                {can.update && imageUrl && (
                                                    <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <span
                                                            aria-hidden
                                                            className={cn(
                                                                'size-1.5 rounded-full',
                                                                annotationsEditor.isDirty
                                                                    ? 'bg-amber-500'
                                                                    : 'bg-emerald-500'
                                                            )}
                                                        />
                                                        {annotationsSaveStatus}
                                                    </span>
                                                )}
                                            </div>

                                            {/* Center — undo / redo, on the same row as the photo name and Save */}
                                            <div className="flex items-center justify-center">
                                                <SowUndoRedoControls
                                                    editor={annotationsEditor}
                                                    visible={can.update}
                                                    hasImage={Boolean(imageUrl)}
                                                />
                                            </div>

                                            {/* Right — photo heading field, then Save */}
                                            {can.update ? (
                                                <div className="flex items-center gap-1.5 lg:justify-self-end">
                                                    <Input
                                                        value={pageTitle}
                                                        onChange={(e) =>
                                                            setPageTitle(e.target.value)
                                                        }
                                                        onBlur={() => void savePageTitle()}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                e.currentTarget.blur();
                                                            }
                                                        }}
                                                        disabled={isSavingTitle}
                                                        placeholder="Photo heading (shown in PDF)…"
                                                        title="Saves automatically when you leave the field"
                                                        className="h-8 w-full text-xs sm:w-64"
                                                    />
                                                    {isSavingTitle && (
                                                        <Loader2 className="size-3.5 shrink-0 animate-spin text-muted-foreground" />
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        disabled={
                                                            !imageUrl ||
                                                            !annotationsEditor.isDirty ||
                                                            annotationsEditor.isSaving
                                                        }
                                                        onClick={() => annotationsEditor.save()}
                                                        title="Save annotations to this photo"
                                                        className="h-8 gap-1.5 bg-info px-3 text-xs text-info-foreground shadow-sm hover:bg-info/90"
                                                    >
                                                        {annotationsEditor.isSaving ? (
                                                            <Loader2 className="size-3.5 animate-spin" />
                                                        ) : (
                                                            <Save className="size-3.5" />
                                                        )}
                                                        Save
                                                    </Button>
                                                </div>
                                            ) : (
                                                activeImage.page_title && (
                                                    <span className="text-xs font-semibold text-foreground/80 lg:justify-self-end">
                                                        {activeImage.page_title}
                                                    </span>
                                                )
                                            )}
                                        </div>

                                        {imageUrl ? (
                                            <SowCanvasEditor
                                                key={activeImage.id}
                                                imageId={activeImage.id}
                                                imageUrl={imageUrl}
                                                editor={annotationsEditor}
                                                readOnly={!can.update}
                                            />
                                        ) : (
                                            photoPlaceholder
                                        )}
                                    </div>
                                )}

                                {images.length > 0 && (
                                    <SowImageGroupBoard
                                        report={report}
                                        canUpdate={can.update}
                                        maxImages={maxImages}
                                        selectedImageId={selectedImageId}
                                        onSelect={handleSelectPhoto}
                                        onImportClick={() => setImportOpen(true)}
                                        onUploadClick={browseFiles}
                                        isUploading={isUploading}
                                        uploadStatus={uploadStatus}
                                    />
                                )}
                            </>
                        )}
                    </div>
                )}

                {activeTab === 'checklist' && (
                    <SowChecklistTab
                        reportId={report.id}
                        checklist={currentChecklist}
                        canEdit={can.update}
                        onSaved={(updated) => {
                            setCurrentChecklist(updated);
                            refresh();
                        }}
                        onChange={setCurrentChecklist}
                    />
                )}

                {activeTab === 'review' && (
                    <SowReviewTab
                        report={report}
                        checklist={currentChecklist}
                        images={images}
                        can={can}
                        isPublishing={isPublishing}
                        isSubmitting={isSubmitting}
                        isReopening={isReopening}
                        isRegenerating={isRegenerating}
                        onPublish={publishSow}
                        onSubmit={submitForReview}
                        onReopen={() => setReopenDialogOpen(true)}
                        onRegenerate={regeneratePdf}
                    />
                )}
            </div>

            <input
                ref={fileInputRef}
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={(e) => void chooseFiles(e)}
            />

            {can.update && (
                <MediaPickerModal
                    isOpen={importOpen}
                    onClose={() => setImportOpen(false)}
                    projectId={report.project_id ?? undefined}
                    projectName={report.project?.name}
                    excludeIds={alreadyAttachedIds}
                    onAssign={importFromLibrary}
                />
            )}

            <AttachProjectDialog
                isOpen={attachOpen}
                onClose={() => setAttachOpen(false)}
                reportId={report.id}
                projects={projects}
            />

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title="Delete SOW report"
                description={`This permanently removes "${report.site_name ?? report.title}" and its photos. This cannot be undone.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={isDeleting}
                onConfirm={destroy}
            />

            <SowReopenDialog
                open={reopenDialogOpen}
                onOpenChange={setReopenDialogOpen}
                technicianName={report.creator?.name}
                loading={isReopening}
                onConfirm={handleReopenConfirm}
            />
        </AppLayout>
    );
}

export default SowShow;
