<?php

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

function recManagerProject(): array
{
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    return [$manager, $project];
}

it('uploads a REC document for a project and records the uploader', function (): void {
    [$manager, $project] = recManagerProject();

    $this->actingAs($manager)
        ->post(route('projects.rec-documents.store', $project), [
            'document' => UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $media = $project->getMedia(Project::REC_DOCUMENTS_COLLECTION)->first();
    expect($media)->not->toBeNull()
        ->and($media->file_name)->toBe('rec.pdf')
        ->and($media->getCustomProperty('uploaded_by'))->toBe($manager->name);
});

it('rejects a REC upload that is not a pdf or image', function (): void {
    [$manager, $project] = recManagerProject();

    $this->actingAs($manager)
        ->post(route('projects.rec-documents.store', $project), [
            'document' => UploadedFile::fake()->create('notes.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors('document');

    expect($project->getMedia(Project::REC_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('denies REC uploads to field supervisors', function (): void {
    [$manager, $project] = recManagerProject();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project->fieldSupervisors()->attach($supervisor);

    $this->actingAs($supervisor)
        ->post(route('projects.rec-documents.store', $project), [
            'document' => UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($project->getMedia(Project::REC_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('serves the REC document inline to a scoped customer', function (): void {
    [$manager, $project] = recManagerProject();
    $media = $project->addMedia(UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->get(route('projects.rec-documents.show', [$project, $media]))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="rec.pdf"');
});

it('offers the REC document as a download', function (): void {
    [$manager, $project] = recManagerProject();
    $media = $project->addMedia(UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.rec-documents.download', [$project, $media]))
        ->assertOk()
        ->assertDownload('rec.pdf');
});

it('serves a preview of the REC document under the same project guard', function (): void {
    [$manager, $project] = recManagerProject();
    $media = $project->addMedia(UploadedFile::fake()->image('rec-scan.png', 400, 300))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.rec-documents.show', [$project, $media]).'?preview=1')
        ->assertOk();

    $outsider = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->actingAs($outsider)
        ->get(route('projects.rec-documents.show', [$project, $media]).'?preview=1')
        ->assertForbidden();
});

it('hides the REC document from customers without project access', function (): void {
    [$manager, $project] = recManagerProject();
    $media = $project->addMedia(UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $outsider = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($outsider)
        ->get(route('projects.rec-documents.show', [$project, $media]))
        ->assertForbidden();
});

it('404s when the media does not belong to the project', function (): void {
    [$manager, $project] = recManagerProject();
    [$otherManager, $otherProject] = recManagerProject();
    $media = $otherProject->addMedia(UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.rec-documents.show', [$project, $media]))
        ->assertNotFound();
});

it('lets the assigned manager remove a REC document but not the customer', function (): void {
    [$manager, $project] = recManagerProject();
    $media = $project->addMedia(UploadedFile::fake()->create('rec.pdf', 200, 'application/pdf'))
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->delete(route('projects.rec-documents.destroy', [$project, $media->id]))
        ->assertRedirect();

    $this->actingAs($manager)
        ->delete(route('projects.rec-documents.destroy', [$project, $media->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($project->getMedia(Project::REC_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('returns the updated REC collection as JSON for an XHR upload', function (): void {
    [$manager, $project] = recManagerProject();

    $this->actingAs($manager)
        ->post(
            route('projects.rec-documents.store', $project),
            ['document' => UploadedFile::fake()->create('rec.pdf', 120, 'application/pdf')],
            ['Accept' => 'application/json'],
        )
        ->assertOk()
        ->assertJsonPath('collection', Project::REC_DOCUMENTS_COLLECTION)
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.name', 'rec.pdf');
});
