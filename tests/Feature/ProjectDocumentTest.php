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

/**
 * The five categories served by ProjectDocumentController.
 * REC keeps its own controller and routes.
 *
 * @return array<string, string>
 */
function projectDocumentCategories(): array
{
    return [
        'ffr-sow' => Project::FFR_SOW_DOCUMENTS_COLLECTION,
        'third-party-compliance' => Project::THIRD_PARTY_COMPLIANCE_COLLECTION,
        'landlord-compliance' => Project::LANDLORD_COMPLIANCE_COLLECTION,
        'cd' => Project::CD_DOCUMENTS_COLLECTION,
        'project-emails' => Project::PROJECT_EMAIL_DOCUMENTS_COLLECTION,
    ];
}

/**
 * @return array{0: User, 1: Project}
 */
function projectDocumentContext(): array
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

function projectDocumentUpload(): UploadedFile
{
    return UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
}

it('uploads a document to every new collection for an admin', function (): void {
    [, $project] = projectDocumentContext();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    foreach (projectDocumentCategories() as $slug => $collection) {
        $this->actingAs($admin)
            ->post(route('projects.documents.store', [$project, $slug]), [
                'document' => projectDocumentUpload(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($project->refresh()->getMedia($collection))->toHaveCount(1);
    }
});

it('lets the assigned manager upload a document and records the uploader', function (): void {
    [$manager, $project] = projectDocumentContext();

    $this->actingAs($manager)
        ->post(route('projects.documents.store', [$project, 'ffr-sow']), [
            'document' => projectDocumentUpload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $media = $project->getMedia(Project::FFR_SOW_DOCUMENTS_COLLECTION)->first();
    expect($media)->not->toBeNull()
        ->and($media->getCustomProperty('uploaded_by'))->toBe($manager->name);
});

it('denies uploads to field supervisors', function (): void {
    [, $project] = projectDocumentContext();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project->fieldSupervisors()->attach($supervisor);

    $this->actingAs($supervisor)
        ->post(route('projects.documents.store', [$project, 'cd']), [
            'document' => projectDocumentUpload(),
        ])
        ->assertRedirect();

    expect($project->getMedia(Project::CD_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('denies uploads to customers', function (): void {
    [, $project] = projectDocumentContext();
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->post(route('projects.documents.store', [$project, 'project-emails']), [
            'document' => projectDocumentUpload(),
        ])
        ->assertRedirect();

    expect($project->getMedia(Project::PROJECT_EMAIL_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('lets an assigned field supervisor download a document', function (): void {
    [, $project] = projectDocumentContext();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project->fieldSupervisors()->attach($supervisor);
    $media = $project->addMedia(UploadedFile::fake()->create('sow.pdf', 120, 'application/pdf'))
        ->toMediaCollection(Project::FFR_SOW_DOCUMENTS_COLLECTION);

    $this->actingAs($supervisor)
        ->get(route('projects.documents.download', [$project, 'ffr-sow', $media]))
        ->assertOk()
        ->assertDownload('sow.pdf');
});

it('redirects guests away from a document', function (): void {
    [, $project] = projectDocumentContext();
    $media = $project->addMedia(UploadedFile::fake()->create('sow.pdf', 120, 'application/pdf'))
        ->toMediaCollection(Project::FFR_SOW_DOCUMENTS_COLLECTION);

    $this->get(route('projects.documents.show', [$project, 'ffr-sow', $media]))
        ->assertRedirect();
});

it('rejects an unsupported file type', function (): void {
    [$manager, $project] = projectDocumentContext();

    $this->actingAs($manager)
        ->post(route('projects.documents.store', [$project, 'cd']), [
            'document' => UploadedFile::fake()->create('photo.png', 100, 'image/png'),
        ])
        ->assertSessionHasErrors('document');

    expect($project->getMedia(Project::CD_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('rejects a document larger than 25 MB', function (): void {
    [$manager, $project] = projectDocumentContext();

    $this->actingAs($manager)
        ->post(route('projects.documents.store', [$project, 'cd']), [
            'document' => UploadedFile::fake()->create('huge.pdf', 26000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document');

    expect($project->getMedia(Project::CD_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('404s for an unknown document collection slug', function (): void {
    [$manager, $project] = projectDocumentContext();

    $this->actingAs($manager)
        ->post(route('projects.documents.store', [$project, 'not-a-collection']), [
            'document' => projectDocumentUpload(),
        ])
        ->assertNotFound();
});

it('404s when the media belongs to another project', function (): void {
    [$manager, $project] = projectDocumentContext();
    [, $otherProject] = projectDocumentContext();
    $media = $otherProject->addMedia(UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::CD_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.documents.show', [$project, 'cd', $media]))
        ->assertNotFound();
});

it('404s when the media is in a different collection', function (): void {
    [$manager, $project] = projectDocumentContext();
    $media = $project->addMedia(UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::CD_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.documents.show', [$project, 'ffr-sow', $media]))
        ->assertNotFound();
});

it('lets the manager remove a document but not the customer', function (): void {
    [$manager, $project] = projectDocumentContext();
    $media = $project->addMedia(UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::FFR_SOW_DOCUMENTS_COLLECTION);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->delete(route('projects.documents.destroy', [$project, 'ffr-sow', $media->id]))
        ->assertRedirect();

    $this->actingAs($manager)
        ->delete(route('projects.documents.destroy', [$project, 'ffr-sow', $media->id]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($project->getMedia(Project::FFR_SOW_DOCUMENTS_COLLECTION))->toBeEmpty();
});

it('includes every document category in the project show payload', function (): void {
    [$manager, $project] = projectDocumentContext();
    $project->addMedia(UploadedFile::fake()->create('rec.pdf', 100, 'application/pdf'))
        ->withCustomProperties(['uploaded_by' => 'Test Manager'])
        ->toMediaCollection(Project::REC_DOCUMENTS_COLLECTION);
    $project->addMedia(UploadedFile::fake()->create('sow.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::FFR_SOW_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('projectDocuments', 6)
                ->has('projectDocuments.rec_documents', 1)
                ->where('projectDocuments.rec_documents.0.name', 'rec.pdf')
                ->where('projectDocuments.rec_documents.0.uploaded_by', 'Test Manager')
                ->has('projectDocuments.ffr_sow_documents', 1)
                ->where('projectDocuments.ffr_sow_documents.0.name', 'sow.pdf')
                ->has('projectDocuments.ffr_sow_documents.0.view_url')
                ->has('projectDocuments.ffr_sow_documents.0.download_url')
        );
});

it('accepts every supported document type', function (): void {
    [$manager, $project] = projectDocumentContext();

    $files = [
        'spec.pdf' => 'application/pdf',
        'spec.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'budget.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'deck.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'notes.txt' => 'text/plain',
    ];

    foreach ($files as $name => $mime) {
        $this->actingAs($manager)
            ->post(route('projects.documents.store', [$project, 'ffr-sow']), [
                'document' => UploadedFile::fake()->create($name, 50, $mime),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect($project->refresh()->getMedia(Project::FFR_SOW_DOCUMENTS_COLLECTION))->toHaveCount(5);
});

it('exposes project documents to a scoped customer on the portal', function (): void {
    [, $project] = projectDocumentContext();
    $project->addMedia(UploadedFile::fake()->create('sow.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::FFR_SOW_DOCUMENTS_COLLECTION);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->get(route('portal.projects.show', $project))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->has('projectDocuments', 6)
                ->has('projectDocuments.ffr_sow_documents', 1)
                ->where('projectDocuments.ffr_sow_documents.0.name', 'sow.pdf')
        );
});

it('returns the updated collection as JSON for an XHR upload', function (): void {
    [$manager, $project] = projectDocumentContext();

    $this->actingAs($manager)
        ->post(
            route('projects.documents.store', [$project, 'cd']),
            ['document' => projectDocumentUpload()],
            ['Accept' => 'application/json'],
        )
        ->assertOk()
        ->assertJsonPath('collection', Project::CD_DOCUMENTS_COLLECTION)
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.name', 'doc.pdf');
});

it('returns the trimmed collection as JSON for an XHR delete', function (): void {
    [$manager, $project] = projectDocumentContext();
    $media = $project->addMedia(UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->toMediaCollection(Project::CD_DOCUMENTS_COLLECTION);

    $this->actingAs($manager)
        ->delete(
            route('projects.documents.destroy', [$project, 'cd', $media->id]),
            [],
            ['Accept' => 'application/json'],
        )
        ->assertOk()
        ->assertJsonPath('collection', Project::CD_DOCUMENTS_COLLECTION)
        ->assertJsonCount(0, 'documents');
});
