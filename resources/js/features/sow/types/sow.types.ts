import type { MediaAsset } from '@/features/media-library/types/media-library.types';
import type { ApiAnnotationLayer } from '../utils/annotation-serializer';

export type SowStatus = 'draft' | 'in_review' | 'published' | 'archived';
export type SowPdfGenerationStatus = 'idle' | 'generating' | 'ready' | 'failed';
export type CompositeStatus = 'not_needed' | 'pending' | 'processing' | 'ready' | 'failed';

/** A named photo grouping; its name becomes a section heading in the PDF. */
export interface SowImageGroup {
    id: number;
    sow_report_id: number;
    name: string;
    sort_order: number;
}

export interface SowAnnotatedImage {
    id: number;
    sow_report_id: number;
    media_asset_id: number;
    /** null = ungrouped. */
    sow_image_group_id: number | null;
    annotation_layer: ApiAnnotationLayer;
    composite_path: string | null;
    composite_status: CompositeStatus;
    sort_order: number;
    page_title: string | null;
    is_cover: boolean;
    include_in_pdf: boolean;
    annotations_updated_at: string | null;
    created_at: string;
    updated_at: string;
    media_asset?: MediaAsset | null;
    /** Server-computed remove rule (ownership + report status). Set on Show. */
    can_delete?: boolean;
}

export interface SowReport {
    id: number;
    project_id: number | null;
    market_id: number;
    created_by: number;
    title: string;
    site_name: string | null;
    report_date: string | null;
    fiber_provider: string;
    cover_media_asset_id: number | null;
    site_address: string | null;
    site_city: string | null;
    site_county: string | null;
    latitude: string | null; // decimal(10,7) — serialized as string
    longitude: string | null;
    project_description: string | null;
    field_survey_date: string | null;
    provider_contact_name: string | null;
    provider_contact_phone: string | null;
    provider_contact_email: string | null;
    demarcation: string | null;
    ps_number: string | null;
    survey_contractor_name: string | null;
    survey_contact_name: string | null;
    survey_contact_phone: string | null;
    survey_contact_email: string | null;
    scope_of_work_description: string | null;
    site_access_notes: string | null;
    status: SowStatus;
    pdf_generation_status: SowPdfGenerationStatus;
    revision: number;
    revision_date: string | null;
    submitted_at: string | null;
    published_at: string | null;
    published_by: number | null;
    reopened_at: string | null;
    reopened_by: number | null;
    reopen_notes: string | null;
    created_at: string;
    updated_at: string;
    // Loaded relations
    market?: { id: number; name: string };
    project?: { id: number; name: string; ps_number?: string | null } | null;
    creator?: { id: number; name: string; profile_photo_url?: string | null };
    reopened_by_user?: { id: number; name: string } | null;
    cover_photo?: MediaAsset | null;
    annotated_images_count?: number;
    annotated_images?: SowAnnotatedImage[];
    image_groups?: SowImageGroup[];
}

export interface SowChecklistEntry {
    id: number;
    label: string;
    is_required: boolean;
    has_explain_field: boolean;
    response: 'yes' | 'no' | 'n_a' | null;
    explanation: string | null;
}

export interface SowReportAbilities {
    update: boolean;
    submit: boolean;
    reopen: boolean;
    archive: boolean;
    unarchive: boolean;
    attach: boolean;
    delete: boolean;
    publish: boolean;
    download: boolean;
    regenerate: boolean;
}

export interface SowProjectOption {
    id: number;
    name: string;
    market_id: number;
    ps_number: string | null;
    address: string | null;
    city: string | null;
    county: string | null;
    state: string | null;
    latitude: string | null;
    longitude: string | null;
    description: string | null;
    /** Project site-photo preview URL (null when the project has no photo). */
    site_photo_url?: string | null;
}

export interface UpdateImagePayload {
    layer?: ApiAnnotationLayer;
    annotations_updated_at?: string | null;
    page_title?: string | null;
    is_cover?: boolean;
    include_in_pdf?: boolean;
}

export interface UpdateImageResponse {
    success: boolean;
    annotated_image: SowAnnotatedImage;
    annotations_updated_at: string | null;
}

export interface ChecklistResponsePayload {
    responses: Array<{
        sow_checklist_item_id: number;
        response: 'yes' | 'no' | 'n_a' | null;
        explanation: string | null;
    }>;
}
