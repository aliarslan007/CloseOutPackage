<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The PDF attachment policy, applied at delivery time: attachments that are
 * missing or oversized are replaced by a fresh signed download link.
 *
 * Both the outbox preview (SendCopEmailAction) and the actual send
 * (DeliverCustomerEmailAction) use this, so what the manager previews is
 * what the customer receives — with the link generated moments before the
 * email leaves, never stored expiring in the database.
 */
final class EmailAttachmentPolicy
{
    public function apply(SendEmailDTO $dto): SendEmailDTO
    {
        if ($dto->attachmentPath === null || $dto->closeoutPackageId === null) {
            return $dto;
        }

        /** @var int $maxBytes */
        $maxBytes = config('cop.email_attachment_max_bytes', 15 * 1024 * 1024);
        $disk = Storage::disk('private');

        if ($disk->exists($dto->attachmentPath) && $disk->size($dto->attachmentPath) <= $maxBytes) {
            return $dto;
        }

        return $this->withSignedLink($dto);
    }

    private function withSignedLink(SendEmailDTO $dto): SendEmailDTO
    {
        $type = $dto->type === EmailType::PROGRESS_PDF ? 'partial' : 'final';
        $link = URL::signedRoute('pdf.signed', [
            'package' => $dto->closeoutPackageId,
            'type' => $type,
        ], now()->addDays(7));

        return new SendEmailDTO(
            to: $dto->to,
            toName: $dto->toName,
            cc: $dto->cc,
            subject: $dto->subject,
            type: $dto->type,
            body: $dto->body."\n\nDownload your PDF package here: {$link}",
            closeoutPackageId: $dto->closeoutPackageId,
            projectId: $dto->projectId,
            contextId: $dto->contextId,
            attachmentPath: null,
            attachmentUrl: $link,
        );
    }
}
