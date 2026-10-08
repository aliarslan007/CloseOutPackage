<?php

declare(strict_types=1);

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\PdfRenderService;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->renderablePackage = function (): array {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id, 'name' => 'Lomas Peak']);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $item = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => 'Were hand holes installed?',
        ]);

        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $item->id,
            'answer' => true,
        ]);

        return ['package' => $package, 'template' => $template];
    };
});

it('renders an entered checklist quantity', function (): void {
    ['package' => $package] = ($this->renderablePackage)();
    $package->checklistResponses()->firstOrFail()->update(['quantity' => 7]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('QUANTITY:')
        ->toContain('>7</span>');
});

it('omits the quantity line when no quantity was entered', function (): void {
    ['package' => $package] = ($this->renderablePackage)();

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])->not->toContain('QUANTITY:');
});

it('renders the template additional site notes and their images in the notes box', function (): void {
    Storage::fake('private');

    ['package' => $package, 'template' => $template] = ($this->renderablePackage)();
    $template->update([
        'additional_site_notes' => 'Two hand holes were poured on the north side.',
    ]);
    $template
        ->addMedia(UploadedFile::fake()->image('note.jpg'))
        ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('ADDITIONAL NOTES AND COMMENTS')
        ->toContain('Two hand holes were poured on the north side.')
        ->toContain('alt="Additional site note image"')
        ->not->toContain('-webkit-line-clamp: 28')
        ->not->toContain('Note truncated');
});

it('clamps overly long additional notes to 28 lines with a truncation notice', function (): void {
    ['package' => $package] = ($this->renderablePackage)();
    $template = $package->template;
    $template->update([
        'additional_site_notes' => str_repeat("Line of additional site note.\n", 35),
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('-webkit-line-clamp: 28')
        ->toContain('Note truncated')
        ->toContain('Templates page');
});

it('prefers the package notes over the template default', function (): void {
    ['package' => $package, 'template' => $template] = ($this->renderablePackage)();
    $template->update(['additional_site_notes' => 'Template default notes']);
    $package->update(['notes' => 'Package specific notes']);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('Package specific notes')
        ->not->toContain('Template default notes');
});

it('falls back to the empty placeholder when no notes or images exist', function (): void {
    ['package' => $package] = ($this->renderablePackage)();

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('No additional notes available.')
        ->not->toContain('alt="Additional site note image"');
});

it('renders package site note images in the notes box', function (): void {
    Storage::fake('private');

    ['package' => $package] = ($this->renderablePackage)();
    $package->update(['notes' => 'Torque values verified on all bolts.']);
    $package
        ->addMedia(UploadedFile::fake()->image('package-note.jpg'))
        ->toMediaCollection(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('Torque values verified on all bolts.')
        ->toContain('alt="Package site note"');
});

it('omits per-answer site notes markup from the checklist', function (): void {
    ['package' => $package] = ($this->renderablePackage)();

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])->not->toContain('SITE NOTES:');
});
