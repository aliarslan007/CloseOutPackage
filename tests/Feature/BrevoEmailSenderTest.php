<?php

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\BrevoEmailSender;
use App\Shared\Exceptions\EmailDeliveryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.brevo.key', 'test-brevo-key');
    config()->set('services.brevo.url', 'https://api.brevo.test/v3/smtp/email');
    config()->set('mail.from.address', 'from@example.com');
    config()->set('mail.from.name', 'Solucomp');
    Storage::fake('private');
});

it('posts the full payload to the Brevo API with the api-key header', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm1'], 201),
    ]);

    app(BrevoEmailSender::class)->send(new SendEmailDTO(
        to: 'to@example.com',
        toName: 'John Doe',
        cc: ['cc1@example.com', 'cc2@example.com'],
        subject: 'Subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'Body text',
    ));

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.brevo.test/v3/smtp/email'
            && $request->header('api-key')[0] === 'test-brevo-key'
            && $data['sender'] === ['name' => 'Solucomp', 'email' => 'from@example.com']
            && $data['to'] === [['name' => 'John Doe', 'email' => 'to@example.com']]
            && $data['cc'] === [['email' => 'cc1@example.com'], ['email' => 'cc2@example.com']]
            && $data['subject'] === 'Subject'
            && $data['textContent'] === 'Body text'
            && ! isset($data['attachment']);
    });
});

it('attaches the file as base64 when the DTO has an attachment path', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm1'], 201),
    ]);

    Storage::disk('private')->put('pdfs/1/final.pdf', 'pdf-content');

    app(BrevoEmailSender::class)->send(new SendEmailDTO(
        to: 'to@example.com',
        subject: 'Subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'Body',
        attachmentPath: 'pdfs/1/final.pdf',
    ));

    Http::assertSent(function (Request $request): bool {
        return ($request->data()['attachment'] ?? null) === [[
            'name' => 'final.pdf',
            'content' => base64_encode('pdf-content'),
        ]];
    });
});

it('throws EmailDeliveryException on a failed Brevo response', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    expect(fn () => app(BrevoEmailSender::class)->send(new SendEmailDTO(
        to: 'to@example.com',
        subject: 'Subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'Body',
    )))->toThrow(EmailDeliveryException::class, 'Brevo API error (HTTP 401)');
});

it('throws EmailDeliveryException when Brevo is not configured', function (): void {
    config()->set('services.brevo.key', null);

    expect(fn () => app(BrevoEmailSender::class)->send(new SendEmailDTO(
        to: 'to@example.com',
        subject: 'Subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'Body',
    )))->toThrow(EmailDeliveryException::class, 'Brevo API is not configured.');
});
