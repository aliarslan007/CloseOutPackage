<?php

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Notifications\PackageFinalApprovalNotification;
use App\Domains\Approval\Notifications\PhotoReplacementRequestedNotification;
use App\Domains\Approval\Notifications\PhotoReviewedNotification;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Actions\AssertPackagePhotosEditableAction;
use App\Domains\Photos\Actions\AssertPackageReviewableAction;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(2)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);

        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $field->markets()->attach($market->id);
        $project->fieldSupervisors()->attach($field->id);

        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

        return compact('market', 'manager', 'project', 'template', 'field', 'admin');
    };

    $this->packageWithPhoto = function (array $fixture): array {
        $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
        ]);

        $package = CloseoutPackage::firstOrFail();
        $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
        $selection = $stage->categorySelections()->firstOrFail();

        $this->actingAs($fixture['field'])
            ->post(route('photos.store', [$package, StageType::PRE_CONSTRUCTION->value]), [
                'photo' => UploadedFile::fake()->image('site.jpg'),
                'category_selection_id' => $selection->id,
                'position' => 1,
            ]);

        $photo = Photo::firstOrFail();

        return compact('package', 'stage', 'selection', 'photo');
    };

    $this->submitPackage = function (CloseoutPackage $package): void {
        foreach ($package->stages()->get() as $stage) {
            $stage->update(['status' => StageStatus::SUBMITTED]);
        }

        $package->update(['status' => PackageStatus::SUBMITTED]);
    };

    $this->uploadAllStagePhotos = function (array $fixture, CloseoutPackage $package): void {
        foreach ($package->stages()->get() as $stage) {
            foreach ($stage->categorySelections()->where('is_selected', true)->get() as $selection) {
                $this->actingAs($fixture['field'])
                    ->post(route('photos.store', [$package, $stage->stage->value]), [
                        'photo' => UploadedFile::fake()->image('site.jpg'),
                        'category_selection_id' => $selection->id,
                        'position' => 1,
                    ])
                    ->assertSessionHasNoErrors();
            }
        }
    };

    $this->approveAllPhotos = function (array $fixture, CloseoutPackage $package): void {
        ($this->submitPackage)($package);

        foreach (Photo::where('closeout_package_id', $package->id)->get() as $photo) {
            $this->actingAs($fixture['manager'])
                ->post(route('manager.photos.approve', $photo))
                ->assertSessionHasNoErrors();
        }
    };

    $this->answerChecklist = function (array $fixture, CloseoutPackage $package): void {
        $responses = $package->checklistResponses()->get()->map(fn ($response): array => [
            'checklist_item_id' => $response->checklist_item_id,
            'answer' => true,
        ])->all();

        $this->actingAs($fixture['field'])
            ->put(route('packages.checklist.update', $package), ['responses' => $responses])
            ->assertSessionHasNoErrors();
    };

    $this->completeSiteInfo = function (array $fixture, CloseoutPackage $package): void {
        $this->actingAs($fixture['field'])
            ->put(route('packages.site-info.update', $package), [
                'complete_date' => '2026-08-01',
            ])
            ->assertSessionHasNoErrors();
    };
});

it('approves a photo as the assigned manager', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::APPROVED)
        ->and($setup['photo']->fresh()->is_approved)->toBeTruthy()
        ->and($setup['photo']->fresh()->approved_by)->toBe($fixture['manager']->id)
        ->and($setup['photo']->fresh()->approved_at)->not->toBeNull();

    $this->assertDatabaseHas('photo_approvals', [
        'photo_id' => $setup['photo']->id,
        'reviewed_by' => $fixture['manager']->id,
        'decision' => ApprovalDecision::APPROVED->value,
    ]);
});

it('flags a photo with a reason', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Photo is too dark.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $photo = $setup['photo']->fresh();
    expect($photo->status)->toBe(PhotoStatus::FLAGGED)
        ->and($photo->flagged_by)->toBe($fixture['manager']->id)
        ->and($photo->flag_reason)->toBe('Photo is too dark.')
        ->and($photo->is_approved)->toBeNull();
});

it('requests a photo replacement and notifies the field tech', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.request-replacement', $setup['photo']), ['reason' => 'Blurry capture.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::REPLACEMENT_REQUESTED);

    Notification::assertSentTo($fixture['field'], PhotoReplacementRequestedNotification::class);
});

it('notifies the field tech when their photo is approved', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertSessionHas('success');

    Notification::assertSentTo(
        $fixture['field'],
        PhotoReviewedNotification::class,
        function ($notification) use ($fixture): bool {
            $data = $notification->toArray($fixture['field']);

            return $data['title'] === 'Photo approved'
                && str_contains($data['body'], 'was approved by '.$fixture['manager']->name);
        }
    );
});

it('notifies the field tech when their photo is flagged with the reason', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Photo is too dark.'])
        ->assertSessionHas('success');

    Notification::assertSentTo(
        $fixture['field'],
        PhotoReviewedNotification::class,
        function ($notification) use ($fixture): bool {
            $data = $notification->toArray($fixture['field']);

            return $data['title'] === 'Photo flagged'
                && str_contains($data['body'], 'was flagged as blurry by '.$fixture['manager']->name)
                && str_contains($data['body'], 'Reason: Photo is too dark.');
        }
    );
});

it('does not double-notify when a replacement is requested', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.request-replacement', $setup['photo']), ['reason' => 'Blurry capture.'])
        ->assertSessionHas('success');

    Notification::assertSentTo($fixture['field'], PhotoReplacementRequestedNotification::class);
    Notification::assertNotSentTo($fixture['field'], PhotoReviewedNotification::class);
});

it('clears the previous reason when a flagged photo is approved', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Photo is too dark.'])
        ->assertRedirect();

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($setup['photo']->fresh())
        ->status->toBe(PhotoStatus::APPROVED)
        ->flag_reason->toBeNull()
        ->is_approved->toBeTruthy();
});

it('bulk reviews photos', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);
    $second = Photo::factory()->create([
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
        'position' => 2,
    ]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.bulk-review', $setup['package']), [
            'decisions' => [
                ['photo_id' => $setup['photo']->id, 'decision' => ApprovalDecision::APPROVED->value],
                ['photo_id' => $second->id, 'decision' => ApprovalDecision::REJECTED->value, 'reason' => 'Wrong angle.'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::APPROVED)
        ->and($second->fresh()->status)->toBe(PhotoStatus::REJECTED);
});

it('invalidates the final PDF when a previously approved photo is re-reviewed', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    ($this->submitPackage)($setup['package']);
    $setup['package']->update([
        'final_pdf_path' => 'pdfs/1/final_R1_1.pdf',
        'final_pdf_generated_at' => now(),
    ]);
    $setup['photo']->update(['is_approved' => true]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Blurry after approval.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($setup['package']->fresh()->final_pdf_path)->toBeNull()
        ->and($setup['package']->fresh()->final_pdf_generated_at)->toBeNull();
});

it('denies photo approval after the package is approved', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    $setup['package']->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackagePhotosEditableAction::MESSAGE);

    expect($setup['photo']->fresh()->is_approved)->toBeNull();
});

it('denies flagging photos after the package is approved', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    $setup['package']->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Too dark.'])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackagePhotosEditableAction::MESSAGE);

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::PENDING);
});

it('denies replacement requests after the package is approved', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    $setup['package']->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.request-replacement', $setup['photo']), ['reason' => 'Blurry.'])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackagePhotosEditableAction::MESSAGE);

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::PENDING);
});

it('denies bulk review after the package is approved', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    $setup['package']->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.bulk-review', $setup['package']), [
            'decisions' => [
                ['photo_id' => $setup['photo']->id, 'decision' => ApprovalDecision::APPROVED->value],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackagePhotosEditableAction::MESSAGE);

    expect($setup['photo']->fresh()->is_approved)->toBeNull();
});

it('denies approving photos while the package is a draft', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackageReviewableAction::MESSAGE);

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::PENDING)
        ->and($setup['photo']->fresh()->is_approved)->toBeNull();
});

it('denies flagging photos while the package is a draft', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $setup['photo']), ['reason' => 'Too dark.'])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackageReviewableAction::MESSAGE);

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::PENDING);
});

it('denies replacement requests while the package is a draft', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.request-replacement', $setup['photo']), ['reason' => 'Blurry.'])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackageReviewableAction::MESSAGE);

    expect($setup['photo']->fresh()->status)->toBe(PhotoStatus::PENDING);
});

it('denies bulk review while the package is a draft', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.bulk-review', $setup['package']), [
            'decisions' => [
                ['photo_id' => $setup['photo']->id, 'decision' => ApprovalDecision::APPROVED->value],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', AssertPackageReviewableAction::MESSAGE);

    expect($setup['photo']->fresh()->is_approved)->toBeNull();
});

it('denies photo review to field supervisors', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('denies photo review to an unassigned manager', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithPhoto)($fixture);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->post(route('manager.photos.approve', $setup['photo']))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('grants final approval when the package is ready', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->uploadAllStagePhotos)($fixture, $package);
    ($this->answerChecklist)($fixture, $package);
    ($this->completeSiteInfo)($fixture, $package);
    ($this->approveAllPhotos)($fixture, $package);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::APPROVED)
        ->and($package->fresh()->approved_by)->toBe($fixture['manager']->id);
});

it('stores Auth Center and manual site details on final approval', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->uploadAllStagePhotos)($fixture, $package);
    ($this->answerChecklist)($fixture, $package);
    ($this->completeSiteInfo)($fixture, $package);
    ($this->approveAllPhotos)($fixture, $package);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
            'site_details' => [
                [
                    'source' => 'auth_center',
                    'site_type' => 'hub',
                    'name' => 'AKRON ALLTEL MTSO -- 1875031',
                    'auth_site_id' => 433,
                    'region' => 'Midwest',
                    'state' => 'OH',
                ],
                [
                    'source' => 'manual',
                    'site_type' => 'smallcell',
                    'name' => 'Columbus Main',
                    'region' => 'Midwest',
                    'state' => 'OH',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $details = $package->fresh()->siteDetails;

    expect($details)->toHaveCount(2)
        ->and($details[0]->source->value)->toBe('auth_center')
        ->and($details[0]->site_type->value)->toBe('hub')
        ->and($details[0]->name)->toBe('AKRON ALLTEL MTSO -- 1875031')
        ->and($details[0]->auth_site_id)->toBe(433)
        ->and($details[0]->region)->toBe('Midwest')
        ->and($details[0]->state)->toBe('OH')
        ->and($details[0]->sort_order)->toBe(0)
        ->and($details[1]->source->value)->toBe('manual')
        ->and($details[1]->site_type->value)->toBe('smallcell')
        ->and($details[1]->name)->toBe('Columbus Main')
        ->and($details[1]->sort_order)->toBe(1);
});

it('rejects invalid site details on final approval', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->uploadAllStagePhotos)($fixture, $package);
    ($this->answerChecklist)($fixture, $package);
    ($this->completeSiteInfo)($fixture, $package);
    ($this->approveAllPhotos)($fixture, $package);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
            'site_details' => [
                [
                    'source' => 'space_station',
                    'site_type' => 'hub',
                    'name' => '',
                ],
            ],
        ])
        ->assertSessionHasErrors(['site_details.0.source', 'site_details.0.name']);

    expect($package->fresh()->status)->not->toBe(PackageStatus::APPROVED)
        ->and($package->fresh()->siteDetails)->toHaveCount(0);
});

it('notifies the assigned field supervisors when the package is finally approved', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $unrelated = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->uploadAllStagePhotos)($fixture, $package);
    ($this->answerChecklist)($fixture, $package);
    ($this->completeSiteInfo)($fixture, $package);
    ($this->approveAllPhotos)($fixture, $package);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
        ])
        ->assertSessionHasNoErrors();

    expect($package->fresh()->status)->toBe(PackageStatus::APPROVED);

    // The assigned field supervisor and every admin are notified; a supervisor
    // not assigned to this project is not.
    Notification::assertSentTo($fixture['field'], PackageFinalApprovalNotification::class);
    Notification::assertSentTo($fixture['admin'], PackageFinalApprovalNotification::class);
    Notification::assertNotSentTo($unrelated, PackageFinalApprovalNotification::class);
});

it('blocks final approval while any photo is still flagged', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->uploadAllStagePhotos)($fixture, $package);
    ($this->answerChecklist)($fixture, $package);
    ($this->completeSiteInfo)($fixture, $package);
    ($this->approveAllPhotos)($fixture, $package);

    $flaggedPhoto = Photo::where('closeout_package_id', $package->id)->firstOrFail();

    $this->actingAs($fixture['manager'])
        ->post(route('manager.photos.flag', $flaggedPhoto), ['reason' => 'Blurry capture.'])
        ->assertSessionHas('success');

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
        ])
        ->assertSessionHasErrors('readiness_failures');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});

it('blocks final approval with readiness failures when photos are pending', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.final-approve', $package), [
            'closeout_package_id' => $package->id,
        ])
        ->assertSessionHasErrors('readiness_failures');

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);
});
