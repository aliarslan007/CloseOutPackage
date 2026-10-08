import { PageProps } from '@/types/inertia';

import type {
    EntityFeedbackNoteItem,
    EntityFeedbackRoundItem,
} from '@/features/entity-feedback/types/entity-feedback.types';
import type { ProjectDocumentsMap } from '@/features/project-documents/types';

export interface CustomerPhotoData {
    id: number;
    category?: string;
    photo_url?: string;
    url?: string;
    thumb_url?: string;
    original_url?: string;
    notes?: string | null;
    is_approved?: boolean | null;
    status?: string | null;
    flag_reason?: string | null;
    flagged_at?: string | null;
    created_at?: string;
    captured_at?: string;
    gps_latitude?: number | null;
    gps_longitude?: number | null;
    uploader?: { id: number; name: string } | null;
    package_category_selection_id?: number | null;
    replaced_photo?: { id: number } | null;
    replaced_by_photo_id?: number | null;
    stage?: { stage?: string; heading?: string } | null;
    category_selection?: {
        id?: number;
        template_picture_category_id?: number | null;
        user_heading?: string | null;
        user_notes?: string | null;
        template_picture_category?: {
            id?: number;
            name?: string;
            stage?: string;
            section?: {
                heading?: string;
                stage?: string;
            } | null;
        } | null;
    } | null;
}

/** One feedback note type for the whole app, owned by the feedback engine. */
export type FeedbackNoteItem = EntityFeedbackNoteItem;

export interface CustomerPackageItem {
    id: number;
    hash_id?: string;
    status: string;
    revision_major?: number;
    revision_minor?: number;
    updated_at?: string;
    approved_at?: string | null;
    /** True when the package has been locked by an admin or the project manager. */
    locked?: boolean;
    /** Who locked the package and when — shown so customers understand the lock. */
    locked_by?: { id: number; name: string } | null;
    locked_at?: string | null;
    /** Backend-computed via CloseoutPackagePolicy::view — the source of truth. */
    can_open?: boolean;
    notes?: string | null;
    site_info?: { notes?: string | null } | null;
    siteInfo?: { notes?: string | null } | null;
    checklist_responses?: Array<{
        id: number;
        answer?: boolean | string | number | null;
        explain?: string | null;
        is_na?: boolean;
        checklistItem?: {
            id: number;
            label: string;
            has_explain_field?: boolean;
            allows_na?: boolean;
        } | null;
    }>;
    checklistResponses?: Array<{
        id: number;
        answer?: boolean | string | number | null;
        explain?: string | null;
        is_na?: boolean;
        checklistItem?: {
            id: number;
            label: string;
            has_explain_field?: boolean;
            allows_na?: boolean;
        } | null;
    }>;
    template?: { id: number; name: string } | null;
    creator?: { id: number; name: string; profile_photo_url?: string | null } | null;
    latest_final_revision?: { id: number; storage_path?: string | null; generated_at?: string } | null;
    latest_partial_revision?: { id: number; storage_path?: string | null; generated_at?: string } | null;
    /** The package's active feedback round, from the single feedback engine. */
    feedback_round?: EntityFeedbackRoundItem | null;
    feedbackRound?: EntityFeedbackRoundItem | null;
    feedback_round_history?: EntityFeedbackRoundItem[];
    feedbackRoundHistory?: EntityFeedbackRoundItem[];
    photos?: CustomerPhotoData[];
    /** Package-level site-note images, read-only in the checklist tab. */
    site_note_images?: CustomerSiteNoteImage[];
}

export interface CustomerSiteNoteImage {
    id: number;
    file_name?: string;
    url: string;
    thumb_url?: string;
}

export interface CustomerProjectDetails {
    id: number;
    hash_id?: string;
    name: string;
    ps_number?: string | null;
    project_number?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    vzw_engineer_name?: string | null;
    notes?: string | null;
    status: string;
    market?: { id: number; name: string } | null;
    manager?: { id: number; name: string; profile_photo_url?: string | null } | null;
    field_supervisors?: Array<{ id: number; name: string }>;
    approved_packages?: CustomerPackageItem[];
    packages?: CustomerPackageItem[];
    progress_percentage?: number | null;
}

export type CustomerProjectShowProps = PageProps<{
    project: CustomerProjectDetails;
    projectDocuments?: ProjectDocumentsMap;
}>;

export interface StageGroup {
    key: 'pre_construction' | 'construction' | 'post_completion' | 'other';
    heading: string;
    badgeColor: string;
    categories: Map<string, CustomerPhotoData[]>;
}
