export interface AssignedProjectItem {
    id: number;
    name: string;
    status: string | null;
}

export interface EngineerItem {
    id: number;
    hash_id: string;
    customer_id: number;
    market_id: number;
    market_name: string | null;
    name: string;
    email: string;
    phone: string | null;
    title: string | null;
    is_default: boolean;
    is_active: boolean;
    has_portal_account: boolean;
    profile_photo_url?: string | null;
    assigned_projects: AssignedProjectItem[];
}

export interface CustomerItem {
    id: number;
    hash_id: string;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    projects_count: number;
    engineers: EngineerItem[];
}

export interface RegionItem {
    id: number;
    name: string;
}

export interface CustomerHierarchyPayload {
    customers: CustomerItem[];
    regions: RegionItem[];
    metrics: {
        customers: number;
        regions: number;
        active_engineers: number;
    };
}
