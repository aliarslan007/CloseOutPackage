import {
    FinalApprovalDialog,
    type SiteDetailEntry,
} from '@/components/packages/final-approval-dialog';
import { PackageLockButton } from '@/components/packages/package-lock-button';
import { PackageLockNotice } from '@/components/packages/package-lock-notice';
import { PackageSiteNotes } from '@/components/packages/package-site-notes';
import { SiteAccessTrigger } from '@/components/packages/site-access-trigger';
import type { PackageSiteNoteImage } from '@/hooks/use-checklist-auto-save';
import {
    AdminNotesCenter,
    type PrimaryTechnicianContact,
} from '@/components/shared/admin-notes-panel';
import { GpsBadge } from '@/components/shared/gps-badge';
import { LockedBadge } from '@/components/shared/locked-badge';
import { PackagePdfActions } from '@/components/shared/package-pdf-actions';
import { PDFRevisionData, PDFRevisionTable } from '@/components/shared/pdf-components';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { StatusBadge } from '@/components/shared/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    EntityFeedbackSection,
    useEntityFeedback,
    buildItemNamesByKey,
    type EntityFeedbackRoundItem,
    type TechnicianOption,
} from '@/features/entity-feedback';
import { FeedbackSectionDivider } from '@/features/entity-feedback/components/FeedbackSectionDivider';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToasts } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ClipboardCheck,
    Download,
    Filter,
    Flag,
    Info,
    Lock,
    RotateCcw,
} from 'lucide-react';
import { useCallback, useEffect, useId, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { PhotoNoteSection } from './components/PhotoNoteSection';
import { PhotoReasonNote } from '@/components/shared/photo-reason-note';

type StageKey = 'pre_construction' | 'construction' | 'post_completion';

type ActiveView = StageKey | 'checklist';

const STAGE_ORDER: StageKey[] = ['pre_construction', 'construction', 'post_completion'];

const SUCCESS_CHIP_CLASS = 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400';

const MUTED_TEXT_CLASS = 'text-muted-foreground';

const STAGE_LABELS: Record<StageKey, string> = {
    pre_construction: 'Pre Construction',
    construction: 'Construction / Installation',
    post_completion: 'Post Completion',
};

const STAGE_SHORT_LABELS: Record<StageKey, string> = {
    pre_construction: 'Pre-Con',
    construction: 'Construction',
    post_completion: 'Post-Comp',
};

/**
 * UI cap for the `reason` field on every replacement/flag request.
 *
 * The backend is more permissive: RequestPhotoReplacementRequest,
 * FlagPhotoRequest and BulkReviewRequest each validate `max:1000`. We cap the
 * field tighter on purpose — a replacement reason is a short instruction, and a
 * 500-character ceiling keeps it readable. The textarea enforces this natively
 * via maxLength; the counter makes the limit visible before the user hits it.
 */
const REASON_MAX = 500;

function ReasonField({
    value,
    onChange,
    placeholder,
}: Readonly<{ value: string; onChange: (value: string) => void; placeholder: string }>) {
    const counterId = useId();
    const remaining = REASON_MAX - value.length;

    return (
        <div className="space-y-1.5">
            <Textarea
                value={value}
                onChange={(e) => onChange(e.target.value)}
                maxLength={REASON_MAX}
                placeholder={placeholder}
                aria-describedby={counterId}
                className="min-h-[80px] text-sm"
            />
            <p
                id={counterId}
                className={cn(
                    'text-right text-xs tabular-nums',
                    remaining <= 50 ? 'text-destructive' : MUTED_TEXT_CLASS
                )}
            >
                {value.length} / {REASON_MAX}
            </p>
        </div>
    );
}

/**
 * ?tab= values kept in the browser URL, so a hard refresh reopens the exact
 * step the user was on instead of falling back to the package's current stage.
 */
const STAGE_URL_SLUGS: Record<ActiveView, string> = {
    pre_construction: 'step-1',
    construction: 'step-2',
    post_completion: 'step-3',
    checklist: 'step-4',
};

function getStageStatusSubtext(
    active: boolean,
    completed: boolean
): { text: string; className: string } {
    if (active) {
        return { text: 'Current', className: 'text-primary font-bold' };
    }
    if (completed) {
        return { text: 'Done', className: 'text-emerald-600 dark:text-emerald-400 font-semibold' };
    }
    return { text: '·', className: 'text-transparent' };
}

interface CategoryData {
    id: number;
    name: string;
    description?: string;
    is_required?: boolean;
}

interface StageData {
    id?: number;
    stage: string;
    status: string;
    category_selections?: Array<{
        template_picture_category_id: number;
        user_notes?: string | null;
    }>;
}

interface ChecklistResponseData {
    id: number;
    answer: boolean | null;
    explain: string | null;
    is_na?: boolean;
    quantity?: number | null;
    checklist_item: {
        id: number;
        label: string;
        has_explain_field: boolean;
        allows_na?: boolean;
    };
}

interface PhotoData {
    id: number;
    url?: string;
    original_url?: string;
    thumb_url?: string | null;
    status?: string;
    flagged?: boolean;
    approved?: boolean;
    created_at?: string;
    replaced_at?: string | null;
    position?: number;
    package_stage_id?: number;
    gps_latitude?: number | null;
    gps_longitude?: number | null;
    notes?: string | null;
    flag_reason?: string | null;
    category_selection?: { template_picture_category_id: number };
    package_category_selection_id?: number;
    photo_approval?: { reason?: string | null } | null;
    uploader?: { name?: string } | null;
}

function getPhotoReason(photo: PhotoData): string {
    return photo.flag_reason || photo.photo_approval?.reason || '';
}

const ATTENTION_STATUSES = new Set(['flagged', 'rejected', 'replacement_requested']);

/**
 * Photos still awaiting a decision: not superseded by a replacement, not already
 * approved, and not flagged/rejected/replacement-requested. These are the photos
 * a bulk "approve all remaining" should target, leaving every other decision intact.
 */
function isPendingForApproval(photo: PhotoData): boolean {
    return (
        !photo.replaced_at &&
        photo.status !== 'approved' &&
        !ATTENTION_STATUSES.has(photo.status || '')
    );
}

function photoCountLabel(count: number): string {
    return `${count} photo${count === 1 ? '' : 's'}`;
}

function photoBelongsToCategories(photo: PhotoData, categoryIds: ReadonlySet<number>): boolean {
    const categoryId =
        photo.category_selection?.template_picture_category_id ??
        photo.package_category_selection_id;
    return categoryId !== undefined && categoryIds.has(categoryId);
}

const FOCUS_PULSE_CLASSES = ['ring-4', 'ring-primary', 'shadow-2xl', 'transition-all'] as const;

function highlightElement(el: Element, durationMs = 3500): void {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add(...FOCUS_PULSE_CLASSES);
    setTimeout(() => el.classList.remove(...FOCUS_PULSE_CLASSES), durationMs);
}

type BulkDialogState = {
    mode: 'approve' | 'replacement';
    scope: 'package' | 'step';
    photos: PhotoData[];
};

function BulkReviewDialog({
    dialog,
    reason,
    onReasonChange,
    loading,
    approvedCount,
    attentionCount,
    onSubmit,
    onClose,
}: Readonly<{
    dialog: BulkDialogState;
    reason: string;
    onReasonChange: (value: string) => void;
    loading: boolean;
    approvedCount: number;
    attentionCount: number;
    onSubmit: () => void;
    onClose: () => void;
}>) {
    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {dialog.mode === 'approve'
                            ? `Approve ${photoCountLabel(dialog.photos.length)}?`
                            : `Request replacement for ${photoCountLabel(dialog.photos.length)}?`}
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    {dialog.mode === 'approve' ? (
                        <div className="rounded-lg bg-muted/40 border border-border p-3 space-y-1 text-xs text-muted-foreground">
                            <p className="font-semibold text-foreground">
                                Will approve {photoCountLabel(dialog.photos.length)}.
                            </p>
                            <p>
                                {approvedCount} already approved and {attentionCount} flagged /
                                replacement-requested will be left as-is.
                            </p>
                        </div>
                    ) : (
                        <ReasonField
                            value={reason}
                            onChange={onReasonChange}
                            placeholder="Reason (required) — e.g. all photos in this batch are blurry"
                        />
                    )}
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            variant={dialog.mode === 'approve' ? 'success' : undefined}
                            loading={loading}
                            onClick={onSubmit}
                        >
                            {dialog.mode === 'approve'
                                ? `Approve ${photoCountLabel(dialog.photos.length)}`
                                : 'Request replacement'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function isNewReplacement(photo: PhotoData, photos?: PhotoData[]): boolean {
    if (photo.replaced_at) {
        return false;
    }
    return (photos || []).some(
        (q) =>
            q.replaced_at !== null &&
            q.replaced_at !== undefined &&
            q.package_category_selection_id === photo.package_category_selection_id &&
            q.position === photo.position &&
            ATTENTION_STATUSES.has(q.status || '')
    );
}

function getEmptyCategoryMessage(onlyReplacements: boolean, stageStatus: string): string {
    if (onlyReplacements) {
        return 'No new replacements in this category.';
    }
    if (stageStatus === 'submitted') {
        return 'No photos for this category.';
    }
    return 'No photos uploaded yet.';
}

const ACTIVE_BADGE_CLASS = 'border-primary bg-primary text-primary-foreground shadow-sm';
const INACTIVE_BADGE_CLASS = 'border-muted-foreground/30 bg-muted text-muted-foreground';
const COMPLETED_BADGE_CLASS = 'border-emerald-600 bg-emerald-600 text-white';

function ChecklistTab({
    active,
    onClick,
}: Readonly<{
    active: boolean;
    onClick: () => void;
}>) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'step' : undefined}
            className={cn(
                'relative flex flex-col items-center justify-between gap-1 w-full h-full min-h-[72px] sm:min-h-[80px] rounded-xl p-1.5 sm:p-2.5 transition-all text-center cursor-pointer min-w-0',
                active
                    ? 'ring-2 ring-primary/60 bg-primary/10 dark:bg-primary/20 border border-primary/40 shadow-xs font-semibold'
                    : 'hover:bg-accent/50 border border-border/50 opacity-85 hover:opacity-100'
            )}
        >
            <span
                className={cn(
                    'flex size-7 sm:size-8 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition-colors',
                    active ? ACTIVE_BADGE_CLASS : INACTIVE_BADGE_CLASS
                )}
            >
                <ClipboardCheck className="size-3.5 sm:size-4" />
            </span>
            <span
                className={cn(
                    'text-[10px] sm:text-xs font-semibold truncate max-w-full leading-tight',
                    active ? 'text-foreground font-bold' : MUTED_TEXT_CLASS
                )}
            >
                Checklist
            </span>
            <span
                className={cn(
                    'text-[9px] sm:text-[10px] uppercase tracking-wide font-semibold leading-none',
                    active ? 'text-primary font-bold' : 'text-transparent'
                )}
            >
                {active ? 'Current' : '·'}
            </span>
        </button>
    );
}

function getAnswerChipClass(answer: boolean | null, isNa?: boolean): string {
    if (isNa) {
        return 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-300 border border-zinc-500/20';
    }
    if (answer === true) {
        return SUCCESS_CHIP_CLASS;
    }
    if (answer === false) {
        return 'bg-red-500/10 text-red-600 dark:text-red-400';
    }
    return 'bg-muted text-muted-foreground';
}

function getAnswerLabel(answer: boolean | null, isNa?: boolean): string {
    if (isNa) {
        return 'N/A';
    }
    if (answer === true) {
        return 'Yes';
    }
    if (answer === false) {
        return 'No';
    }
    return 'Not answered';
}

function ChecklistReviewView({
    responses,
    packageId,
    packageNotes,
    packageNoteImages,
    canEditSiteNotes,
}: Readonly<{
    responses: ChecklistResponseData[];
    packageId: number | string;
    packageNotes: string;
    packageNoteImages: PackageSiteNoteImage[];
    canEditSiteNotes: boolean;
}>) {
    if (responses.length === 0) {
        return (
            <div className="p-10 border border-dashed rounded-xl text-center text-sm text-muted-foreground bg-card">
                No checklist items for this package.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {responses.map((r, index) => (
                <div
                    key={r.checklist_item.id}
                    className="rounded-xl border bg-card overflow-hidden"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3 p-4 bg-muted/30">
                        <p className="flex items-center gap-2.5 text-sm font-semibold">
                            <span
                                className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                                title={`Question ${index + 1} of ${responses.length}`}
                            >
                                {index + 1}
                            </span>
                            {r.checklist_item.label}
                        </p>
                        <div className="flex items-center gap-2 shrink-0">
                            {!r.is_na && r.quantity != null && (
                                <span className="inline-flex items-center rounded-full border border-border bg-card px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground">
                                    Qty {r.quantity}
                                </span>
                            )}
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                    getAnswerChipClass(r.answer, r.is_na)
                                )}
                            >
                                {getAnswerLabel(r.answer, r.is_na)}
                            </span>
                        </div>
                    </div>
                    {r.explain && (
                        <div className="p-4 border-t border-border">
                            <p className="text-xs font-bold text-foreground">Note</p>
                            <p className="mt-1 text-sm text-foreground">{r.explain}</p>
                        </div>
                    )}
                </div>
            ))}
            <PackageSiteNotes
                packageId={packageId}
                notes={packageNotes}
                images={packageNoteImages}
                canEdit={canEditSiteNotes}
            />
        </div>
    );
}

interface PackageReviewProps {
    package: {
        id: number;
        hash_id?: string;
        project_id?: number;
        status?: string;
        final_pdf_path?: string | null;
        revision_major?: number;
        revision_minor?: number;
        current_stage?: string;
        currentStage?: string;
        stages?: StageData[];
        template?: {
            name?: string;
            sections?: Array<{
                stage?: string;
                heading?: string;
                admin_notes?: string | null;
                categories?: CategoryData[];
            }>;
        };
        photos?: PhotoData[];
        checklist_responses?: ChecklistResponseData[];
        notes?: string | null;
        site_note_images?: PackageSiteNoteImage[];
        project?: { id?: number; hash_id?: string; name: string; vzw_engineer_email?: string };
        projectName?: string;
        title?: string;
        locked?: boolean;
        locked_at?: string | null;
        locked_by?: { id: number; name: string } | null;
        /** Scope of work and site access are captured per package, on the pre-construction step. */
        site_info?: {
            scope_of_work?: string | null;
            site_access_notes?: string | null;
        } | null;
    };
    readiness?: {
        is_ready: boolean;
        failures: Array<{ message: string }>;
    };
    revisions?: PDFRevisionData[];
    partial_pdf_available?: boolean;
    partial_pdf_generated_at?: string | null;
    final_pdf_generated_at?: string | null;
    /** True when customer feedback is waiting on the manager to review it. */
    has_pending_feedback?: boolean;
    /** The active Entity Feedback round, or null before one is opened. */
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedback_round?: EntityFeedbackRoundItem | null;
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
    feedback_round_history?: EntityFeedbackRoundItem[];
    /** Project field supervisors, for the forward-to-technician dialog. */
    technicians?: TechnicianOption[];
    /** Backend-computed via CloseoutPackagePolicy::lock — the source of truth. */
    can_lock?: boolean;
    can_unlock?: boolean;
    /** Backend-computed via CloseoutPackagePolicy::updateSiteNotes. */
    can_update_site_notes?: boolean;
    /** The project's starred primary field technician; null until one is set. */
    primary_technician?: PrimaryTechnicianContact | null;
}

function getBadgeClass(completed: boolean, active: boolean): string {
    if (completed) {
        return COMPLETED_BADGE_CLASS;
    }
    if (active) {
        return ACTIVE_BADGE_CLASS;
    }
    return INACTIVE_BADGE_CLASS;
}

function getTabLabelClass(completed: boolean, active: boolean): string {
    if (active) {
        return 'text-foreground font-bold';
    }
    if (completed) {
        return 'text-emerald-600 dark:text-emerald-400';
    }
    return MUTED_TEXT_CLASS;
}

function StageTab({
    stage,
    index,
    completed,
    active,
    onClick,
}: Readonly<{
    stage: StageKey;
    index: number;
    completed: boolean;
    active: boolean;
    onClick: () => void;
}>) {
    const shortLabel = STAGE_SHORT_LABELS[stage];
    const statusSubtext = getStageStatusSubtext(active, completed);

    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'step' : undefined}
            className={cn(
                'relative flex flex-col items-center justify-between gap-1 w-full h-full min-h-[72px] sm:min-h-[80px] rounded-xl p-1.5 sm:p-2.5 transition-all text-center cursor-pointer min-w-0',
                active
                    ? 'ring-2 ring-primary/60 bg-primary/10 dark:bg-primary/20 border border-primary/40 shadow-xs font-semibold'
                    : 'hover:bg-accent/50 border border-border/50 opacity-85 hover:opacity-100'
            )}
        >
            <span
                className={cn(
                    'flex size-7 sm:size-8 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition-colors',
                    getBadgeClass(completed, active)
                )}
            >
                {completed ? <Check className="size-3.5 sm:size-4" /> : index + 1}
            </span>
            <span
                className={cn(
                    'text-[10px] sm:text-xs font-semibold truncate max-w-full leading-tight',
                    getTabLabelClass(completed, active)
                )}
            >
                <span className="sm:hidden">{shortLabel}</span>
                <span className="hidden sm:inline">{STAGE_LABELS[stage]}</span>
            </span>
            <span
                className={cn(
                    'text-[9px] sm:text-[10px] uppercase tracking-wide font-semibold leading-none',
                    statusSubtext.className
                )}
            >
                {statusSubtext.text}
            </span>
        </button>
    );
}

type PackageAction = 'approve' | 'final_pdf' | 'partial_pdf' | 'email_final';

function submitPhotoReview(
    reviewTarget: { photo: PhotoData; action: 'flag' | 'replacement' } | null,
    reason: string,
    setReviewing: (value: boolean) => void,
    setReviewTarget: (value: null) => void,
    setReason: (value: string) => void,
    onOptimisticUpdate?: (photoId: number, newStatus: string, reason: string) => void
): void {
    if (!reviewTarget) {
        return;
    }
    if (reason.trim() === '') {
        toast.error('Please add a reason.');
        return;
    }
    const isFlag = reviewTarget.action === 'flag';
    const newStatus = isFlag ? 'flagged' : 'replacement_requested';
    onOptimisticUpdate?.(reviewTarget.photo.id, newStatus, reason.trim());

    setReviewing(true);
    router.post(
        route(
            isFlag ? 'manager.photos.flag' : 'manager.photos.request-replacement',
            reviewTarget.photo.id
        ),
        { reason },
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(isFlag ? 'Photo flagged as blurry.' : 'Replacement requested.'),
            onFinish: () => {
                setReviewing(false);
                setReviewTarget(null);
                setReason('');
            },
        }
    );
}

function runPackageAction(
    action: PackageAction,
    routeName: string,
    successMessage: string,
    packageId: number | string,
    setLoading: (value: PackageAction | null) => void,
    payload?: Parameters<typeof router.post>[1],
    onSuccess?: () => void
): void {
    setLoading(action);
    router.post(route(routeName, packageId), payload, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(successMessage);
            onSuccess?.();
        },
        onError: (errors) => {
            const messages = Object.values(errors).flat();
            toast.error(
                messages.length > 0 ? messages.join('\n') : 'Action failed. Please try again.'
            );
        },
        onFinish: () => setLoading(null),
    });
}

function getActionsDescription(
    status: string | undefined,
    readiness: { is_ready: boolean } | undefined
): string {
    if (status === 'approved') {
        return 'Package approved. Generate and email the final PDF.';
    }
    if (readiness?.is_ready) {
        return 'Everything is ready for approval.';
    }
    return 'Resolve the items below before approving the package.';
}

function PackageActionsPanel({
    status,
    readiness,
    actionLoading,
    onApprove,
}: Readonly<{
    status?: string;
    readiness?: { is_ready: boolean; failures: Array<{ message: string }> };
    actionLoading: string | null;
    onApprove: () => void;
}>) {
    // ponytail: removed feedback banner — the Customer Feedback button already blinks when feedback is pending

    const isAwaitingApproval = status === 'submitted' || status === 'under_review';
    if (!isAwaitingApproval) {
        return null;
    }

    return (
        <div className="rounded-xl border bg-card p-4 space-y-3">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <h2 className="font-bold text-lg">Package Approval</h2>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        {getActionsDescription(status, readiness)}
                    </p>
                </div>
                <Button onClick={onApprove} loading={actionLoading === 'approve'} variant="success">
                    <Check className="size-4" /> Approve Package
                </Button>
            </div>
            {readiness && !readiness.is_ready && (
                <ul className="space-y-1 rounded-lg bg-amber-500/10 border border-amber-500/30 p-3 text-xs text-amber-700 dark:text-amber-400">
                    {readiness.failures.map((failure) => (
                        <li key={failure.message}>• {failure.message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function formatTimestamp(value?: string, withYear = false): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleString([], {
        year: withYear ? 'numeric' : undefined,
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function ReviewCategoryCard({
    category,
    index,
    packageId,
    photos,
    allPhotos,
    supervisorNotes,
    selectedStageStatus,
    onlyReplacements,
    readOnly,
    onApprove,
    onFlag,
    onRequestReplacement,
    onNoteUpdated,
}: Readonly<{
    category: CategoryData;
    index: number;
    packageId: number | string;
    photos: PhotoData[];
    allPhotos: PhotoData[];
    supervisorNotes: string;
    selectedStageStatus: string;
    onlyReplacements: boolean;
    readOnly: boolean;
    onApprove: (photo: PhotoData) => void;
    onFlag: (photo: PhotoData) => void;
    onRequestReplacement: (photo: PhotoData) => void;
    onNoteUpdated?: (photoId: number, notes: string | null) => void;
}>) {
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
    const lightboxPhotos: LightboxPhoto[] = photos.map((photo) => ({
        id: photo.id,
        url: photo.url || photo.original_url || '',
        caption: category.name,
        alt: `${category.name} ${photo.id}`,
        status: photo.status,
        reason: getPhotoReason(photo) || undefined,
        notes: photo.notes || undefined,
        latitude: photo.gps_latitude,
        longitude: photo.gps_longitude,
    }));

    return (
        <div className="rounded-xl border-2 bg-card shadow-sm overflow-hidden">
            <div className="flex flex-wrap items-center justify-between gap-3 p-4 bg-muted/30">
                <div className="flex items-center gap-2 min-w-0 flex-wrap">
                    <span
                        title={`Category ${index}`}
                        className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                    >
                        {index}
                    </span>
                    <h3 className="font-semibold text-sm min-w-0 break-words">{category.name}</h3>
                    {category.is_required && (
                        <span className="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                            Required
                        </span>
                    )}
                </div>
                <span className="text-xs text-muted-foreground shrink-0">
                    {photos.length} photo{photos.length === 1 ? '' : 's'}
                </span>
            </div>

            <div className="p-4 space-y-3.5">
                {category.description && (
                    <div className="rounded-lg bg-muted/40 border border-border/70 p-2.5 text-xs text-muted-foreground flex items-start gap-2">
                        <Info className="size-4 shrink-0 text-primary/70 mt-0.5" />
                        <div>
                            <span className="font-semibold text-foreground mr-1">Description:</span>
                            <span>{category.description}</span>
                        </div>
                    </div>
                )}

                {supervisorNotes && (
                    <div className="rounded-lg bg-muted/30 border border-border/70 p-2.5 text-xs">
                        <span className="font-semibold text-muted-foreground uppercase text-[10px] tracking-wide block mb-0.5">
                            Supervisor Notes
                        </span>
                        <span className="text-foreground">{supervisorNotes}</span>
                    </div>
                )}
                {photos.length === 0 ? (
                    <div className="p-6 border border-dashed rounded-lg text-center text-xs text-muted-foreground bg-muted/10">
                        {getEmptyCategoryMessage(onlyReplacements, selectedStageStatus)}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        {photos.map((photo, photoIndex) => (
                            <div
                                key={photo.id}
                                data-photo-id={photo.id}
                                className="rounded-lg border overflow-hidden bg-card"
                            >
                                <div className="relative aspect-square bg-muted">
                                    {isNewReplacement(photo, allPhotos) && (
                                        <span className="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/90 text-white px-2 py-0.5 text-[10px] font-semibold">
                                            <RotateCcw className="size-3" />
                                            New replacement
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => setLightboxIndex(photoIndex)}
                                        aria-label={`View ${category.name} photo full size`}
                                        className="block size-full cursor-zoom-in"
                                    >
                                        <img
                                            src={
                                                photo.thumb_url ||
                                                photo.url ||
                                                photo.original_url ||
                                                ''
                                            }
                                            alt={`${category.name} ${photo.id}`}
                                            className="size-full object-cover"
                                            loading="lazy"
                                        />
                                    </button>
                                    <a
                                        href={photo.url || photo.original_url || ''}
                                        download
                                        aria-label={`Download ${category.name} photo`}
                                        className="absolute top-2 right-2 bg-black/70 text-white p-1.5 rounded-md hover:bg-black/90 transition-colors"
                                    >
                                        <Download className="size-4" />
                                    </a>
                                    {photo.gps_latitude != null && photo.gps_longitude != null && (
                                        <GpsBadge
                                            latitude={photo.gps_latitude}
                                            longitude={photo.gps_longitude}
                                            className="absolute bottom-2 left-2"
                                        />
                                    )}
                                </div>
                                <div className="p-2.5 space-y-1.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <StatusBadge status={photo.status || 'pending'} />
                                        {photo.flagged && <Badge variant="warning">Blurry</Badge>}
                                    </div>
                                    {photo.uploader?.name && (
                                        <p className="text-[11px] text-muted-foreground truncate">
                                            By {photo.uploader.name}
                                        </p>
                                    )}
                                    {photo.created_at && (
                                        <p className="text-[11px] text-muted-foreground">
                                            {formatTimestamp(photo.created_at)}
                                        </p>
                                    )}
                                    <PhotoReasonNote reason={getPhotoReason(photo)} />
                                    <PhotoNoteSection
                                        packageId={packageId}
                                        photoId={photo.id}
                                        initialNotes={photo.notes}
                                        isLocked={readOnly}
                                        onNoteUpdated={onNoteUpdated}
                                        className="mt-1"
                                    />
                                    {!readOnly && (
                                        <div className="flex items-center gap-1 pt-2 border-t">
                                            <Button
                                                size="sm"
                                                variant={
                                                    photo.status === 'approved'
                                                        ? 'success'
                                                        : 'outline'
                                                }
                                                className="h-7 flex-1 px-2 text-xs"
                                                onClick={() => onApprove(photo)}
                                            >
                                                <Check className="size-3 mr-1" /> Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant={
                                                    photo.status === 'flagged'
                                                        ? 'warning'
                                                        : 'outline'
                                                }
                                                className="h-7 px-2 text-xs"
                                                onClick={() => onFlag(photo)}
                                                title="Flag as blurry"
                                            >
                                                <Flag className="size-3" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant={
                                                    photo.status === 'replacement_requested' ||
                                                    photo.status === 'rejected'
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                                className="h-7 px-2 text-xs"
                                                onClick={() => onRequestReplacement(photo)}
                                                title="Request replacement"
                                            >
                                                <RotateCcw className="size-3" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
            <PhotoLightbox
                photos={lightboxPhotos}
                index={lightboxIndex}
                onClose={() => setLightboxIndex(null)}
            />
        </div>
    );
}

function applyPhotoApproval(photo: PhotoData): PhotoData {
    return {
        ...photo,
        status: 'approved',
        approved: true,
        flagged: false,
        flag_reason: null,
    };
}

function applyPhotoReviewUpdate(photo: PhotoData, newStatus: string, reason: string): PhotoData {
    return {
        ...photo,
        status: newStatus,
        approved: false,
        flagged: newStatus === 'flagged',
        flag_reason: reason,
    };
}

function applyBulkPhotoReview(
    photo: PhotoData,
    targetIds: ReadonlySet<number>,
    newStatus: string,
    reason: string | null
): PhotoData {
    if (!targetIds.has(photo.id)) {
        return photo;
    }
    return {
        ...photo,
        status: newStatus,
        approved: newStatus === 'approved',
        flagged: false,
        flag_reason: reason,
    };
}

export function PackageReview({
    package: pkg,
    readiness,
    revisions = [],
    partial_pdf_available: partialPdfAvailable = false,
    partial_pdf_generated_at: partialPdfGeneratedAt = null,
    final_pdf_generated_at: finalPdfGeneratedAt = null,
    has_pending_feedback: hasPendingFeedback = false,
    feedbackRound: rawFeedbackRound = null,
    feedback_round: rawFeedbackRoundSnake = null,
    feedbackRoundHistory: rawFeedbackRoundHistory = [],
    feedback_round_history: rawFeedbackRoundHistorySnake = [],
    technicians = [],
    can_lock,
    can_unlock,
    can_update_site_notes,
    primary_technician: primaryTechnician = null,
}: Readonly<PackageReviewProps>) {
    useFlashToasts();
    const { auth } = usePage<PageProps>().props;
    const isAdmin = auth.user.roles.includes('admin');
    const isManager = isAdmin || auth.user.roles.includes('manager');
    const isFieldTech = auth.user.roles.includes('field_supervisor');

    const effectiveFeedbackRound = rawFeedbackRound ?? rawFeedbackRoundSnake ?? null;
    const effectiveFeedbackRoundHistory =
        rawFeedbackRoundHistory.length > 0 ? rawFeedbackRoundHistory : rawFeedbackRoundHistorySnake;

    const [localPhotos, setLocalPhotos] = useState<PhotoData[]>(pkg.photos || []);
    const feedback = useEntityFeedback({
        entityType: 'closeout_package',
        entityId: Number(pkg.id),
        round: effectiveFeedbackRound,
        isManager,
        isTechnician: isFieldTech,
    });

    const handleJumpToPhoto = useCallback(
        (photoId: number) => {
            const photo = (pkg.photos || []).find((p) => p.id === photoId);
            if (!photo) return;

            let targetStage = (pkg.stages || []).find((s) => s.id === photo.package_stage_id);
            if (!targetStage) {
                targetStage = (pkg.stages || []).find((s) =>
                    (s.category_selections || []).some(
                        (cs) =>
                            cs.template_picture_category_id ===
                            photo.category_selection?.template_picture_category_id
                    )
                );
            }
            if (targetStage) {
                setActiveView(targetStage.stage as StageKey);
            }

            const scrollToTarget = () => {
                const el = document.querySelector(`[data-photo-id="${photoId}"]`);
                if (el) {
                    highlightElement(el);
                    return true;
                }
                return false;
            };

            setTimeout(() => {
                if (!scrollToTarget()) {
                    setTimeout(scrollToTarget, 300);
                }
            }, 150);
        },
        [pkg.photos, pkg.stages]
    );

    useEffect(() => {
        setLocalPhotos(pkg.photos || []);
    }, [pkg.photos]);

    const isApproved = pkg.status === 'approved';
    const isDraft = pkg.status === 'draft';
    const currentStage = (pkg.currentStage || pkg.current_stage || 'pre_construction') as StageKey;
    // Seed from ?tab= so a hard refresh reopens the same step; unknown or
    // missing values fall back to the package's current stage.
    const [activeView, setActiveView] = useState<ActiveView>(() => {
        const tab = new URLSearchParams(window.location.search).get('tab');
        const match = (Object.entries(STAGE_URL_SLUGS) as Array<[ActiveView, string]>).find(
            ([, slug]) => slug === tab
        );
        return match ? match[0] : currentStage;
    });
    // Keep ?tab= in sync on every view change so a hard refresh reopens
    // the same step. replaceState only rewrites the address bar.
    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', STAGE_URL_SLUGS[activeView]);
        window.history.replaceState(null, '', url);
    }, [activeView]);
    const [onlyReplacements, setOnlyReplacements] = useState(false);
    const [reviewTarget, setReviewTarget] = useState<{
        photo: PhotoData;
        action: 'flag' | 'replacement';
    } | null>(null);
    const [reason, setReason] = useState('');
    const [reviewing, setReviewing] = useState(false);
    const [actionLoading, setActionLoading] = useState<
        'approve' | 'final_pdf' | 'partial_pdf' | 'email_final' | null
    >(null);
    const [finalDialogOpen, setFinalDialogOpen] = useState(false);
    const [bulkDialog, setBulkDialog] = useState<{
        mode: 'approve' | 'replacement';
        scope: 'package' | 'step';
        photos: PhotoData[];
    } | null>(null);
    const [bulkReason, setBulkReason] = useState('');
    const [bulkLoading, setBulkLoading] = useState(false);

    const categoryMap = useMemo(() => {
        const map = new Map<number, string>();
        pkg.template?.sections?.forEach((section) => {
            section.categories?.forEach((cat) => {
                map.set(cat.id, cat.name);
            });
        });
        return map;
    }, [pkg.template]);

    // Thread labels need section AND category names keyed by type: a bare id
    // collides across those tables, which is how a "Grounding" thread ended up
    // titled "Construction / Installation" for the manager.
    const itemNamesByKey = useMemo(
        () =>
            buildItemNamesByKey({
                templateSections: pkg.template?.sections,
                stages: pkg.stages,
                photos: localPhotos,
            }),
        [pkg.template?.sections, pkg.stages, localPhotos]
    );

    const dynamicReadiness = useMemo(() => {
        const nonPhotoFailures = (readiness?.failures || []).filter(
            (f) =>
                !f.message.includes('has not been reviewed yet') &&
                !f.message.includes('needs a replacement') &&
                !f.message.includes('has a pending replacement request')
        );

        const activePhotos = localPhotos.filter((p) => !p.replaced_at);

        const unreviewedFailures: Array<{ message: string }> = [];
        const replacementFailures: Array<{ message: string }> = [];

        activePhotos.forEach((photo) => {
            const catId =
                photo.category_selection?.template_picture_category_id ??
                photo.package_category_selection_id;
            const catName =
                typeof catId === 'number' ? (categoryMap.get(catId) ?? 'Unknown') : 'Unknown';

            if (photo.status === 'approved') {
                return;
            }

            if (photo.status === 'flagged') {
                replacementFailures.push({
                    message: `Photo in category '${catName}' is flagged and needs a replacement.`,
                });
            } else if (photo.status === 'replacement_requested') {
                replacementFailures.push({
                    message: `Photo in category '${catName}' has a pending replacement request.`,
                });
            } else {
                unreviewedFailures.push({
                    message: `Photo in category '${catName}' has not been reviewed yet.`,
                });
            }
        });

        const allFailures = [...nonPhotoFailures, ...replacementFailures, ...unreviewedFailures];

        return {
            is_ready: allFailures.length === 0,
            failures: allFailures,
        };
    }, [localPhotos, readiness?.failures, categoryMap]);

    const stageStatusMap = new Map((pkg.stages || []).map((s) => [s.stage, s.status]));
    const selectedSection = pkg.template?.sections?.find((s) => s.stage === activeView);
    const selectedCategories = selectedSection?.categories || [];
    const selectedStageSelections =
        pkg.stages?.find((s) => s.stage === activeView)?.category_selections || [];
    const selectedStageStatus = stageStatusMap.get(activeView) || 'not_started';
    const newReplacementCount = localPhotos.filter((p) => isNewReplacement(p, localPhotos)).length;

    const readOnly = isApproved || isDraft;
    const allPhotos = localPhotos;
    const remainingPhotos = allPhotos.filter(isPendingForApproval);
    const stageCategoryIds = new Set(selectedCategories.map((c) => c.id));
    const approvedPhotos = allPhotos.filter((p) => !p.replaced_at && p.status === 'approved');
    const attentionPhotos = allPhotos.filter(
        (p) => !p.replaced_at && ATTENTION_STATUSES.has(p.status || '')
    );
    const canBulkReview = !readOnly && remainingPhotos.length > 0;

    const getSupervisorNotes = (categoryId: number): string =>
        selectedStageSelections.find((sel) => sel.template_picture_category_id === categoryId)
            ?.user_notes || '';

    const getPhotos = (categoryId: number): PhotoData[] =>
        localPhotos.filter((p) => {
            const catId =
                p.category_selection?.template_picture_category_id ??
                p.package_category_selection_id;
            return catId === categoryId && !p.replaced_at;
        });

    const handleApprove = (photo: PhotoData) => {
        setLocalPhotos((prev) => prev.map((p) => (p.id === photo.id ? applyPhotoApproval(p) : p)));
        router.post(
            route('manager.photos.approve', photo.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Photo approved.'),
            }
        );
    };

    const handleOptimisticReviewUpdate = (
        photoId: number,
        newStatus: string,
        newReason: string
    ) => {
        setLocalPhotos((prev) =>
            prev.map((p) =>
                p.id === photoId ? applyPhotoReviewUpdate(p, newStatus, newReason) : p
            )
        );
    };

    const handleNoteUpdated = (photoId: number, newNotes: string | null) => {
        setLocalPhotos((prev) =>
            prev.map((p) => (p.id === photoId ? { ...p, notes: newNotes } : p))
        );
    };

    const submitReview = () => {
        submitPhotoReview(
            reviewTarget,
            reason,
            setReviewing,
            setReviewTarget,
            setReason,
            handleOptimisticReviewUpdate
        );
    };

    const submitBulkReview = () => {
        if (!bulkDialog || bulkDialog.photos.length === 0) {
            return;
        }
        if (bulkDialog.mode === 'replacement' && bulkReason.trim() === '') {
            toast.error('Please add a reason.');
            return;
        }
        const targetIds = new Set(bulkDialog.photos.map((p) => p.id));
        const newStatus = bulkDialog.mode === 'approve' ? 'approved' : 'replacement_requested';
        const replacementReason = bulkDialog.mode === 'replacement' ? bulkReason.trim() : null;

        setLocalPhotos((prev) =>
            prev.map((p) => applyBulkPhotoReview(p, targetIds, newStatus, replacementReason))
        );

        setBulkLoading(true);
        const decisions = bulkDialog.photos.map((photo) => ({
            photo_id: photo.id,
            decision: bulkDialog.mode === 'approve' ? 'approved' : 'replacement_requested',
            ...(bulkDialog.mode === 'replacement' ? { reason: bulkReason.trim() } : {}),
        }));
        router.post(
            route('manager.packages.bulk-review', pkg.hash_id || pkg.id),
            { decisions },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        bulkDialog.mode === 'approve'
                            ? `${photoCountLabel(bulkDialog.photos.length)} approved.`
                            : `Replacement requested for ${photoCountLabel(bulkDialog.photos.length)}.`
                    ),
                onError: (errors) => {
                    const messages = Object.values(errors).flat();
                    toast.error(
                        messages.length > 0
                            ? messages.join('\n')
                            : 'Bulk review failed. Please try again.'
                    );
                },
                onFinish: () => {
                    setBulkLoading(false);
                    setBulkDialog(null);
                    setBulkReason('');
                },
            }
        );
    };

    const handleApprovePackage = (entries: SiteDetailEntry[]) => {
        runPackageAction(
            'approve',
            'manager.packages.final-approve',
            'Package approved.',
            pkg.hash_id || pkg.id,
            setActionLoading,
            {
                closeout_package_id: pkg.hash_id || pkg.id,
                site_details: entries,
            },
            () => setFinalDialogOpen(false)
        );
    };

    const finalPdfDownloadUrl = pkg.final_pdf_path
        ? route('packages.pdf.download', pkg.hash_id || pkg.id)
        : null;

    const projectName = pkg.projectName || pkg.project?.name || `Package #${pkg.hash_id || pkg.id}`;
    const revision = `R${pkg.revision_major ?? 1}.${pkg.revision_minor ?? 0}`;

    return (
        <AppLayout
            breadcrumb={[
                {
                    label: 'Close-Out Packages',
                    href: route('packages.index', {
                        project: pkg.project?.hash_id || pkg.project_id,
                    }),
                },
                { label: projectName },
            ]}
        >
            <Head title={`Review - ${projectName}`} />
            <div className="space-y-2">
                <Link
                    href={route('packages.index', {
                        project: pkg.project?.hash_id || pkg.project_id,
                    })}
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="size-4" />
                    Back to Close-Out Packages
                </Link>

                {/* Locked banner — visible only to the locker and admins, who
                    are the only users allowed to reach this page. */}
                {pkg.locked && (
                    <PackageLockNotice locked_by={pkg.locked_by} locked_at={pkg.locked_at} />
                )}

                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">{projectName}</h1>
                            {pkg.locked && <LockedBadge />}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            Status: <StatusBadge status={pkg.status || 'draft'} /> · Current Stage:{' '}
                            {STAGE_LABELS[currentStage]} · Revision {revision}
                            {isApproved && (
                                <span
                                    className="inline-flex items-center gap-1 ml-2 rounded-md bg-emerald-500/10 border border-emerald-500/30 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                    title="All photos are finalized and can no longer be approved, flagged, or replaced. This package is read-only."
                                >
                                    <Lock className="size-3" /> Read-only
                                </span>
                            )}
                            {isDraft && (
                                <span
                                    className="inline-flex items-center gap-1 ml-2 rounded-md bg-amber-500/10 border border-amber-500/30 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400"
                                    title="The field technician has not submitted this package for review yet. Photos cannot be approved, flagged, or replacement-requested until it is submitted."
                                >
                                    <Lock className="size-3" /> Draft — Review Locked
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 w-full lg:w-auto">
                        {/* Primary: report delivery (PDF generation, viewing, email) */}
                        <div className="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                            <PackagePdfActions
                                packageId={pkg.hash_id || pkg.id}
                                numericPackageId={pkg.id}
                                status={pkg.status}
                                finalPdfAvailable={finalPdfDownloadUrl !== null}
                                partialPdfAvailable={partialPdfAvailable}
                                partialPdfGeneratedAt={partialPdfGeneratedAt}
                                finalPdfGeneratedAt={finalPdfGeneratedAt}
                                defaultEmail={pkg.project?.vzw_engineer_email}
                                canManage
                            />
                        </div>

                        {/* Secondary: package context & workflow */}
                        <div className="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                            {activeView === 'pre_construction' && (
                                <SiteAccessTrigger
                                    packageId={pkg.hash_id || pkg.id}
                                    scopeOfWork={pkg.site_info?.scope_of_work}
                                    siteAccessNotes={pkg.site_info?.site_access_notes}
                                    canEdit={false}
                                />
                            )}
                            {(can_lock || can_unlock) && (
                                <PackageLockButton
                                    packageId={pkg.hash_id || pkg.id}
                                    locked={Boolean(pkg.locked)}
                                    canLock={Boolean(can_lock)}
                                    canUnlock={Boolean(can_unlock)}
                                />
                            )}
                        </div>
                    </div>
                </div>

                {/* Package actions panel (Approval when submitted) */}
                <PackageActionsPanel
                    status={pkg.status}
                    readiness={dynamicReadiness}
                    actionLoading={actionLoading}
                    onApprove={() => setFinalDialogOpen(true)}
                />

                <FinalApprovalDialog
                    open={finalDialogOpen}
                    loading={actionLoading === 'approve'}
                    onOpenChange={setFinalDialogOpen}
                    onConfirm={handleApprovePackage}
                />

                {/* Stage tabs */}
                <AdminNotesCenter
                    notes={selectedSection?.admin_notes}
                    primaryTechnician={primaryTechnician}
                >
                    <div className="p-2 sm:p-3 rounded-xl border bg-card shadow-2xs">
                        <div className="grid grid-cols-4 gap-1.5 sm:gap-2.5 w-full py-1">
                            {STAGE_ORDER.map((stage, idx) => {
                                const completed = stageStatusMap.get(stage) === 'submitted';
                                return (
                                    <StageTab
                                        key={stage}
                                        stage={stage}
                                        index={idx}
                                        completed={completed}
                                        active={stage === activeView}
                                        onClick={() => setActiveView(stage)}
                                    />
                                );
                            })}
                            <ChecklistTab
                                active={activeView === 'checklist'}
                                onClick={() => setActiveView('checklist')}
                            />
                        </div>
                    </div>
                </AdminNotesCenter>

                {/* Stage view: Checklist OR Categories */}
                <div className="space-y-4">
                    {/* Filter bar for Category view */}
                    {activeView !== 'checklist' && (
                        <div className="flex items-center justify-between gap-3 flex-wrap">
                            <span className="text-xs text-muted-foreground">
                                Step:{' '}
                                <strong className="text-foreground">
                                    {STAGE_LABELS[activeView]}
                                </strong>{' '}
                                · {selectedCategories.length} categories
                            </span>
                            <div className="flex items-center gap-2">
                                {/* The active state deliberately avoids the primary
                                    button, which is near-black here: it looked
                                    switched-off, so a manager could not tell that
                                    the filter was on and some photos were hidden.
                                    Amber matches the "needs attention" vocabulary
                                    already on these cards. */}
                                <Button
                                    size="sm"
                                    variant="outline"
                                    aria-pressed={onlyReplacements}
                                    className={cn(
                                        'h-8 text-xs gap-1.5',
                                        onlyReplacements
                                            ? 'border-amber-500 bg-amber-500 text-white shadow-xs hover:border-amber-600 hover:bg-amber-600'
                                            : 'text-muted-foreground'
                                    )}
                                    onClick={() => setOnlyReplacements(!onlyReplacements)}
                                >
                                    <Filter className="size-3.5" />
                                    Only replacements
                                    {newReplacementCount > 0 && (
                                        <span
                                            className={cn(
                                                'ml-1 rounded px-1.5 py-0 text-[10px] font-bold tabular-nums',
                                                onlyReplacements
                                                    ? 'bg-white/25 text-white'
                                                    : 'bg-muted text-muted-foreground'
                                            )}
                                        >
                                            {newReplacementCount}
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}

                    {activeView === 'checklist' && (
                        <ChecklistReviewView
                            responses={pkg.checklist_responses || []}
                            packageId={pkg.hash_id || pkg.id}
                            packageNotes={pkg.notes ?? ''}
                            packageNoteImages={pkg.site_note_images ?? []}
                            canEditSiteNotes={Boolean(can_update_site_notes)}
                        />
                    )}

                    {activeView !== 'checklist' && (
                        <div className="space-y-2">
                            {selectedCategories.length === 0 ? (
                                <div className="p-10 border border-dashed rounded-xl text-center text-sm text-muted-foreground bg-card">
                                    No photo categories for this step yet.
                                </div>
                            ) : (
                                selectedCategories.map((cat, idx) => (
                                    <ReviewCategoryCard
                                        key={cat.id}
                                        category={cat}
                                        index={idx + 1}
                                        packageId={pkg.hash_id || pkg.id}
                                        photos={
                                            onlyReplacements
                                                ? getPhotos(cat.id).filter((p) =>
                                                      isNewReplacement(p, localPhotos)
                                                  )
                                                : getPhotos(cat.id)
                                        }
                                        allPhotos={localPhotos}
                                        supervisorNotes={getSupervisorNotes(cat.id)}
                                        selectedStageStatus={selectedStageStatus}
                                        onlyReplacements={onlyReplacements}
                                        readOnly={readOnly}
                                        onApprove={handleApprove}
                                        onFlag={(photo) =>
                                            setReviewTarget({ photo, action: 'flag' })
                                        }
                                        onRequestReplacement={(photo) =>
                                            setReviewTarget({ photo, action: 'replacement' })
                                        }
                                        onNoteUpdated={handleNoteUpdated}
                                    />
                                ))
                            )}
                        </div>
                    )}
                </div>
            </div>{' '}
            {/* Revision history */}
            {revisions.length > 0 && (
                <div className="rounded-xl border bg-card shadow-sm overflow-hidden">
                    <div className="p-4 border-b border-border">
                        <h2 className="font-bold text-lg">Revision History</h2>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Every generated PDF for this package, newest first.
                        </p>
                    </div>
                    <PDFRevisionTable
                        revisions={revisions.map((rev) => ({
                            ...rev,
                            generatedAt: rev.generatedAt
                                ? formatTimestamp(rev.generatedAt, true) || '—'
                                : '—',
                        }))}
                    />
                </div>
            )}
            {canBulkReview && (
                <div className="sticky bottom-0 z-20 -mx-3 sm:-mx-4 lg:-mx-5 mt-4 px-3 sm:px-4 lg:px-5 py-3 border-t border-border bg-card/95 backdrop-blur">
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div className="text-xs text-muted-foreground leading-relaxed">
                            <span className="font-semibold text-foreground">
                                {remainingPhotos.length} photo
                                {remainingPhotos.length === 1 ? '' : 's'}
                            </span>{' '}
                            ready to approve · {approvedPhotos.length} already approved ·{' '}
                            {attentionPhotos.length} flagged / replacement-requested
                            <span className="hidden md:inline"> — those will be left as-is</span>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setBulkDialog({
                                        mode: 'replacement',
                                        scope: 'package',
                                        photos: remainingPhotos,
                                    })
                                }
                            >
                                <RotateCcw className="size-3 mr-1" />
                                Request replacement for remaining
                            </Button>
                            <Button
                                size="sm"
                                variant="success"
                                onClick={() =>
                                    setBulkDialog({
                                        mode: 'approve',
                                        scope: 'package',
                                        photos: remainingPhotos,
                                    })
                                }
                            >
                                <Check className="size-3 mr-1" />
                                Approve all remaining ({remainingPhotos.length})
                            </Button>
                        </div>
                    </div>
                </div>
            )}
            {bulkDialog && (
                <BulkReviewDialog
                    dialog={bulkDialog}
                    reason={bulkReason}
                    onReasonChange={setBulkReason}
                    loading={bulkLoading}
                    approvedCount={
                        bulkDialog.scope === 'step'
                            ? approvedPhotos.filter((p) =>
                                  photoBelongsToCategories(p, stageCategoryIds)
                              ).length
                            : approvedPhotos.length
                    }
                    attentionCount={
                        bulkDialog.scope === 'step'
                            ? attentionPhotos.filter((p) =>
                                  photoBelongsToCategories(p, stageCategoryIds)
                              ).length
                            : attentionPhotos.length
                    }
                    onSubmit={submitBulkReview}
                    onClose={() => {
                        setBulkDialog(null);
                        setBulkReason('');
                    }}
                />
            )}
            <Dialog
                open={reviewTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReviewTarget(null);
                        setReason('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {reviewTarget?.action === 'flag'
                                ? 'Flag photo as blurry'
                                : 'Request replacement'}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <ReasonField
                            value={reason}
                            onChange={setReason}
                            placeholder="Reason (required) — e.g. photo is blurry"
                        />
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setReviewTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button size="sm" onClick={submitReview} loading={reviewing}>
                                Submit
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
            <FeedbackSectionDivider highlight={hasPendingFeedback} />
            <EntityFeedbackSection
                round={feedback.activeRound}
                history={effectiveFeedbackRoundHistory}
                isManager={isManager}
                isTechnician={isFieldTech}
                technicians={technicians}
                onDeleteNote={feedback.deleteNote}
                onDeleteRound={feedback.deleteRound}
                onEditNote={feedback.editNote}
                isSubmitting={feedback.isSubmitting}
                onAcknowledge={feedback.acknowledge}
                canAcknowledge={feedback.canAcknowledge}
                isAcknowledged={feedback.isAcknowledged}
                onResolveNote={feedback.resolveNote}
                onResolveAll={feedback.resolveAll}
                onForwardTechnician={feedback.forwardToTechnician}
                onSubmitToManager={feedback.submitToManager}
                onReply={async (photoId, photoType, body, replacementFile, options) => {
                    await feedback.saveAnnotationNote({
                        entityType: 'closeout_package',
                        entityId: Number(pkg.id),
                        originalImageUrl: null,
                        body,
                        sourcePhotoId: photoId,
                        sourcePhotoType: photoType === null ? 'section' : photoType,
                        replacementImageFile: replacementFile,
                        replaceInChangeOrder: options?.replaceInChangeOrder,
                        addToChangeOrderPhotos: options?.addToChangeOrderPhotos,
                    });
                }}
                canReply={feedback.canReply}
                onJumpToPhoto={handleJumpToPhoto}
                itemNamesByKey={itemNamesByKey}
            />
        </AppLayout>
    );
}

export default PackageReview;
