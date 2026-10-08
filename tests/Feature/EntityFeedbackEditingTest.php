<?php

use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Managers and customers both make mistakes, and a typo should not be
 * permanent. What they may correct, and for how long, is the whole subject
 * here:
 *
 *  - the author of a message may correct that message,
 *  - only for a short window after writing it, measured from the write so that
 *    editing does not buy another window,
 *  - the window applies to a manager's reply too,
 *  - the server decides; the client only shows or hides the menu item,
 *  - the length rule is the same for a query and for an answer.
 */

// --- the manager's reply --------------------------------------------------

it('lets the manager correct a reply inside the edit window', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Conduit is not grounded.');

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Regrounded it.',
        ])
        ->assertRedirect();

    expect($note->fresh()->manager_response)->toBe('Regrounded it.');

    // Same endpoint, second pass: the reply is a message, so it is corrected
    // rather than posted again.
    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Regrounded it and retorqued the lug.',
        ])
        ->assertRedirect();

    expect($note->fresh()->manager_response)->toBe('Regrounded it and retorqued the lug.');
})->group('edit');

it('refuses to change a reply once the edit window has passed', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture);

    $note->forceFill([
        'manager_response' => 'PUBLISHED WORDING',
        'resolved_at' => now()->subMinutes(EntityFeedbackNote::editWindowMinutes() + 5),
        'resolved_by' => $fixture['manager']->id,
        'response_sent_at' => now()->subMinutes(10),
    ])->save();

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'REWRITTEN MUCH LATER',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($note->fresh()->manager_response)->toBe('PUBLISHED WORDING');
})->group('edit');

it('refuses a reply written by somebody else, however fresh it is', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture);

    $note->forceFill([
        'manager_response' => 'TECHNICIAN WORDING',
        'resolved_at' => now(),
        'resolved_by' => $fixture['admin']->id,
    ])->save();

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'HIJACKED',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($note->fresh()->manager_response)->toBe('TECHNICIAN WORDING');
})->group('edit');

// --- the customer's own message -------------------------------------------

it('lets the author correct their own message and marks it edited', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'The label is missing on panel 3.');

    $this->actingAs($fixture['customer'])
        ->patch(route('entity-feedback.notes.update', $note), [
            'body' => 'The label is missing on panel 4.',
        ])
        ->assertRedirect();

    $note->refresh();

    expect($note->body)->toBe('The label is missing on panel 4.')
        ->and($note->isEdited())->toBeTrue();

    // The marker has to reach the client, or the reader cannot tell the message
    // changed under them.
    $view = app(FeedbackRoundPresenter::class)
        ->present($round->fresh()->load('notes.author'), $fixture['customer']);

    expect($view['notes'][0]['edited_at'])->not->toBeNull();
})->group('edit');

it('refuses a message edit once the edit window has passed', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Original wording.');

    $note->forceFill([
        'created_at' => now()->subMinutes(EntityFeedbackNote::editWindowMinutes() + 5),
    ])->save();

    $this->actingAs($fixture['customer'])
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Too late.'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($note->fresh()->body)->toBe('Original wording.');
})->group('edit');

it('refuses an edit from anyone but the author', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Original wording.');

    $this->actingAs($fixture['manager'])
        ->patchJson(route('entity-feedback.notes.update', $note), ['body' => 'Rewritten by staff.'])
        ->assertForbidden();

    expect($note->fresh()->body)->toBe('Original wording.')
        ->and($note->fresh()->isEdited())->toBeFalse();
})->group('edit');

// --- what the client is told ----------------------------------------------

it('tells each viewer which of their own messages are still editable', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);

    $fresh = packageNote($round, $fixture, 'Written just now.');
    $stale = packageNote($round, $fixture, 'Written a while ago.');
    $stale->forceFill([
        'created_at' => now()->subMinutes(EntityFeedbackNote::editWindowMinutes() + 5),
    ])->save();

    $note = $fresh->fresh();
    $note->forceFill([
        'manager_response' => 'Answered.',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ])->save();

    $presenter = app(FeedbackRoundPresenter::class);
    $asCustomer = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);
    $asManager = $presenter->present($round->fresh()->load('notes.author'), $fixture['manager']);

    $customerFlags = collect($asCustomer['notes'])->keyBy('id');

    // The author may correct a fresh message, not an old one, and never the
    // reply — that is the manager's own text.
    expect($customerFlags[$fresh->id]['can_edit'])->toBeTrue()
        ->and($customerFlags[$fresh->id]['can_edit_answer'])->toBeFalse()
        ->and($customerFlags[$stale->id]['can_edit'])->toBeFalse()
        // A manager may not edit a customer's message, but may correct their own reply.
        ->and(collect($asManager['notes'])->keyBy('id')[$fresh->id]['can_edit'])->toBeFalse()
        ->and(collect($asManager['notes'])->keyBy('id')[$fresh->id]['can_edit_answer'])->toBeTrue();
})->group('edit');

it('shares the edit window with the client so the menu matches the rule', function (): void {
    // The window is configuration, so it is proven by reconfiguring it rather
    // than by asserting today's default.
    config()->set('entity-feedback.edit_window_minutes', 2);

    $fixture = feedbackFixture();

    expect(EntityFeedbackNote::editWindowMinutes())->toBe(2);

    $this->actingAs($fixture['manager'])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feedbackEditWindowMinutes', 2)
        );
})->group('edit');

// --- the length rule ------------------------------------------------------

it('caps a message at the shared limit and shares that limit with the client', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Question');

    $limit = EntityFeedbackNote::MAX_BODY_LENGTH;

    $this->actingAs($fixture['customer'])
        ->patch(route('entity-feedback.notes.update', $note), ['body' => str_repeat('a', $limit + 1)])
        ->assertSessionHasErrors('body');

    // The client renders the counter from this shared prop, so the prop is the
    // contract between the rule and the counter.
    expect($limit)->toBe(3000);

    $this->actingAs($fixture['customer'])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('feedbackMessageLimit', $limit)
        );
})->group('edit');

it('caps a manager response at the shared limit', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Question');

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => str_repeat('a', EntityFeedbackNote::MAX_BODY_LENGTH + 1),
        ])
        ->assertSessionHasErrors('manager_response');

    expect($note->fresh()->manager_response)->toBeNull();
})->group('edit');
