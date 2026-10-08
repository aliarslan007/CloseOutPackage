<?php

declare(strict_types=1);

namespace App\Domains\Email\Enums;

enum EmailType: string
{
    case STAGE_UPLOAD = 'stage_upload';
    case REPLACEMENT_REQUEST = 'replacement_request';
    case PDF_READY = 'pdf_ready';
    case FINAL_PDF_EMAIL = 'final_pdf_email';
    case PROGRESS_PDF = 'progress_pdf';
    case CHANGE_ORDER = 'change_order';
    case ISSUE_FORWARD = 'issue_forward';
    case PORTAL_ACCESS_GRANTED = 'portal_access_granted';
    case PORTAL_ACCESS_REVOKED = 'portal_access_revoked';
    case CUSTOMER_REVIEW_OPENED = 'customer_review_opened';
    case REVISION_READY = 'revision_ready';
    case GENERAL = 'general';

    /**
     * The single source of truth for how this email is delivered. Both the
     * customer kill switch and the manual outbox read this, so a new type
     * cannot be added without stating its delivery behaviour (the match is
     * exhaustive on purpose).
     */
    public function deliveryMode(): DeliveryMode
    {
        return match ($this) {
            // One-time portal handover: still automatic.
            self::PORTAL_ACCESS_GRANTED => DeliveryMode::AUTO,

            // Access revocation no longer emails the customer at all.
            self::PORTAL_ACCESS_REVOKED => DeliveryMode::NONE,

            // A manager must release these from the Customer Emails outbox.
            self::CHANGE_ORDER,
            self::FINAL_PDF_EMAIL,
            self::PROGRESS_PDF,
            self::ISSUE_FORWARD,
            self::CUSTOMER_REVIEW_OPENED,
            self::REVISION_READY => DeliveryMode::MANUAL,

            // Staff-only notifications, never gated by the customer kill switch.
            self::STAGE_UPLOAD,
            self::REPLACEMENT_REQUEST,
            self::PDF_READY,
            self::GENERAL => DeliveryMode::INTERNAL,
        };
    }

    /**
     * Customer-facing emails go to external recipients (customers and
     * customer engineers). They are the emails gated by the customer
     * email kill switch (config: cop.customer_emails_enabled).
     *
     * Derived from deliveryMode() so the two can never disagree.
     */
    public function isCustomerFacing(): bool
    {
        return $this->deliveryMode() !== DeliveryMode::INTERNAL;
    }

    /**
     * True when a manager has to release the email from the outbox.
     */
    public function requiresManualSend(): bool
    {
        return $this->deliveryMode() === DeliveryMode::MANUAL;
    }

    /**
     * Types a manager can see and act on in the Customer Emails outbox.
     *
     * @return list<self>
     */
    public static function manualTypes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->requiresManualSend(),
        ));
    }

    public function label(): string
    {
        return match ($this) {
            self::FINAL_PDF_EMAIL => 'Final PDF Package',
            self::PROGRESS_PDF => 'Progress PDF Package',
            self::CHANGE_ORDER => 'Change Order Request',
            self::PORTAL_ACCESS_GRANTED => 'Portal Access Granted',
            self::PORTAL_ACCESS_REVOKED => 'Portal Access Revoked',
            self::CUSTOMER_REVIEW_OPENED => 'Customer Review Requested',
            self::REVISION_READY => 'Revised Package Ready',
            self::ISSUE_FORWARD => 'Forwarded Project Issues',
            self::STAGE_UPLOAD => 'Stage Upload Notification',
            self::REPLACEMENT_REQUEST => 'Photo Replacement Request',
            self::PDF_READY => 'PDF Ready Notification',
            self::GENERAL => 'General Notification',
        };
    }
}
