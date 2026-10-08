export interface CustomerProject {
    id: number;
    hash_id?: string;
    name: string;
    ps_number?: string;
    project_number?: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    status?: string;
    market?: { id: number; name: string } | null;
    manager?: { id: number; name: string; email?: string; profile_photo_url?: string | null } | null;
    latest_package?: {
        id: number;
        hash_id?: string;
        status: string;
        revision_major?: number;
        revision_minor?: number;
        updated_at?: string;
    } | null;
    progress_percentage?: number;
    total_packages_count?: number;
    approved_packages_count?: number;
    rework_packages_count?: number;
    updated_at?: string;
    created_at?: string;
}

export interface CustomerPortalProps {
    projects: CustomerProject[];
}

export type PortalStatusFilter = 'all' | 'in_progress' | 'approved' | 'attention';

export type PortalSortOption = 'activity' | 'progress_desc' | 'progress_asc' | 'name_asc';

export type PortalViewMode = 'list' | 'grid';

export interface PortalMetrics {
    total: number;
    inProgress: number;
    approved: number;
    needAttention: number;
    avgProgress: number;
    /** Sites where no close-out package has been submitted yet. */
    notStarted: number;
}
