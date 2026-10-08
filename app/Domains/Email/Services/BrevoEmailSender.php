<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Shared\Exceptions\EmailDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Sends emails through the Brevo transactional API (POST /v3/smtp/email).
 */
final class BrevoEmailSender
{
    public function send(
        SendEmailDTO $dto,
        ?string $apiKey = null,
        ?string $fromAddress = null,
        ?string $fromName = null,
    ): ?string {
        $key = (is_string($apiKey) && $apiKey !== '') ? $apiKey : config('services.brevo.key');
        $url = config('services.brevo.url') ?: 'https://api.brevo.com/v3/smtp/email';

        if (! is_string($key) || $key === '') {
            throw new EmailDeliveryException('Brevo API is not configured.');
        }

        $senderEmail = (is_string($fromAddress) && $fromAddress !== '')
            ? $fromAddress
            : config('mail.from.address');

        $senderName = (is_string($fromName) && $fromName !== '')
            ? $fromName
            : config('mail.from.name');

        if (! is_string($senderEmail) || $senderEmail === '') {
            throw new EmailDeliveryException('Sender email address is required for Brevo.');
        }

        $payload = $this->payload(
            $dto,
            $senderEmail,
            is_string($senderName) && $senderName !== '' ? $senderName : $senderEmail
        );

        $response = Http::withHeaders([
            'api-key' => $key,
            'accept' => 'application/json',
        ])
            ->timeout(60)
            ->post((string) $url, $payload);

        if ($response->failed()) {
            throw new EmailDeliveryException(sprintf(
                'Brevo API error (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }

        $messageId = $response->json('messageId');

        return is_string($messageId) && $messageId !== '' ? $messageId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SendEmailDTO $dto, string $senderEmail, string $senderName): array
    {
        $toName = is_string($dto->toName) && $dto->toName !== '' ? $dto->toName : $dto->to;

        $htmlContent = app(EmailTemplateRenderer::class)->render($dto);

        $payload = [
            'sender' => [
                'name' => $senderName,
                'email' => $senderEmail,
            ],
            'to' => [[
                'name' => $toName,
                'email' => $dto->to,
            ]],
            'subject' => $dto->subject,
            'textContent' => $dto->body,
            'htmlContent' => $htmlContent,
        ];

        if ($dto->cc !== []) {
            $payload['cc'] = array_map(
                fn (string $email): array => ['email' => $email],
                $dto->cc,
            );
        }

        $attachment = $this->attachment($dto);

        if ($attachment !== null) {
            $payload['attachment'] = [$attachment];
        }

        return $payload;
    }

    /**
     * @return array{name: string, content?: string, url?: string}|null
     */
    private function attachment(SendEmailDTO $dto): ?array
    {
        if ($dto->attachmentUrl !== null && $dto->attachmentUrl !== '') {
            return [
                'name' => $dto->attachmentFilename() ?? 'Closeout-Package.pdf',
                'url' => $dto->attachmentUrl,
            ];
        }

        if ($dto->attachmentPath === null || $dto->attachmentPath === '') {
            return null;
        }

        $content = Storage::disk('private')->get($dto->attachmentPath);

        if (! is_string($content) || $content === '') {
            throw new EmailDeliveryException('Attachment file not found: '.$dto->attachmentPath);
        }

        return [
            'name' => $dto->attachmentName ?? basename($dto->attachmentPath),
            'content' => base64_encode($content),
        ];
    }
}
