/**
 * Shared types + route keys for the customer-email send flow (Phase 9).
 * Labels for email types are reused from the email-logs domain:
 * components/email-logs/email-log-labels.ts (EMAIL_TYPE_LABELS).
 */

export type EmailTypeValue =
    | 'final_pdf_email'
    | 'progress_pdf'
    | 'stage_upload'
    | 'replacement_request'
    | 'pdf_ready'
    | 'change_order'
    | 'issue_forward'
    | 'general';

/** A picker entry — an active template resolved with tokens at send time. */
export interface EmailTemplateSummary {
    readonly id: number;
    readonly name: string;
    readonly subject_template: string;
    readonly body_template: string;
    readonly is_default: boolean;
}

/** Resolved subject/body from the preview endpoint. */
export interface EmailPreviewPayload {
    readonly subject: string;
    readonly body: string;
}

/** What the send flow submits to the backend endpoint. */
export interface SendEmailOverrides {
    readonly emailTemplateId: number | null;
    readonly subjectOverride: string;
    readonly bodyOverride: string;
    readonly emailTo: string;
}

/** Pragmatic recipient validation shared by the send-flow UI. */
export function isValidEmail(value: string): boolean {
    const email = value.trim();
    const atIndex = email.indexOf('@');

    return atIndex > 0 && atIndex < email.length - 1 && email.lastIndexOf('.') > atIndex;
}

export const EMAIL_TEMPLATE_PICKER_ROUTE = 'admin.email-templates.picker';
export const EMAIL_TEMPLATE_PREVIEW_ROUTE = 'admin.email-templates.preview';
