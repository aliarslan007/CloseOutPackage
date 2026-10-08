<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The listings' "Feedback awaiting reply" flag is one aliased aggregate
 * (HasCustomerFeedback::scopeWithPendingFeedbackCount). If its status
 * predicate, alias, or the relation's entity-type guard drifts, packages get
 * flagged for rounds nobody submitted — or silently skipped when one was.
 */
it('counts only submitted feedback rounds as pending for the list flag', function (): void {
    $fixture = feedbackFixture();

    $waiting = $fixture['package'];
    $settled = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    foreach ([$waiting, $settled] as $package) {
        $package->feedbackRounds()->create([
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'round_number' => 1,
            'status' => FeedbackRoundStatus::SUBMITTED->value,
            'opened_by' => $fixture['manager']->id,
        ]);
    }

    // The customer accepted the second package's round: nothing is awaiting anyone.
    $settled->feedbackRounds()->update(['status' => FeedbackRoundStatus::ACCEPTED->value]);

    $counts = CloseoutPackage::query()
        ->withPendingFeedbackCount()
        ->pluck('pending_feedback_count', 'id');

    expect($counts[$waiting->id])->toBe(1)
        ->and($counts[$settled->id])->toBe(0);
});
