<?php

use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Jobs\SendPackageSubmittedNotificationJob;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Notifications\PackageSubmittedNotification;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $supervisor->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $project->fieldSupervisors()->attach($supervisor->id);

        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(1)->create(['template_id' => $template->id]);

        $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();

        $package->update(['current_stage' => StageType::POST_COMPLETION]);

        $categoryIds = $template->sections()->where('stage', StageType::POST_COMPLETION->value)
            ->firstOrFail()->categories()->pluck('id')->all();

        $this->actingAs($supervisor)
            ->post(route('packages.stages.draft', [$package, StageType::POST_COMPLETION->value]), [
                'category_selections' => array_map(fn (int $id): array => [
                    'category_id' => $id,
                    'is_selected' => true,
                    'picture_count' => 1,
                ], $categoryIds),
                'checklist_responses' => [[
                    'checklist_item_id' => $template->checklistItems()->firstOrFail()->id,
                    'answer' => true,
                    'explain' => null,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $package->stages()
            ->where('stage', '!=', StageType::POST_COMPLETION->value)
            ->update([
                'status' => StageStatus::SUBMITTED->value,
                'submitted_at' => now(),
                'submitted_by' => $supervisor->id,
            ]);

        $this->actingAs($supervisor)
            ->post(route('packages.stages.submit', [$package, StageType::POST_COMPLETION->value]))
            ->assertSessionHas('success');

        return compact('package', 'manager', 'supervisor', 'template');
    };
});

it('queues a manager notification job when the package is submitted', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $fixture['package']))
        ->assertSessionHas('success');

    Queue::assertPushed(
        SendPackageSubmittedNotificationJob::class,
        fn (SendPackageSubmittedNotificationJob $job): bool => $job->package->is($fixture['package'])
            && $job->submitter->is($fixture['supervisor'])
    );
});

it('notifies the project manager from the queued job', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();

    (new SendPackageSubmittedNotificationJob($fixture['package'], $fixture['supervisor']))->handle();

    Notification::assertSentTo($fixture['manager'], PackageSubmittedNotification::class);
});

it('skips the notification when the project has no manager', function (): void {
    Notification::fake();

    $submitter = User::factory()->create();
    $project = Project::factory()->create(['manager_id' => null]);
    $package = CloseoutPackage::factory()->create(['project_id' => $project->id]);

    (new SendPackageSubmittedNotificationJob($package, $submitter))->handle();

    Notification::assertNothingSent();
});

it('shows a meaningful title and description naming the submitter', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $submitter = User::factory()->create(['name' => 'Jordan Supervisor'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $package = CloseoutPackage::factory()->create(['project_id' => $project->id]);

    $manager->notify(new PackageSubmittedNotification($package, $submitter));

    $this->actingAs($manager)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Package submitted for review')
        ->assertJsonPath(
            'items.0.description',
            "Close-out package #{$package->id} for {$package->project->name} was submitted for review by Jordan Supervisor."
        );
});
