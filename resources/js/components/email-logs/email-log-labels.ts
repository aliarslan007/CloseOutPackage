export const EMAIL_STATUS_LABELS: Record<string, string> = {
    ready: 'Not sent',
    scheduled: 'Scheduled',
    pending: 'Sending',
    sent: 'Sent',
    failed: 'Failed',
    cancelled: 'Cancelled',
    suppressed: 'Suppressed',
};

export const EMAIL_TYPE_LABELS: Record<string, string> = {
    stage_upload: 'Stage Upload',
    replacement_request: 'Replacement Request',
    pdf_ready: 'PDF Ready',
    final_pdf_email: 'Final PDF',
    progress_pdf: 'Progress PDF',
    change_order: 'Change Order',
    issue_forward: 'Issue Forward',
    portal_access_granted: 'Portal Access',
    portal_access_revoked: 'Access Revoked',
    customer_review_opened: 'Customer Review',
    revision_ready: 'Revision Ready',
    general: 'General',
};

export function statusBadgeVariant(status: string): 'success' | 'destructive' | 'warning' | 'info' | 'secondary' {
    if (status === 'sent') return 'success';
    if (status === 'failed') return 'destructive';
    if (status === 'scheduled' || status === 'pending') return 'info';
    if (status === 'ready') return 'warning';
    return 'secondary';
}

export interface EmailLogItem {
    id: number;
    recipient_email: string;
    recipient_name?: string | null;
    sender_email: string;
    sender_name?: string | null;
    subject: string;
    type: string;
    status: string;
    sent_at?: string | null;
    scheduled_at?: string | null;
    cancelled_at?: string | null;
    created_at: string;
    failed_reason?: string | null;
    project?: { id: number; name: string } | null;
    package?: { id: number } | null;
    change_order?: { id: number; title?: string } | null;
    creator?: { id: number; name: string } | null;
}

export interface EmailLogPagination {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

export type EmailLogSort =
    | 'newest'
    | 'oldest'
    | 'recipient_asc'
    | 'recipient_desc'
    | 'sender_asc'
    | 'sender_desc'
    | 'subject_asc'
    | 'subject_desc';

export interface EmailLogFilterState {
    search: string;
    date_from: string;
    date_to: string;
    sort: EmailLogSort;
}

export type DatePreset = 'all' | 'yesterday' | 'last_7_days' | 'this_month' | 'custom';

const DATE_PRESET_LABELS: Record<DatePreset, string> = {
    all: 'All dates',
    yesterday: 'Yesterday',
    last_7_days: 'Last 7 days',
    this_month: 'This month',
    custom: 'Custom range',
};

export function datePresetLabel(preset: DatePreset): string {
    return DATE_PRESET_LABELS[preset];
}

export function toISODate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/**
 * Resolve a preset into an inclusive date range (YYYY-MM-DD) relative to today.
 */
export function datePresetRange(
    preset: Exclude<DatePreset, 'all' | 'custom'>
): { date_from: string; date_to: string } {
    const today = new Date();

    switch (preset) {
        case 'yesterday': {
            const day = new Date(today);
            day.setDate(today.getDate() - 1);
            const iso = toISODate(day);

            return { date_from: iso, date_to: iso };
        }
        case 'last_7_days': {
            const start = new Date(today);
            start.setDate(today.getDate() - 6);

            return { date_from: toISODate(start), date_to: toISODate(today) };
        }
        case 'this_month': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);

            return { date_from: toISODate(start), date_to: toISODate(today) };
        }
    }
}

/**
 * Detect which preset a given (server-provided) date range corresponds to.
 */
export function resolveDatePreset(dateFrom?: string, dateTo?: string): DatePreset {
    if (!dateFrom && !dateTo) return 'all';

    const yesterday = datePresetRange('yesterday');
    if (dateFrom === yesterday.date_from && dateTo === yesterday.date_to) return 'yesterday';

    const week = datePresetRange('last_7_days');
    if (dateFrom === week.date_from && dateTo === week.date_to) return 'last_7_days';

    const month = datePresetRange('this_month');
    if (dateFrom === month.date_from && dateTo === month.date_to) return 'this_month';

    return 'custom';
}

export const EMAIL_SORT_OPTIONS: Array<{ value: EmailLogSort; label: string }> = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'recipient_asc', label: 'Recipient (A–Z)' },
    { value: 'recipient_desc', label: 'Recipient (Z–A)' },
    { value: 'sender_asc', label: 'Sender (A–Z)' },
    { value: 'sender_desc', label: 'Sender (Z–A)' },
    { value: 'subject_asc', label: 'Subject (A–Z)' },
    { value: 'subject_desc', label: 'Subject (Z–A)' },
];

export const EMAIL_SORT_DEFAULT: EmailLogSort = 'newest';
