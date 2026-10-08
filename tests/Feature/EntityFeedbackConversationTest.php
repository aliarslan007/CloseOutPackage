<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\EntityFeedback\Actions\ResolveAllFeedbackNotesAction;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntityFeedbackConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $customer;

    private Project $project;

    private ChangeOrder $changeOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $fixture = feedbackFixture();
        $this->manager = $fixture['manager'];
        $this->customer = $fixture['customer'];
        $this->project = $fixture['project'];

        $this->changeOrder = ChangeOrder::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->manager->id,
            'status' => ChangeOrderStatus::SENT_TO_CUSTOMER->value,
        ]);
    }

    public function test_manager_and_customer_can_exchange_unlimited_messages_in_a_thread(): void
    {
        // 1. Open and submit a round
        $round = EntityFeedbackRound::query()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::OPEN,
            'opened_by' => $this->customer->id,
        ]);

        // Customer creates initial feedback on photo #10
        $round->notes()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'supporting_photo',
            'body' => 'Can you clarify this cable routing?',
            'created_by' => $this->customer->id,
        ]);

        // Submit to manager
        $round->update([
            'status' => FeedbackRoundStatus::SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $this->customer->id,
        ]);

        // 2. Manager posts first reply with a replacement image
        $response = $this->actingAs($this->manager)->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'supporting_photo',
            'body' => 'We verified the routing on-site. Here is the updated capture.',
            'replacement_image' => UploadedFile::fake()->image('replacement.jpg', 800, 600),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('entity_feedback_notes', 2);

        $managerNote = EntityFeedbackNote::query()->latest('id')->first();
        $this->assertNotNull($managerNote);
        $this->assertSame($this->manager->id, $managerNote->created_by);
        $this->assertSame('We verified the routing on-site. Here is the updated capture.', $managerNote->body);
        $this->assertNotNull($managerNote->replacement_media_id);

        // 3. Customer replies back in the same thread
        $customerReply = $this->actingAs($this->customer)->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'supporting_photo',
            'body' => 'Looks much better, thank you!',
        ]);

        $customerReply->assertRedirect();
        $this->assertDatabaseCount('entity_feedback_notes', 3);

        // 4. Manager posts another message in the same thread (unlimited messages)
        $managerSecondReply = $this->actingAs($this->manager)->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'supporting_photo',
            'body' => 'Glad to help. Let us know if anything else is needed.',
        ]);

        $managerSecondReply->assertRedirect();
        $this->assertDatabaseCount('entity_feedback_notes', 4);

        // 5. Manager sends final resolution
        app(ResolveAllFeedbackNotesAction::class)->execute($round->fresh(), $this->manager, 'All items resolved.');

        $round->refresh();
        $this->assertSame(FeedbackRoundStatus::RESOLVED, $round->status);
        $this->assertNotNull($round->resolved_at);
        $this->assertNotNull($round->resolved_by);
    }

    public function test_manager_can_replace_change_order_photo_via_feedback_reply(): void
    {
        Storage::fake('private');

        $photo = $this->changeOrder->photos()->create([
            'uploaded_by' => $this->manager->id,
            'original_name' => 'original.jpg',
            'path' => 'change-orders/'.$this->changeOrder->id.'/original.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
        Storage::disk('private')->put($photo->path, 'fake original content');

        $round = EntityFeedbackRound::query()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::SUBMITTED,
            'opened_by' => $this->customer->id,
            'submitted_at' => now(),
            'submitted_by' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->manager)->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => $photo->id,
            'source_photo_type' => 'change_order_photo',
            'body' => 'Here is the clear replacement photo.',
            'replacement_image' => UploadedFile::fake()->image('clear-replacement.jpg', 1200, 900),
            'replace_in_change_order' => true,
        ]);

        $response->assertRedirect();

        $photo->refresh();
        $this->assertSame('clear-replacement.jpg', $photo->original_name);
        $this->assertTrue(Storage::disk('private')->exists($photo->path));
    }

    public function test_manager_can_include_new_change_order_photo_via_feedback_reply(): void
    {
        Storage::fake('private');

        $round = EntityFeedbackRound::query()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::SUBMITTED,
            'opened_by' => $this->customer->id,
            'submitted_at' => now(),
            'submitted_by' => $this->customer->id,
        ]);

        $this->assertCount(0, $this->changeOrder->photos);

        $response = $this->actingAs($this->manager)->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'section',
            'body' => 'Added a supplemental photo as requested.',
            'replacement_image' => UploadedFile::fake()->image('supplemental.jpg', 1200, 900),
            'add_to_change_order_photos' => true,
        ]);

        $response->assertRedirect();

        $this->changeOrder->refresh();
        $this->assertCount(1, $this->changeOrder->photos);
        $newPhoto = $this->changeOrder->photos()->first();
        $this->assertSame('supplemental.jpg', $newPhoto->original_name);
        $this->assertTrue(Storage::disk('private')->exists($newPhoto->path));
    }

    public function test_user_can_edit_own_note_within_window(): void
    {
        $round = EntityFeedbackRound::query()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::SUBMITTED,
            'opened_by' => $this->customer->id,
            'submitted_at' => now(),
            'submitted_by' => $this->customer->id,
        ]);

        $note = $round->notes()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER,
            'entity_id' => $this->changeOrder->id,
            'source_photo_id' => 10,
            'source_photo_type' => 'supporting_photo',
            'body' => 'Initial inquiry text.',
            'created_by' => $this->customer->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->customer)->patch(route('entity-feedback.notes.update', $note), [
            'body' => 'Corrected inquiry text.',
        ]);

        $response->assertRedirect();
        $note->refresh();
        $this->assertSame('Corrected inquiry text.', $note->body);
        $this->assertNotNull($note->edited_at);
    }
}
