/** Project Documents Hub types live with the feature; re-exported for page props. */
export type { ProjectDocument, ProjectDocumentsMap } from '@/features/project-documents/types';

export interface ProjectIssueItem {
    id: number;
    type: string;
    description: string;
    verizon_guidance?: string | null;
    resolved_at?: string | null;
    created_at: string;
}

/**
 * @deprecated Legacy REC row shape for the retired `RecDocumentsCard`.
 * New work uses `ProjectDocument` from `@/features/project-documents/types`.
 */
export interface ProjectRecDocument {
    id: number;
    name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_by?: string | null;
    uploaded_at?: string | null;
    view_url: string;
    /** Thumbnail-serving variant of view_url (falls back to the original until generated). */
    preview_url: string;
    download_url: string;
}

export interface ProjectShowDetail {
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
    awarded_date?: string | null;
    rec_issued_date?: string | null;
    construction_started_date?: string | null;
    site_completed_date?: string | null;
    closeout_package_date?: string | null;
    notes?: string | null;
    market?: { id: number; name: string } | null;
    creator?: { id: number; name: string; profile_photo_url?: string | null } | null;
    manager?: { id: number; name: string; email: string } | null;
    /** The project's primary field technician — always one of field_supervisors. */
    primary_supervisor_id?: number | null;
    field_supervisors: Array<{ id: number; name: string; email?: string | null }>;
    issues: ProjectIssueItem[];
    closeout_packages?: Array<{
        id: number;
        title: string;
        revision_number: string;
        status: string;
        created_at?: string;
    }>;
}

export const PROJECT_STATUS_META: Record<string, { label: string; explanation: string }> = {
    pending: { label: 'Pending', explanation: 'Waiting for field work to begin.' },
    in_progress: { label: 'In Progress', explanation: 'Field work is underway.' },
    submitted: { label: 'Submitted', explanation: 'Photos are waiting for manager review.' },
    approved: { label: 'Approved', explanation: 'Approved — the final package is ready.' },
    rejected: { label: 'Rejected', explanation: 'Needs fixes before it can be approved.' },
    completed: { label: 'Completed', explanation: 'This site is finished.' },
    archived: { label: 'Archived', explanation: 'This site is closed and archived.' },
};
