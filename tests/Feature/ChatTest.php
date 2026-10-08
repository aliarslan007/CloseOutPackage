<?php

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $field->markets()->attach($market->id);
        $project->fieldSupervisors()->attach($field->id);

        return compact('market', 'manager', 'project', 'field');
    };
});

it('sends a chat message and strips HTML from the body', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])
        ->post(route('projects.chat.store', $fixture['project']), [
            'body' => 'The gate is open <script>alert(1)</script> now.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $message = ChatMessage::firstOrFail();

    expect($message)
        ->project_id->toBe($fixture['project']->id)
        ->sender_id->toBe($fixture['field']->id)
        ->body->toBe('The gate is open now.');
});

it('lists chat messages for project participants', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Hello manager',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('projects.chat.index', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Chat')
            ->has('messages.data', 1)
            ->where('messages.data.0.body', 'Hello manager'));
});

it('marks incoming messages as read', function (): void {
    $fixture = ($this->fixture)();
    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Unread message',
    ]);

    $this->actingAs($fixture['manager'])
        ->put(route('projects.chat.read', $fixture['project']))
        ->assertRedirect();

    expect(ChatMessage::firstOrFail()->read_at)->not->toBeNull();
});

it('denies chat access to users who are not participants', function (): void {
    $fixture = ($this->fixture)();
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->post(route('projects.chat.store', $fixture['project']), ['body' => 'Hi'])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('rejects an empty chat message', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])
        ->post(route('projects.chat.store', $fixture['project']), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('lists chat projects for participants', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['manager'])
        ->getJson(route('chat.projects'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $fixture['project']->id);

    $stranger = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($stranger)
        ->getJson(route('chat.projects'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('reports unread message counts per project', function (): void {
    $fixture = ($this->fixture)();
    $second = Project::factory()->create([
        'market_id' => $fixture['market']->id,
        'manager_id' => $fixture['manager']->id,
    ]);
    $second->fieldSupervisors()->attach($fixture['field']->id);

    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Unread message',
    ]);
    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Another unread message',
    ]);
    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Already read',
        'read_at' => now(),
    ]);
    ChatMessage::factory()->create([
        'project_id' => $second->id,
        'sender_id' => $fixture['manager']->id,
        'body' => 'Manager own message — not unread',
    ]);

    $this->actingAs($fixture['manager'])
        ->getJson(route('chat.projects'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $fixture['project']->id, 'unread_count' => 2])
        ->assertJsonFragment(['id' => $second->id, 'unread_count' => 0]);
});

it('returns chat messages as JSON for the chat widget', function (): void {
    $fixture = ($this->fixture)();
    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Hello manager',
    ]);

    $this->actingAs($fixture['manager'])
        ->getJson(route('projects.chat.messages', $fixture['project']))
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Hello manager')
        ->assertJsonPath('data.0.senderName', $fixture['field']->name)
        ->assertJsonPath('data.0.isSelf', false);
});

it('denies chat messages to non-participants', function (): void {
    $fixture = ($this->fixture)();
    $stranger = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($stranger)
        ->getJson(route('projects.chat.messages', $fixture['project']))
        ->assertForbidden();
});

it('notifies the other participant with a database notification', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])
        ->post(route('projects.chat.store', $fixture['project']), ['body' => 'The gate is open.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($fixture['manager']->unreadNotifications()->count())->toBe(1)
        ->and($fixture['manager']->unreadNotifications()->first()->data['body'])->toBe('The gate is open.');
});

it('marks chat messages and chat notifications as read when the chat is opened', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['field'])
        ->post(route('projects.chat.store', $fixture['project']), ['body' => 'Check the rack.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['manager'])
        ->putJson(route('projects.chat.read', $fixture['project']))
        ->assertOk();

    $message = ChatMessage::firstOrFail();
    expect($message->read_at)->not->toBeNull()
        ->and($fixture['manager']->fresh()->unreadNotifications()->count())->toBe(0);
});

it('bypasses project assignment for admins in the chat picker', function (): void {
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $unassigned = Project::factory()->create(['market_id' => $fixture['market']->id]);

    $this->actingAs($admin)
        ->getJson(route('chat.projects'))
        ->assertOk()
        ->assertJsonFragment(['id' => $fixture['project']->id])
        ->assertJsonFragment(['id' => $unassigned->id]);
});

it('lets an admin read any project chat', function (): void {
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    ChatMessage::factory()->create([
        'project_id' => $fixture['project']->id,
        'sender_id' => $fixture['field']->id,
        'body' => 'Site update',
    ]);

    $this->actingAs($admin)
        ->getJson(route('projects.chat.messages', $fixture['project']))
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Site update');
});

it('lets an admin send messages to any project chat', function (): void {
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.chat.store', $fixture['project']), ['body' => 'Admin checking in.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ChatMessage::firstOrFail())
        ->sender_id->toBe($admin->id)
        ->body->toBe('Admin checking in.');
});
