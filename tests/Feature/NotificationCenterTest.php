<?php

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Notifications\ChatMessageReceivedNotification;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Notifications\StageUploadedNotification;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessNotification;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\PdfGeneration\Notifications\PdfReadyNotification;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Notifications\ProjectCreatedNotification;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

function databaseNotification(string $body): Notification
{
    return new class($body) extends Notification
    {
        public function __construct(private readonly string $body) {}

        /**
         * @return list<string>
         */
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(object $notifiable): array
        {
            return ['body' => $this->body];
        }
    };
}

it('lists the user notifications with an unread count', function (): void {
    $user = User::factory()->create();
    $user->notify(databaseNotification('Manager reviewed your package'));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('items.0.description', 'Manager reviewed your package')
        ->assertJsonPath('items.0.read', false)
        ->assertJsonPath('items.0.category', 'other');
});

it('marks all notifications as read', function (): void {
    $user = User::factory()->create();
    $user->notify(databaseNotification('First'));
    $user->notify(databaseNotification('Second'));

    $this->actingAs($user)
        ->postJson(route('notifications.read'))
        ->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('flags chat notifications with the chat category', function (): void {
    $user = User::factory()->create();
    $message = ChatMessage::factory()->create();
    $user->notify(new ChatMessageReceivedNotification($message));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.category', 'chat')
        ->assertJsonPath('items.0.title', 'New chat message');
});

it('shows a meaningful title and description for a created project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['name' => 'Ohio Tower 7']);
    $user->notify(new ProjectCreatedNotification($project));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'New project created')
        ->assertJsonPath('items.0.description', 'Project "Ohio Tower 7" has been created.');
});

it('shows a meaningful title and description for a submitted stage', function (): void {
    $user = User::factory()->create();
    $package = CloseoutPackage::factory()->create();
    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::CONSTRUCTION,
    ]);
    $user->notify(new StageUploadedNotification($package, $stage));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Stage submitted')
        ->assertJsonPath('items.0.description', 'Construction for '.$package->project->name.' has been submitted for review.');

    $user2 = User::factory()->create();
    $preStage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::PRE_CONSTRUCTION,
    ]);
    $user2->notify(new StageUploadedNotification($package, $preStage));

    $this->actingAs($user2)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Stage submitted')
        ->assertJsonPath('items.0.description', 'Pre Construction for '.$package->project->name.' has been submitted for review.');
});

it('shows a meaningful title and description for granted portal access', function (): void {
    $user = User::factory()->create();
    $access = CustomerProjectAccess::factory()->create(['user_id' => $user->id]);
    $user->notify(new CustomerPortalAccessNotification($access));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'Portal access granted')
        ->assertJsonPath('items.0.description', 'You now have access to "'.$access->project->name.'" in the customer portal.');
});

it('exposes pdf metadata so the UI can reveal the download action', function (): void {
    $user = User::factory()->create();
    $revision = PackageRevision::factory()->create(['type' => PdfType::PARTIAL]);
    $user->notify(new PdfReadyNotification($revision));

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.category', 'pdf')
        ->assertJsonPath('items.0.package_id', $revision->closeout_package_id)
        ->assertJsonPath('items.0.pdf_type', 'partial')
        ->assertJsonPath('items.0.generated_at', $revision->generated_at?->toISOString());

    $finalRevision = PackageRevision::factory()->create(['type' => PdfType::FINAL]);
    $user2 = User::factory()->create();
    $user2->notify(new PdfReadyNotification($finalRevision));

    $this->actingAs($user2)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.pdf_type', 'final')
        ->assertJsonPath('items.0.package_id', $finalRevision->closeout_package_id);
});

it('falls back to a mapped title for legacy notifications without a payload title', function (): void {
    $user = User::factory()->create();
    $package = CloseoutPackage::factory()->create();
    $user->notify(new ProjectCreatedNotification($package->project));

    // Simulate a row persisted before notifications became self-describing.
    $user->notifications()->first()->update(['data' => [
        'project_id' => $package->project->id,
        'project_name' => $package->project->name,
    ]]);

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('items.0.title', 'New project created');
});
