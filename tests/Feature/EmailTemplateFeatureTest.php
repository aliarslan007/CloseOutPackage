<?php

declare(strict_types=1);

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
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

    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
});

it('allows managers and admins to view the email template library', function (): void {
    EmailTemplate::factory()->create([
        'name' => 'Custom Review Template',
        'email_type' => EmailType::CUSTOMER_REVIEW_OPENED,
    ]);

    $this->actingAs($this->manager)
        ->get(route('admin.email-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailTemplates/Index')
            ->has('templates.data', 1)
            ->has('emailTypes')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.email-templates.index'))
        ->assertOk();
});

it('forbids technicians from accessing email template library', function (): void {
    $this->actingAs($this->tech)
        ->get(route('admin.email-templates.index'))
        ->assertForbidden();
});

it('passes default templates and variables to create page for managers', function (): void {
    $this->actingAs($this->manager)
        ->get(route('admin.email-templates.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailTemplates/Show')
            ->has('defaultTemplates')
            ->has('variables')
            ->has('emailTypes')
        );
});

it('allows a manager to create and update an email template', function (): void {
    $storeResponse = $this->actingAs($this->manager)
        ->post(route('admin.email-templates.store'), [
            'email_type' => EmailType::CHANGE_ORDER->value,
            'name' => 'Manager Custom CO Template',
            'subject_template' => 'CO for {{site_name}}',
            'body_template' => 'Please review: {{change_order_number}}',
            'status' => EmailTemplateStatus::ACTIVE->value,
            'is_default' => true,
        ]);

    $template = EmailTemplate::where('name', 'Manager Custom CO Template')->firstOrFail();
    $storeResponse->assertRedirect(route('admin.email-templates.show', $template));
    expect($template->is_default)->toBeTrue();

    $updateResponse = $this->actingAs($this->manager)
        ->put(route('admin.email-templates.update', $template), [
            'email_type' => EmailType::CHANGE_ORDER->value,
            'name' => 'Updated CO Template',
            'subject_template' => 'Updated Subject {{site_name}}',
            'body_template' => 'Updated Body',
            'status' => EmailTemplateStatus::ACTIVE->value,
            'is_default' => true,
        ]);

    $updateResponse->assertRedirect(route('admin.email-templates.show', $template));
    expect($template->fresh()->name)->toBe('Updated CO Template');
});
