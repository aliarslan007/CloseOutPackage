<?php

use App\Console\Commands\CleanupTempPdfsCommand;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\PdfRenderService;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->renderedPackage = function (): array {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id, 'name' => 'Lomas Peak']);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $pre = $template->sections()->where('stage', 'pre_construction')->firstOrFail();
        TemplatePictureCategory::factory()->create(['template_section_id' => $pre->id, 'name' => 'MMP']);
        TemplatePictureCategory::factory()->create(['template_section_id' => $pre->id, 'name' => 'Grounding']);
        $checklist = ChecklistItem::factory()->create(['template_id' => $template->id, 'label' => 'Site access granted?']);

        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'revision_major' => 2,
            'revision_minor' => 4,
        ]);
        $package->siteInfo()->create([
            'site_name' => 'Lomas Peak',
            'address' => '9654 Jamboree Road, Orange, CA 92869',
            'vzw_engineer_name' => 'Ryan Temple',
            'vzw_engineer_email' => 'ryan.temple@verizonwireless.com',
            'author_name' => 'Mike Mullerleile',
            'author_email' => 'mmullerleile@cikpower.com',
            'pm_name' => 'Project Manager',
            'pm_email' => 'pm@example.com',
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $checklist->id,
            'answer' => true,
        ]);

        return ['package' => $package, 'template' => $template];
    };
});

it('renders the cover, checklist, pictures list, and photo grid', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $photoItems = [
        ['path' => '/tmp/missing-1.jpg', 'heading' => 'MMP', 'category' => 'MMP'],
        ['path' => '/tmp/missing-2.jpg', 'heading' => 'MMP', 'category' => 'MMP'],
        ['path' => '/tmp/missing-3.jpg', 'heading' => 'MMP', 'category' => 'MMP'],
        ['path' => '/tmp/missing-4.jpg', 'heading' => 'MMP', 'category' => 'MMP'],
        ['path' => '/tmp/missing-5.jpg', 'heading' => 'MMP', 'category' => 'MMP'],
    ];

    $result = app(PdfRenderService::class)->render($package, $photoItems, 'R2.4', '2026-08-03');

    expect($result['totalPages'])->toBe(6)
        ->and($result['html'])
        ->toContain('Lomas Peak')
        ->toContain('9654 Jamboree Road')
        ->toContain('Ryan Temple')
        ->toContain('Mike Mullerleile')
        ->toContain('Site access granted?')
        ->toContain('YES')
        ->toContain('Mmp (1)')
        ->toContain('Mmp (2)')
        ->toContain('Mmp (3)')
        ->toContain('Mmp (4)')
        ->toContain('Mmp (5)')
        ->toContain('R2.4')
        ->toContain('1 (6)')
        ->toContain('2 (6)')
        ->toContain('3 (6)')
        ->toContain('4 (6)')
        ->toContain('5 (6)')
        ->toContain('6 (6)')
        ->toContain('alt="SoluComp logo"')
        ->toContain('Verizon Wireless Proprietary Information')
        ->toContain('Prepared by <a href="https://www.solucomp.com"')
        ->toContain('www.SoluComp.com</a>');
});

it('renders stage heading and sub-headings with page break between sub-categories', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    // Category 1 has 5 photos (4 on page 1, 1 on page 2)
    // Category 2 has 2 photos (starts on a new page -> page 3)
    $photoItems = [
        ['path' => '/tmp/site-1.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/site-2.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/site-3.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/site-4.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/site-5.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/eq-1.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Pre-Construction Pictures'],
        ['path' => '/tmp/eq-2.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Pre-Construction Pictures'],
    ];

    $result = app(PdfRenderService::class)->render($package, $photoItems, 'R1.0', '2026-08-03');

    // 1 cover + 1 checklist + 1 additional notes + 1 required pictures + 2 pages for Final Site Overview + 1 page for Completed Equipment = 7 pages
    expect($result['totalPages'])->toBe(7)
        ->and($result['html'])
        ->toContain('Pre-Construction Pictures')
        ->toContain('photo-stage-heading')
        ->toContain('Final Site Overview')
        ->toContain('Completed Equipment')
        ->toContain('photo-sub-heading')
        ->toContain('1 (7)')
        ->toContain('4 (7)')
        ->toContain('5 (7)')
        ->toContain('6 (7)')
        ->toContain('7 (7)');
});

it('renders the checklist answer NO with an explanation', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $response = $package->checklistResponses()->firstOrFail();
    $response->checklistItem()->update(['has_explain_field' => true]);
    $response->update(['answer' => false, 'explain' => 'Gate locked']);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('NO')
        ->toContain('Gate locked');
});

it('renders hub and site details on page two and shifts the checklist', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $package->siteDetails()->createMany([
        [
            'source' => 'auth_center',
            'site_type' => 'hub',
            'name' => 'AKRON ALLTEL MTSO -- 1875031',
            'auth_site_id' => 433,
            'region' => 'Midwest',
            'state' => 'OH',
            'sort_order' => 0,
        ],
        [
            'source' => 'manual',
            'site_type' => 'smallcell',
            'name' => 'Columbus Main',
            'region' => 'Midwest',
            'state' => 'OH',
            'sort_order' => 1,
        ],
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('HUB / SITE DETAILS')
        ->toContain('AKRON ALLTEL MTSO -- 1875031')
        ->toContain('Columbus Main')
        ->toContain('Site Access Info')
        ->toContain('Manual')
        // Page 2 is the site-details page; the checklist now starts on page 3.
        ->toContain('2 (5)')
        ->toContain('3 (5)')
        ->and($result['totalPages'])->toBe(5);
});

it('skips the site details page when no details were recorded', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->not->toContain('HUB / SITE DETAILS')
        ->toContain('2 (4)')
        ->and($result['totalPages'])->toBe(4);
});

it('renders long site names in the header with adaptive font size and flow', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $longName = 'Tower 5587 — Cedar Avenue Expansion Very Long Site Substation Facility Phase 2';
    $package->siteInfo()->update(['site_name' => $longName]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain($longName)
        ->toContain('font-size: 13px;')
        ->toContain('class="header-title-cell"');
});

it('cleans up temporary PDFs older than the TTL', function (): void {
    Storage::fake('private');

    Storage::disk('private')->put('temp/old.pdf', 'old');
    Storage::disk('private')->put('temp/new.pdf', 'new');
    Storage::disk('private')->put('temp/current.pdf', 'current');

    $oldPath = Storage::disk('private')->path('temp/old.pdf');
    touch($oldPath, now()->subHours(48)->getTimestamp());

    $this->artisan(CleanupTempPdfsCommand::class)->assertSuccessful();

    expect(Storage::disk('private')->exists('temp/old.pdf'))->toBeFalse()
        ->and(Storage::disk('private')->exists('temp/new.pdf'))->toBeTrue()
        ->and(Storage::disk('private')->exists('temp/current.pdf'))->toBeTrue();
});

it('cleans up orphaned optimized photos older than the TTL', function (): void {
    $suffix = uniqid(more_entropy: true);
    $oldPhoto = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cop_photo_test_old'.$suffix;
    $newPhoto = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cop_photo_test_new'.$suffix;

    file_put_contents($oldPhoto, 'old-photo');
    file_put_contents($newPhoto, 'new-photo');
    touch($oldPhoto, now()->subHours(48)->getTimestamp());

    try {
        $this->artisan(CleanupTempPdfsCommand::class)->assertSuccessful();

        expect(is_file($oldPhoto))->toBeFalse()
            ->and(is_file($newPhoto))->toBeTrue();
    } finally {
        @unlink($oldPhoto);
        @unlink($newPhoto);
    }
});

it('does not delete unrelated system temp files', function (): void {
    $suffix = uniqid(more_entropy: true);
    $unrelated = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unrelated_tmp_file_'.$suffix;

    file_put_contents($unrelated, 'keep-me');
    touch($unrelated, now()->subHours(48)->getTimestamp());

    try {
        $this->artisan(CleanupTempPdfsCommand::class)->assertSuccessful();

        expect(is_file($unrelated))->toBeTrue();
    } finally {
        @unlink($unrelated);
    }
});

it('renders the deadline date as COMPLETE DATE instead of the current generation date', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $package->project()->update(['completed_date' => '2026-11-20']);
    $package->siteInfo()->update(['complete_date' => '2026-11-20']);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('COMPLETE DATE:')
        ->toContain('11/20/2026')
        ->not->toContain(now()->format('n/j/Y'));
});

it('renders the complete site address incorporating city, state, and zip with wrapping styles', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $package->project()->update([
        'address' => '742 Evergreen Terrace',
        'city' => 'Springfield',
        'state' => 'OR',
        'zip' => '97477',
    ]);
    // siteInfo only has the street address
    $package->siteInfo()->update(['address' => '742 Evergreen Terrace']);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('742 Evergreen Terrace, Springfield, OR 97477')
        ->toContain('word-break: break-word')
        ->toContain('overflow-wrap: break-word');
});

it('includes the 2-line clamp and truncation style for the cover template name', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('-webkit-line-clamp: 2')
        ->toContain('text-overflow: ellipsis')
        ->toContain('class="cover-title-primary"');
});

it('renders the checklist N/A option with checked SVG box when marked N/A', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $response = $package->checklistResponses()->firstOrFail();
    $response->checklistItem()->update(['allows_na' => true]);
    $response->update(['answer' => null, 'is_na' => true, 'explain' => null]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('N/A')
        ->toContain('colspan="4"')
        ->toContain('width: 73%;');
});

it('renders photo notes with adaptive sizing classes for short, medium, and 500-char notes', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $shortNote = 'Short note here.';
    $mediumNote = 'NOTE: The standard chunk of Lorem Ipsum used since 1966 is reproduced below for those interested. Sections 1.10.32 and 1.10.33 from de Finibus Bonorum et Malorum by Cicero are also reproduced.';
    $longNote = str_repeat('Detailed antenna azimuth and tilt verification completed at site. ', 7); // ~460 chars

    $photoItems = [
        ['path' => '/tmp/photo-1.jpg', 'heading' => 'Antenna 1', 'category' => 'Antenna', 'notes' => $shortNote],
        ['path' => '/tmp/photo-2.jpg', 'heading' => 'Antenna 2', 'category' => 'Antenna', 'notes' => $mediumNote],
        ['path' => '/tmp/photo-3.jpg', 'heading' => 'Antenna 3', 'category' => 'Antenna', 'notes' => $longNote],
        ['path' => '/tmp/photo-4.jpg', 'heading' => 'Antenna 4', 'category' => 'Antenna', 'notes' => null],
    ];

    $result = app(PdfRenderService::class)->render($package, $photoItems, 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('has-note-md')
        ->toContain('note-md')
        ->toContain('has-note-lg')
        ->toContain('note-lg')
        ->toContain('photo-cell-note-box')
        ->toContain('photo-note-label')
        ->toContain($shortNote)
        ->toContain($mediumNote);
});

it('shows the author company from the creator profile on the cover', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $manager = User::factory()->create(['company' => 'BuildCo Services']);
    $package->siteInfo()->update([
        'author_user_id' => $manager->id,
        'author_company' => null,
        'author_name' => $manager->name,
        'pm_company' => 'PM Corp',
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('BuildCo Services')
        ->toContain('PM Corp');
});

it('shows the technician company when the technician authored the package', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $tech = User::factory()->create(['company' => 'TechCo Field']);
    $package->siteInfo()->update([
        'author_user_id' => $tech->id,
        'author_company' => null,
        'author_name' => $tech->name,
        'author_email' => null,
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('TechCo Field')
        ->toContain($tech->email);
});

it('paginates a large checklist with notes and quantities without breaking page numbering', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $template = $fixture['template'];

    foreach (range(1, 29) as $index) {
        $item = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => "Checklist question number {$index}",
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $item->id,
            'answer' => $index % 2 === 0,
            'explain' => $index % 2 === 0 ? null : "Note for question {$index} with extra detail.",
            'quantity' => $index % 3 === 0 ? $index : null,
        ]);
    }

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['totalPages'])->toBeGreaterThan(3)
        ->and(substr_count($result['html'], 'ADDITIONAL NOTES AND COMMENTS'))->toBe(1)
        ->and($result['html'])
        ->toContain('Checklist question number 29')
        ->toContain('Note for question 29 with extra detail.')
        ->toContain("3 ({$result['totalPages']})");
});

it('renders scope of work and site access notes on page two and shifts the checklist', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $package->siteInfo()->update([
        'scope_of_work' => 'Replace 3 RRUs and re-terminate fiber at sector 2.',
        'site_access_notes' => 'Check in at the north gate, gate code #4921.',
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('SCOPE OF WORK AND SITE ACCESS')
        ->toContain('Replace 3 RRUs and re-terminate fiber at sector 2.')
        ->toContain('Check in at the north gate, gate code #4921.')
        ->toContain('2 (5)')
        ->toContain('3 (5)')
        ->and($result['totalPages'])->toBe(5);
});

it('renders the scope page when only the scope of work is present', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $package->siteInfo()->update(['scope_of_work' => 'Swap the sector 1 antenna.']);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('SCOPE OF WORK AND SITE ACCESS')
        ->toContain('Swap the sector 1 antenna.')
        ->and($result['totalPages'])->toBe(5);
});

it('skips the scope page when scope and site access notes are empty', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->not->toContain('SCOPE OF WORK AND SITE ACCESS')
        ->toContain('2 (4)')
        ->and($result['totalPages'])->toBe(4);
});

it('scales font size and padding adaptively for large 5000 character scope and site access notes', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $package->siteInfo()->update([
        'scope_of_work' => str_repeat('Scope description item. ', 100), // ~2400 chars
        'site_access_notes' => str_repeat('Site access instruction note. ', 85), // ~2500 chars
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('SCOPE OF WORK AND SITE ACCESS')
        ->toContain('font-size: 11px')
        ->toContain('line-height: 1.30')
        ->toContain('padding: 4.5px 8px')
        ->toContain('2 (5)')
        ->and($result['totalPages'])->toBe(5);
});

it('fits eight plain checklist questions on one page', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $template = $fixture['template'];

    foreach (range(1, 7) as $index) {
        $item = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => "Plain checklist question {$index}",
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $item->id,
            'answer' => true,
        ]);
    }

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('Plain checklist question 7')
        ->and($result['totalPages'])->toBe(4);
});

it('shares the page when a checklist question carries a max-length note', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $template = $fixture['template'];

    $package->checklistResponses()->firstOrFail()->update(['explain' => str_repeat('N', 1000)]);

    foreach (range(1, 4) as $index) {
        $item = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => "Plain question beside long note {$index}",
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $item->id,
            'answer' => true,
        ]);
    }

    $result = app(PdfRenderService::class)->render($package->refresh(), [], 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain('Plain question beside long note 4')
        ->and($result['totalPages'])->toBe(4);
});

it('packs noted checklist questions to maximize space and splits onto next page when page budget is exceeded', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $template = $fixture['template'];

    $package->checklistResponses()->firstOrFail()->update(['explain' => 'Note for noted question 0.']);

    foreach (range(1, 10) as $index) {
        $item = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => "Noted checklist question {$index}",
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $item->id,
            'answer' => false,
            'explain' => "Note for noted question {$index}.",
        ]);
    }

    // 11 noted questions easily fit on 1 checklist page -> 4 total pages (Cover, Checklist, Additional Notes, Pictures)
    $elevenResult = app(PdfRenderService::class)->render($package->refresh(), [], 'R1.0', '2026-08-03');

    expect($elevenResult['html'])
        ->toContain('Noted checklist question 10')
        ->and($elevenResult['totalPages'])->toBe(4);

    // Add 10 more questions with notes to create a 2nd checklist page
    foreach (range(11, 20) as $index) {
        $extra = ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'label' => "Noted checklist question {$index}",
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $package->id,
            'checklist_item_id' => $extra->id,
            'answer' => false,
            'explain' => "Note for noted question {$index}.",
        ]);
    }

    $largeResult = app(PdfRenderService::class)->render($package->refresh(), [], 'R1.0', '2026-08-03');

    expect($largeResult['html'])
        ->toContain('Noted checklist question 20')
        ->and($largeResult['totalPages'])->toBe(5);
});

it('renders additional notes supporting images in 2x2 grid with 4 per page and paginates remaining', function (): void {
    Storage::fake('private');

    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];
    $template = $fixture['template'];

    // Attach 6 note images to template
    foreach (range(1, 6) as $index) {
        $template
            ->addMedia(UploadedFile::fake()->image("note-img-{$index}.jpg"))
            ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);
    }

    $result = app(PdfRenderService::class)->render($package->refresh(), [], 'R1.0', '2026-08-03');

    // 1 cover + 1 checklist + 1 notes text + 2 notes image pages (4 on page 1, 2 on page 2) + 1 pictures = 6 total pages
    expect($result['totalPages'])->toBe(6)
        ->and($result['html'])
        ->toContain('Additional Notes Supporting Images')
        ->toContain('(Cont.)')
        ->toContain('Supporting Note Image #1')
        ->toContain('Supporting Note Image #4')
        ->toContain('Supporting Note Image #5')
        ->toContain('Supporting Note Image #6');
});

it('renders long subcategory photo headings with text wrapping styles so the full name is visible', function (): void {
    $fixture = ($this->renderedPackage)();
    $package = $fixture['package'];

    $longCategoryName = 'Sector Alpha Primary Antenna Mounting Bracket And Cable Tray Alignment Verification';
    $photoItems = [
        ['path' => '/tmp/long-cat-1.jpg', 'heading' => $longCategoryName, 'category' => $longCategoryName],
    ];

    $result = app(PdfRenderService::class)->render($package, $photoItems, 'R1.0', '2026-08-03');

    expect($result['html'])
        ->toContain(ucwords(strtolower($longCategoryName)))
        ->toContain('white-space: normal;')
        ->toContain('overflow-wrap: break-word;');
});
