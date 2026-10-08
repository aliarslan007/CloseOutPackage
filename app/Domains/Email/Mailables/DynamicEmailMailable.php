<?php

declare(strict_types=1);

namespace App\Domains\Email\Mailables;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DynamicEmailMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SendEmailDTO $dto,
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        $from = null;
        $effectiveFromAddress = $this->fromAddress ?: (is_string(config('mail.from.address')) ? config('mail.from.address') : null);
        $effectiveFromName = $this->fromName ?: (is_string(config('mail.from.name')) ? config('mail.from.name') : null);

        if (is_string($effectiveFromAddress) && $effectiveFromAddress !== '') {
            $from = new Address(
                $effectiveFromAddress,
                is_string($effectiveFromName) && $effectiveFromName !== '' ? $effectiveFromName : $effectiveFromAddress
            );
        }

        $toName = is_string($this->dto->toName) && $this->dto->toName !== '' ? $this->dto->toName : $this->dto->to;
        $to = [new Address($this->dto->to, $toName)];

        $cc = [];
        foreach ($this->dto->cc as $ccAddress) {
            $cc[] = new Address($ccAddress);
        }

        return new Envelope(
            from: $from,
            to: $to,
            cc: $cc,
            subject: $this->dto->subject,
        );
    }

    public function content(): Content
    {
        $html = app(EmailTemplateRenderer::class)->render($this->dto);

        return new Content(
            htmlString: $html,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->dto->attachmentPath === null || $this->dto->attachmentPath === '') {
            return [];
        }

        $content = Storage::disk('private')->get($this->dto->attachmentPath);

        if (! is_string($content) || $content === '') {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => $content,
                $this->dto->attachmentFilename() ?? 'Closeout-Package.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
