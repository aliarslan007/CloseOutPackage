export const PROJECT_STATUSES = [
    'pending',
    'in_progress',
    'submitted',
    'approved',
    'rejected',
    'completed',
    'archived',
] as const;

export type ProjectStatusValue = (typeof PROJECT_STATUSES)[number];

export interface ProjectFormValues {
    market_id: number | '';
    customer_id: number | '';
    customer_engineer_id: number | '';
    name: string;
    ps_number: string;
    project_number: string;
    address: string;
    city: string;
    state: string;
    zip: string;
    completed_date: string;
    awarded_date: string;
    rec_issued_date: string;
    construction_started_date: string;
    site_completed_date: string;
    notes: string;
    status: ProjectStatusValue;
    manager_id: number | '';
    supervisor_ids: number[];
    site_photo: File | null;
}

export interface MarketOptionItem {
    id: number;
    name: string;
}

export interface CustomerOptionItem {
    id: number;
    name: string;
    code: string;
}

export interface EngineerOptionItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    title: string | null;
    is_default: boolean;
}

/** Engineers keyed by customer id, then region (market) id. */
export type EngineersByCustomerAndMarket = Record<number, Record<number, EngineerOptionItem[]>>;
