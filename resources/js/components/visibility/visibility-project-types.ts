export interface VisibilityProjectMarket {
    id: number;
    name: string;
}

export interface VisibilityProjectUser {
    id: number;
    name: string;
    email: string;
}

export interface VisibilityPackageStage {
    id: number;
    stage: string;
    status: string;
    photos?: VisibilityPackagePhoto[];
}

export interface VisibilityPackagePhoto {
    id: number;
    position: number;
    status: string;
    flag_reason?: string | null;
    captured_at?: string | null;
    url?: string | null;
    thumb_url?: string | null;
    gps_latitude?: number | null;
    gps_longitude?: number | null;
    category_selection?: {
        template_picture_category?: {
            id: number;
            name: string;
        } | null;
    } | null;
    photo_approval?: {
        reason?: string | null;
    } | null;
}

export interface VisibilityChecklistItem {
    id: number;
    answer: boolean | null;
    explain?: string | null;
    is_na?: boolean;
    quantity?: number | null;
    checklist_item: {
        id: number;
        label: string;
        has_explain_field: boolean;
    };
}

export interface VisibilityPackageItem {
    id: number;
    hash_id?: string;
    status: string;
    current_stage?: string | null;
    revision_major: number;
    revision_minor: number;
    created_at?: string;
    template?: { id: number; name: string } | null;
    stages?: VisibilityPackageStage[];
    checklist_responses?: VisibilityChecklistItem[];
    photos_count?: number;
    checklist_responses_count?: number;
    answered_checklist_responses?: number;
    locked?: boolean;
    /** Backend-computed via CloseoutPackagePolicy::view — the source of truth. */
    can_open?: boolean;
}

export interface VisibilityIssueItem {
    id: number;
    type: string;
    description: string;
    verizon_guidance?: string | null;
    resolved_at?: string | null;
    created_at: string;
}

export interface VisibilityIssuesPage {
    data: VisibilityIssueItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    path?: string;
}

export interface VisibilityProjectDetail {
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
    vzw_engineer_email?: string | null;
    vzw_engineer_phone?: string | null;
    status: string;
    progress_percentage: number;
    completed_date?: string | null;
    notes?: string | null;
    market?: VisibilityProjectMarket | null;
    manager?: VisibilityProjectUser | null;
    field_supervisors: VisibilityProjectUser[];
    issues: VisibilityIssuesPage;
    closeout_packages: VisibilityPackageItem[];
}
