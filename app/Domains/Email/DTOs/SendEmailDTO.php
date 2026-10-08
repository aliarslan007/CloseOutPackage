<?php

declare(strict_types=1);

namespace App\Domains\Email\DTOs;

use App\Domains\Email\Enums\EmailType;

final readonly class SendEmailDTO
{
    /**
     * @param  list<string>  $cc
     */
    public function __construct(
        public string $to,
        public ?string $toName = null,
        public array $cc = [],
        public string $subject = '',
        public EmailType $type = EmailType::GENERAL,
        public string $body = '',
        public ?int $closeoutPackageId = null,
        public ?int $projectId = null,
        public ?string $contextId = null,
        public ?string $attachmentPath = null,
        public ?string $attachmentUrl = null,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?string $managerNote = null,
        public ?string $forwardedByName = null,
        public ?string $forwardedByRole = null,
        /** Client-facing attachment name; falls back to the stored file's name. */
        public ?string $attachmentName = null,
    ) {}

    /**
     * The filename the recipient sees, independent of how the file is stored.
     */
    public function attachmentFilename(): ?string
    {
        if ($this->attachmentName !== null && $this->attachmentName !== '') {
            return $this->attachmentName;
        }

        return $this->attachmentPath !== null && $this->attachmentPath !== ''
            ? basename($this->attachmentPath)
            : null;
    }

    public function withAttachment(string $path): self
    {
        return new self(
            to: $this->to,
            toName: $this->toName,
            cc: $this->cc,
            subject: $this->subject,
            type: $this->type,
            body: $this->body,
            closeoutPackageId: $this->closeoutPackageId,
            projectId: $this->projectId,
            contextId: $this->contextId,
            attachmentPath: $path,
            attachmentUrl: $this->attachmentUrl,
            actionUrl: $this->actionUrl,
            actionLabel: $this->actionLabel,
            managerNote: $this->managerNote,
            forwardedByName: $this->forwardedByName,
            forwardedByRole: $this->forwardedByRole,
            attachmentName: $this->attachmentName,
        );
    }

    /**
     * Serialize for storage in email_logs.payload, so the outbox can deliver
     * the email later without rebuilding it from live relations. Contains no
     * secrets: change-order tokens are never part of a DTO.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'toName' => $this->toName,
            'cc' => $this->cc,
            'subject' => $this->subject,
            'type' => $this->type->value,
            'body' => $this->body,
            'closeoutPackageId' => $this->closeoutPackageId,
            'projectId' => $this->projectId,
            'contextId' => $this->contextId,
            'attachmentPath' => $this->attachmentPath,
            'attachmentUrl' => $this->attachmentUrl,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel,
            'managerNote' => $this->managerNote,
            'forwardedByName' => $this->forwardedByName,
            'forwardedByRole' => $this->forwardedByRole,
            'attachmentName' => $this->attachmentName,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            to: (string) ($data['to'] ?? ''),
            toName: isset($data['toName']) ? (string) $data['toName'] : null,
            cc: array_values(array_map(strval(...), (array) ($data['cc'] ?? []))),
            subject: (string) ($data['subject'] ?? ''),
            type: EmailType::from((string) ($data['type'] ?? EmailType::GENERAL->value)),
            body: (string) ($data['body'] ?? ''),
            closeoutPackageId: isset($data['closeoutPackageId']) ? (int) $data['closeoutPackageId'] : null,
            projectId: isset($data['projectId']) ? (int) $data['projectId'] : null,
            contextId: isset($data['contextId']) ? (string) $data['contextId'] : null,
            attachmentPath: isset($data['attachmentPath']) ? (string) $data['attachmentPath'] : null,
            attachmentUrl: isset($data['attachmentUrl']) ? (string) $data['attachmentUrl'] : null,
            actionUrl: isset($data['actionUrl']) ? (string) $data['actionUrl'] : null,
            actionLabel: isset($data['actionLabel']) ? (string) $data['actionLabel'] : null,
            managerNote: isset($data['managerNote']) ? (string) $data['managerNote'] : null,
            forwardedByName: isset($data['forwardedByName']) ? (string) $data['forwardedByName'] : null,
            forwardedByRole: isset($data['forwardedByRole']) ? (string) $data['forwardedByRole'] : null,
            attachmentName: isset($data['attachmentName']) && is_string($data['attachmentName']) ? $data['attachmentName'] : null,
        );
    }
}
