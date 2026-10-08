export type ChangeOrderStatus =
    | 'draft'
    | 'sent_to_customer'
    | 'customer_action_required'
    // The status service produces this when items are approved and others are not,
    // so the client has to know about it.
    | 'partially_approved'
    | 'approved'
    | 'rejected';

export type CustomerDecision = 'pending' | 'approved' | 'rejected';

export interface ChangeOrderItem {
    id?: number;
    sort_order?: number;
    description: string;
    vendor_name?: string[] | null;
    category?: string | null;
    amount: number | string;
    is_included: boolean;
    cost_absorbed_by_solucomp?: boolean;
    customer_decision?: CustomerDecision;
    customer_decision_note?: string | null;
    decided_at?: string | null;
}

export interface ChangeOrderRecipientUser {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string | null;
}

export interface ChangeOrderRecipient {
    id: number;
    change_order_id: number;
    user_id: number;
    user?: ChangeOrderRecipientUser;
    viewed_at?: string | null;
    responded_at?: string | null;
}

export interface ChangeOrderPhoto {
    id: number;
    media_asset_id?: number | null;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    /** Stream endpoint URL; token pages append ?token= for no-auth access. */
    url: string;
    created_at?: string;
}

export interface ChangeOrderCreator {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string | null;
}

export interface CustomerFilterOption {
    id: number;
    name: string;
    email?: string;
    company?: string | null;
    profile_photo_url?: string | null;
}

export interface ChangeOrderProject {
    id: number;
    hash_id?: string;
    name: string;
    project_number?: string | null;
    ps_number?: string | null;
    customer_id?: number | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    zip?: string | null;
    customer?: {
        id: number;
        name: string;
        code?: string | null;
    } | null;
    market?: {
        id: number;
        name: string;
    };
}

export interface ChangeOrder {
    id: number;
    project_id: number;
    created_by: number;
    title: string;
    description?: string | null;
    change_order_date?: string | null;
    status: ChangeOrderStatus;
    total_amount: number | string;
    sent_at?: string | null;
    decision_deadline_at?: string | null;
    created_at: string;
    updated_at: string;
    items?: ChangeOrderItem[];
    recipients?: ChangeOrderRecipient[];
    recipients_count?: number;
    photos?: ChangeOrderPhoto[];
    creator?: ChangeOrderCreator;
    project?: ChangeOrderProject;
}

export interface CustomerOption {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string | null;
}

/**
 * Fallback cap for supporting photos on a single change order (JN Order).
 * The backend is the source of truth and shares the real value through the
 * `photoLimit` Inertia prop; this keeps the UI honest if that prop is missing.
 */
export const DEFAULT_CHANGE_ORDER_PHOTO_LIMIT = 20;

/**
 * Fallback cap for the title. The backend is the source of truth and shares the
 * real value through the `titleLimit` Inertia prop. The `title` column stays
 * varchar(255) — this cap is the contract, and the wider column is headroom.
 */
export const DEFAULT_CHANGE_ORDER_TITLE_LIMIT = 100;

/**
 * Fallback cap for the cover note / description. The backend is the source of
 * truth and shares the real value through the `descriptionLimit` Inertia prop.
 */
export const DEFAULT_CHANGE_ORDER_DESCRIPTION_LIMIT = 5000;

/**
 * Fallback cap for one itemized cost line's description. The backend is the
 * source of truth and shares the real value through the `itemDescriptionLimit`
 * Inertia prop.
 */
export const DEFAULT_CHANGE_ORDER_ITEM_DESCRIPTION_LIMIT = 2000;

/**
 * Fallback cap for vendors per itemized cost line. The backend is the source
 * of truth and shares the real value through the `itemVendorLimit` prop.
 */
export const DEFAULT_CHANGE_ORDER_ITEM_VENDOR_LIMIT = 5;

export const ITEM_CATEGORIES = [
    'Third-party Service',
    'Permits',
    'Materials',
    'Labor',
    'Equipment',
    'Safety & Compliance',
    'Other',
] as const;

export type ItemCategory = typeof ITEM_CATEGORIES[number];
