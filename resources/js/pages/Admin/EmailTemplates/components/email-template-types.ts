/**
 * Shared types for the admin Email Templates library pages (Phase 11).
 */

export type EmailTemplateStatusValue = 'active' | 'draft' | 'archived';

export interface EmailTypeOption {
    readonly value: string;
    readonly label: string;
}

export interface EmailTemplateItem {
    readonly id: number;
    readonly email_type: string;
    readonly name: string;
    readonly subject_template: string;
    readonly body_template: string;
    readonly status: EmailTemplateStatusValue;
    readonly is_default: boolean;
    readonly created_at: string;
    readonly updated_at: string;
    readonly creator?: { readonly id: number; readonly name: string } | null;
}

/** Shape Inertia serializes a LengthAwarePaginator into. */
export interface PaginatedEmailTemplates {
    readonly data: EmailTemplateItem[];
    readonly current_page: number;
    readonly last_page: number;
    readonly per_page: number;
    readonly total: number;
    readonly from: number | null;
    readonly to: number | null;
}

export type EmailTemplateVariables = Record<string, ReadonlyArray<string>>;

export interface DefaultEmailTemplateDefinition {
    readonly name: string;
    readonly subject: string;
    readonly body: string;
}

export type DefaultEmailTemplatesMap = Record<string, DefaultEmailTemplateDefinition>;

export const EMAIL_TEMPLATE_STATUS_LABELS: Record<EmailTemplateStatusValue, string> = {
    active: 'Active',
    draft: 'Draft',
    archived: 'Archived',
};

export function statusBadgeVariant(
    status: EmailTemplateStatusValue,
): 'success' | 'warning' | 'secondary' {
    if (status === 'active') return 'success';
    if (status === 'draft') return 'warning';

    return 'secondary';
}
