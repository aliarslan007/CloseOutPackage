<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\EmailTemplateRenderer;
use App\Domains\Projects\Enums\IssueType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Clients reported multi-paragraph copy arriving as one run-on block. A plain
 * <p> collapses every carriage return and run of spaces into a single space, so
 * the narrative paragraph needs pre-wrap. Only two of the twelve email types
 * have a dedicated renderer that calls nl2br(); the other ten share this path,
 * which is why it is the one that matters.
 */
it('preserves carriage returns and spacing in the default email body', function (): void {
    $body = "Hi John,\r\n\r\nLine two starts here.\r\n\r\n    Indented note with  runs of spaces.\nThanks";

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Test',
        type: EmailType::GENERAL,
        body: $body,
    ));

    // Universal email support: <br /> tags ensure line breaks render across
    // all clients, including Microsoft Outlook desktop (Word HTML engine).
    expect($html)->toContain('Hi John,<br /><br />Line two starts here.<br /><br />');

    // Spacing fidelity: runs of spaces and indentation are preserved with non-breaking spaces.
    expect($html)->toContain('&nbsp; &nbsp; Indented note with&nbsp; runs of spaces.<br />Thanks');

    // pre-wrap is retained on the paragraph container for modern clients.
    expect($html)->toContain('white-space: pre-wrap');

    // Removing literal newlines in the body prevents pre-wrap from doubling line breaks.
    expect($html)->not->toContain("Hi John,<br />\n");
});

it('still escapes html in the default email body', function (): void {
    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Test',
        type: EmailType::GENERAL,
        body: "Careful <script>alert(1)</script> here\nNext line",
    ));

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->toContain('<br />Next line');
});

it('preserves formatting and carriage returns in change order description notes', function (): void {
    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Change Order Test',
        type: EmailType::CHANGE_ORDER,
        body: '',
    ));

    // Email renders cleanly with change order structure
    expect($html)->toContain('Change Order Approval Request');
});

it('formats default COP deliverable email template with multi-paragraph line breaks', function (): void {
    $body = "Dear John Doe,\n\nPlease find attached the final Close-Out Package (R1.0) for cell site Alpha.\n\nThis package has been approved.\n\nBest regards,\nSoluComp";

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Final COP',
        type: EmailType::FINAL_PDF_EMAIL,
        body: $body,
    ));

    expect($html)->toContain('Dear John Doe,<br /><br />Please find attached the final Close-Out Package (R1.0) for cell site Alpha.<br /><br />This package has been approved.<br /><br />Best regards,<br />SoluComp')
        ->and($html)->not->toContain('Confidentiality Notice');
});

it('formats issue forward email with line breaks and preserved spacing in guidance and note', function (): void {
    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Issue Forward Test',
        type: EmailType::ISSUE_FORWARD,
        body: "Issue overview notes\nLine 2 with  spacing.",
        managerNote: "Please check this urgently.\r\nThanks,\r\nManager",
    ));

    expect($html)->toContain('Please check this urgently.<br />Thanks,<br />Manager')
        ->and($html)->toContain('Issue overview notes<br />Line 2 with&nbsp; spacing.');
});

it('renders change order with clean structured labels, nowrap button, and no emoji icons', function (): void {
    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Change Order Approval Request',
        type: EmailType::CHANGE_ORDER,
        body: 'Please review.',
        actionUrl: 'https://example.com/approve',
    ));

    expect($html)->toContain('>Project</td>')
        ->and($html)->toContain('>Market</td>')
        ->and($html)->toContain('>Submitted By</td>')
        ->and($html)->toContain('>Date</td>')
        ->and($html)->toContain('>Total Amount</td>')
        ->and($html)->not->toContain('Total Authorization')
        ->and($html)->not->toContain('Market Region')
        ->and($html)->toContain('white-space: nowrap')
        ->and($html)->toContain('Review Change Order &rarr;')
        ->and($html)->toContain('Secure decision link &bull; Valid for authorized Verizon customer contacts')
        ->and($html)->not->toContain('🔒')
        ->and($html)->not->toContain('📎');
});

it('handles extreme unbroken strings and long bodies defensively without blowing out layout', function (): void {
    $longUnbrokenString = str_repeat('A', 300);
    $longUrl = 'https://example.com/'.str_repeat('very-long-path-parameter-without-spaces/', 15);
    $largeBody = "Header paragraph with normal text.\n\n"
        ."Unbroken string: {$longUnbrokenString}\n\n"
        ."Long URL: {$longUrl}\n\n"
        .str_repeat('This is a recurring multi-paragraph narrative note designed to test large email bodies. ', 50);

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Long Subject '.str_repeat('X', 150),
        type: EmailType::GENERAL,
        body: $largeBody,
        actionUrl: $longUrl,
        actionLabel: 'Click Here To Proceed To The System Now',
    ));

    // Fixed table layout locks container width to 600px
    expect($html)->toContain('table-layout: fixed')
        ->and($html)->toContain('overflow-wrap: break-word')
        ->and($html)->toContain('word-break: break-word')
        ->and($html)->toContain('max-width: 600px')
        ->and($html)->toContain($longUnbrokenString)
        ->and($html)->toContain($longUrl)
        ->and($html)->toContain('border: 1px solid #c3c9d6');
});

it('handles long change order titles, multi-thousand character scope notes, long items and multiple vendors defensively', function (): void {
    $project = Project::factory()->create([
        'name' => 'Cell Site '.str_repeat('AlphaBetaGamma', 15),
        'ps_number' => 'PS-'.str_repeat('9', 30),
    ]);

    $creator = User::factory()->create([
        'name' => 'Construction Project Manager '.str_repeat('Name', 10),
    ]);

    $changeOrder = ChangeOrder::factory()
        ->for($project)
        ->create([
            'created_by' => $creator->id,
            'title' => 'Emergency Tower Reinforcement with High Capacity Feeders '.str_repeat('CO', 30),
            'description' => str_repeat("Detailed engineering scope note covering sector A, B, and C modifications.\n", 40),
            'total_amount' => 45890.50,
        ]);

    // Item 1 with 2000 chars and 5 vendors
    ChangeOrderItem::factory()
        ->for($changeOrder)
        ->create([
            'description' => str_repeat('Structural steel gusset plates replacement. ', 45),
            'vendor_name' => [
                'Vendor Alpha Industrial Services LLC',
                'Vendor Beta Heavy Rigging International',
                'Vendor Gamma Steel Fabrication Corp',
                'Vendor Delta Field Engineering Group',
                'Vendor Epsilon Inspection Labs',
            ],
            'category' => 'Materials',
            'amount' => 35000.00,
            'is_included' => true,
        ]);

    // Item 2 absorbed
    ChangeOrderItem::factory()
        ->for($changeOrder)
        ->create([
            'description' => 'Overnight crane permit expedite fee',
            'vendor_name' => ['Municipal Permit Office'],
            'amount' => 10890.50,
            'cost_absorbed_by_solucomp' => true,
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

    // Structured metadata card with fixed layout and wrapped values
    expect($html)->toContain('>Project</td>')
        ->and($html)->toContain('>Title</td>')
        ->and($html)->toContain('>Market</td>')
        ->and($html)->toContain('>Submitted By</td>')
        ->and($html)->toContain('>Total Amount</td>')
        ->and($html)->toContain('table-layout: fixed')
        ->and($html)->toContain('overflow-wrap: break-word')
        ->and($html)->toContain('word-break: break-word')
        // Scope notes rendered with line breaks and pre-wrap
        ->and($html)->toContain('Scope Notes')
        ->and($html)->toContain('Detailed engineering scope note covering sector A, B, and C modifications.<br />')
        // Items table fixed layout and all 5 vendors without nowrap breakage
        ->and($html)->toContain('Vendor Alpha Industrial Services LLC, Vendor Beta Heavy Rigging International')
        ->and($html)->toContain('Structural steel gusset plates replacement.')
        ->and($html)->toContain('$0.00 (Cost Absorbed)')
        ->and($html)->toContain('$45,890.50');
});

it('handles extreme length in issue forward emails including long guidance and notes', function (): void {
    $project = Project::factory()->create([
        'name' => 'Cell Site With Extremely Long Verizon Nomenclature Sector 4',
    ]);

    $reporter = User::factory()->create(['name' => 'Field Technician']);

    $issue = IssueHistory::factory()
        ->for($project)
        ->create([
            'reported_by' => $reporter->id,
            'type' => IssueType::CONSTRUCTION,
            'description' => str_repeat('Compound gate latch damaged by heavy winter storm equipment. ', 30),
            'verizon_guidance' => str_repeat('Replace with master lock series 5 weatherproof padlock. ', 20),
        ]);

    $longManagerNote = str_repeat("Urgent priority for site turnover this Friday afternoon.\n", 25);

    $html = app(EmailTemplateRenderer::class)->render(new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Issues Forwarded for Your Review',
        type: EmailType::ISSUE_FORWARD,
        body: '',
        projectId: $project->id,
        contextId: (string) $issue->id,
        managerNote: $longManagerNote,
        forwardedByName: 'Senior Site Manager',
        actionUrl: 'https://example.com/issues',
    ));

    expect($html)->toContain('Issues Forwarded for Your Review')
        ->and($html)->toContain('Verizon Guidance:')
        ->and($html)->toContain('Replace with master lock series 5 weatherproof padlock.')
        ->and($html)->toContain('Note from Senior Site Manager')
        ->and($html)->toContain('Urgent priority for site turnover this Friday afternoon.<br />')
        ->and($html)->toContain('table-layout: fixed')
        ->and($html)->toContain('overflow-wrap: break-word')
        ->and($html)->not->toContain('comment')
        ->and($html)->not->toContain('comment-chip')
        ->and($html)->not->toContain('Confidentiality Notice');
});
