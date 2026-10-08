<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Services;

use App\Domains\Email\Enums\EmailType;

/**
 * Baseline wording definitions for each EmailType.
 * Seeded by DefaultEmailTemplateSeeder and shared with the template editor
 * for one-click "Reset to standard wording" actions.
 */
final class EmailTemplateDefaultDefinitions
{
    /**
     * @return array{name: string, subject: string, body: string}
     */
    public static function forType(EmailType $type): array
    {
        return match ($type) {
            EmailType::FINAL_PDF_EMAIL => [
                'name' => 'Final COP — Standard',
                'subject' => '{{site_name}} → {{market_name}} → Close-Out Package {{revision_label}}',
                'body' => "Dear {{recipient_name}},\n\nPlease find attached the final Close-Out Package ({{revision_label}}) for the cell site\n{{site_name}} (PS: {{ps_number}}) located in the {{market_name}} region.\n\nThis package has been reviewed and approved for your records.\n\nIf you have any questions, please contact {{manager_name}} directly.\n\nBest regards,\nSoluComp",
            ],
            EmailType::PROGRESS_PDF => [
                'name' => 'Progress Package — Standard',
                'subject' => '{{site_name}} — Progress Package {{revision_label}}',
                'body' => "Dear {{recipient_name}},\n\nAttached is the progress Close-Out Package ({{revision_label}}) for {{site_name}}\n(PS: {{ps_number}}) in the {{market_name}} region.\n\nThis snapshot reflects the current state of the site work. The final package will\nfollow once all close-out items are complete.\n\nFor questions, please contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::STAGE_UPLOAD => [
                'name' => 'Stage Upload Notification',
                'subject' => 'New stage upload for {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nA new stage \"{{stage_name}}\" has been uploaded for {{site_name}} (PS: {{ps_number}})\nin the {{market_name}} region by {{uploaded_by}} on {{uploaded_at}}.\n\nYou can review the upload in the project portal. For questions, please contact\n{{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::REPLACEMENT_REQUEST => [
                'name' => 'Replacement Request — Standard',
                'subject' => 'Replacement request for {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nA replacement request has been submitted for {{site_name}} (PS: {{ps_number}}) in\nthe {{market_name}} region.\n\nPlease review the request details in the project portal and respond at your\nearliest convenience. For questions, please contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::PDF_READY => [
                'name' => 'PDF Ready Notification',
                'subject' => 'Your document for {{site_name}} is ready',
                'body' => "Dear {{recipient_name}},\n\nA document for {{site_name}} (PS: {{ps_number}}) in the {{market_name}} region is\nnow ready for your review.\n\nPlease access it through the project portal. For questions, please contact\n{{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::CHANGE_ORDER => [
                'name' => 'Change Order — Standard',
                'subject' => 'Change Order {{change_order_number}} for {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nChange Order {{change_order_number}} has been issued for {{site_name}}\n(PS: {{ps_number}}) in the {{market_name}} region.\n\nPlease review the change order details in the project portal. For questions,\nplease contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::ISSUE_FORWARD => [
                'name' => 'Issue Forward — Standard',
                'subject' => '{{issue_type}} issue forwarded for {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nA {{issue_type}} issue at {{site_name}} (PS: {{ps_number}}) in the {{market_name}}\nregion has been forwarded for your attention.\n\nPlease review the issue in the project portal. For questions, please contact\n{{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::PORTAL_ACCESS_GRANTED => [
                'name' => 'Portal Access Granted — Standard',
                'subject' => 'Portal Access Granted — {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nYou have been granted access to {{site_name}} (PS: {{ps_number}}) in the close-out package customer portal.\n\nYou can view approved site photos, deliverable packages, and project updates directly in your portal.\n\nIf you have any questions or need assistance, please contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::PORTAL_ACCESS_REVOKED => [
                'name' => 'Portal Access Revoked — Standard',
                'subject' => 'Portal Access Revoked — {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nYour access to project {{site_name}} (PS: {{ps_number}}) in the close-out package customer portal has been revoked.\n\nIf you believe this was done in error or require continued access, please contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::CUSTOMER_REVIEW_OPENED => [
                'name' => 'Customer Review Requested — Standard',
                'subject' => 'Close-Out Package Review Requested — {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nYou are invited to review the close-out package for {{site_name}} (PS: {{ps_number}}) in the {{market_name}} region (feedback round {{round_number}}).\n\nPlease review the package photos and checklist in your customer portal and add any feedback notes if adjustments are needed.\n\nFor any questions, please contact {{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
            EmailType::REVISION_READY => [
                'name' => 'Revised Package Ready — Standard',
                'subject' => 'Revised Close-Out Package Ready — {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nThe manager has resolved all feedback notes on the close-out package for {{site_name}} (PS: {{ps_number}}) (feedback round {{round_number}}).\n\nThe revised package is ready for your re-examination in the customer portal. Please review and accept the package or submit any further notes.\n\nBest regards,\nSoluComp",
            ],
            EmailType::GENERAL => [
                'name' => 'General Notification',
                'subject' => 'Update regarding {{site_name}}',
                'body' => "Dear {{recipient_name}},\n\nThere is an update regarding {{site_name}} (PS: {{ps_number}}) in the\n{{market_name}} region.\n\nPlease review the details in the project portal. For questions, please contact\n{{manager_name}}.\n\nBest regards,\nSoluComp",
            ],
        };
    }

    /**
     * @return array<string, array{name: string, subject: string, body: string}>
     */
    public static function all(): array
    {
        $result = [];
        foreach (EmailType::cases() as $type) {
            $result[$type->value] = self::forType($type);
        }

        return $result;
    }
}
