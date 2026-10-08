<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * The annotation is drawn on a canvas by the customer and stored on the private
 * local disk. Two bugs shipped from that: the URL handed to the browser was a
 * raw /storage/... path (403), and building it read a relation that no caller
 * had eager loaded (LazyLoadingViolationException, 500).
 */
function annotatedNoteFor(array $fixture): EntityFeedbackNote
{
    $round = $fixture['changeOrder']->latestFeedbackRound()
        ?? $fixture['changeOrder']->feedbackRounds()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::OPEN->value,
            'opened_by' => $fixture['manager']->id,
        ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $fixture['changeOrder']->id,
        'source_photo_id' => 1,
        'source_photo_type' => 'photo',
        'original_image_url' => 'https://example.test/photo.jpg',
        'body' => 'Please retake this.',
        'created_by' => $fixture['customer']->id,
    ]);

    $media = $note->addMedia(UploadedFile::fake()->image('annotation.jpg'))
        ->usingFileName('feedback-annotation-'.$note->id.'.jpg')
        ->usingName('Annotated Feedback Image')
        ->toMediaCollection(EntityFeedbackNote::MEDIA_COLLECTION);

    $note->update(['annotated_media_id' => $media->id]);

    return $note->fresh();
}

/**
 * Submits the round, which is the moment a customer's draft becomes readable by
 * staff.
 */
function submitRound(EntityFeedbackNote $note): void
{
    $note->round->update([
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $note->created_by,
    ]);
}

function feedbackFixtureWithChangeOrder(): array
{
    $fixture = feedbackFixture();

    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    return [...$fixture, 'changeOrder' => $changeOrder];
}

it('serves the annotation as an authorized route instead of a 403 disk path', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    $url = $note->annotated_image_url;

    expect($url)->not->toBeNull()
        ->and($url)->not->toContain('/storage/')
        ->and($url)->toContain(route('entity-feedback.notes.image', [
            'note' => $note->getRouteKey(),
            'kind' => 'annotated',
        ]));

    $this->actingAs($fixture['manager'])
        ->get($url)
        ->assertForbidden();

    submitRound($note);

    $this->actingAs($fixture['manager'])
        ->get($url)
        ->assertOk();
})->group('annotation-image');

it('serves the annotation to the customer who wrote it', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    $this->actingAs($fixture['customer'])
        ->get($note->annotated_image_url)
        ->assertOk();
})->group('annotation-image');

it('refuses the annotation to a customer with no project access', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    $stranger = User::factory()->create()
        ->assignRole(Role::CUSTOMER->value);

    $this->actingAs($stranger)
        ->get($note->annotated_image_url)
        ->assertForbidden();
})->group('annotation-image');

it('refuses the annotation to a guest', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    // The auth middleware bounces a guest to the app's session-expired landing;
    // the point is that no bytes of the annotation are served.
    $this->get($note->annotated_image_url)->assertRedirect('/session-expired');
})->group('annotation-image');

it('refuses an undelegated field supervisor', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    $tech = User::factory()->create()
        ->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($tech)
        ->get($note->annotated_image_url)
        ->assertForbidden();
})->group('annotation-image');

it('404s a kind the note does not have', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    expect($note->replacement_image_url)->toBeNull();

    submitRound($note);

    $this->actingAs($fixture['manager'])
        ->get(route('entity-feedback.notes.image', [
            'note' => $note->getRouteKey(),
            'kind' => 'replacement',
        ]))
        ->assertNotFound();
})->group('annotation-image');

it('rejects an unknown image kind at the route', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    submitRound($note);

    $this->actingAs($fixture['manager'])
        ->get(route('entity-feedback.notes.image', [
            'note' => $note->getRouteKey(),
            'kind' => 'secrets',
        ]))
        ->assertNotFound();
})->group('annotation-image');

it('builds both image urls without lazy loading a relation', function (): void {
    $fixture = feedbackFixtureWithChangeOrder();
    $note = annotatedNoteFor($fixture);

    // A note with nothing loaded must still serialize: this is the exact path
    // that threw LazyLoadingViolationException on the customer change-order page.
    $bare = EntityFeedbackNote::query()->findOrFail($note->id);

    expect($bare->annotated_image_url)->not->toBeNull()
        ->and($bare->replacement_image_url)->toBeNull()
        ->and($bare->toArray())->toHaveKey('annotated_image_url');
})->group('annotation-image');
