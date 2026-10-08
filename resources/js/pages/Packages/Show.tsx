import { CapturedPhoto, InAppCamera } from '@/components/mobile/in-app-camera';
import { PackageSiteNotes } from '@/components/packages/package-site-notes';
import { PackageLockButton } from '@/components/packages/package-lock-button';
import { PackageLockNotice } from '@/components/packages/package-lock-notice';
import { SiteAccessTrigger } from '@/components/packages/site-access-trigger';
import {
    AdminNotesCenter,
    type PrimaryTechnicianContact,
} from '@/components/shared/admin-notes-panel';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { GpsBadge } from '@/components/shared/gps-badge';
import { LockedBadge } from '@/components/shared/locked-badge';
import { PackagePdfActions } from '@/components/shared/package-pdf-actions';
import { LightboxPhoto, PhotoLightbox } from '@/components/shared/photo-lightbox';
import { RefreshButton } from '@/components/shared/refresh-button';
import { StatusBadge } from '@/components/shared/status-badge';
import type { FavoriteSnapshotItem } from '@/components/templates/favorite-types';
import { FavoriteData, FavoritesDialog } from '@/components/templates/favorites-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    EntityFeedbackSection,
    useEntityFeedback,
    extractFeedbackTargetPhotoIds,
    extractFeedbackTargetCategoryIds,
    buildItemNamesByKey,
    type EntityFeedbackRoundItem,
    type TechnicianOption,
} from '@/features/entity-feedback';
import { FeedbackSectionDivider } from '@/features/entity-feedback/components/FeedbackSectionDivider';
import { MediaPickerModal, mediaLibraryApi } from '@/features/media-library';
import { DriftWarningPanel, type DriftConflict, type DriftReport } from '@/features/packages';
import { useGeolocation } from '@/features/tracking';
import {
    CHECKLIST_NOTE_MAX_LENGTH,
    CHECKLIST_QUANTITY_MAX,
    useChecklistAutoSave,
    type ChecklistResponseData,
    type ChecklistState,
    type PackageSiteNoteImage,
} from '@/hooks/use-checklist-auto-save';
import AppLayout from '@/layouts/app-layout';
import { canSubmitPackageWorkflow } from '@/lib/permissions';
import { runWithConcurrency } from '@/lib/concurrency';
import { cn } from '@/lib/utils';
import { preparePhotoForUpload, rejectedPhotoMessage } from '@/services/compression.service';
import { photoUploadService } from '@/services/photo-upload.service';
import type { PageProps } from '@/types/inertia';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Bookmark,
    Camera,
    Check,
    ClipboardCheck,
    Download,
    FileText,
    ImageIcon,
    Images,
    Info,
    Loader2,
    Lock,
    RotateCcw,
    Send,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import type { ClipboardEvent, DragEvent } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { PhotoNoteSection } from './components/PhotoNoteSection';
import { PhotoReasonNote } from '@/components/shared/photo-reason-note';

interface CategoryData {
    id: number;
    name: string;
    description?: string;
    default_picture_count?: number;
    is_required?: boolean;
}

interface CategorySelectionData {
    id: number;
    template_picture_category_id: number;
    picture_count?: number;
    is_selected?: boolean;
    user_heading?: string | null;
    user_notes?: string | null;
}

interface PhotoData {
    id: number;
    url?: string;
    original_url?: string;
    thumb_url?: string | null;
    replaced_at?: string | null;
    position?: number;
    status?: string;
    uploaded_by?: number | null;
    approved_by?: number | null;
    flag_reason?: string | null;
    package_stage_id?: number;
    category_selection?: { template_picture_category_id: number };
    package_category_selection_id?: number;
    photo_approval?: { reason?: string | null } | null;
    gps_latitude?: number | null;
    gps_longitude?: number | null;
    notes?: string | null;
}

interface PackageShowProps {
    package: {
        id: number;
        hash_id?: string;
        project_id?: number;
        status?: string;
        /** Server-derived stored bytes of all current photos + note images. */
        photos_size_bytes?: number | null;
        revision_major?: number;
        revision_minor?: number;
        current_stage?: string;
        currentStage?: string;
        stages?: Array<{
            id?: number;
            stage: string;
            status: string;
            category_selections?: CategorySelectionData[];
        }>;
        template?: {
            id?: number;
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
        final_pdf_path?: string | null;
        project?: { id?: number; hash_id?: string; name: string };
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
    favorites?: FavoriteData[];
    drift?: DriftReport;
    /** Backend-computed via CloseoutPackagePolicy::lock — the source of truth. */
    can_lock?: boolean;
    can_unlock?: boolean;
    /** Backend-computed via CloseoutPackagePolicy::updateSiteNotes. */
    can_update_site_notes?: boolean;
    has_feedback?: boolean;
    has_pending_feedback?: boolean;
    /** The active Entity Feedback round, or null before one is opened. */
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedback_round?: EntityFeedbackRoundItem | null;
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
    feedback_round_history?: EntityFeedbackRoundItem[];
    /** Project field supervisors, for the forward-to-technician dialog. */
    technicians?: TechnicianOption[];
    /** The project's starred primary field technician; null until one is set. */
    primary_technician?: PrimaryTechnicianContact | null;
}

type StageKey = 'pre_construction' | 'construction' | 'post_completion';

type ActiveView = StageKey | 'checklist';

const MUTED_TEXT_CLASS = 'text-muted-foreground';
const ACTIVE_BADGE_CLASS = 'border-primary bg-primary text-primary-foreground shadow-sm';
const INACTIVE_BADGE_CLASS = 'border-muted-foreground/30 bg-muted text-muted-foreground';
const COMPLETED_BADGE_CLASS = 'border-emerald-600 bg-emerald-600 text-white';

const ATTENTION_STATUSES = new Set(['flagged', 'rejected', 'replacement_requested']);

const DRAFT_ROUTE_NAME = 'packages.stages.draft';

type CategoryStatus = 'flagged' | 'rejected' | 'approved' | 'pending' | 'empty';

function getCategoryStatus(photos: PhotoData[]): CategoryStatus {
    if (photos.length === 0) {
        return 'empty';
    }
    if (photos.some((p) => p.status === 'flagged')) {
        return 'flagged';
    }
    if (photos.some((p) => p.status === 'rejected' || p.status === 'replacement_requested')) {
        return 'rejected';
    }
    if (photos.every((p) => p.status === 'approved')) {
        return 'approved';
    }
    return 'pending';
}

function getCategoryBorderClass(status: CategoryStatus): string {
    if (status === 'flagged') {
        return 'border-red-500/80 shadow-lg ring-1 ring-red-500/30';
    }
    if (status === 'rejected') {
        return 'border-amber-500/80 shadow-lg ring-1 ring-amber-500/30';
    }
    if (status === 'approved') {
        return 'border-emerald-500/70 shadow-sm';
    }
    if (status === 'pending') {
        return 'border-blue-500/50';
    }
    return 'border-border';
}

function getPhotoRingClass(status?: string): string {
    if (status === 'approved') {
        return 'ring-2 ring-emerald-500/70 border-emerald-500/60';
    }
    if (status === 'flagged') {
        return 'ring-2 ring-red-500/80 border-red-500/60';
    }
    if (status === 'rejected' || status === 'replacement_requested') {
        return 'ring-2 ring-amber-500/80 border-amber-500/60';
    }
    return 'border-border';
}

function getPhotoStatusLabel(status?: string): string {
    if (status === 'flagged') {
        return 'Blurry';
    }
    if (status === 'replacement_requested') {
        return 'Replacement Requested';
    }
    if (status === 'approved') {
        return 'Approved';
    }
    if (status === 'rejected') {
        return 'Rejected';
    }
    return 'Pending';
}

function getPhotoReason(photo: PhotoData): string {
    return photo.flag_reason || photo.photo_approval?.reason || '';
}

function getRejectionReasons(photos?: PhotoData[]): string[] {
    const reasons = new Set<string>();
    for (const photo of photos || []) {
        const reason = getPhotoReason(photo).trim();
        if (reason !== '') {
            reasons.add(reason);
        }
    }
    return [...reasons];
}

function getLockedStatusLabel(status?: string): string {
    if (status === 'submitted') {
        return 'Submitted waiting for manager review';
    }
    if (status === 'under_review') {
        return 'Under Review';
    }
    if (status === 'approved') {
        return 'Approved';
    }
    if (status === 'rejected') {
        return 'Rejected';
    }
    return '';
}

function getSubmitLabel(stage: StageKey, isSubmitted: boolean): string {
    if (stage === 'post_completion' && !isSubmitted) {
        return 'Submit Package';
    }
    if (isSubmitted) {
        return 'Update Stage';
    }
    return 'Submit Stage';
}

function getCategoryUploadErrorMessage(errors?: Partial<Record<string, string>>): string {
    if (errors?.category_selection_id) {
        return errors.category_selection_id;
    }
    if (errors?.photo) {
        return errors.photo;
    }
    if (errors?.position) {
        return errors.position;
    }
    return 'Upload failed. Please check the image and try again.';
}

function isCategoryRemovedError(message: string): boolean {
    const lower = message.toLowerCase();
    return lower.includes('removed') || lower.includes('invalid') || lower.includes('refresh');
}

function getStageBadgeClass(completed: boolean, active: boolean): string {
    if (completed) {
        return COMPLETED_BADGE_CLASS;
    }
    if (active) {
        return ACTIVE_BADGE_CLASS;
    }
    return INACTIVE_BADGE_CLASS;
}

function getStageLabelClass(completed: boolean, active: boolean): string {
    if (active) {
        return 'text-foreground font-bold';
    }
    if (completed) {
        return 'text-emerald-600 dark:text-emerald-400';
    }
    return MUTED_TEXT_CLASS;
}

function getChecklistStatusSubtext(
    active: boolean,
    complete: boolean
): { text: string; className: string } {
    if (active) {
        return { text: 'Current', className: 'text-primary' };
    }
    if (complete) {
        return { text: 'Complete', className: 'text-emerald-600' };
    }
    return { text: '·', className: 'text-transparent' };
}

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

function ChecklistTab({
    active,
    complete,
    onClick,
}: Readonly<{
    active: boolean;
    complete: boolean;
    onClick: () => void;
}>) {
    const statusSubtext = getChecklistStatusSubtext(active, complete);

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
                    getStageBadgeClass(complete, active)
                )}
            >
                {complete ? (
                    <Check className="size-3.5 sm:size-4" />
                ) : (
                    <ClipboardCheck className="size-3.5 sm:size-4" />
                )}
            </span>
            <span
                className={cn(
                    'text-[10px] sm:text-xs font-semibold truncate max-w-full leading-tight',
                    getStageLabelClass(complete, active)
                )}
            >
                Checklist
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

function ChecklistStatusBadge({
    readOnly,
    saving,
}: Readonly<{
    readOnly: boolean;
    saving: boolean;
}>) {
    if (readOnly) {
        return (
            <span className="inline-flex items-center rounded-full border border-border bg-muted px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                Read-only
            </span>
        );
    }
    if (saving) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 animate-pulse shadow-2xs">
                <Loader2 className="size-3.5 animate-spin" />
                <span>Saving answers...</span>
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400 shadow-2xs">
            <Check className="size-3.5" />
            <span>All answers auto-saved</span>
        </span>
    );
}

function ChecklistView({
    packageId,
    projectId,
    responses,
    state,
    onAnswer,
    onExplain,
    onQuantity,
    onFlush,
    saving,
    readOnly,
    packageNotes,
    packageNoteImages,
    canEditSiteNotes,
}: Readonly<{
    packageId: number | string;
    projectId?: number | null;
    responses: ChecklistResponseData[];
    state: Record<number, ChecklistState>;
    onAnswer: (itemId: number, answer: boolean | 'na') => void;
    onExplain: (itemId: number, explain: string) => void;
    onQuantity: (itemId: number, quantity: string) => void;
    onFlush?: () => void;
    saving: boolean;
    readOnly: boolean;
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
        <div className="space-y-2">
            <div>
                <div className="flex items-center justify-between">
                    <h2 className="font-bold text-lg">Package Checklist</h2>
                    <ChecklistStatusBadge readOnly={readOnly} saving={saving} />
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                    Answers are automatically saved when you make a selection. No manual saving
                    needed.
                </p>
            </div>
            {responses.map((r, index) => {
                const st = state[r.checklist_item.id] || {
                    answer: null,
                    explain: '',
                    is_na: false,
                    quantity: '',
                };
                const allowsNa = Boolean(r.checklist_item.allows_na);
                return (
                    <div
                        key={r.checklist_item.id}
                        className="rounded-xl border bg-card overflow-hidden"
                    >
                        <div className="flex items-center justify-between gap-3 p-4 bg-muted/30">
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
                                <Button
                                    size="sm"
                                    disabled={readOnly}
                                    variant={
                                        !st.is_na && st.answer === true ? 'success' : 'outline'
                                    }
                                    onClick={() => onAnswer(r.checklist_item.id, true)}
                                    className="cursor-pointer"
                                    title="Mark as Yes (auto-saves immediately)"
                                >
                                    <Check className="size-3 mr-1" /> Yes
                                </Button>
                                <Button
                                    size="sm"
                                    disabled={readOnly}
                                    variant={
                                        !st.is_na && st.answer === false ? 'destructive' : 'outline'
                                    }
                                    onClick={() => onAnswer(r.checklist_item.id, false)}
                                    className="cursor-pointer"
                                    title="Mark as No (auto-saves immediately)"
                                >
                                    <X className="size-3 mr-1" /> No
                                </Button>
                                {allowsNa && (
                                    <Button
                                        size="sm"
                                        disabled={readOnly}
                                        variant={st.is_na ? 'secondary' : 'outline'}
                                        onClick={() => onAnswer(r.checklist_item.id, 'na')}
                                        className={`cursor-pointer ${st.is_na ? 'bg-zinc-700 text-white hover:bg-zinc-800 dark:bg-zinc-600 dark:hover:bg-zinc-500 font-semibold' : ''}`}
                                        title="Mark as Not Applicable (auto-saves immediately)"
                                    >
                                        N/A
                                    </Button>
                                )}
                            </div>
                        </div>
                        {r.checklist_item.has_explain_field && !st.is_na && st.answer !== null && (
                            <div className="p-4 border-t border-border">
                                <label
                                    htmlFor={`checklist-explain-${r.checklist_item.id}`}
                                    className="text-xs font-bold text-foreground"
                                >
                                    Add a note{' '}
                                    <span className="font-medium text-muted-foreground">
                                        (optional)
                                    </span>
                                </label>
                                <Textarea
                                    id={`checklist-explain-${r.checklist_item.id}`}
                                    value={st.explain}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        onExplain(
                                            r.checklist_item.id,
                                            e.target.value.slice(0, CHECKLIST_NOTE_MAX_LENGTH)
                                        )
                                    }
                                    onBlur={onFlush}
                                    maxLength={CHECKLIST_NOTE_MAX_LENGTH}
                                    placeholder="Add a note about your answer (optional)…"
                                    className="mt-1.5 min-h-[60px] text-xs"
                                />
                                <div className="mt-1 flex items-center justify-end">
                                    <span className="text-[10px] tabular-nums text-muted-foreground">
                                        {st.explain.length} /{' '}
                                        {CHECKLIST_NOTE_MAX_LENGTH.toLocaleString()}
                                    </span>
                                </div>
                            </div>
                        )}
                        {!st.is_na && st.answer !== null && (
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-border px-4 py-3">
                                <label
                                    htmlFor={`checklist-quantity-${r.checklist_item.id}`}
                                    className="text-xs font-medium text-muted-foreground"
                                >
                                    Quantity (optional)
                                </label>
                                <Input
                                    id={`checklist-quantity-${r.checklist_item.id}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={CHECKLIST_QUANTITY_MAX}
                                    step={1}
                                    value={st.quantity}
                                    disabled={readOnly}
                                    onChange={(e) =>
                                        onQuantity(r.checklist_item.id, e.target.value)
                                    }
                                    onBlur={onFlush}
                                    placeholder="e.g. 2"
                                    className="h-8 w-24 text-xs"
                                    title="How many were installed or replaced at this site"
                                />
                                <span className="text-[11px] text-muted-foreground">
                                    Printed on the close-out package only when you enter a value.
                                </span>
                            </div>
                        )}
                    </div>
                );
            })}
            <PackageSiteNotes
                packageId={packageId}
                notes={packageNotes}
                images={packageNoteImages}
                canEdit={canEditSiteNotes}
                projectId={projectId}
            />
        </div>
    );
}

const STAGE_ORDER: StageKey[] = ['pre_construction', 'construction', 'post_completion'];

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

// ponytail: single source for minimum photos per required category – pictureCount is capacity, not requirement
const MIN_PHOTOS_PER_REQUIRED_CATEGORY = 1;

interface CategoryState {
    selected: boolean;
    heading: string;
    notes: string;
    pictureCount: number;
}

interface GpsFix {
    latitude: number;
    longitude: number;
    accuracy: number;
}

type GpsRequest = { status: 'ok'; fix: GpsFix } | { status: 'denied' | 'unavailable' };

const PACKAGES_INDEX_ROUTE = 'packages.index';

let sessionCachedFix: GpsFix | null = null;
let lastFixTimestamp = 0;
const GPS_CACHE_DURATION_MS = 300000;

function requestGps(forceFresh = false): Promise<GpsRequest> {
    if (!('geolocation' in navigator) || !globalThis.isSecureContext) {
        return Promise.resolve({ status: 'unavailable' });
    }

    if (!forceFresh && sessionCachedFix && Date.now() - lastFixTimestamp < GPS_CACHE_DURATION_MS) {
        return Promise.resolve({ status: 'ok', fix: sessionCachedFix });
    }

    if (navigator.permissions?.query) {
        return navigator.permissions
            .query({ name: 'geolocation' })
            .then((permission) => {
                if (permission.state === 'denied') {
                    return { status: 'denied' } as const;
                }
                return getCurrentPosition();
            })
            .catch(() => getCurrentPosition());
    }

    return getCurrentPosition();
}

function getCurrentPosition(): Promise<GpsRequest> {
    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve({ status: 'unavailable' });
            return;
        }

        const cacheFix = (position: GeolocationPosition): GpsFix => {
            const fix: GpsFix = {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            };
            sessionCachedFix = fix;
            lastFixTimestamp = Date.now();
            return fix;
        };

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ status: 'ok', fix: cacheFix(position) }),
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    resolve({ status: 'denied' });
                    return;
                }
                // Fallback attempt with maximum relaxed options (network/IP location for laptops)
                navigator.geolocation.getCurrentPosition(
                    (position) => resolve({ status: 'ok', fix: cacheFix(position) }),
                    (retryError) =>
                        resolve(
                            retryError.code === retryError.PERMISSION_DENIED
                                ? { status: 'denied' }
                                : { status: 'unavailable' }
                        ),
                    { enableHighAccuracy: false, timeout: 4000, maximumAge: GPS_CACHE_DURATION_MS }
                );
            },
            { enableHighAccuracy: false, timeout: 3000, maximumAge: GPS_CACHE_DURATION_MS }
        );
    });
}

function getChecklistResponseIssue(r: ChecklistResponseData, st?: ChecklistState): string | null {
    if (st?.is_na) {
        return r.checklist_item.allows_na
            ? null
            : `"${r.checklist_item.label}" does not allow N/A.`;
    }
    // Notes are optional for every answer — they never block submission.
    return null;
}

function buildInitialCategoryState(
    categories: CategoryData[],
    selections: CategorySelectionData[]
): Record<number, CategoryState> {
    const state: Record<number, CategoryState> = {};
    for (const cat of categories) {
        const sel = selections.find((s) => s.template_picture_category_id === cat.id);
        state[cat.id] = {
            // A category the template now marks required can never be unchecked.
            selected: cat.is_required ? true : (sel?.is_selected ?? true),
            heading: sel?.user_heading || cat.name,
            notes: sel?.user_notes ?? '',
            // The template owns the bucket cap, so it wins over a stale snapshot.
            pictureCount: cat.default_picture_count ?? sel?.picture_count ?? 1,
        };
    }
    return state;
}

/**
 * Merges a package category selection with live editor state (when the
 * selection belongs to the stage being edited) into a favorite snapshot item.
 */
function toFavoriteSnapshotItem(
    selection: CategorySelectionData,
    live: CategoryState | undefined,
    category: CategoryData | undefined
): FavoriteSnapshotItem {
    const isLive = live !== undefined && category !== undefined;

    if (!isLive) {
        return {
            category_id: selection.template_picture_category_id,
            is_selected: selection.is_selected ?? true,
            picture_count: selection.picture_count ?? 1,
            user_heading: selection.user_heading,
            user_notes: selection.user_notes,
        };
    }

    return {
        category_id: selection.template_picture_category_id,
        is_selected: live.selected,
        picture_count: live.pictureCount,
        user_heading: live.heading === category.name ? null : live.heading,
        user_notes: live.notes || null,
    };
}

const MEDIA_ASSET_ID_TYPE = 'text/x-media-asset-id';
const MEDIA_ASSET_IDS_TYPE = 'text/x-media-asset-ids';

function hasMediaOrFileDrop(types: readonly string[]): boolean {
    return (
        types.includes(MEDIA_ASSET_ID_TYPE) ||
        types.includes(MEDIA_ASSET_IDS_TYPE) ||
        types.includes('Files')
    );
}

function parseDroppedMediaAssetIds(dataTransfer: DataTransfer): number[] | null {
    const multipleRaw = dataTransfer.getData(MEDIA_ASSET_IDS_TYPE);
    if (multipleRaw) {
        try {
            const parsed = JSON.parse(multipleRaw);
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.map(Number).filter((id) => id > 0);
            }
        } catch {
            // fallback to single id
        }
    }

    const singleRaw = dataTransfer.getData(MEDIA_ASSET_ID_TYPE);
    if (singleRaw) {
        const id = Number(singleRaw);
        if (id > 0) return [id];
    }

    return null;
}

function useCategoryDrop(
    categoryId: number,
    onUpload: (categoryId: number, files: File[]) => void,
    onMediaDrop:
        ((categoryId: number, assetId: number, multipleIds?: number[]) => void) | undefined,
    enabled: boolean
) {
    const [isDragOver, setIsDragOver] = useState(false);
    const dragCounter = useRef(0);

    const handleDragEnter = useCallback(
        (e: DragEvent<HTMLDivElement>) => {
            if (!enabled) return;
            e.preventDefault();
            e.stopPropagation();
            dragCounter.current += 1;
            if (hasMediaOrFileDrop(e.dataTransfer.types)) setIsDragOver(true);
        },
        [enabled]
    );

    const handleDragOver = useCallback(
        (e: DragEvent<HTMLDivElement>) => {
            if (!enabled) return;
            e.preventDefault();
            e.stopPropagation();
            if (hasMediaOrFileDrop(e.dataTransfer.types)) e.dataTransfer.dropEffect = 'copy';
        },
        [enabled]
    );

    const handleDragLeave = useCallback((e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        e.stopPropagation();
        dragCounter.current -= 1;
        if (dragCounter.current <= 0) {
            dragCounter.current = 0;
            setIsDragOver(false);
        }
    }, []);

    const handleDrop = useCallback(
        (e: DragEvent<HTMLDivElement>) => {
            if (!enabled) return;
            e.preventDefault();
            e.stopPropagation();
            dragCounter.current = 0;
            setIsDragOver(false);

            if (onMediaDrop) {
                const droppedIds = parseDroppedMediaAssetIds(e.dataTransfer);
                if (droppedIds && droppedIds.length > 0) {
                    onMediaDrop(categoryId, droppedIds[0], droppedIds);
                    return;
                }
            }

            const files = Array.from(e.dataTransfer.files ?? []).filter(
                (f) => f.type.startsWith('image/') || /\.(jpe?g|png|webp|heic)$/i.test(f.name)
            );
            if (files.length > 0) onUpload(categoryId, files);
        },
        [categoryId, enabled, onMediaDrop, onUpload]
    );

    const handlePaste = useCallback(
        (e: ClipboardEvent<HTMLDivElement>) => {
            if (!enabled) return;
            const files = Array.from(e.clipboardData.files ?? []).filter((f) =>
                f.type.startsWith('image/')
            );
            if (files.length > 0) {
                e.preventDefault();
                onUpload(categoryId, files);
            }
        },
        [categoryId, enabled, onUpload]
    );

    return {
        isDragOver,
        handleDragEnter,
        handleDragOver,
        handleDragLeave,
        handleDrop,
        handlePaste,
    };
}

const BUCKET_CAPACITY_FULL_MESSAGE =
    'Bucket capacity is full. Remove an existing one to add a new one.';

const CATEGORY_NOT_READY_MESSAGE =
    'This category is not ready for upload yet. Please save your draft first.';

/** Human-readable stored size (e.g. "45.2 MB", "1.3 GB"); '' for nothing to show. */
export function humanizeBytes(bytes?: number | null): string {
    if (!bytes || bytes <= 0) {
        return '';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }

    return `${value >= 100 || unit === 0 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`;
}

/** Parallel photo uploads per batch — bounded so slow devices don't saturate. */
const BATCH_UPLOAD_CONCURRENCY = 2;

/** Trailing debounce for the partial reload after batch uploads settle. */
const BATCH_RELOAD_DEBOUNCE_MS = 600;

function getBlockedUploadReason(overLimit: boolean, isCapacityFull: boolean): string | undefined {
    if (overLimit) {
        return 'Template drift conflict must be resolved before uploading.';
    }
    if (isCapacityFull) {
        return BUCKET_CAPACITY_FULL_MESSAGE;
    }
    return undefined;
}

interface CategoryUploadToolbarProps {
    categoryId: number;
    categoryName: string;
    isUploadBlocked: boolean;
    blockedReason?: string;
    onCamera: (categoryId: number) => void;
    onUpload: (categoryId: number, files: File[]) => void;
    onMediaPicker?: (categoryId: number, categoryName: string) => void;
}

function CategoryUploadToolbar({
    categoryId,
    categoryName,
    isUploadBlocked,
    blockedReason,
    onCamera,
    onUpload,
    onMediaPicker,
}: Readonly<CategoryUploadToolbarProps>) {
    return (
        <div className="flex flex-wrap gap-2">
            <span
                title={
                    isUploadBlocked
                        ? blockedReason
                        : 'Open in-app camera to capture cell site photo directly.'
                }
                className="inline-block"
            >
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    onClick={() => onCamera(categoryId)}
                    disabled={isUploadBlocked}
                    className={cn(
                        'inline-flex items-center gap-1.5 px-3 py-2 h-auto rounded-md text-xs font-semibold',
                        isUploadBlocked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                    )}
                >
                    <Camera className="size-3.5" />
                    Camera
                </Button>
            </span>
            <span
                title={
                    isUploadBlocked
                        ? blockedReason
                        : 'Select one or more images (JPG, PNG, WEBP or HEIC, max 7MB each). Photos are compressed automatically before upload. Capacity is picture_count, at least 1 required to proceed.'
                }
                className="inline-block"
            >
                <label
                    className={cn(
                        'inline-flex items-center gap-1.5 px-3 py-2 rounded-md bg-success text-success-foreground text-xs font-semibold shadow-sm transition-colors',
                        isUploadBlocked
                            ? 'opacity-50 cursor-not-allowed pointer-events-none'
                            : 'hover:bg-success/90 cursor-pointer'
                    )}
                >
                    <Upload className="size-3.5" />
                    Upload Images
                    <input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp,image/heic"
                        className="hidden"
                        disabled={isUploadBlocked}
                        onChange={(e) => {
                            const files = Array.from(e.target.files ?? []);
                            if (files.length > 0) onUpload(categoryId, files);
                            e.target.value = '';
                        }}
                    />
                </label>
            </span>
            {onMediaPicker && (
                <span
                    title={
                        isUploadBlocked
                            ? blockedReason
                            : 'Pick photos from your Media Library to add to this category.'
                    }
                    className="inline-block"
                >
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => onMediaPicker(categoryId, categoryName)}
                        className={cn(
                            'inline-flex items-center gap-1.5 px-3 py-2 h-auto rounded-md text-xs font-semibold bg-indigo-600 text-white shadow-xs transition-colors border-0',
                            isUploadBlocked
                                ? 'opacity-50 cursor-not-allowed'
                                : 'hover:bg-indigo-700 cursor-pointer'
                        )}
                        disabled={isUploadBlocked}
                    >
                        <Images className="size-3.5 text-white" />
                        From Library
                    </Button>
                </span>
            )}
        </div>
    );
}

function computeIsPhotoTargeted(
    photoId: number,
    photoStatus: string | null | undefined,
    isPackageLocked: boolean,
    isFeedbackReworkActive: boolean,
    targetedPhotoIds?: Set<number>,
    isCategoryTargeted?: boolean
): boolean {
    if (!isPackageLocked) {
        return true;
    }
    const needsAttention = ATTENTION_STATUSES.has(photoStatus || '');
    if (needsAttention) {
        return true;
    }
    if (isFeedbackReworkActive) {
        if (targetedPhotoIds?.has(photoId)) {
            return true;
        }
        if (isCategoryTargeted) {
            return true;
        }
    }
    return false;
}

function CategoryRowBadges({
    required,
    overLimit,
    conflict,
    isSectionFeedbackTargeted,
    excessPhotos,
}: Readonly<{
    required: boolean;
    overLimit: boolean;
    conflict?: DriftConflict;
    isSectionFeedbackTargeted: boolean;
    excessPhotos: number;
}>) {
    return (
        <>
            {isSectionFeedbackTargeted && (
                <span
                    className="inline-flex items-center gap-1 rounded-full border border-amber-500/50 bg-amber-500/20 px-2.5 py-0.5 text-[10px] sm:text-[11px] font-bold text-amber-800 dark:text-amber-200 animate-pulse"
                    title="Customer provided feedback on this section. You can replace photos, delete, or upload new photos."
                >
                    <AlertTriangle className="size-3 text-amber-600 dark:text-amber-400 shrink-0" />
                    Section Feedback · Needs Attention
                </span>
            )}
            {required ? (
                <span className="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                    Required
                </span>
            ) : (
                <span className="inline-flex items-center rounded-full border border-border bg-muted px-2 py-0.5 text-[10px] sm:text-[11px] font-medium text-muted-foreground">
                    Optional
                </span>
            )}
            {overLimit && conflict && (
                <span
                    className="inline-flex items-center gap-1 rounded-md border border-destructive/40 bg-destructive/10 px-2 py-0.5 text-[10px] font-semibold text-destructive sm:text-[11px]"
                    title={`The template reduced this bucket to ${conflict.newValue}, but ${conflict.currentPhotoCount} photo(s) are uploaded.`}
                >
                    <AlertTriangle className="size-3" /> Over limit: remove {excessPhotos} photo
                    {excessPhotos === 1 ? '' : 's'}
                </span>
            )}
        </>
    );
}

function CategoryRow({
    packageId,
    selectionId,
    isLocked,
    isPackageLocked,
    isFeedbackReworkActive = false,
    isCategoryTargeted = false,
    isSectionFeedbackTargeted = false,
    category,
    index,
    state,
    photos,
    onStateChange,
    onUpload,
    onCamera,
    onMediaPicker,
    onMediaDrop,
    onReplace,
    onDelete,
    canDeletePhoto,
    conflict,
    targetedPhotoIds,
}: Readonly<{
    packageId: number | string;
    selectionId?: number;
    isLocked: boolean;
    isPackageLocked?: boolean;
    isFeedbackReworkActive?: boolean;
    isCategoryTargeted?: boolean;
    isSectionFeedbackTargeted?: boolean;
    category: CategoryData;
    index: number;
    state: CategoryState;
    photos: PhotoData[];
    onStateChange: (field: Partial<CategoryState>) => void;
    onUpload: (categoryId: number, files: File[]) => void;
    onCamera: (categoryId: number) => void;
    onMediaPicker?: (categoryId: number, categoryName: string) => void;
    onMediaDrop?: (categoryId: number, assetId: number, multipleIds?: number[]) => void;
    onReplace: (photo: PhotoData, file: File) => void;
    onDelete: (photo: PhotoData) => void;
    canDeletePhoto: (photo: PhotoData) => boolean;
    conflict?: DriftConflict;
    targetedPhotoIds?: Set<number>;
}>) {
    const [collapsed, setCollapsed] = useState(false);
    const required = Boolean(category.is_required);
    const categoryStatus = getCategoryStatus(photos);
    const overLimit = conflict !== undefined;
    const isCapacityFull = photos.length >= state.pictureCount;
    const isUploadBlocked = isLocked || overLimit || isCapacityFull;
    const dropEnabled = !isLocked && state.selected && !collapsed && !isUploadBlocked;
    const blockedReason = isLocked
        ? 'Upload is locked for this category.'
        : getBlockedUploadReason(overLimit, isCapacityFull);
    const excessPhotos = conflict ? Math.max(0, conflict.currentPhotoCount - conflict.newValue) : 0;
    const {
        isDragOver,
        handleDragEnter,
        handleDragOver,
        handleDragLeave,
        handleDrop,
        handlePaste,
    } = useCategoryDrop(category.id, onUpload, onMediaDrop, dropEnabled);

    return (
        <div
            data-category-id={category.id}
            data-selection-id={selectionId}
            className={cn(
                'rounded-xl border-2 bg-card overflow-hidden transition-all shadow-sm',
                isSectionFeedbackTargeted
                    ? 'border-amber-500 ring-2 ring-amber-500/40 shadow-md bg-amber-500/[0.02]'
                    : getCategoryBorderClass(categoryStatus),
                isDragOver && 'ring-2 ring-indigo-500/50 border-indigo-500',
                collapsed && 'opacity-75 border-dashed',
                !state.selected && 'opacity-60 border-dashed'
            )}
        >
            {/* Category header */}
            <div
                className={cn(
                    'flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 p-3 sm:p-4',
                    isSectionFeedbackTargeted
                        ? 'bg-amber-500/10 dark:bg-amber-500/15 border-b border-amber-500/20'
                        : 'bg-muted/30'
                )}
            >
                <div className="flex items-start sm:items-center gap-2.5 min-w-0 flex-1">
                    <span
                        title={`Category ${index}`}
                        className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground mt-0.5 sm:mt-0"
                    >
                        {index}
                    </span>
                    <input
                        type="checkbox"
                        checked={state.selected}
                        disabled={required || isLocked}
                        onChange={(e) => onStateChange({ selected: e.target.checked })}
                        aria-label={
                            required ? `${category.name} (required)` : `Include ${category.name}`
                        }
                        className="size-5 shrink-0 accent-primary mt-0.5 sm:mt-0"
                    />
                    <div className="min-w-0 flex-1">
                        <h3 className="text-sm font-semibold text-foreground break-words leading-snug">
                            {category.name}
                        </h3>
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-wrap pl-8 sm:pl-0">
                    <CategoryRowBadges
                        required={required}
                        overLimit={overLimit}
                        conflict={conflict}
                        isSectionFeedbackTargeted={isSectionFeedbackTargeted}
                        excessPhotos={excessPhotos}
                    />
                    <span
                        className="inline-flex items-center gap-1 rounded-md border-2 border-primary/30 bg-primary/10 px-2 py-0.5 sm:px-2.5 sm:py-1 text-[10px] sm:text-[11px] font-extrabold text-primary shadow-2xs"
                        title={`Bucket capacity ${state.pictureCount} (admin defined), at least ${MIN_PHOTOS_PER_REQUIRED_CATEGORY} required to submit – ${category.name}`}
                    >
                        <ImageIcon className="size-3 sm:size-3.5" /> Bucket: {state.pictureCount} ·
                        Min {MIN_PHOTOS_PER_REQUIRED_CATEGORY}
                    </span>
                    <button
                        type="button"
                        onClick={() => setCollapsed(!collapsed)}
                        aria-label={
                            collapsed ? `Expand ${category.name}` : `Collapse ${category.name}`
                        }
                        className="size-7 sm:size-8 flex items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground ml-auto cursor-pointer"
                    >
                        {collapsed ? '▼' : '▲'}
                    </button>
                </div>
            </div>

            {/* Category body */}
            {!collapsed && state.selected && (
                <section
                    aria-label={`Upload zone for ${category.name}`}
                    className={cn(
                        'p-4 space-y-3.5 border-t border-border relative outline-none',
                        isDragOver &&
                            'bg-indigo-500/5 ring-2 ring-indigo-500/40 border-indigo-500/50'
                    )}
                    onDragOver={handleDragOver}
                    onDragEnter={handleDragEnter}
                    onDragLeave={handleDragLeave}
                    onDrop={handleDrop}
                    onPaste={handlePaste}
                >
                    {isDragOver && (
                        <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-1.5 bg-background/90 backdrop-blur-xs rounded-b-xl border-2 border-dashed border-indigo-600 dark:border-indigo-400 pointer-events-none p-4 text-center animate-in fade-in-50 duration-150">
                            <Images className="size-7 text-indigo-600 dark:text-indigo-400 animate-bounce" />
                            <span className="text-sm font-bold text-foreground">
                                Drop photo to add to {category.name}
                            </span>
                            <span className="text-xs text-muted-foreground font-medium">
                                Capacity: {photos.length} / {state.pictureCount} photo
                                {state.pictureCount === 1 ? '' : 's'} · min{' '}
                                {MIN_PHOTOS_PER_REQUIRED_CATEGORY}
                            </span>
                        </div>
                    )}
                    {category.description && (
                        <div className="rounded-lg bg-muted/40 border border-border/70 p-2.5 text-xs text-muted-foreground flex items-start gap-2">
                            <Info className="size-4 shrink-0 text-primary/70 mt-0.5" />
                            <div>
                                <span className="font-semibold text-foreground mr-1">
                                    Description:
                                </span>
                                <span>{category.description}</span>
                            </div>
                        </div>
                    )}

                    <div>
                        <label
                            htmlFor={`notes-${category.id}`}
                            className="text-[11px] font-semibold text-muted-foreground uppercase tracking-wide"
                        >
                            Supervisor Notes (Optional)
                        </label>
                        <Textarea
                            id={`notes-${category.id}`}
                            value={state.notes}
                            onChange={(e) => onStateChange({ notes: e.target.value })}
                            placeholder="Add supervisor notes for this category if needed..."
                            className="min-h-[48px] text-xs mt-1 bg-background"
                            disabled={isLocked}
                        />
                    </div>

                    <div className="space-y-2">
                        <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-xs font-semibold">
                                Uploaded Photos ({photos.length} / {state.pictureCount} capacity ·
                                min {MIN_PHOTOS_PER_REQUIRED_CATEGORY})
                            </span>
                            <CategoryUploadToolbar
                                categoryId={category.id}
                                categoryName={category.name}
                                isUploadBlocked={isUploadBlocked}
                                blockedReason={blockedReason}
                                onCamera={onCamera}
                                onUpload={onUpload}
                                onMediaPicker={onMediaPicker}
                            />
                        </div>

                        {photos.length === 0 ? (
                            <div className="p-6 border border-dashed rounded-lg text-center text-xs text-muted-foreground bg-muted/10">
                                No photos uploaded yet. Drag &amp; drop, paste (Ctrl+V), or tap
                                Camera / Upload to add.
                            </div>
                        ) : (
                            <CategoryPhotoGrid
                                packageId={packageId}
                                isPackageLocked={isPackageLocked ?? isLocked}
                                isFeedbackReworkActive={isFeedbackReworkActive}
                                isCategoryTargeted={isCategoryTargeted || !isLocked}
                                categoryName={category.name}
                                photos={photos}
                                onReplace={onReplace}
                                onDelete={onDelete}
                                canDeletePhoto={canDeletePhoto}
                                targetedPhotoIds={targetedPhotoIds}
                            />
                        )}
                    </div>
                </section>
            )}
        </div>
    );
}

function CategoryPhotoGrid({
    packageId,
    isPackageLocked = false,
    isFeedbackReworkActive = false,
    isCategoryTargeted = false,
    categoryName,
    photos,
    onReplace,
    onDelete,
    canDeletePhoto,
    targetedPhotoIds,
}: Readonly<{
    packageId: number | string;
    isPackageLocked?: boolean;
    isFeedbackReworkActive?: boolean;
    isCategoryTargeted?: boolean;
    categoryName: string;
    photos: PhotoData[];
    onReplace: (photo: PhotoData, file: File) => void;
    onDelete: (photo: PhotoData) => void;
    canDeletePhoto: (photo: PhotoData) => boolean;
    targetedPhotoIds?: Set<number>;
}>) {
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
    const lightboxPhotos: LightboxPhoto[] = photos.map((photo) => ({
        id: photo.id,
        url: photo.url || photo.original_url || '',
        caption: categoryName,
        alt: `${categoryName} ${photo.id}`,
        status: photo.status,
        reason: getPhotoReason(photo) || undefined,
        notes: photo.notes || undefined,
        latitude: photo.gps_latitude,
        longitude: photo.gps_longitude,
    }));

    return (
        <>
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                {photos.map((photo, photoIndex) => {
                    const needsAttention = ATTENTION_STATUSES.has(photo.status || '');
                    const isPhotoTargeted = computeIsPhotoTargeted(
                        photo.id,
                        photo.status,
                        isPackageLocked,
                        isFeedbackReworkActive,
                        targetedPhotoIds,
                        isCategoryTargeted
                    );

                    return (
                        <div key={photo.id} data-photo-id={photo.id} className="space-y-1">
                            <div
                                className={cn(
                                    'relative rounded-lg overflow-hidden aspect-square bg-muted',
                                    getPhotoRingClass(photo.status)
                                )}
                            >
                                <button
                                    type="button"
                                    onClick={() => setLightboxIndex(photoIndex)}
                                    aria-label={`View ${categoryName} photo full size`}
                                    className="block size-full cursor-zoom-in"
                                >
                                    <img
                                        src={
                                            photo.thumb_url || photo.url || photo.original_url || ''
                                        }
                                        alt={`${categoryName} ${photo.id}`}
                                        className="size-full object-cover"
                                        loading="lazy"
                                    />
                                </button>
                                <a
                                    href={photo.url || photo.original_url || ''}
                                    download
                                    aria-label={`Download ${categoryName} photo`}
                                    className="absolute top-2 right-11 bg-black/70 text-white p-1.5 rounded-md hover:bg-black/90 transition-colors"
                                >
                                    <Download className="size-4" />
                                </a>
                                {isPhotoTargeted && canDeletePhoto(photo) && (
                                    <button
                                        type="button"
                                        onClick={() => onDelete(photo)}
                                        aria-label={`Delete ${categoryName} photo`}
                                        title="Remove this photo"
                                        className="absolute top-2 right-2 bg-red-600/90 text-white p-1.5 rounded-md hover:bg-red-700 transition-colors"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                )}
                                <span className="absolute top-2 left-2">
                                    <StatusBadge
                                        status={photo.status || 'pending'}
                                        label={getPhotoStatusLabel(photo.status)}
                                    />
                                </span>
                                {photo.gps_latitude != null && photo.gps_longitude != null && (
                                    <GpsBadge
                                        latitude={photo.gps_latitude}
                                        longitude={photo.gps_longitude}
                                        className="absolute bottom-2 left-2"
                                    />
                                )}
                                {needsAttention && (
                                    <span className="absolute top-10 left-2 inline-flex items-center gap-1 rounded-full bg-amber-500/90 text-white px-2 py-0.5 text-[10px] font-semibold">
                                        <AlertTriangle className="size-3" />
                                        Needs attention
                                    </span>
                                )}
                            </div>
                            <PhotoReasonNote reason={getPhotoReason(photo)} />
                            {isPhotoTargeted && (
                                <label className="inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-md border border-input bg-card hover:bg-accent cursor-pointer text-xs font-medium">
                                    <RotateCcw className="size-3.5" />
                                    Replace Photo
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,image/heic"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) onReplace(photo, file);
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                            <PhotoNoteSection
                                packageId={packageId}
                                photoId={photo.id}
                                initialNotes={photo.notes}
                                isLocked={!isPhotoTargeted}
                                className="mt-1"
                            />
                        </div>
                    );
                })}
            </div>
            <PhotoLightbox
                photos={lightboxPhotos}
                index={lightboxIndex}
                onClose={() => setLightboxIndex(null)}
            />
        </>
    );
}

function StepHint({
    activeView,
    isLocked,
    isChecklistView,
}: Readonly<{
    activeView: ActiveView;
    isLocked: boolean;
    isChecklistView: boolean;
}>) {
    if (isLocked) {
        return (
            <div className="mt-1.5 rounded-lg border border-border/60 bg-muted/40 px-2.5 py-1 text-center text-xs text-muted-foreground">
                <p className="inline-block leading-snug">
                    <Lock className="inline size-3.5 mr-1.5 -mt-0.5 shrink-0 align-middle" />
                    <span>
                        This package was submitted and is read-only. Only photos marked for
                        replacement can be replaced.
                    </span>
                </p>
            </div>
        );
    }
    if (isChecklistView) {
        return (
            <div className="mt-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-center text-xs text-amber-800 dark:text-amber-300">
                <p className="inline-block leading-snug">
                    <ClipboardCheck className="inline size-3.5 mr-1.5 -mt-0.5 text-amber-600 shrink-0 align-middle" />
                    <span>
                        Answer all checklist items (auto-saved as you answer), then submit the
                        package for manager review.
                    </span>
                </p>
            </div>
        );
    }
    const stageIndex = STAGE_ORDER.indexOf(activeView as StageKey);
    const next = STAGE_ORDER[stageIndex + 1];
    if (next) {
        return (
            <div className="mt-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-center sm:text-left text-xs text-emerald-950 dark:text-emerald-200">
                <p className="inline-block leading-snug">
                    <Send className="inline size-3.5 mr-1.5 -mt-0.5 text-emerald-600 dark:text-emerald-400 shrink-0 align-middle" />
                    <span className="font-semibold">
                        Done with {STAGE_LABELS[activeView as StageKey]}?
                    </span>{' '}
                    Click &quot;Submit Stage&quot; above to continue to {STAGE_LABELS[next]}.
                </p>
            </div>
        );
    }
    return (
        <div className="mt-1.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-center sm:text-left text-xs text-emerald-950 dark:text-emerald-200">
            <p className="inline-block leading-snug">
                <ClipboardCheck className="inline size-3.5 mr-1.5 -mt-0.5 text-emerald-600 dark:text-emerald-400 shrink-0 align-middle" />
                <span className="font-semibold">All photo steps accessible.</span> Click
                &quot;Checklist&quot; tab when you are ready to complete required questions.
            </p>
        </div>
    );
}

function GpsNoticeBanner({
    denied,
    onRetry,
}: Readonly<{
    denied: boolean;
    onRetry: () => void;
}>) {
    return (
        <div className="rounded-xl border border-amber-500/50 bg-amber-500/10 p-4 flex items-start gap-3">
            <AlertTriangle className="size-5 text-amber-600 shrink-0 mt-0.5" />
            <div className="flex-1">
                <p className="text-sm font-bold text-amber-700 dark:text-amber-400">
                    {denied
                        ? 'Location permission is blocked.'
                        : 'Location is unavailable on this connection.'}
                </p>
                <p className="mt-1 text-xs text-amber-700/90 dark:text-amber-400/90">
                    {denied ? (
                        <>
                            GPS proves each photo was taken on site, so your manager can verify the
                            work. The browser did not show the permission popup because the
                            permission was already blocked. Click the padlock in the address bar →
                            Site settings → Location → Allow, then retry.
                        </>
                    ) : (
                        <>
                            The device could not provide a location fix (weak signal, OS location
                            services off, or non-HTTPS connection). Browser permission may already
                            be granted — check device location settings, move near a window, then
                            retry.
                        </>
                    )}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    className="mt-2 h-7 text-[11px]"
                    onClick={onRetry}
                >
                    Try again
                </Button>
            </div>
        </div>
    );
}

function StageTab({
    stage,
    index,
    completed,
    active,
    attentionCount,
    onClick,
}: Readonly<{
    stage: StageKey;
    index: number;
    completed: boolean;
    active: boolean;
    attentionCount: number;
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
                    getStageBadgeClass(completed, active)
                )}
            >
                {completed ? <Check className="size-3.5 sm:size-4" /> : index + 1}
            </span>
            <span
                className={cn(
                    'text-[10px] sm:text-xs font-semibold truncate max-w-full leading-tight',
                    getStageLabelClass(completed, active)
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
            {attentionCount > 0 && (
                <span className="absolute -top-1 -right-1 inline-flex items-center gap-0.5 rounded-full border border-amber-500/60 bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs">
                    <AlertTriangle className="size-2.5" />
                    <span>{attentionCount}</span>
                </span>
            )}
        </button>
    );
}

function ReadOnlyCategories({
    packageId,
    stage,
    sections,
    stages,
    photos,
    onReplace,
    pulsePhotoId,
}: Readonly<{
    packageId: number | string;
    stage: StageKey;
    sections?: Array<{ stage?: string; admin_notes?: string | null; categories?: CategoryData[] }>;
    stages?: Array<{ stage?: string; category_selections?: CategorySelectionData[] }>;
    photos?: PhotoData[];
    onReplace: (photo: PhotoData, file: File) => void;
    pulsePhotoId: number | null;
}>) {
    const targetSection = sections?.find((s) => s.stage === stage);
    const categories = targetSection?.categories || [];
    const stageSelections = stages?.find((s) => s.stage === stage)?.category_selections || [];

    const getPhotos = (categoryId: number): PhotoData[] =>
        (photos || []).filter((p) => {
            const catId =
                p.category_selection?.template_picture_category_id ??
                p.package_category_selection_id;
            return catId === categoryId && !p.replaced_at;
        });

    return (
        <div className="space-y-2">
            {categories.map((cat, idx) => {
                const sel = stageSelections.find((s) => s.template_picture_category_id === cat.id);
                return (
                    <ReadOnlyCategory
                        key={cat.id}
                        packageId={packageId}
                        category={cat}
                        index={idx + 1}
                        photos={getPhotos(cat.id)}
                        supervisorNotes={sel?.user_notes}
                        onReplace={onReplace}
                        pulsePhotoId={pulsePhotoId}
                    />
                );
            })}
        </div>
    );
}

function ReadOnlyCategory({
    packageId,
    category,
    index,
    photos,
    supervisorNotes,
    onReplace,
    pulsePhotoId,
}: Readonly<{
    packageId: number | string;
    category: CategoryData;
    index: number;
    photos: PhotoData[];
    supervisorNotes?: string | null;
    onReplace: (photo: PhotoData, file: File) => void;
    pulsePhotoId: number | null;
}>) {
    const categoryStatus = getCategoryStatus(photos);
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
        <div
            data-category-id={category.id}
            className={cn(
                'rounded-xl border-2 bg-card overflow-hidden transition-all shadow-sm',
                getCategoryBorderClass(categoryStatus)
            )}
        >
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 p-3 sm:p-4 bg-muted/30">
                <div className="flex items-start sm:items-center gap-2.5 min-w-0 flex-1">
                    <span
                        title={`Category ${index}`}
                        className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground mt-0.5 sm:mt-0"
                    >
                        {index}
                    </span>
                    <h3 className="font-semibold text-sm min-w-0 break-words leading-snug">
                        {category.name}
                    </h3>
                </div>
                <div className="flex items-center gap-2 pl-8 sm:pl-0">
                    {category.is_required && (
                        <span className="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                            Required
                        </span>
                    )}
                    <span className="text-xs text-muted-foreground shrink-0 font-medium">
                        {photos.length} photo{photos.length === 1 ? '' : 's'}
                    </span>
                </div>
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
                        No photos for this category.
                    </div>
                ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        {photos.map((photo, photoIndex) => {
                            const needsAttention = ATTENTION_STATUSES.has(photo.status || '');
                            return (
                                <div
                                    key={photo.id}
                                    data-photo-id={photo.id}
                                    className={cn(
                                        'rounded-lg border overflow-hidden bg-card',
                                        getPhotoRingClass(photo.status),
                                        pulsePhotoId === photo.id &&
                                            'animate-pulse ring-4 ring-red-500/50'
                                    )}
                                >
                                    <div className="relative aspect-square bg-muted">
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
                                        <span className="absolute top-2 left-2">
                                            <StatusBadge
                                                status={photo.status || 'pending'}
                                                label={getPhotoStatusLabel(photo.status)}
                                            />
                                        </span>
                                        {photo.gps_latitude != null &&
                                            photo.gps_longitude != null && (
                                                <GpsBadge
                                                    latitude={photo.gps_latitude}
                                                    longitude={photo.gps_longitude}
                                                    className="absolute bottom-2 left-2"
                                                />
                                            )}
                                        {needsAttention && (
                                            <span className="absolute top-10 left-2 inline-flex items-center gap-1 rounded-full bg-amber-500/90 text-white px-2 py-0.5 text-[10px] font-semibold">
                                                <AlertTriangle className="size-3" />
                                                Needs attention
                                            </span>
                                        )}
                                    </div>
                                    <div className="p-2.5 space-y-1.5">
                                        <PhotoReasonNote reason={getPhotoReason(photo)} />
                                        {needsAttention && (
                                            <label className="inline-flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-md border border-input bg-card hover:bg-accent cursor-pointer text-xs font-medium">
                                                <RotateCcw className="size-3.5" />
                                                Replace Photo
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp,image/heic"
                                                    className="hidden"
                                                    onChange={(e) => {
                                                        const file = e.target.files?.[0];
                                                        if (file) onReplace(photo, file);
                                                        e.target.value = '';
                                                    }}
                                                />
                                            </label>
                                        )}
                                        <PhotoNoteSection
                                            packageId={packageId}
                                            photoId={photo.id}
                                            initialNotes={photo.notes}
                                            isLocked={!needsAttention}
                                            className="mt-1"
                                        />
                                    </div>
                                </div>
                            );
                        })}
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

function extractProjectId(pkg: PackageShowProps['package']): number | null {
    const raw = pkg.project_id ?? pkg.project?.id;
    if (typeof raw === 'number') {
        return raw;
    }
    return Number(raw) || null;
}

function useGpsPermissionStatus() {
    const [gpsNotice, setGpsNotice] = useState<'denied' | 'unavailable' | null>(null);
    const [gpsPermissionState, setGpsPermissionState] = useState<
        'prompt' | 'granted' | 'denied' | 'unsupported'
    >('prompt');

    useEffect(() => {
        if (!('geolocation' in navigator) || !globalThis.isSecureContext) {
            setGpsPermissionState('unsupported');
            return;
        }

        if (navigator.permissions?.query) {
            navigator.permissions
                .query({ name: 'geolocation' })
                .then((perm) => {
                    setGpsPermissionState(perm.state);
                    if (perm.state === 'denied') {
                        setGpsNotice('denied');
                    } else if (perm.state === 'granted') {
                        setGpsNotice(null);
                    }
                    perm.onchange = () => {
                        setGpsPermissionState(perm.state);
                        if (perm.state === 'denied') {
                            setGpsNotice('denied');
                        } else if (perm.state === 'granted') {
                            setGpsNotice(null);
                        }
                    };
                })
                .catch(() => {
                    // Fallback to prompt
                });
        }
    }, []);

    return {
        gpsNotice,
        setGpsNotice,
        gpsPermissionState,
        setGpsPermissionState,
    };
}

interface PackageHeaderActionGroupProps {
    isRejected: boolean;
    canResubmit: boolean;
    canSubmitWorkflow: boolean;
    resubmitting: boolean;
    handleResubmit: () => void;
    isLocked: boolean;
    lockedLabel: string;
    isChecklistView: boolean;
    isAdmin: boolean;
    setShowDeleteConfirm: (open: boolean) => void;
    submitting: boolean;
    handleSubmitPackage: () => void;
    setFavoritesOpen: (open: boolean) => void;
    handleSaveDraft: () => void;
    saving: boolean;
    handleSubmit: () => void;
    submitLabel: string;
}

function PackageHeaderActionGroup({
    isRejected,
    canResubmit,
    canSubmitWorkflow,
    resubmitting,
    handleResubmit,
    isLocked,
    lockedLabel,
    isChecklistView,
    isAdmin,
    setShowDeleteConfirm,
    submitting,
    handleSubmitPackage,
    setFavoritesOpen,
    handleSaveDraft,
    saving,
    handleSubmit,
    submitLabel,
}: Readonly<PackageHeaderActionGroupProps>) {
    if (isRejected && canResubmit) {
        if (!canSubmitWorkflow) {
            return null;
        }
        return (
            <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <Button
                    onClick={handleResubmit}
                    loading={resubmitting}
                    variant="success"
                    size="sm"
                    className="gap-1.5 font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/30 cursor-pointer h-9 px-3 shrink-0"
                    title="Resubmit this package to your manager after replacing flagged photos."
                >
                    <Send className="size-4" /> Resubmit Package
                </Button>
            </div>
        );
    }

    if (isLocked) {
        return (
            <span className="inline-flex items-center rounded-full border border-border bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground shrink-0">
                {lockedLabel}
            </span>
        );
    }

    if (isChecklistView) {
        return (
            <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                {!isLocked && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowDeleteConfirm(true)}
                        className="gap-1.5 cursor-pointer shrink-0 h-9 px-2.5 sm:px-3 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        title="Permanently delete this close-out package."
                        aria-label="Delete Package"
                    >
                        <Trash2 className="size-4" />
                        <span>Delete</span>
                    </Button>
                )}
                {canSubmitWorkflow && (
                    <Button
                        onClick={handleSubmitPackage}
                        loading={submitting}
                        variant="success"
                        size="sm"
                        className="gap-1.5 font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/30 cursor-pointer h-9 px-3 shrink-0"
                        title="Finalize and submit the complete close-out package for manager review."
                    >
                        <Send className="size-4" /> Submit Package
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
            {!isLocked && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowDeleteConfirm(true)}
                    className="gap-1.5 cursor-pointer shrink-0 h-9 px-2.5 sm:px-3 text-destructive hover:bg-destructive/10 hover:text-destructive"
                    title="Permanently delete this close-out package."
                    aria-label="Delete Package"
                >
                    <Trash2 className="size-4" />
                    <span>Delete</span>
                </Button>
            )}
            {!isAdmin && (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setFavoritesOpen(true)}
                    className="gap-1.5 cursor-pointer h-9 px-2.5 sm:px-3 shrink-0 hidden sm:inline-flex"
                    title="Manage your favorite template layouts or save this setup for future sites."
                >
                    <Bookmark className="size-4" />
                    <span>Favorites</span>
                </Button>
            )}
            {canSubmitWorkflow && (
                <>
                    <Button
                        onClick={handleSaveDraft}
                        loading={saving}
                        variant="outline"
                        size="sm"
                        className="cursor-pointer h-9 px-3 flex-1 sm:flex-initial"
                        title="Save your current photos as a draft so you can safely exit and resume later."
                    >
                        Save Draft
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        loading={submitting}
                        variant="success"
                        size="sm"
                        className="gap-1.5 font-bold shadow-md bg-emerald-600 hover:bg-emerald-700 text-white ring-2 ring-emerald-500/30 cursor-pointer h-9 px-3 flex-1 sm:flex-initial"
                        title="Submit this stage to notify your project manager and proceed to the next step."
                    >
                        <Send className="size-4" /> {submitLabel}
                    </Button>
                </>
            )}
        </div>
    );
}

interface CanDeletePhotoContext {
    userId: number | undefined;
    isAdmin: boolean;
    isPackageLocked: boolean;
    isFeedbackReworkActive?: boolean;
    isManager?: boolean;
    isPhotoTargeted?: boolean;
}

/**
 * Mirror of PhotoPolicy::delete for the category delete affordance: own
 * uploads in unlocked packages, pending or self-approved (a manager's own
 * auto-approved photo carries no one else's review, so a wrong image stays
 * removable). Manager-approved technician photos stay protected.
 */
function canDeletePackagePhoto(photo: PhotoData, context: CanDeletePhotoContext): boolean {
    const {
        userId,
        isAdmin,
        isPackageLocked,
        isFeedbackReworkActive = false,
        isManager = false,
        isPhotoTargeted = true,
    } = context;

    if (isPackageLocked && !isPhotoTargeted) {
        return false;
    }
    if (isAdmin || (isFeedbackReworkActive && isPhotoTargeted) || isManager) {
        return true;
    }
    if (photo.uploaded_by !== userId && photo.uploaded_by != null) {
        return false;
    }
    const status = photo.status ?? 'pending';
    if (status === 'pending') {
        return true;
    }
    return status === 'approved' && photo.approved_by != null && photo.approved_by === userId;
}

function resolveFreePosition(
    photos: PhotoData[],
    pictureCount: number,
    explicitPosition?: number
): number | null {
    if (explicitPosition) {
        return explicitPosition;
    }
    if (photos.length >= pictureCount) {
        return null;
    }
    let pos = 1;
    while (pos < pictureCount && photos.some((photo) => photo.position === pos)) {
        pos += 1;
    }
    return pos;
}

const FOCUS_PULSE_CLASSES = ['ring-4', 'ring-primary', 'shadow-2xl', 'transition-all'] as const;

function highlightElement(el: Element, durationMs = 3500): void {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add(...FOCUS_PULSE_CLASSES);
    setTimeout(() => el.classList.remove(...FOCUS_PULSE_CLASSES), durationMs);
}

/** Try to scroll a photo element into view. Returns true when found. */
function scrollToPhotoEl(photoId: number): boolean {
    const el = document.querySelector(`[data-photo-id="${photoId}"]`);
    if (!el) return false;
    highlightElement(el);
    return true;
}

/** Try to scroll a category/section element into view. Returns true when found. */
function scrollToCategoryEl(targetId: number): boolean {
    const el = document.querySelector(
        `[data-category-id="${targetId}"], [data-selection-id="${targetId}"]`
    );
    if (!el) return false;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightElement(el);
    return true;
}

type PkgStageEntry = {
    id?: number;
    stage?: string;
    category_selections?: Array<{ id?: number; template_picture_category_id?: number }>;
};

/** Resolve the stage key that contains a given category/selection id. */
function findCategoryStage(stages: PkgStageEntry[], targetId: number): StageKey | undefined {
    for (const stage of stages) {
        const has = (stage.category_selections ?? []).some(
            (cs) => cs.id === targetId || cs.template_picture_category_id === targetId
        );
        if (has && stage.stage) return stage.stage as StageKey;
    }
    return undefined;
}

type TemplateSectionEntry = {
    stage?: string;
    categories?: Array<{ id?: number }>;
};

/** Fallback: resolve stage from template sections when category lookup failed. */
function findStageFromTemplateSections(
    sections: TemplateSectionEntry[],
    targetId: number
): StageKey | undefined {
    for (const section of sections) {
        const has = (section.categories ?? []).some((c) => c.id === targetId);
        if (has && section.stage) return section.stage as StageKey;
    }
    return undefined;
}

export function PackageShow({
    package: pkg,
    favorites,
    drift,
    can_lock,
    can_unlock,
    can_update_site_notes,
    has_pending_feedback,
    feedbackRound: rawFeedbackRound = null,
    feedback_round: rawFeedbackRoundSnake = null,
    feedbackRoundHistory: rawFeedbackRoundHistory = [],
    feedback_round_history: rawFeedbackRoundHistorySnake = [],
    technicians = [],
    primary_technician: primaryTechnician,
}: Readonly<PackageShowProps>) {
    const { auth, flash } = usePage<PageProps>().props;
    const isAdmin = auth.user.roles.includes('admin');
    const isManager = isAdmin || auth.user.roles.includes('manager');
    const isFieldTech = auth.user.roles.includes('field_supervisor');
    const canSubmitWorkflow = canSubmitPackageWorkflow(auth.user);
    const projectId = extractProjectId(pkg);

    const effectiveFeedbackRound = rawFeedbackRound ?? rawFeedbackRoundSnake ?? null;
    const effectiveFeedbackRoundHistory =
        rawFeedbackRoundHistory.length > 0 ? rawFeedbackRoundHistory : rawFeedbackRoundHistorySnake;

    const feedback = useEntityFeedback({
        entityType: 'closeout_package',
        entityId: Number(pkg.id),
        round: effectiveFeedbackRound,
        isManager,
        isTechnician: isFieldTech,
    });

    useGeolocation(projectId, { enabled: isFieldTech });

    /**
     * One debounced partial reload shared by every batch upload completion.
     * Safe to fire during an axios upload batch — unlike an Inertia visit
     * started by doUpload, a reload can never cancel an axios request.
     */
    const batchReloadTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const scheduleBatchReload = useCallback(() => {
        if (batchReloadTimer.current !== null) {
            clearTimeout(batchReloadTimer.current);
        }
        batchReloadTimer.current = setTimeout(() => {
            batchReloadTimer.current = null;
            router.reload({ only: ['package'] });
        }, BATCH_RELOAD_DEBOUNCE_MS);
    }, []);
    useEffect(
        () => () => {
            if (batchReloadTimer.current !== null) {
                clearTimeout(batchReloadTimer.current);
            }
        },
        []
    );

    // Surface server-side rejections (e.g. a template-drift conflict) that the
    // action handlers swallow when they bail out on a flash error.
    useEffect(() => {
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.error]);

    const currentStage = (pkg.currentStage || pkg.current_stage || 'pre_construction') as StageKey;
    const isLocked = Boolean(pkg.status) && pkg.status !== 'draft';
    const currentUserId = auth.user.id;
    const isAssignedToCurrentTech =
        isFieldTech &&
        (effectiveFeedbackRound?.assigned_to_user_id === currentUserId ||
            (effectiveFeedbackRound?.assigned_to_user_id == null &&
                effectiveFeedbackRound?.assigned_at != null));
    const isFeedbackReworkActive = Boolean(
        effectiveFeedbackRound?.status === 'submitted' && (isManager || isAssignedToCurrentTech)
    );
    const isEffectivelyLocked = isLocked && !isFeedbackReworkActive;

    const handleJumpToPhoto = useCallback(
        (targetId: number, targetType?: string | null) => {
            const isSectionOrCat = targetType === 'category' || targetType === 'section';
            const photo = isSectionOrCat
                ? undefined
                : (pkg.photos ?? []).find((p) => p.id === targetId);

            if (photo) {
                let targetStage = (pkg.stages ?? []).find((s) => s.id === photo.package_stage_id);
                targetStage ??= (pkg.stages ?? []).find((s) =>
                    (s.category_selections ?? []).some(
                        (cs) =>
                            cs.id === photo.package_category_selection_id ||
                            cs.template_picture_category_id ===
                                photo.category_selection?.template_picture_category_id
                    )
                );
                if (targetStage?.stage) {
                    setActiveView(targetStage.stage as StageKey);
                }
                setTimeout(() => {
                    if (!scrollToPhotoEl(targetId)) {
                        setTimeout(() => scrollToPhotoEl(targetId), 300);
                    }
                }, 150);
                return;
            }

            // Category / section jump: resolve which stage tab to activate.
            const stageKey =
                findCategoryStage(pkg.stages ?? [], targetId) ??
                // Template sections carry no id of their own, so a section-level
                // note can only be resolved through the category it names.
                findStageFromTemplateSections(pkg.template?.sections ?? [], targetId);

            if (stageKey) {
                setActiveView(stageKey);
            }

            setTimeout(() => {
                if (!scrollToCategoryEl(targetId)) {
                    setTimeout(() => scrollToCategoryEl(targetId), 300);
                }
            }, 150);
        },
        [pkg.photos, pkg.stages, pkg.template?.sections]
    );

    // Seed from ?tab= (written below on every change) so a hard refresh reopens
    // the same step; unknown or missing values fall back to the current stage.
    const [activeView, setActiveView] = useState<ActiveView>(() => {
        const tab = new URLSearchParams(window.location.search).get('tab');
        const match = (Object.entries(STAGE_URL_SLUGS) as Array<[ActiveView, string]>).find(
            ([, slug]) => slug === tab
        );
        return match ? match[0] : currentStage;
    });
    const isChecklistView = activeView === 'checklist';
    const activeStage: StageKey = isChecklistView ? currentStage : activeView;

    // Keep ?tab= in sync on every view change — one place, covers every
    // setActiveView call site (tabs, checklist, submit-flow auto-advance).
    // replaceState only rewrites the address bar; no server round-trip.
    useEffect(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', STAGE_URL_SLUGS[activeView]);
        window.history.replaceState(null, '', url);
    }, [activeView]);

    const categories = useMemo(
        () => pkg.template?.sections?.find((s) => s.stage === activeStage)?.categories || [],
        [pkg.template?.sections, activeStage]
    );
    const stageSelections = useMemo(
        () => pkg.stages?.find((s) => s.stage === activeStage)?.category_selections || [],
        [pkg.stages, activeStage]
    );

    const feedbackTargetPhotoIds = useMemo(
        () =>
            isFeedbackReworkActive
                ? extractFeedbackTargetPhotoIds(effectiveFeedbackRound?.notes)
                : new Set<number>(),
        [isFeedbackReworkActive, effectiveFeedbackRound?.notes]
    );

    const feedbackTargetCategoryIds = useMemo(
        () =>
            isFeedbackReworkActive
                ? extractFeedbackTargetCategoryIds({
                      notes: effectiveFeedbackRound?.notes,
                      photos: pkg.photos,
                      stages: pkg.stages,
                      templateSections: pkg.template?.sections,
                  })
                : new Set<number>(),
        [
            isFeedbackReworkActive,
            effectiveFeedbackRound?.notes,
            pkg.photos,
            pkg.stages,
            pkg.template?.sections,
        ]
    );

    const [reworkTargetedCategoryIds, setReworkTargetedCategoryIds] = useState<Set<number>>(
        new Set()
    );

    /**
     * Sections touched by an issue the customer withdrew, and sections touched by
     * one they did not. The sticky set below keeps a section unlocked after staff
     * answer it; a withdrawn issue gets nothing kept, because the customer took
     * the change off the table and the section's highlight and photo tools have to
     * go with it. A section with a live issue alongside a withdrawn one stays lit.
     */
    const withdrawnTargetCategoryIds = useMemo(
        () =>
            extractFeedbackTargetCategoryIds({
                notes: (effectiveFeedbackRound?.notes ?? []).filter((n) => n.is_withdrawn),
                // Withdrawing stamps the whole conversation, so an answered-then-withdrawn
                // issue is a thread of resolved turns only - count them or the section,
                // already answered, never goes dark.
                includeResolvedNotes: true,
                photos: pkg.photos,
                stages: pkg.stages,
                templateSections: pkg.template?.sections,
            }),
        [effectiveFeedbackRound?.notes, pkg.photos, pkg.stages, pkg.template?.sections]
    );

    const liveTargetCategoryIds = useMemo(
        () =>
            extractFeedbackTargetCategoryIds({
                notes: (effectiveFeedbackRound?.notes ?? []).filter((n) => !n.is_withdrawn),
                includeResolvedNotes: true,
                photos: pkg.photos,
                stages: pkg.stages,
                templateSections: pkg.template?.sections,
            }),
        [effectiveFeedbackRound?.notes, pkg.photos, pkg.stages, pkg.template?.sections]
    );

    useEffect(() => {
        setReworkTargetedCategoryIds((prev) => {
            const next = new Set(prev);
            for (const id of feedbackTargetCategoryIds) {
                next.add(id);
            }
            for (const id of withdrawnTargetCategoryIds) {
                if (!liveTargetCategoryIds.has(id)) {
                    next.delete(id);
                }
            }
            return next;
        });
    }, [feedbackTargetCategoryIds, withdrawnTargetCategoryIds, liveTargetCategoryIds]);

    const itemNamesByKey = useMemo(
        () =>
            buildItemNamesByKey({
                templateSections: pkg.template?.sections,
                stages: pkg.stages,
                photos: pkg.photos,
            }),
        [pkg.template?.sections, pkg.stages, pkg.photos]
    );

    // Conflicts for the stage currently on screen, keyed by template category id.
    const stageDriftByCategory = useMemo(() => {
        const map = new Map<number, DriftConflict>();
        const stageId = pkg.stages?.find((s) => s.stage === activeStage)?.id;
        if (stageId === undefined) {
            return map;
        }
        for (const conflict of drift?.conflicts || []) {
            if (conflict.stageId === stageId) {
                map.set(conflict.categoryId, conflict);
            }
        }
        return map;
    }, [drift?.conflicts, pkg.stages, activeStage]);

    const [categoryState, setCategoryState] = useState<Record<number, CategoryState>>(() =>
        buildInitialCategoryState(categories, stageSelections)
    );
    const [saving, setSaving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [resubmitting, setResubmitting] = useState(false);
    const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);

    const checklistResponses = useMemo(
        () => pkg.checklist_responses || [],
        [pkg.checklist_responses]
    );
    const {
        checklistState,
        checklistSaving,
        handleChecklistAnswer,
        handleChecklistExplain,
        handleChecklistQuantity,
        flushAutoSave,
        buildChecklistPayload,
    } = useChecklistAutoSave({
        packageId: pkg.hash_id || pkg.id,
        checklistResponses,
        isLocked,
    });
    const [cameraOpen, setCameraOpen] = useState(false);
    const [cameraCategoryId, setCameraCategoryId] = useState<number | null>(null);
    const [pendingUpload, setPendingUpload] = useState<{
        categoryId: number;
        files: File[];
    } | null>(null);
    const [gpsDialogOpen, setGpsDialogOpen] = useState(false);
    const { gpsNotice, setGpsNotice, gpsPermissionState, setGpsPermissionState } =
        useGpsPermissionStatus();
    const [deletePhotoTarget, setDeletePhotoTarget] = useState<PhotoData | null>(null);
    const [deletingPhoto, setDeletingPhoto] = useState(false);

    const stageKey = `${pkg.id}:${activeStage}`;
    const lastStageKeyRef = useRef(stageKey);
    useEffect(() => {
        if (lastStageKeyRef.current === stageKey) {
            // Same stage, but categories or stageSelections updated (e.g. from refresh button or template drift sync)
            setCategoryState((prev) => {
                const initial = buildInitialCategoryState(categories, stageSelections);
                const next: Record<number, CategoryState> = {};
                for (const cat of categories) {
                    const existing = prev[cat.id];
                    if (existing) {
                        next[cat.id] = {
                            ...existing,
                            selected: cat.is_required ? true : existing.selected,
                            pictureCount:
                                cat.default_picture_count ??
                                initial[cat.id]?.pictureCount ??
                                existing.pictureCount,
                        };
                    } else {
                        next[cat.id] = initial[cat.id];
                    }
                }
                return next;
            });
        } else {
            lastStageKeyRef.current = stageKey;
            setCategoryState((prev) => ({
                ...buildInitialCategoryState(categories, stageSelections),
                ...prev,
            }));
            setLastSavedAt(null);
        }
    }, [stageKey, activeStage, categories, stageSelections]);

    const [pulsePhotoId, setPulsePhotoId] = useState<number | null>(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [favoritesOpen, setFavoritesOpen] = useState(false);
    useEffect(() => {
        const attentionPhoto = (pkg.photos || []).find(
            (p) => ATTENTION_STATUSES.has(p.status || '') && !p.replaced_at
        );
        if (!attentionPhoto) {
            return;
        }
        const stage = (pkg.stages || []).find((s) => s.id === attentionPhoto.package_stage_id);
        if (!stage) {
            return;
        }
        setActiveView(stage.stage as StageKey);
        setPulsePhotoId(attentionPhoto.id);
        const scrollTimer = setTimeout(() => {
            document
                .querySelector(`[data-photo-id="${attentionPhoto.id}"]`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
        const pulseTimer = setTimeout(() => setPulsePhotoId(null), 5000);
        return () => {
            clearTimeout(scrollTimer);
            clearTimeout(pulseTimer);
        };
    }, [pkg.id, pkg.photos, pkg.stages]);

    const getPhotos = useCallback(
        (categoryId: number): PhotoData[] =>
            (pkg.photos || []).filter((p) => {
                const catId =
                    p.category_selection?.template_picture_category_id ??
                    p.package_category_selection_id;
                return catId === categoryId && !p.replaced_at;
            }),
        [pkg.photos]
    );

    const getSelectionId = useCallback(
        (categoryId: number): number | undefined => {
            const selection = stageSelections.find(
                (sel) => sel.template_picture_category_id === categoryId
            );
            return selection?.id;
        },
        [stageSelections]
    );

    const stageHasAttention = (stageKey: StageKey): number => {
        const stage = pkg.stages?.find((s) => s.stage === stageKey);
        if (!stage?.id) {
            return 0;
        }
        return (pkg.photos || []).filter(
            (p) =>
                p.package_stage_id === stage.id &&
                ATTENTION_STATUSES.has(p.status || '') &&
                !p.replaced_at
        ).length;
    };

    const handleReplacePhoto = async (photo: PhotoData, rawFile: File) => {
        if (!photo.package_category_selection_id || !photo.position) {
            toast.error('Could not replace this photo. Please try again.');
            return;
        }

        const toastId = `replace-${photo.id}`;
        toast.loading('Compressing photo…', { id: toastId });
        const prepared = await preparePhotoForUpload(rawFile, { maxMb: 20 });
        if (!prepared.ok) {
            toast.error(rejectedPhotoMessage(rawFile, prepared.reason, { maxMb: 20 }), {
                id: toastId,
            });
            return;
        }

        const formData = new FormData();
        formData.append('photo', prepared.file);
        formData.append('photo_id', String(photo.id));

        toast.loading('Replacing photo…', { id: toastId });
        router.post(
            route('photos.replace', { package: pkg.hash_id || pkg.id, photo: photo.id }),
            formData,
            {
                preserveState: true,
                onSuccess: (page) => {
                    const flashError = (page.props as { flash?: { error?: string | null } })?.flash
                        ?.error;
                    if (flashError) {
                        toast.error(flashError, { id: toastId });
                    } else {
                        toast.success('Photo replaced.', { id: toastId });
                    }
                },
                onError: (errors: Record<string, string>) => {
                    const firstError =
                        errors?.photo ||
                        errors?.photo_id ||
                        errors?.message ||
                        errors?.error ||
                        (typeof errors === 'object' && errors !== null
                            ? Object.values(errors)[0]
                            : null);

                    toast.error(
                        firstError || 'Replace failed. Please check the image and try again.',
                        { id: toastId }
                    );
                },
            }
        );
    };

    const handleCategoryChange = (categoryId: number, field: Partial<CategoryState>) => {
        setCategoryState((prev) => ({
            ...prev,
            [categoryId]: { ...prev[categoryId], ...field },
        }));
    };

    const handleScrollToCategory = (stageId: number, categoryId: number) => {
        const stageKey = (pkg.stages || []).find((s) => s.id === stageId)?.stage as
            StageKey | undefined;

        if (stageKey) {
            setActiveView(stageKey);
        }

        // Wait for the stage (and its categories) to render before scrolling.
        setTimeout(() => {
            document
                .querySelector(`[data-category-id="${categoryId}"]`)
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    };

    const handleResubmit = () => {
        setResubmitting(true);
        router.post(
            route('packages.resubmit', { package: pkg.hash_id || pkg.id }),
            {},
            {
                preserveState: true,
                onSuccess: (page) => {
                    const flash = (page.props as unknown as PageProps).flash;
                    if (flash?.error) {
                        return;
                    }
                    toast.success('Package resubmitted for manager review.');
                },
                onError: () => toast.error('Could not resubmit the package. Please try again.'),
                onFinish: () => setResubmitting(false),
            }
        );
    };

    /**
     * Batch uploads post through axios (photoUploadService), NOT Inertia —
     * Inertia v2 serializes visits (maxConcurrent: 1) and is interruptible,
     * so an autosave visit during an upload used to kill the batch silently.
     * Positions are pre-assigned from a snapshot, so concurrent uploads never
     * race for the same slot. Camera and single-file uploads keep using
     * doUpload() below — their UX (loading toast per file) is unchanged.
     */
    const executeBatchUpload = async (categoryId: number, files: File[], fix?: GpsFix) => {
        const selectionId = getSelectionId(categoryId);
        if (!selectionId) {
            toast.error(CATEGORY_NOT_READY_MESSAGE);
            return;
        }

        const currentPhotos = getPhotos(categoryId);
        const occupied = new Set(
            currentPhotos
                .map((p) => p.position)
                .filter((pos): pos is number => typeof pos === 'number')
        );
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;

        const targetPositions: number[] = [];
        for (let pos = 1; pos <= pictureCount && targetPositions.length < files.length; pos++) {
            if (!occupied.has(pos)) {
                targetPositions.push(pos);
            }
        }

        const batchToastId = `batch-upload-${categoryId}`;
        toast.loading(`Uploading 0 / ${files.length} photos…`, {
            id: batchToastId,
            duration: Infinity,
        });

        let done = 0;
        let failed = 0;
        let categoryRemoved = false;

        await runWithConcurrency(files, BATCH_UPLOAD_CONCURRENCY, async (file, i) => {
            const targetPos = targetPositions[i] ?? i + 1;
            try {
                await photoUploadService.upload({
                    packageId: pkg.id,
                    stage: activeStage,
                    file,
                    categorySelectionId: selectionId,
                    position: targetPos,
                    gps: fix
                        ? { latitude: fix.latitude, longitude: fix.longitude, accuracy: 0 }
                        : undefined,
                });
                done += 1;
            } catch (errors) {
                failed += 1;
                const message = getCategoryUploadErrorMessage(
                    errors as Partial<Record<string, string>>
                );
                toast.error(`"${file.name}": ${message}`);
                if (isCategoryRemovedError(message)) {
                    categoryRemoved = true;
                }
            } finally {
                done + failed > 0 &&
                    toast.loading(`Uploading ${done + failed} / ${files.length} photos…`, {
                        id: batchToastId,
                        duration: Infinity,
                    });
            }
        });

        toast.dismiss(batchToastId);

        if (files.length === 1) {
            if (failed === 0) {
                toast.success(fix ? 'Photo uploaded with location.' : 'Photo uploaded.');
            }
            // Single-file failure already showed its own detailed toast.
        } else if (failed === 0) {
            toast.success(`Uploaded all ${done} photos.`);
        } else {
            toast.warning(`Uploaded ${done} of ${files.length} photos — ${failed} failed.`);
        }

        if (categoryRemoved) {
            router.reload({ only: ['package'] });
            return;
        }

        // Partial reloads can never interrupt axios uploads; debounce so 50
        // completions trigger one refresh, flushed immediately at batch end.
        scheduleBatchReload();
    };

    const handleUpload = async (categoryId: number, files: File[]) => {
        const photos = getPhotos(categoryId);
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;
        const remaining = Math.max(0, pictureCount - photos.length);
        const batch = files.slice(0, remaining);
        if (batch.length === 0) {
            toast.info(BUCKET_CAPACITY_FULL_MESSAGE);
            return;
        }
        if (files.length > batch.length) {
            toast.info(
                `Capacity ${pictureCount} — uploading the first ${batch.length} of ${files.length} selected.`
            );
        }

        if (gpsPermissionState === 'prompt') {
            setPendingUpload({ categoryId, files: batch });
            setGpsDialogOpen(true);
            return;
        }

        // Best-effort GPS capture (never blocks upload)
        const result = await requestGps();
        const fix = result.status === 'ok' ? result.fix : (sessionCachedFix ?? undefined);
        await executeBatchUpload(categoryId, batch, fix);
    };

    const proceedUpload = async () => {
        setGpsDialogOpen(false);
        const pending = pendingUpload;
        setPendingUpload(null);
        if (!pending) return;

        const result = await requestGps();
        const fix = result.status === 'ok' ? result.fix : (sessionCachedFix ?? undefined);
        if (result.status === 'ok') {
            setGpsPermissionState('granted');
            setGpsNotice(null);
        } else {
            setGpsPermissionState(result.status === 'denied' ? 'denied' : 'unsupported');
        }

        await executeBatchUpload(pending.categoryId, pending.files, fix);
    };

    const doUpload = async (
        categoryId: number,
        file: File,
        forcedGps?: Pick<GpsFix, 'latitude' | 'longitude'>,
        explicitPosition?: number,
        options: { skipCompression?: boolean } = {}
    ) => {
        const selectionId = getSelectionId(categoryId);
        if (!selectionId) {
            toast.error(CATEGORY_NOT_READY_MESSAGE);
            return;
        }

        const toastId = `upload-${categoryId}-${file.name}`;

        // Camera captures arrive already compressed; file-picker selections are
        // size-checked and compressed here to stay well under POST limits.
        let uploadFile = file;
        if (!options.skipCompression) {
            toast.loading('Compressing photo…', { id: toastId });
            const prepared = await preparePhotoForUpload(file);
            if (!prepared.ok) {
                toast.error(rejectedPhotoMessage(file, prepared.reason), { id: toastId });
                return;
            }
            uploadFile = prepared.file;
        }

        const photos = getPhotos(categoryId);
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;
        const position = resolveFreePosition(photos, pictureCount, explicitPosition);
        if (position === null) {
            toast.error(BUCKET_CAPACITY_FULL_MESSAGE, { id: toastId });
            return;
        }
        toast.loading('Uploading photo…', { id: toastId });

        let fix: GpsFix | null = null;
        if (forcedGps) {
            fix = { ...forcedGps, accuracy: 0 };
        } else {
            const result = await requestGps();
            if (result.status === 'ok') {
                fix = result.fix;
                setGpsNotice(null);
            } else if (sessionCachedFix) {
                fix = sessionCachedFix;
            }
        }

        const formData = new FormData();
        formData.append('photo', uploadFile);
        formData.append('category_selection_id', String(selectionId));
        formData.append('position', String(position));
        if (fix) {
            formData.append('gps_latitude', String(fix.latitude));
            formData.append('gps_longitude', String(fix.longitude));
            formData.append('gps_accuracy', String(fix.accuracy));
        }
        formData.append('captured_at', new Date().toISOString().replace('T', ' ').slice(0, 19));

        return new Promise<void>((resolve) => {
            router.post(route('photos.store', { package: pkg.id, stage: activeStage }), formData, {
                preserveState: true,
                onSuccess: () => {
                    toast.success(fix ? 'Photo uploaded with location.' : 'Photo uploaded.', {
                        id: toastId,
                    });
                    resolve();
                },
                onError: (errors) => {
                    const message = getCategoryUploadErrorMessage(errors);
                    toast.error(message, {
                        id: toastId,
                    });
                    if (isCategoryRemovedError(message)) {
                        router.reload({ only: ['package'] });
                    }
                    resolve();
                },
            });
        });
    };

    const openCamera = (categoryId: number) => {
        const photos = getPhotos(categoryId);
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;
        if (photos.length >= pictureCount) {
            toast.info(BUCKET_CAPACITY_FULL_MESSAGE);
            return;
        }
        setCameraCategoryId(categoryId);
        setCameraOpen(true);
    };

    const [mediaPickerTarget, setMediaPickerTarget] = useState<{
        isOpen: boolean;
        categoryId: number;
        categoryName: string;
        selectionId: number;
        availableSlots: number;
    } | null>(null);

    const handleOpenMediaPicker = (categoryId: number, categoryName: string) => {
        const selectionId = getSelectionId(categoryId);
        if (!selectionId) {
            toast.error(CATEGORY_NOT_READY_MESSAGE);
            return;
        }

        const photos = getPhotos(categoryId);
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;
        const availableSlots = Math.max(0, pictureCount - photos.length);

        if (availableSlots <= 0) {
            toast.warning(BUCKET_CAPACITY_FULL_MESSAGE);
            return;
        }

        setMediaPickerTarget({
            isOpen: true,
            categoryId,
            categoryName,
            selectionId,
            availableSlots,
        });
    };

    const handleMediaAssetDrop = async (
        categoryId: number,
        assetId: number,
        multipleIds?: number[]
    ) => {
        const selectionId = getSelectionId(categoryId);
        const categoryName = categories.find((c) => c.id === categoryId)?.name ?? 'Category';

        if (!selectionId) {
            toast.error(
                `Category "${categoryName}" is not ready for upload yet. Please save your draft first.`
            );
            return;
        }

        const photos = getPhotos(categoryId);
        const pictureCount = categoryState[categoryId]?.pictureCount ?? 1;
        const availableSlots = Math.max(0, pictureCount - photos.length);

        if (availableSlots <= 0) {
            toast.warning(
                `Category "${categoryName}" capacity reached (${pictureCount} photo${pictureCount === 1 ? '' : 's'}).`
            );
            return;
        }

        const idsToAssign =
            multipleIds && multipleIds.length > 0
                ? multipleIds.slice(0, availableSlots)
                : [assetId];

        try {
            await mediaLibraryApi.assignMediaAsset({
                media_asset_ids: idsToAssign,
                category_selection_id: selectionId,
            });
            toast.success(`Added ${idsToAssign.length} photo(s) from library to ${categoryName}.`);
            router.reload({ only: ['package'] });
        } catch (err: unknown) {
            const data = (
                err as {
                    response?: {
                        data?: {
                            message?: string;
                            errors?: Record<string, string[]>;
                        };
                    };
                }
            )?.response?.data;
            const message =
                data?.errors?.category_selection_id?.[0] ??
                data?.message ??
                `Failed to add photo to ${categoryName}.`;
            toast.error(message);

            if (isCategoryRemovedError(message)) {
                router.reload({ only: ['package'] });
            }
        }
    };

    const handleDeletePhoto = () => {
        if (!deletePhotoTarget) return;
        setDeletingPhoto(true);
        router.delete(
            route('photos.destroy', {
                package: pkg.id,
                photo: deletePhotoTarget.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeletePhotoTarget(null);
                    setDeletingPhoto(false);
                    toast.success('Photo removed.');
                },
                onError: () => {
                    setDeletingPhoto(false);
                    toast.error('Could not remove the photo. Please try again.');
                },
            }
        );
    };

    const handleCameraCapture = (photo: CapturedPhoto) => {
        setCameraOpen(false);
        const forcedGps =
            photo.latitude != null && photo.longitude != null
                ? { latitude: photo.latitude, longitude: photo.longitude }
                : undefined;
        if (cameraCategoryId != null) {
            void doUpload(cameraCategoryId, photo.file, forcedGps, undefined, {
                skipCompression: true,
            });
        }
        setCameraCategoryId(null);
    };

    const retryGps = async () => {
        const result = await requestGps(true);
        if (result.status === 'ok') {
            setGpsNotice(null);
            setGpsPermissionState('granted');
            toast.success(
                `Location granted (${result.fix.latitude.toFixed(4)}, ${result.fix.longitude.toFixed(4)}). You can now upload photos.`
            );
        } else {
            setGpsPermissionState(result.status === 'denied' ? 'denied' : 'unsupported');
            toast.error('Location still not available. Check browser settings and try again.');
        }
    };

    const buildDraftPayload = () =>
        categories.map((cat) => {
            const st = categoryState[cat.id] || {
                selected: true,
                heading: cat.name,
                notes: '',
                pictureCount: cat.default_picture_count ?? 1,
            };
            return {
                category_id: cat.id,
                is_selected: st.selected,
                picture_count: st.pictureCount,
                user_heading: st.heading === cat.name ? null : st.heading,
                user_notes: st.notes || null,
            };
        });

    /**
     * Snapshot of the whole page-3 configuration (all three stages), merging
     * the live edits of the current stage with the persisted selections of
     * the other stages.
     */
    const buildFavoriteSnapshot = () => {
        const items: FavoriteSnapshotItem[] = [];

        for (const stage of pkg.stages || []) {
            for (const selection of stage.category_selections || []) {
                const isCurrentStage = stage.stage === currentStage;
                items.push(
                    toFavoriteSnapshotItem(
                        selection,
                        isCurrentStage
                            ? categoryState[selection.template_picture_category_id]
                            : undefined,
                        isCurrentStage
                            ? categories.find(
                                  (cat) => cat.id === selection.template_picture_category_id
                              )
                            : undefined
                    )
                );
            }
        }

        return items;
    };

    const handleSaveDraft = () => {
        if (categories.length === 0) {
            toast.info('No photo categories in this step to save.');
            return;
        }

        setSaving(true);
        router.post(
            route(DRAFT_ROUTE_NAME, { package: pkg.hash_id || pkg.id, stage: activeStage }),
            { category_selections: buildDraftPayload() },
            {
                preserveState: true,
                onSuccess: () => {
                    setLastSavedAt(
                        new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    );
                    toast.success('Draft saved. You can continue later.');
                },
                onError: () => toast.error('Could not save the draft. Please try again.'),
                onFinish: () => setSaving(false),
            }
        );
    };

    const checklistIssues = (): string[] => {
        if (currentStage !== 'post_completion' || checklistResponses.length === 0) {
            return [];
        }
        const issues: string[] = [];
        const unansweredLabels: string[] = [];
        for (const r of checklistResponses) {
            const st = checklistState[r.checklist_item.id];
            const issue = getChecklistResponseIssue(r, st);
            if (issue) {
                issues.push(issue);
            }
            if (!st?.is_na && (st?.answer ?? null) === null) {
                unansweredLabels.push(r.checklist_item.label);
            }
        }
        if (unansweredLabels.length === 1) {
            issues.push(
                `Checklist item "${unansweredLabels[0]}" is unanswered. Please answer it before submitting.`
            );
        } else if (unansweredLabels.length > 1) {
            issues.push(
                `Please answer all checklist items before submitting the package (${unansweredLabels.length} unanswered).`
            );
        }
        return issues;
    };

    const submitIssues = (): string[] => {
        const issues: string[] = [];
        for (const cat of categories) {
            if (!cat.is_required) continue;
            const st = categoryState[cat.id];
            const count = getPhotos(cat.id).length;
            // A required category must be included with at least one photo.
            if (!st?.selected || count < MIN_PHOTOS_PER_REQUIRED_CATEGORY) {
                issues.push(
                    `${cat.name}: Required - at least ${MIN_PHOTOS_PER_REQUIRED_CATEGORY} photo needed (capacity ${st?.pictureCount ?? 1}).`
                );
            }
        }

        return issues;
    };

    const handleSubmit = () => {
        if (stageDriftByCategory.size > 0) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            toast.error(
                'Resolve the template changes listed at the top before submitting this stage.'
            );
            return;
        }

        const issues = submitIssues();
        if (issues.length > 0) {
            toast.error(`Please fix these before submitting:\n${issues.join('\n')}`);
            return;
        }

        setSubmitting(true);

        const doSubmit = () => {
            router.post(
                route('packages.stages.submit', {
                    package: pkg.hash_id || pkg.id,
                    stage: activeStage,
                }),
                {},
                {
                    preserveState: true,
                    onSuccess: (page) => {
                        const flash = (page.props as unknown as PageProps).flash;
                        if (flash?.error) {
                            return;
                        }
                        const activeStageIndex = STAGE_ORDER.indexOf(activeStage);
                        const nextStageKey = STAGE_ORDER[activeStageIndex + 1];
                        if (activeStage === 'post_completion' || !nextStageKey) {
                            setActiveView('checklist');
                            toast.success(
                                'Post Completion submitted. Complete the checklist, then submit the package.'
                            );
                        } else {
                            setActiveView(nextStageKey);
                            toast.success(
                                `${STAGE_LABELS[activeStage]} submitted. Next: ${STAGE_LABELS[nextStageKey]}.`
                            );
                        }
                    },
                    onError: () => toast.error('Could not submit. Please try again.'),
                    onFinish: () => setSubmitting(false),
                }
            );
        };

        if (categories.length === 0) {
            doSubmit();
            return;
        }

        router.post(
            route(DRAFT_ROUTE_NAME, { package: pkg.hash_id || pkg.id, stage: activeStage }),
            { category_selections: buildDraftPayload() },
            {
                preserveState: true,
                onSuccess: doSubmit,
                onError: () => {
                    setSubmitting(false);
                    toast.error('Could not save the draft before submitting. Please try again.');
                },
            }
        );
    };

    const handleSubmitPackage = () => {
        if (drift?.hasUnsafeDrift) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            toast.error('Resolve the template changes listed at the top before submitting.');
            return;
        }

        const issues = checklistIssues();
        if (issues.length > 0) {
            toast.error(`Please fix these before submitting:\n${issues.join('\n')}`);
            return;
        }

        setSubmitting(true);

        const doSubmitPackage = () => {
            router.post(
                route('packages.submit', { package: pkg.hash_id || pkg.id }),
                {},
                {
                    preserveState: true,
                    onSuccess: (page) => {
                        const flash = (page.props as unknown as PageProps).flash;
                        if (!flash?.error) {
                            toast.success('Package submitted. The manager will review it now.');
                        }
                    },
                    onError: () => toast.error('Could not submit the package. Please try again.'),
                    onFinish: () => setSubmitting(false),
                }
            );
        };

        const doSaveChecklist = () => {
            if (checklistResponses.length === 0) {
                doSubmitPackage();
                return;
            }

            router.put(
                route('packages.checklist.update', { package: pkg.hash_id || pkg.id }),
                { responses: buildChecklistPayload() },
                {
                    preserveState: true,
                    onSuccess: doSubmitPackage,
                    onError: () => {
                        setSubmitting(false);
                        toast.error('Could not save the checklist. Please try again.');
                    },
                }
            );
        };

        if (categories.length === 0) {
            doSaveChecklist();
            return;
        }

        router.post(
            route(DRAFT_ROUTE_NAME, { package: pkg.hash_id || pkg.id, stage: currentStage }),
            { category_selections: buildDraftPayload() },
            {
                preserveState: true,
                onSuccess: doSaveChecklist,
                onError: () => {
                    setSubmitting(false);
                    toast.error('Could not save the draft before submitting. Please try again.');
                },
            }
        );
    };

    const projectName = pkg.projectName || pkg.project?.name || `Package #${pkg.hash_id || pkg.id}`;
    const stageSubmitted = pkg.stages?.find((s) => s.stage === activeStage)?.status === 'submitted';
    const submitLabel = getSubmitLabel(activeStage, Boolean(stageSubmitted));
    const revision = `R${pkg.revision_major ?? 1}.${pkg.revision_minor ?? 0}`;
    const packageSizeLabel = humanizeBytes(pkg.photos_size_bytes);
    const isApproved = pkg.status === 'approved';
    const lockedLabel = getLockedStatusLabel(pkg.status);
    const isRejected = pkg.status === 'rejected';
    const rejectionReasons = getRejectionReasons(pkg.photos);
    const hasPendingReplacement = (pkg.photos || []).some(
        (p) => ATTENTION_STATUSES.has(p.status || '') && !p.replaced_at
    );
    const canResubmit = isRejected && !hasPendingReplacement;
    const checklistComplete = checklistResponses.every((r) => {
        const st = checklistState[r.checklist_item.id];
        if (st?.is_na) {
            return Boolean(r.checklist_item.allows_na);
        }
        return (st?.answer ?? null) !== null;
    });

    const handleApplyFavorite = (favorite: FavoriteData) => {
        const snapshotById = new Map(favorite.snapshot.map((item) => [item.category_id, item]));
        const applicable = categories.filter((cat) => snapshotById.has(cat.id));

        if (applicable.length === 0) {
            toast.info('This favorite has no matching categories for the current step.');
            return;
        }

        setCategoryState((prev) => {
            const next = { ...prev };
            for (const cat of applicable) {
                const item = snapshotById.get(cat.id);
                if (!item) {
                    continue;
                }
                next[cat.id] = {
                    // Required categories can never be unchecked (their checkbox
                    // is disabled), so a favorite can not uncheck them either.
                    selected: cat.is_required ? true : (item.is_selected ?? true),
                    heading: item.user_heading || cat.name,
                    notes: item.user_notes || cat.description || '',
                    pictureCount: item.picture_count ?? cat.default_picture_count ?? 1,
                };
            }
            return next;
        });

        setFavoritesOpen(false);
        toast.success(
            `Favorite "${favorite.name}" applied to ${applicable.length} categor${
                applicable.length === 1 ? 'y' : 'ies'
            }. Save your draft to keep the changes.`
        );
    };

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('packages.destroy', { package: pkg.hash_id || pkg.id }), {
            onSuccess: () => {
                toast.success('Package deleted.');
                router.visit(
                    route(PACKAGES_INDEX_ROUTE, { project: pkg.project?.hash_id || pkg.project_id })
                );
            },
            onError: () => {
                toast.error('Could not delete the package. Please try again.');
                setDeleting(false);
            },
        });
    };

    const currentActiveSection = pkg.template?.sections?.find((s) => s.stage === activeView);
    const activeAdminNotes = currentActiveSection?.admin_notes;

    const editableCategories =
        categories.length === 0 ? (
            <div className="space-y-2">
                <div className="p-10 border border-dashed rounded-xl text-center text-sm text-muted-foreground bg-card">
                    No photo categories for this step yet.
                </div>
            </div>
        ) : (
            <div className="space-y-2">
                {categories.map((cat, idx) => {
                    const st = categoryState[cat.id] || {
                        selected: true,
                        heading: cat.name,
                        notes: '',
                        pictureCount: cat.default_picture_count ?? 1,
                    };
                    const catPhotos = getPhotos(cat.id);
                    const hasAttentionPhotos = catPhotos.some((p) =>
                        ATTENTION_STATUSES.has(p.status || '')
                    );
                    const hasTargetedPhotos =
                        isFeedbackReworkActive &&
                        catPhotos.some((p) => feedbackTargetPhotoIds.has(p.id));
                    const catSelectionId = getSelectionId(cat.id);
                    const isSectionFeedbackTargeted =
                        isFeedbackReworkActive &&
                        (feedbackTargetCategoryIds.has(cat.id) ||
                            reworkTargetedCategoryIds.has(cat.id) ||
                            (catSelectionId != null &&
                                (feedbackTargetCategoryIds.has(catSelectionId) ||
                                    reworkTargetedCategoryIds.has(catSelectionId))));
                    const isCategoryTargeted =
                        !isLocked ||
                        (isFeedbackReworkActive &&
                            (hasTargetedPhotos || isSectionFeedbackTargeted || hasAttentionPhotos));
                    const isCategoryLocked = isLocked && !isCategoryTargeted;

                    return (
                        <CategoryRow
                            key={cat.id}
                            packageId={pkg.hash_id || pkg.id}
                            selectionId={catSelectionId}
                            isLocked={isCategoryLocked}
                            isPackageLocked={isLocked}
                            isFeedbackReworkActive={isFeedbackReworkActive}
                            isCategoryTargeted={isCategoryTargeted}
                            isSectionFeedbackTargeted={isSectionFeedbackTargeted}
                            category={cat}
                            index={idx + 1}
                            state={st}
                            photos={catPhotos}
                            onStateChange={(field) => handleCategoryChange(cat.id, field)}
                            onUpload={handleUpload}
                            onCamera={openCamera}
                            onMediaPicker={handleOpenMediaPicker}
                            onMediaDrop={handleMediaAssetDrop}
                            onReplace={handleReplacePhoto}
                            onDelete={setDeletePhotoTarget}
                            targetedPhotoIds={
                                isFeedbackReworkActive && feedbackTargetPhotoIds.size > 0
                                    ? feedbackTargetPhotoIds
                                    : undefined
                            }
                            canDeletePhoto={(photo) => {
                                const isPhotoTargeted = computeIsPhotoTargeted(
                                    photo.id,
                                    photo.status,
                                    isLocked,
                                    isFeedbackReworkActive,
                                    feedbackTargetPhotoIds,
                                    isCategoryTargeted
                                );
                                return canDeletePackagePhoto(photo, {
                                    userId: auth.user.id,
                                    isAdmin,
                                    isPackageLocked: isLocked,
                                    isFeedbackReworkActive,
                                    isManager,
                                    isPhotoTargeted,
                                });
                            }}
                            conflict={stageDriftByCategory.get(cat.id)}
                        />
                    );
                })}
            </div>
        );

    return (
        <AppLayout
            breadcrumb={[
                { label: 'Projects', href: route('projects.index') },
                {
                    label: projectName,
                    href: route('projects.show', pkg.project?.hash_id || pkg.project?.id || pkg.id),
                },
                { label: 'Close-Out Package' },
            ]}
        >
            <Head title={`COP Builder - ${projectName}`} />

            <div className="space-y-3 pb-8">
                {/* Template drift — blocks submission until resolved */}
                <DriftWarningPanel
                    conflicts={drift?.conflicts || []}
                    onScrollToCategory={handleScrollToCategory}
                />

                {/* Back button */}
                <Link
                    href={route(PACKAGES_INDEX_ROUTE, {
                        project: pkg.project?.hash_id || pkg.project_id,
                    })}
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft className="size-4" />
                    Back to Close-Out Packages
                </Link>

                {/* GPS permission notice */}
                {gpsNotice && (
                    <GpsNoticeBanner denied={gpsNotice === 'denied'} onRetry={retryGps} />
                )}

                {/* Rejected banner */}
                {isRejected && (
                    <div className="rounded-xl border border-amber-500/80 bg-amber-500/10 p-4 flex items-start gap-3">
                        <AlertTriangle className="size-5 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-bold text-amber-700 dark:text-amber-400">
                                This package was rejected.
                            </p>
                            {rejectionReasons.length > 0 ? (
                                <ul className="mt-1 space-y-0.5 text-xs text-amber-700/90 dark:text-amber-400/90">
                                    {rejectionReasons.map((reason) => (
                                        <li key={reason}>• Reason: {reason}</li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-1 text-xs text-amber-700/90 dark:text-amber-400/90">
                                    Photos marked for replacement need your attention.
                                </p>
                            )}
                            <p className="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400">
                                {canResubmit
                                    ? 'All replacements uploaded. Resubmit the package for manager review.'
                                    : 'Action required: replace the highlighted photos and resubmit the package for review.'}
                            </p>
                        </div>
                    </div>
                )}

                {/* Locked banner — visible only to the locker and admins, who
                    are the only users allowed to reach this page. */}
                {pkg.locked && (
                    <PackageLockNotice locked_by={pkg.locked_by} locked_at={pkg.locked_at} />
                )}

                {/* Header */}
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                            <h1 className="text-xl sm:text-2xl font-bold tracking-tight">
                                {projectName}
                            </h1>
                            <StatusBadge status={pkg.status || 'draft'} />
                            {pkg.locked && <LockedBadge />}
                            <span className="text-xs text-muted-foreground font-mono font-medium">
                                Rev {revision}
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                            {isApproved && !pkg.final_pdf_path && (
                                <span
                                    className="inline-flex items-center gap-1 rounded-md bg-blue-500/10 border border-blue-500/30 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 dark:text-blue-300"
                                    title="This package has been approved, but the Project Manager has not yet compiled the final PDF report. Please wait or contact your project manager."
                                >
                                    <FileText className="size-3" /> Final PDF Pending
                                </span>
                            )}
                            {lastSavedAt && (
                                <span className="text-emerald-600 dark:text-emerald-400 font-medium">
                                    ✓ Saved {lastSavedAt}
                                </span>
                            )}
                            {packageSizeLabel && (
                                <span
                                    className="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground"
                                    title="Total stored size of all current photos and site-note images in this package"
                                >
                                    <Images className="size-3.5" /> {packageSizeLabel}
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 w-full lg:w-auto">
                        {/* Primary: report delivery (PDF generation, viewing, email) */}
                        <div className="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                            {isApproved && Boolean(pkg.final_pdf_path) && (
                                <PackagePdfActions
                                    packageId={pkg.hash_id || pkg.id}
                                    numericPackageId={pkg.id}
                                    status={pkg.status}
                                    finalPdfAvailable={Boolean(pkg.final_pdf_path)}
                                    canManage={false}
                                />
                            )}
                        </div>

                        {/* Secondary: package context & workflow */}
                        <div className="flex flex-wrap items-center gap-2 justify-start lg:justify-end">
                            {activeView === 'pre_construction' && (
                                <SiteAccessTrigger
                                    packageId={pkg.hash_id || pkg.id}
                                    scopeOfWork={pkg.site_info?.scope_of_work}
                                    siteAccessNotes={pkg.site_info?.site_access_notes}
                                    canEdit={!isLocked}
                                />
                            )}
                            <RefreshButton
                                label="Refresh"
                                className="h-9 px-2.5 sm:px-3 text-xs shrink-0"
                                title="Refresh the package data to see the latest review decisions."
                            />
                            {(can_lock || can_unlock) && (
                                <PackageLockButton
                                    packageId={pkg.hash_id || pkg.id}
                                    locked={Boolean(pkg.locked)}
                                    canLock={Boolean(can_lock)}
                                    canUnlock={Boolean(can_unlock)}
                                />
                            )}
                            <PackageHeaderActionGroup
                                isRejected={isRejected}
                                canResubmit={canResubmit}
                                canSubmitWorkflow={canSubmitWorkflow}
                                resubmitting={resubmitting}
                                handleResubmit={handleResubmit}
                                isLocked={isLocked}
                                lockedLabel={lockedLabel}
                                isChecklistView={isChecklistView}
                                isAdmin={isAdmin}
                                setShowDeleteConfirm={setShowDeleteConfirm}
                                submitting={submitting}
                                handleSubmitPackage={handleSubmitPackage}
                                setFavoritesOpen={setFavoritesOpen}
                                handleSaveDraft={handleSaveDraft}
                                saving={saving}
                                handleSubmit={handleSubmit}
                                submitLabel={submitLabel}
                            />
                        </div>
                    </div>
                </div>

                {/* Stage tabs */}
                <AdminNotesCenter notes={activeAdminNotes} primaryTechnician={primaryTechnician}>
                    <div className="p-2 sm:p-3 rounded-xl border bg-card shadow-2xs">
                        <div className="grid grid-cols-4 gap-1.5 sm:gap-2.5 w-full py-1">
                            {STAGE_ORDER.map((stage, idx) => {
                                const stageStatus = pkg.stages?.find(
                                    (s) => s.stage === stage
                                )?.status;
                                const completed = stageStatus === 'submitted';
                                return (
                                    <StageTab
                                        key={stage}
                                        stage={stage}
                                        index={idx}
                                        completed={completed}
                                        active={stage === activeView}
                                        attentionCount={stageHasAttention(stage)}
                                        onClick={() => setActiveView(stage)}
                                    />
                                );
                            })}
                            <ChecklistTab
                                active={activeView === 'checklist'}
                                complete={checklistComplete}
                                onClick={() => setActiveView('checklist')}
                            />
                        </div>
                        <StepHint
                            activeView={activeView}
                            isLocked={isLocked}
                            isChecklistView={isChecklistView}
                        />
                    </div>
                </AdminNotesCenter>

                {/* Content */}
                {(() => {
                    if (isChecklistView) {
                        return (
                            <ChecklistView
                                packageId={pkg.hash_id || pkg.id}
                                projectId={projectId}
                                responses={checklistResponses}
                                state={checklistState}
                                onAnswer={handleChecklistAnswer}
                                onExplain={handleChecklistExplain}
                                onQuantity={handleChecklistQuantity}
                                onFlush={flushAutoSave}
                                saving={checklistSaving}
                                readOnly={isLocked}
                                packageNotes={pkg.notes ?? ''}
                                packageNoteImages={pkg.site_note_images ?? []}
                                canEditSiteNotes={Boolean(can_update_site_notes)}
                            />
                        );
                    }
                    if (!isEffectivelyLocked) {
                        return editableCategories;
                    }
                    return (
                        <ReadOnlyCategories
                            packageId={pkg.hash_id || pkg.id}
                            stage={activeView}
                            sections={pkg.template?.sections}
                            stages={pkg.stages}
                            photos={pkg.photos}
                            onReplace={handleReplacePhoto}
                            pulsePhotoId={pulsePhotoId}
                        />
                    );
                })()}
            </div>

            <FavoritesDialog
                open={favoritesOpen}
                onOpenChange={setFavoritesOpen}
                favorites={favorites || []}
                templateId={pkg.template?.id}
                canSave={!isLocked}
                snapshot={buildFavoriteSnapshot()}
                onApply={handleApplyFavorite}
            />

            <ConfirmDialog
                open={showDeleteConfirm}
                onOpenChange={(open) => {
                    if (!open && !deleting) setShowDeleteConfirm(false);
                }}
                title="Delete package"
                description={`Are you sure you want to delete this close-out package for "${projectName}"? You can restore it from the admin Deleted Packages page.`}
                confirmLabel="Delete"
                variant="destructive"
                loading={deleting}
                onConfirm={handleDelete}
            />

            <ConfirmDialog
                open={Boolean(deletePhotoTarget)}
                onOpenChange={(open) => {
                    if (!open && !deletingPhoto) setDeletePhotoTarget(null);
                }}
                title="Remove this photo?"
                description="This photo will be permanently deleted from the package. You can upload a replacement afterwards."
                confirmLabel="Remove Photo"
                variant="destructive"
                loading={deletingPhoto}
                onConfirm={handleDeletePhoto}
            />

            <ConfirmDialog
                open={gpsDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setGpsDialogOpen(false);
                        setPendingUpload(null);
                    }
                }}
                title="Enable site location for photo verification"
                description="Verizon requires GPS coordinates with every cell-site photo to verify on-site work. When prompted by your browser, tap Allow. You only need to grant permission once — subsequent uploads will record location automatically."
                confirmLabel="Allow location & upload"
                variant="success"
                onConfirm={proceedUpload}
            />

            <InAppCamera
                open={cameraOpen}
                onOpenChange={setCameraOpen}
                onCapture={handleCameraCapture}
                categoryTitle="Site Photo"
            />

            {(isManager || isAssignedToCurrentTech) && (
                <FeedbackSectionDivider highlight={has_pending_feedback} />
            )}

            {(isManager || isAssignedToCurrentTech) && (
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
                            sourcePhotoType: photoType ?? 'section',
                            replacementImageFile: replacementFile,
                            replaceInChangeOrder: options?.replaceInChangeOrder,
                            addToChangeOrderPhotos: options?.addToChangeOrderPhotos,
                        });
                    }}
                    canReply={feedback.canReply}
                    onJumpToPhoto={handleJumpToPhoto}
                    itemNamesByKey={itemNamesByKey}
                />
            )}

            {mediaPickerTarget?.isOpen && (
                <MediaPickerModal
                    isOpen={mediaPickerTarget.isOpen}
                    onClose={() => setMediaPickerTarget(null)}
                    projectId={pkg.project_id}
                    projectName={projectName}
                    categoryName={mediaPickerTarget.categoryName}
                    categorySelectionId={mediaPickerTarget.selectionId}
                    availableSlots={mediaPickerTarget.availableSlots}
                    onAssigned={() => {
                        router.reload({ only: ['package'] });
                    }}
                />
            )}
        </AppLayout>
    );
}

export default PackageShow;
