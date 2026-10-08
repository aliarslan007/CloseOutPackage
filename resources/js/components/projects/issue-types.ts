export const ISSUE_TYPES = [
    'lock',
    'site_access',
    'construction',
    'verizon_guidance',
    'other',
] as const;

export type IssueTypeValue = (typeof ISSUE_TYPES)[number];

export const ISSUE_TYPE_LABELS: Record<IssueTypeValue, string> = {
    lock: 'Lock Issue',
    site_access: 'Site Access',
    construction: 'Construction',
    verizon_guidance: 'Verizon Guidance',
    other: 'General Issue',
};

export const QUICK_REPLY_CHIPS = [
    'On my way to site',
    'Key located, entering now',
    'Waiting for Verizon engineer',
    'Access granted',
    'Issue resolved on-site',
];

export interface ProjectStakeholder {
    id: number;
    name: string;
    email?: string;
    phone?: string | null;
    profile_photo_url?: string | null;
    roles?: Array<{ name: string }>;
}

export interface IssueAttachment {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    url: string;
    original_url?: string;
    preview_url?: string;
    thumb_url?: string | null;
}

export interface IssueComment {
    id: number;
    issue_history_id?: number;
    user_id?: number;
    body: string;
    created_at: string;
    attachments?: IssueAttachment[];
    author: {
        id: number;
        name: string;
        phone?: string | null;
        profile_photo_url?: string | null;
        roles?: Array<{ name: string }>;
    };
}

export interface IssueItem {
    id: number;
    hash_id?: string;
    reported_by?: number;
    resolved_by?: number | null;
    type: IssueTypeValue;
    description: string;
    verizon_guidance?: string | null;
    resolved_at?: string | null;
    created_at: string;
    attachments?: IssueAttachment[];
    reporter?: {
        id: number;
        name: string;
        phone?: string | null;
        company?: string | null;
        profile_photo_url?: string | null;
        roles?: Array<{ name: string }>;
    } | null;
    resolver?: {
        id: number;
        name: string;
        phone?: string | null;
        company?: string | null;
        profile_photo_url?: string | null;
        roles?: Array<{ name: string }>;
    } | null;
    comments: IssueComment[];
    feedback_round?: import('@/features/entity-feedback').EntityFeedbackRoundItem | null;
}

export type IssueStatusFilter = 'all' | 'open' | 'resolved';

export const ISSUE_TAB_STORAGE_KEY = 'project_issues_tab_filter';

export function getStoredIssueTab(): IssueStatusFilter {
    try {
        const stored = localStorage.getItem(ISSUE_TAB_STORAGE_KEY);
        if (stored === 'all' || stored === 'open' || stored === 'resolved') {
            return stored;
        }
    } catch {
        // ignore localStorage access error
    }
    return 'open';
}

export function setStoredIssueTab(filter: IssueStatusFilter): void {
    try {
        localStorage.setItem(ISSUE_TAB_STORAGE_KEY, filter);
    } catch {
        // ignore
    }
}
