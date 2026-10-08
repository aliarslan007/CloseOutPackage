<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;

final class EmailTemplateRenderer
{
    public function render(SendEmailDTO $dto): string
    {
        if ($dto->type === EmailType::CHANGE_ORDER) {
            return $this->renderChangeOrderEmail($dto);
        }

        if ($dto->type === EmailType::ISSUE_FORWARD) {
            return $this->renderIssueForwardEmail($dto);
        }

        $title = $this->resolveTitle($dto);
        $project = $this->resolveProject($dto);
        $package = $this->resolvePackage($dto);

        $siteName = $project->name ?? $this->parseSubjectPart($dto->subject, 0, 'Cell Site');
        $marketName = $project->market->name ?? $this->parseSubjectPart($dto->subject, 1, 'Verizon Market');
        $psNumber = $project->ps_number ?? ($project !== null ? 'PS-'.$project->id : null);
        $revisionLabel = $package?->latestFinalRevision()?->first()->revision_label
            ?? $this->extractRevisionLabel($dto->body)
            ?? 'R1.0';

        $bodyText = $this->cleanBodyText($dto->body);

        $actionButtonHtml = '';
        if ($dto->actionUrl !== null && $dto->actionUrl !== '') {
            $label = $dto->actionLabel ?? 'Open';
            if (! str_contains($label, '→') && ! str_contains($label, '&rarr;')) {
                $label .= ' &rarr;';
            }
            $actionButtonHtml = $this->renderButton($dto->actionUrl, $label);
        } elseif ($dto->attachmentUrl !== null && $dto->attachmentUrl !== '') {
            $actionButtonHtml = $this->renderButton(
                $dto->attachmentUrl,
                'Download Close-Out Package (PDF) &rarr;',
                'Secure download link &bull; Valid for 7 days'
            );
        }

        $attachmentNoticeHtml = $this->renderAttachmentNotice(
            $dto->attachmentPath !== null && $dto->attachmentPath !== '' ? $dto->attachmentFilename() : null
        );

        $isCopPackageType = $package !== null || in_array($dto->type, [
            EmailType::FINAL_PDF_EMAIL,
            EmailType::PROGRESS_PDF,
            EmailType::STAGE_UPLOAD,
            EmailType::REPLACEMENT_REQUEST,
            EmailType::CUSTOMER_REVIEW_OPENED,
            EmailType::REVISION_READY,
        ], true);

        $metadataHtml = $isCopPackageType
            ? $this->renderMetadataGrid($siteName, $psNumber, $marketName, $revisionLabel)
            : '';

        $contentHtml = <<<HTML
            <!-- Body Narrative -->
            <div style="font-size: 14px; color: #374151; margin: 0 0 24px 0; line-height: 1.65; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;">{$bodyText}</div>

            {$metadataHtml}

            {$actionButtonHtml}

            {$attachmentNoticeHtml}
HTML;

        $signOffName = $this->resolveSignOffName($dto, $project);

        return $this->renderBaseTemplate($dto->subject, $title, $contentHtml, $signOffName);
    }

    private function renderBaseTemplate(
        string $subject,
        string $title,
        string $contentHtml,
        string $signOffName,
    ): string {
        $logoUrl = $this->resolveLogoUrl();
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
    <style type="text/css">
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .email-outer-td {
                padding: 12px 6px !important;
            }
            .email-card-td {
                padding: 24px 14px !important;
            }
            .email-meta-td {
                padding: 14px 12px !important;
            }
            .email-meta-label {
                width: 90px !important;
                padding-right: 8px !important;
                font-size: 12px !important;
            }
            .email-meta-value {
                font-size: 12px !important;
            }
            .email-item-col {
                padding: 8px 6px !important;
            }
            .email-btn-wrap {
                margin: 20px 0 16px 0 !important;
            }
            .email-btn {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                box-sizing: border-box !important;
                padding: 14px 16px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #374151; line-height: 1.65; -webkit-font-smoothing: antialiased;">
    
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; width: 100%; margin: 0; padding: 32px 16px;">
        <tr>
            <td align="center" class="email-outer-td" style="padding: 0;">
                
                <!-- Main Container Card (Qoder Inspired Architecture) -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border: 1px solid #c3c9d6; border-radius: 10px; overflow: hidden; text-align: left; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); table-layout: fixed;">
                    <tr>
                        <td class="email-card-td" style="padding: 40px 36px; overflow-wrap: break-word; word-break: break-word;">
                            
                            <!-- Brand Header -->
                            <div style="margin-bottom: 28px;">
                                <img src="{$logoUrl}" alt="SoluComp" style="height: 38px; max-height: 38px; width: auto; display: block; border: 0;" />
                            </div>

                            <!-- Main Section Title -->
                            <h1 style="font-size: 24px; font-weight: 700; color: #111827; margin: 0 0 20px 0; letter-spacing: -0.4px; line-height: 1.3; overflow-wrap: break-word; word-break: break-word;">
                                {$title}
                            </h1>

                            {$contentHtml}

                            <!-- Professional Sign-Off -->
                            <div style="margin: 32px 0 0 0; font-size: 14px; color: #374151; line-height: 1.6; overflow-wrap: break-word; word-break: break-word;">
                                <p style="margin: 0 0 4px 0; color: #6b7280;">Best regards,</p>
                                <p style="margin: 0; font-weight: 600; color: #111827; overflow-wrap: break-word; word-break: break-word;">{$signOffName}</p>
                            </div>

                            <!-- Footer Divider -->
                            <div style="border-top: 1px solid #e5e7eb; margin-top: 36px; padding-top: 24px; text-align: left; font-size: 12px; color: #9ca3af; line-height: 1.6; overflow-wrap: break-word; word-break: break-word;">
                                <p style="margin: 0 0 4px 0; font-weight: 600; color: #6b7280;">SoluComp &mdash; Network Made Easy</p>
                                <p style="margin: 0 0 8px 0;">Automated Transmission &bull; Do not reply directly to this notification.</p>
                                <p style="margin: 0; font-size: 11px; color: #9ca3af;">&copy; {$year} SoluComp. All rights reserved.</p>
                            </div>

                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function renderButton(string $url, string $label, ?string $subtext = null): string
    {
        $buttonLabel = $label;
        if (! str_contains($buttonLabel, '→') && ! str_contains($buttonLabel, '&rarr;')) {
            $buttonLabel .= ' &rarr;';
        }

        $subtextHtml = $subtext !== null && $subtext !== ''
            ? sprintf('<div style="font-size: 11px; color: #6b7280; margin-top: 10px; font-weight: 500; overflow-wrap: break-word; word-break: break-word;">%s</div>', $subtext)
            : '';

        return sprintf(
            '<div class="email-btn-wrap" style="margin: 28px 0 24px 0; text-align: left;">
                <a class="email-btn" href="%s" target="_blank" style="display: inline-block; max-width: 100%%; box-sizing: border-box; background-color: #22c55e; color: #ffffff; font-weight: 700; font-size: 15px; text-decoration: none; padding: 14px 28px; border-radius: 8px; box-shadow: 0 2px 8px rgba(34, 197, 94, 0.25); letter-spacing: 0.2px; white-space: nowrap; text-align: center;">
                    %s
                </a>
                %s
            </div>',
            e($url),
            $buttonLabel,
            $subtextHtml
        );
    }

    private function renderAttachmentNotice(?string $filename): string
    {
        if ($filename === null || $filename === '') {
            return '';
        }

        return sprintf(
            '<div style="margin: 20px 0; padding: 14px 18px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; color: #374151; overflow-wrap: break-word; word-break: break-word;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 4px;">Attached Deliverable (PDF)</div>
                <div style="font-family: monospace; font-size: 13px; color: #111827; font-weight: 600; overflow-wrap: break-word; word-break: break-all;">%s</div>
            </div>',
            e($filename)
        );
    }

    private function resolveSignOffName(SendEmailDTO $dto, ?Project $project = null, ?string $explicitName = null): string
    {
        if (is_string($explicitName) && trim($explicitName) !== '') {
            return trim($explicitName).' · SoluComp Project Management';
        }

        $managerName = $project?->manager?->name;
        if (is_string($managerName) && trim($managerName) !== '') {
            return trim($managerName).' · SoluComp Project Management';
        }

        return 'SoluComp Project Management';
    }

    private function renderPsRow(?string $psNumber): string
    {
        if ($psNumber === null || $psNumber === '') {
            return '';
        }

        return sprintf(
            '<tr>
                <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">PS Number</td>
                <td style="padding-bottom: 10px; color: #111827; font-weight: 600; font-family: monospace; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">%s</td>
            </tr>',
            e($psNumber)
        );
    }

    private function renderMetadataGrid(
        string $siteName,
        ?string $psNumber,
        string $marketName,
        string $revisionLabel,
    ): string {
        return <<<HTML
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 24px; font-size: 13px;">
                    <tr>
                        <td style="padding: 18px 20px; overflow-wrap: break-word; word-break: break-word;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%;">
                                <tr>
                                    <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Site</td>
                                    <td style="padding-bottom: 10px; color: #111827; font-weight: 700; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$siteName}</td>
                                </tr>
                                {$this->renderPsRow($psNumber)}
                                <tr>
                                    <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Market</td>
                                    <td style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$marketName}</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Revision</td>
                                    <td style="padding-bottom: 10px; color: #16a34a; font-weight: 700; font-family: monospace; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$revisionLabel}</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: middle;">Status</td>
                                    <td style="vertical-align: middle; overflow-wrap: break-word; word-break: break-word;">
                                        <span style="display: inline-block; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-weight: 600; font-size: 11px; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                           Approved &amp; Verified
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
        HTML;
    }

    private function resolveTitle(SendEmailDTO $dto): string
    {
        return match ($dto->type ?? null) {
            EmailType::FINAL_PDF_EMAIL => 'Final Close-Out Package Deliverable',
            EmailType::PROGRESS_PDF => 'Site Construction Progress Report',
            EmailType::STAGE_UPLOAD => 'Field Construction Stage Uploaded',
            EmailType::REPLACEMENT_REQUEST => 'Photo Replacement Required',
            EmailType::PDF_READY => 'PDF Document Ready',
            EmailType::CHANGE_ORDER => 'Change Order Approval Request',
            EmailType::ISSUE_FORWARD => 'Issues Forwarded for Your Review',
            EmailType::PORTAL_ACCESS_GRANTED => 'Portal Access Granted',
            EmailType::PORTAL_ACCESS_REVOKED => 'Portal Access Revoked',
            EmailType::CUSTOMER_REVIEW_OPENED => 'Customer Review Requested',
            EmailType::REVISION_READY => 'Revised Package Ready',
            EmailType::GENERAL => $dto->subject !== '' ? $dto->subject : 'Project Update Notification',
            default => $dto->subject !== '' ? $dto->subject : 'Close-Out Package Notification',
        };
    }

    private function resolveProject(SendEmailDTO $dto): ?Project
    {
        if ($dto->projectId === null) {
            return null;
        }

        return Project::query()->with(['market', 'manager'])->find($dto->projectId);
    }

    private function resolvePackage(SendEmailDTO $dto): ?CloseoutPackage
    {
        if ($dto->closeoutPackageId === null) {
            return null;
        }

        return CloseoutPackage::query()->with(['project.market', 'project.manager', 'latestFinalRevision'])->find($dto->closeoutPackageId);
    }

    private function parseSubjectPart(string $subject, int $index, string $default): string
    {
        $parts = explode(' -> ', $subject);

        return isset($parts[$index]) && trim($parts[$index]) !== '' ? trim($parts[$index]) : $default;
    }

    private function extractRevisionLabel(string $body): ?string
    {
        if (preg_match('/(R\d+\.\d+)/i', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function cleanBodyText(string $body): string
    {
        $cleaned = (string) preg_replace('/Download your PDF package here:\s*https?:\/\/[^\s<]+/i', '', $body);
        $cleaned = (string) preg_replace('/Download your PDF here:\s*https?:\/\/[^\s<]+/i', '', $cleaned);

        return $this->formatNarrativeText(trim($cleaned));
    }

    /**
     * Formats user-authored plain text for robust HTML email rendering.
     *
     * Preserves carriage returns, newlines, blank lines, and runs of spaces / indentation
     * across all email clients — including Microsoft Outlook desktop (Word HTML
     * engine which ignores CSS white-space) and modern web/mobile clients.
     */
    private function formatNarrativeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Normalize carriage returns to standard newline \n
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        // 2. Safely escape HTML characters to prevent XSS
        $escaped = e($normalized);

        // 3. Convert tabs to 4 spaces
        $escaped = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $escaped);

        // 4. Preserve runs of 2+ spaces (e.g. indentation or deliberate alignment)
        $spaced = preg_replace_callback('/ {2,}/', static function (array $matches): string {
            $len = strlen($matches[0]);
            $result = '';
            for ($i = 0; $i < $len; $i++) {
                $result .= ($i % 2 === 0) ? '&nbsp;' : ' ';
            }

            return $result;
        }, $escaped);

        // 5. Convert newlines to HTML <br /> tags without trailing \n
        return (string) preg_replace('/\n/', '<br />', (string) $spaced);
    }

    /**
     * "View full description" line, shown only when the email actually clipped
     * the text. Silently shortening a scope or a spec detail without saying so
     * would leave the customer approving against text they never received.
     */
    private function renderEmailDescriptionFooter(?string $fullDescription): string
    {
        $full = trim((string) $fullDescription);

        if ($full === '' || $full === $this->truncateForEmail($full)) {
            return '';
        }

        $fullWordCount = count(preg_split('/\s+/', $full) ?: []);

        return sprintf(
            '<div style="margin-top: 5px; font-size: 11px; color: #6b7280; font-style: italic;">%s</div>',
            e("Full description ({$fullWordCount} words) — open the review page to read it in full.")
        );
    }

    /** Visible characters of an item description in the email table, ellipsis included. */
    private const EMAIL_ITEM_DESCRIPTION_CHARS = 50;

    private const TRUNCATION_SUFFIX = '…';

    /**
     * Clamp an item description to a character budget for the email table.
     *
     * A change-order item allows ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH
     * (2000) characters. Pasting that much into a 4-column email table makes
     * the row taller than the phone screen, so the mobile client scrolls the
     * customer through a wall of text to reach the amount. The review page
     * still carries the full text - only the email is shortened.
     *
     * The budget is spent on whole words: the cut retreats to the last space so
     * a vendor or spec name is never left as a dangling "installatio…". The
     * suffix is written outside the cut so it never reads as part of the text.
     */
    private function truncateForEmail(string $text, int $maxChars = self::EMAIL_ITEM_DESCRIPTION_CHARS): string
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return '';
        }

        if (mb_strlen($trimmed) <= $maxChars) {
            return $trimmed;
        }

        $slice = mb_substr($trimmed, 0, max(1, $maxChars - mb_strlen(self::TRUNCATION_SUFFIX)));

        // Back off to the last complete word when the budget landed mid-word.
        if (preg_match('/^(.*)\s+\S*$/su', $slice, $matches) === 1 && trim($matches[1]) !== '') {
            $slice = $matches[1];
        }

        return rtrim($slice).self::TRUNCATION_SUFFIX;
    }

    private function resolveLogoUrl(): string
    {
        $customUrl = config('cop.email_logo_url');
        if (is_string($customUrl) && $customUrl !== '') {
            return $customUrl;
        }

        $appUrl = (string) config('app.url');
        $isPublicHttps = str_starts_with($appUrl, 'https://')
            && ! str_contains($appUrl, '.test')
            && ! str_contains($appUrl, 'localhost');

        if ($isPublicHttps) {
            if (is_file(public_path('assets/solucomp-brand-logo.png'))) {
                return asset('assets/solucomp-brand-logo.png');
            }

            if (is_file(public_path('assets/logo-email.png'))) {
                return asset('assets/logo-email.png');
            }
        }

        return 'https://solucomp.com/images/backgrounds/logo.png';
    }

    private function renderChangeOrderEmail(SendEmailDTO $dto): string
    {
        $changeOrder = null;
        if ($dto->contextId !== null && is_numeric($dto->contextId)) {
            /** @var ChangeOrder|null $changeOrder */
            $changeOrder = ChangeOrder::query()
                ->with(['project.market', 'creator', 'items'])
                ->find((int) $dto->contextId);
        }

        $project = $changeOrder?->project ?? $this->resolveProject($dto);
        $siteName = $project->name ?? 'Verizon Cell Site';
        $creatorName = $changeOrder?->creator?->name ?? 'Project Manager';
        $description = $changeOrder->description ?? '';

        $metadataCardHtml = $this->renderChangeOrderMetadataCard($changeOrder, $project, $creatorName);
        $descriptionHtml = $this->renderChangeOrderDescriptionBox($description);
        $itemsTableHtml = $this->renderChangeOrderItemsTable($changeOrder);
        $actionUrl = $dto->actionUrl ?? '';

        $ctaHtml = $actionUrl !== ''
            ? $this->renderButton($actionUrl, 'Review Change Order', 'Secure decision link &bull; Valid for authorized Verizon customer contacts')
            : '';

        $contentHtml = <<<HTML
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.65;">
                A change order request has been submitted by <strong style="color: #111827;">{$creatorName}</strong> for Verizon project <strong style="color: #111827;">{$siteName}</strong>. Please review the itemized cost breakdown below and record your authorization decisions.
            </p>

            {$metadataCardHtml}

            {$descriptionHtml}

            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; margin: 24px 0 10px 0;">
                Itemized Cost Breakdown
            </div>

            {$itemsTableHtml}

            {$ctaHtml}

            <div style="margin: 24px 0 0 0; padding: 14px 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px; color: #6b7280; line-height: 1.5;">
                <strong style="color: #374151;">Need clarification or have questions?</strong> Reply directly to this email notification &mdash; Project Manager <strong style="color: #111827;">{$creatorName}</strong> is CC'd on this message.
            </div>
HTML;

        $signOffName = $creatorName.' · SoluComp Project Management';

        return $this->renderBaseTemplate($dto->subject, 'Change Order Approval Request', $contentHtml, $signOffName);
    }

    private function renderChangeOrderMetadataCard(?ChangeOrder $changeOrder, ?Project $project, string $creatorName): string
    {
        $siteName = $project->name ?? 'Verizon Cell Site';
        $marketName = $project->market->name ?? 'Verizon Region';
        $psNumber = $project->ps_number ?? ($project !== null ? 'PS-'.$project->id : null);
        $totalFormatted = '$'.number_format((float) ($changeOrder?->total_amount ?? 0), 2);
        $sentDate = $changeOrder?->sent_at?->format('M j, Y') ?? date('M j, Y');
        $coTitle = $changeOrder?->title;
        $coNumber = $changeOrder !== null ? ($changeOrder->hash_id ?? ($changeOrder->id ? '#'.$changeOrder->id : null)) : null;

        $psRowHtml = '';
        if ($psNumber !== null && $psNumber !== '') {
            $psRowHtml = sprintf(
                '<tr>
                    <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">PS Number</td>
                    <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; font-family: monospace; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">%s</td>
                </tr>',
                e($psNumber)
            );
        }

        $numberRowHtml = '';
        if ($coNumber !== null && trim((string) $coNumber) !== '') {
            $numberRowHtml = sprintf(
                '<tr>
                    <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Order #</td>
                    <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; font-family: monospace; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">%s</td>
                </tr>',
                e((string) $coNumber)
            );
        }

        $titleRowHtml = '';
        if ($coTitle !== null && trim($coTitle) !== '') {
            $titleRowHtml = sprintf(
                '<tr>
                    <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Title</td>
                    <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">%s</td>
                </tr>',
                e($coTitle)
            );
        }

        return <<<HTML
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
            <tr>
                <td class="email-meta-td" style="padding: 18px 20px; overflow-wrap: break-word; word-break: break-word;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%;">
                        <tr>
                            <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Project</td>
                            <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 700; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$siteName}</td>
                        </tr>
                        {$psRowHtml}
                        {$numberRowHtml}
                        {$titleRowHtml}
                        <tr>
                            <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Market</td>
                            <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$marketName}</td>
                        </tr>
                        <tr>
                            <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Submitted By</td>
                            <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$creatorName}</td>
                        </tr>
                        <tr>
                            <td class="email-meta-label" style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Date</td>
                            <td class="email-meta-value" style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$sentDate}</td>
                        </tr>
                        <tr>
                            <td class="email-meta-label" style="color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: middle;">Total Amount</td>
                            <td class="email-meta-value" style="vertical-align: middle; overflow-wrap: break-word; word-break: break-word;">
                                <span style="color: #16a34a; font-size: 16px; font-weight: 800; font-family: monospace;">
                                    {$totalFormatted}
                                </span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
HTML;
    }

    private function renderChangeOrderDescriptionBox(string $description): string
    {
        if (trim($description) === '') {
            return '';
        }

        return sprintf(
            '<div style="margin: 16px 0; padding: 14px 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-left: 3px solid #22c55e; border-radius: 0 6px 6px 0; font-size: 13px; color: #374151; line-height: 1.6; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #16a34a; margin-bottom: 6px; letter-spacing: 0.5px;">Scope Notes</div>
                %s
            </div>',
            $this->formatNarrativeText(trim($description))
        );
    }

    private function renderChangeOrderItemsTable(?ChangeOrder $changeOrder): string
    {
        if ($changeOrder === null) {
            return '';
        }

        $items = $changeOrder->items
            ->where('is_included', true)
            ->values();

        if ($items->isEmpty()) {
            return '<p style="font-size: 13px; color: #6b7280; font-style: italic;">No included items found.</p>';
        }

        $rowsHtml = '';
        foreach ($items as $index => $item) {
            $num = $index + 1;
            $vendors = $item->vendor_name;
            $vendor = is_array($vendors) && $vendors !== [] ? e(implode(', ', $vendors)) : '—';
            $vendorBadge = $vendor !== '—'
                ? sprintf('<span style="display: inline-block; font-size: 11px; color: #4b5563; background-color: #f3f4f6; border: 1px solid #e5e7eb; padding: 2px 7px; border-radius: 4px; font-weight: 500; margin-right: 6px; margin-top: 4px;"><strong style="color: #374151;">Vendor:</strong> %s</span>', $vendor)
                : '';
            $category = $item->category !== null && $item->category !== '' && $item->category !== 'Service' ? e($item->category) : null;
            $categoryBadge = $category !== null
                ? sprintf('<span style="display: inline-block; font-size: 11px; color: #4b5563; background-color: #e5e7eb; padding: 2px 7px; border-radius: 4px; font-weight: 500; margin-right: 6px; margin-top: 4px;">%s</span>', $category)
                : '';
            $amountFormatted = $item->cost_absorbed_by_solucomp
                ? '<span style="color: #16a34a; font-weight: 600; font-size: 12px;">$0.00 (Cost Absorbed)</span>'
                : '$'.number_format((float) $item->amount, 2);
            $bgColor = $index % 2 === 0 ? '#ffffff' : '#f9fafb';
            // Clamped before escaping/formatting: the ellipsis must not be
            // escaped, and formatNarrativeText handles the <br /> conversion.
            $itemDescription = $this->formatNarrativeText(
                $this->truncateForEmail((string) $item->description)
            );
            $itemDescription .= $this->renderEmailDescriptionFooter($item->description);

            $metaBadgesHtml = '';
            if ($vendorBadge !== '' || $categoryBadge !== '') {
                $metaBadgesHtml = sprintf('<div style="margin-top: 4px; line-height: 1.4;">%s%s</div>', $vendorBadge, $categoryBadge);
            }

            $rowsHtml .= <<<HTML
            <tr style="background-color: {$bgColor};">
                <td class="email-item-col" style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-weight: 600; font-size: 12px; vertical-align: top; text-align: center; width: 28px;">{$num}</td>
                <td class="email-item-col" style="padding: 10px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; word-break: break-word;">
                    <div style="font-weight: 600; color: #111827; font-size: 13px; line-height: 1.45; word-break: break-word;">{$itemDescription}</div>
                    {$metaBadgesHtml}
                </td>
                <td class="email-item-col" style="padding: 10px 10px; border-bottom: 1px solid #e5e7eb; text-align: right; font-family: monospace; font-weight: 700; color: #111827; font-size: 13px; vertical-align: top; white-space: nowrap; width: 90px;">{$amountFormatted}</td>
            </tr>
HTML;
        }

        $totalFormatted = '$'.number_format((float) $changeOrder->total_amount, 2);

        return <<<HTML
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%; border: 1px solid #e5e7eb; border-radius: 6px; border-collapse: separate; border-spacing: 0; overflow: hidden; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: center; font-size: 11px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.5px; width: 28px; white-space: nowrap;">#</th>
                    <th style="padding: 10px 10px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 11px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.5px;">Item &amp; Details</th>
                    <th style="padding: 10px 10px; border-bottom: 1px solid #e5e7eb; text-align: right; font-size: 11px; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.5px; width: 90px; white-space: nowrap;">Amount</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
            <tfoot>
                <tr style="background-color: #f3f4f6;">
                    <td colspan="2" style="padding: 12px 14px; text-align: right; font-weight: 700; color: #111827; font-size: 13px; border-top: 2px solid #e5e7eb; white-space: nowrap;">
                        Total Amount:
                    </td>
                    <td style="padding: 12px 10px; text-align: right; font-family: monospace; font-weight: 800; color: #16a34a; font-size: 15px; border-top: 2px solid #e5e7eb; white-space: nowrap;">
                        {$totalFormatted}
                    </td>
                </tr>
            </tfoot>
        </table>
HTML;
    }

    private function renderIssueForwardEmail(SendEmailDTO $dto): string
    {
        $project = $this->resolveProject($dto);
        $siteName = $project->name ?? $this->parseSubjectPart($dto->subject, 0, 'Cell Site');
        $marketName = $project->market->name ?? $this->parseSubjectPart($dto->subject, 1, 'Verizon Market');
        $psNumber = $project->ps_number ?? ($project !== null ? 'PS-'.$project->id : null);
        $forwardedByName = $dto->forwardedByName ?? 'Project Manager';
        $forwardedByRole = $dto->forwardedByRole ?? 'Manager';
        $actionUrl = $dto->actionUrl ?? '';

        ['html' => $issueCardsHtml, 'count' => $count] = $this->buildForwardedIssueCards($dto, $project);
        $plural = $count === 1 ? 'issue' : 'issues';
        $forwardedByLabel = e($forwardedByName).' <span style="color: #8b909a; font-weight: 400;">('.e(ucfirst((string) $forwardedByRole)).')</span>';
        $metaTableHtml = $this->renderIssueForwardMetaTable($siteName, $psNumber, $marketName, $forwardedByLabel);
        $managerNoteHtml = $this->renderIssueForwardNote($dto->managerNote, $forwardedByName);
        $ctaHtml = $actionUrl !== ''
            ? $this->renderButton($actionUrl, 'View on Customer Portal', 'Secure project link &bull; Verizon authorized access')
            : '';

        $contentHtml = <<<HTML
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.65; overflow-wrap: break-word; word-break: break-word;">
                {$forwardedByLabel} forwarded <strong style="color: #111827;">{$count} {$plural}</strong> from <strong style="color: #111827;">{$siteName}</strong> for your review and guidance.
            </p>

            {$metaTableHtml}

            {$managerNoteHtml}

            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin: 24px 0 10px 0;">
                Forwarded Issues ({$count})
            </div>

            {$issueCardsHtml}

            {$ctaHtml}

            <div style="margin: 24px 0 0 0; padding: 14px 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px; color: #6b7280; line-height: 1.5; overflow-wrap: break-word; word-break: break-word;">
                <strong style="color: #374151;">How to respond:</strong> Reply directly to this email. Your reply will be received by the project manager and admin team for follow-up on site.
            </div>
HTML;

        $signOffName = $forwardedByName.' · SoluComp Project Management';

        return $this->renderBaseTemplate($dto->subject, 'Issues Forwarded for Your Review', $contentHtml, $signOffName);
    }

    /**
     * @return array{html: string, count: int}
     */
    private function buildForwardedIssueCards(SendEmailDTO $dto, ?Project $project): array
    {
        $issueIds = $this->extractForwardedIssueIds($dto->contextId);
        if ($issueIds === [] || $project === null) {
            return [
                'html' => sprintf(
                    '<div style="border: 1px dashed #d1d5db; border-radius: 8px; padding: 18px; text-align: center; font-size: 13px; color: #6b7280; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;">%s</div>',
                    $this->formatNarrativeText($dto->body !== '' ? $dto->body : 'Issues forwarded for your review.')
                ),
                'count' => 1,
            ];
        }

        $issues = IssueHistory::query()
            ->whereIn('id', $issueIds)
            ->where('project_id', $project->id)
            ->with(['reporter'])
            ->get()
            ->sortBy(fn (IssueHistory $issue): int => array_search($issue->id, $issueIds, true) ?: PHP_INT_MAX)
            ->values();

        $html = '';
        foreach ($issues as $index => $issue) {
            $html .= $this->renderSingleForwardedIssueCard($issue, $index + 1);
        }

        return [
            'html' => $html,
            'count' => max($issues->count(), 1),
        ];
    }

    /**
     * @return list<int>
     */
    private function extractForwardedIssueIds(?string $contextId): array
    {
        if (! is_string($contextId) || $contextId === '') {
            return [];
        }

        return array_values(array_map('intval', array_filter(
            array_map('trim', explode(',', $contextId)),
            fn (string $v): bool => $v !== '' && is_numeric($v)
        )));
    }

    private function renderSingleForwardedIssueCard(IssueHistory $issue, int $num): string
    {
        $typeValue = $issue->type instanceof \BackedEnum ? $issue->type->value : (string) $issue->type;
        $typeBadge = $this->issueForwardTypeBadge($typeValue);
        $statusBadge = $this->issueForwardStatusBadge($issue->resolved_at !== null);
        $reporterName = e($issue->reporter?->name ?? 'Field Team');
        $createdAt = $issue->created_at?->format('M j, Y \a\t g:i A') ?? '';
        $description = $this->formatNarrativeText($issue->description ?? '');
        $guidanceHtml = $this->renderIssueForwardGuidance($issue->verizon_guidance);

        return <<<HTML
        <div class="issue-card" style="border: 1px solid #e5e7eb; border-radius: 8px; background-color: #f9fafb; padding: 16px 18px; margin-bottom: 12px; overflow-wrap: break-word; word-break: break-word;">
            <div class="issue-card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                <div class="issue-card-header-left" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; row-gap: 6px;">
                    <span style="font-size: 11px; font-weight: 700; color: #6b7280; margin-right: 2px;">#{$num}</span>
                    {$typeBadge}
                </div>
                {$statusBadge}
            </div>
            <div style="font-size: 13px; color: #111827; line-height: 1.6; white-space: pre-wrap; word-break: break-word; overflow-wrap: break-word;">{$description}</div>
            {$guidanceHtml}
            <div style="margin-top: 10px; font-size: 11px; color: #6b7280; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; overflow-wrap: break-word; word-break: break-word;">
                <span>Reported by <strong style="color: #111827;">{$reporterName}</strong></span>
                <span style="color: #9ca3af;">&bull;</span>
                <span>{$createdAt}</span>
            </div>
        </div>
HTML;
    }

    private function renderIssueForwardGuidance(?string $guidance): string
    {
        if (! is_string($guidance) || trim($guidance) === '') {
            return '';
        }

        return sprintf(
            '<div style="margin-top: 10px; padding: 10px 12px; background-color: #eff6ff; border: 1px solid #bfdbfe; border-left: 3px solid #3b82f6; border-radius: 0 6px 6px 0; font-size: 12px; color: #1e3a8a; line-height: 1.5; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;">
                <strong style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #1d4ed8;">Verizon Guidance:</strong>
                <div style="margin-top: 4px; overflow-wrap: break-word; word-break: break-word;">%s</div>
            </div>',
            $this->formatNarrativeText(trim($guidance))
        );
    }

    private function renderIssueForwardMetaTable(string $siteName, ?string $psNumber, string $marketName, string $forwardedByLabel): string
    {
        $psRowHtml = '';
        if ($psNumber !== null && $psNumber !== '') {
            $psRowHtml = sprintf(
                '<tr><td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">PS Number</td><td style="padding-bottom: 10px; color: #111827; font-weight: 600; font-family: monospace; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">%s</td></tr>',
                e($psNumber)
            );
        }

        return <<<HTML
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 18px; font-size: 13px;">
            <tr>
                <td style="padding: 18px 20px; overflow-wrap: break-word; word-break: break-word;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="table-layout: fixed; width: 100%;">
                        <tr>
                            <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Site</td>
                            <td style="padding-bottom: 10px; color: #111827; font-weight: 700; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$siteName}</td>
                        </tr>
                        {$psRowHtml}
                        <tr>
                            <td style="padding-bottom: 10px; color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Market</td>
                            <td style="padding-bottom: 10px; color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$marketName}</td>
                        </tr>
                        <tr>
                            <td style="color: #6b7280; font-weight: 500; white-space: nowrap; width: 110px; padding-right: 16px; vertical-align: top;">Forwarded By</td>
                            <td style="color: #111827; font-weight: 600; vertical-align: top; overflow-wrap: break-word; word-break: break-word;">{$forwardedByLabel}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
HTML;
    }

    private function renderIssueForwardNote(?string $managerNote, string $forwardedByName): string
    {
        if (! is_string($managerNote) || trim($managerNote) === '') {
            return '';
        }

        return sprintf(
            '<div style="margin: 18px 0; padding: 14px 16px; background-color: #fffbeb; border: 1px solid #fde68a; border-left: 3px solid #f59e0b; border-radius: 0 8px 8px 0; overflow-wrap: break-word; word-break: break-word;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #92400e; margin-bottom: 6px;">Note from %s</div>
                <div style="font-size: 13px; color: #78350f; line-height: 1.6; white-space: pre-wrap; word-break: break-word; overflow-wrap: break-word;">%s</div>
            </div>',
            e($forwardedByName),
            $this->formatNarrativeText(trim($managerNote))
        );
    }

    private function issueForwardTypeBadge(string $type): string
    {
        $map = [
            'lock' => ['label' => 'Lock Issue', 'bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#fde68a'],
            'site_access' => ['label' => 'Site Access', 'bg' => '#ffedd5', 'color' => '#9a3412', 'border' => '#fed7aa'],
            'construction' => ['label' => 'Construction', 'bg' => '#dbeafe', 'color' => '#1e40af', 'border' => '#bfdbfe'],
            'verizon_guidance' => ['label' => 'Verizon Guidance', 'bg' => '#f3e8ff', 'color' => '#6b21a8', 'border' => '#e9d5ff'],
            'other' => ['label' => 'General Issue', 'bg' => '#e0f2fe', 'color' => '#0c4a6e', 'border' => '#bae6fd'],
        ];

        $cfg = $map[$type] ?? $map['other'];

        return sprintf(
            '<span class="type-badge" style="display: inline-block; background-color: %s; color: %s; border: 1px solid %s; font-weight: 700; font-size: 11px; padding: 3px 10px; border-radius: 12px; white-space: nowrap; margin-right: 2px;">%s</span>',
            $cfg['bg'],
            $cfg['color'],
            $cfg['border'],
            e($cfg['label'])
        );
    }

    private function issueForwardStatusBadge(bool $isResolved): string
    {
        if ($isResolved) {
            return '<span class="status-badge" style="display: inline-block; background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; font-size: 10px; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; margin-left: 6px;">Resolved</span>';
        }

        return '<span class="status-badge" style="display: inline-block; background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 700; font-size: 10px; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; margin-left: 6px;">Open</span>';
    }
}
