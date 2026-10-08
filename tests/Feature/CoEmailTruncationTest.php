<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\EmailTemplateRenderer;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('clamps a 2000-char item description to a 50-char budget in the email and flags the truncation', function (): void {
    $project = Project::factory()->create(['name' => 'Cell Site Alpha']);
    $creator = User::factory()->create(['name' => 'PM Manager']);

    $changeOrder = ChangeOrder::factory()->for($project)->create([
        'created_by' => $creator->id,
        'title' => 'Emergency Tower Reinforcement',
        'total_amount' => 35000.00,
    ]);

    // 50 x 42 = 2100 chars, i.e. over the 2000-char field limit (max:2000
    // counts the trailing space away, so stay comfortably past it).
    $full = str_repeat('Structural steel gusset plates replacement. ', 50);

    ChangeOrderItem::factory()->for($changeOrder)->create([
        'description' => $full,
        'vendor_name' => ['Vendor Alpha'],
        'amount' => 35000.00,
        'is_included' => true,
    ]);

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Change Order Approval Request',
        type: EmailType::CHANGE_ORDER,
        body: '',
        contextId: (string) $changeOrder->id,
        actionUrl: 'https://example.com/co/decide',
    ));

    // A 50-char budget fits exactly ONE 43-char phrase plus the ellipsis.
    // It must not carry a second phrase, nor a half-word ("...replacement. S").
    expect(substr_count($html, 'Structural steel gusset plates replacement.'))->toBe(1)
        ->and($html)->toContain('Structural steel gusset plates replacement.…')
        ->and($html)->not->toContain('replacement. S')
        ->and(mb_strlen('Structural steel gusset plates replacement.…'))->toBeLessThanOrEqual(50);

    // The ellipsis proves it was clamped rather than rendered whole.
    expect($html)->toContain('…');

    // And the customer is told the text was shortened, with the real count.
    expect($html)->toContain('Full description (250 words)')
        ->and($html)->toContain('open the review page to read it in full');

    // The full description is NOT present anywhere in the email.
    expect(strlen($full))->toBeGreaterThan(2000);
    expect($html)->not->toContain($full);
});

it('leaves a short item description untouched and adds no truncation footer', function (): void {
    $project = Project::factory()->create(['name' => 'Cell Site Bravo']);
    $creator = User::factory()->create(['name' => 'PM Manager']);

    $changeOrder = ChangeOrder::factory()->for($project)->create([
        'created_by' => $creator->id,
        'title' => 'Small Fix',
        'total_amount' => 100.00,
    ]);

    ChangeOrderItem::factory()->for($changeOrder)->create([
        'description' => 'Replace one missing bolt.',
        'vendor_name' => ['Vendor Beta'],
        'amount' => 100.00,
        'is_included' => true,
    ]);

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Change Order Approval Request',
        type: EmailType::CHANGE_ORDER,
        body: '',
        contextId: (string) $changeOrder->id,
        actionUrl: 'https://example.com/co/decide',
    ));

    expect($html)->toContain('Replace one missing bolt.')
        ->and($html)->not->toContain('Full description')
        ->and($html)->not->toContain('…');
});

it('clamps on a word boundary so a vendor name is never split mid-word', function (): void {
    $project = Project::factory()->create(['name' => 'Cell Site Charlie']);
    $creator = User::factory()->create(['name' => 'PM Manager']);

    $changeOrder = ChangeOrder::factory()->for($project)->create([
        'created_by' => $creator->id,
        'title' => 'Word Boundary Test',
        'total_amount' => 500.00,
    ]);

    // Word 51 onwards is "Installation" — must not survive as a fragment.
    $words = array_merge(
        array_fill(0, 50, 'alpha'),
        array_fill(0, 5, 'Installation'),
    );

    ChangeOrderItem::factory()->for($changeOrder)->create([
        'description' => implode(' ', $words),
        'vendor_name' => ['Vendor Gamma'],
        'amount' => 500.00,
        'is_included' => true,
    ]);

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Change Order Approval Request',
        type: EmailType::CHANGE_ORDER,
        body: '',
        contextId: (string) $changeOrder->id,
        actionUrl: 'https://example.com/co/decide',
    ));

    // "Installation" starts at word 51, so it must not appear in any form.
    expect($html)->not->toContain('Installatio')
        ->and($html)->not->toContain('Install</div>')
        ->and($html)->toContain('…');
});
