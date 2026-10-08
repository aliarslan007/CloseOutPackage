<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Services;

use App\Domains\Email\Enums\EmailType;

/**
 * Single source of truth for which {{tokens}} each EmailType supports.
 * EmailTemplateResolver supplies the send-time values; the editor UI uses
 * this list to render the token chips for the selected email type.
 */
final class EmailTemplateVariableRegistry
{
    /** @var list<string> */
    private const COMMON = [
        'site_name',
        'market_name',
        'ps_number',
        'manager_name',
        'recipient_name',
        'current_date',
        'package_link',
    ];

    /**
     * Token names available for this email type.
     *
     * @return list<string>
     */
    public function variablesFor(EmailType $type): array
    {
        return match ($type) {
            EmailType::FINAL_PDF_EMAIL => [...self::COMMON, 'revision_label', 'vzw_engineer_name'],
            EmailType::PROGRESS_PDF => [...self::COMMON, 'revision_label', 'vzw_engineer_name'],
            EmailType::STAGE_UPLOAD => [...self::COMMON, 'stage_name', 'uploaded_by', 'uploaded_at'],
            EmailType::CHANGE_ORDER => [...self::COMMON, 'change_order_number', 'change_order_title', 'total_amount', 'review_link'],
            EmailType::ISSUE_FORWARD => [...self::COMMON, 'issue_type', 'issue_count', 'manager_note', 'portal_link'],
            EmailType::PORTAL_ACCESS_GRANTED => [...self::COMMON, 'portal_link'],
            EmailType::PORTAL_ACCESS_REVOKED => self::COMMON,
            EmailType::CUSTOMER_REVIEW_OPENED => [...self::COMMON, 'round_number', 'package_id', 'review_link'],
            EmailType::REVISION_READY => [...self::COMMON, 'round_number', 'package_id', 'review_link'],
            EmailType::REPLACEMENT_REQUEST => [...self::COMMON, 'category_name'],
            EmailType::PDF_READY,
            EmailType::GENERAL => self::COMMON,
        };
    }

    /**
     * Token map for every email type at once (frontend editor payload).
     *
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return array_combine(
            array_column(EmailType::cases(), 'value'),
            array_map($this->variablesFor(...), EmailType::cases()),
        );
    }
}
