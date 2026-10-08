<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Models\Concerns;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Opts an entity into the single feedback engine.
 *
 * The `*FeedbackRound` methods are convenience accessors; the lowercase
 * relations exist so callers can eager-load a round (an eager load needs a
 * relation, not a method) and keep the round query off the hot path.
 */
trait HasCustomerFeedback
{
    /**
     * @return HasMany<EntityFeedbackRound, $this>
     */
    public function feedbackRounds(): HasMany
    {
        return $this->hasMany(EntityFeedbackRound::class, 'entity_id')
            ->where('entity_type', $this->feedbackEntityType()->value);
    }

    /**
     * The newest round still awaiting the customer's acceptance — open,
     * submitted or resolved. This decides whether a live feedback loop remains,
     * and is deliberately distinct from the draft-only open round.
     *
     * @return HasOne<EntityFeedbackRound, $this>
     */
    public function activeFeedbackRound(): HasOne
    {
        return $this->hasOne(EntityFeedbackRound::class, 'entity_id')
            ->where('entity_type', $this->feedbackEntityType()->value)
            ->whereIn('status', [
                FeedbackRoundStatus::OPEN->value,
                FeedbackRoundStatus::SUBMITTED->value,
                FeedbackRoundStatus::RESOLVED->value,
            ])
            ->latestOfMany('round_number');
    }

    /**
     * The draft round a customer can still add notes to.
     *
     * @return HasOne<EntityFeedbackRound, $this>
     */
    public function openFeedbackRound(): HasOne
    {
        return $this->hasOne(EntityFeedbackRound::class, 'entity_id')
            ->where('entity_type', $this->feedbackEntityType()->value)
            ->where('status', FeedbackRoundStatus::OPEN->value)
            ->latestOfMany('round_number');
    }

    public function latestFeedbackRound(): ?EntityFeedbackRound
    {
        return $this->feedbackRounds()
            ->with(['notes.author', 'openedBy', 'submittedBy', 'resolvedBy'])
            ->latest('round_number')
            ->first();
    }

    public function latestActiveFeedbackRound(): ?EntityFeedbackRound
    {
        // A decided entity has no live round, even if an earlier round was left
        // open: the customer must not be shown an editor for a change order they
        // have already ruled on. The rounds stay reachable as history.
        if ($this->feedbackIsDecided()) {
            return null;
        }

        return $this->activeFeedbackRound()
            ->with([
                'notes.author',
                'openedBy',
                'submittedBy',
                'assignedToUser',
            ])
            ->first();
    }

    public function latestOpenFeedbackRound(): ?EntityFeedbackRound
    {
        return $this->openFeedbackRound()
            ->with(['notes.author', 'openedBy'])
            ->first();
    }

    /**
     * Whether a technician is working through a live review right now.
     *
     * Like hasPendingFeedback(), an assignment carries no work once the customer
     * has withdrawn every issue in it, so this must not keep a locked package
     * editable on its own.
     */
    public function hasActiveFeedbackAssignedToTechnician(): bool
    {
        $round = $this->latestActiveFeedbackRound();

        return $round !== null
            && ! $round->issuesAllWithdrawn()
            && ($round->assigned_at !== null || $round->assigned_to_user_id !== null);
    }

    /**
     * Adds a `pending_feedback_count` attribute: rounds the customer has
     * submitted that still await an answer — the same SUBMITTED signal the
     * package screen's feedback divider nudges on, so a list can never flag a
     * row the detail page disagrees with. One aggregate join, so paginated
     * listings get the flag without an exists() query per row.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithPendingFeedbackCount(Builder $query): Builder
    {
        return $query->withCount([
            'feedbackRounds as pending_feedback_count' => fn (Builder $q) => $q->awaitingReply(),
        ]);
    }

    /**
     * Every round ever opened on this entity, oldest first, with the notes and
     * people needed to render a conversation. This is the archive behind the
     * review history, not a lifecycle query.
     *
     * @return Collection<int, EntityFeedbackRound>
     */
    public function feedbackRoundHistory(): Collection
    {
        return $this->feedbackRounds()
            ->with(['notes.author', 'openedBy', 'submittedBy', 'assignedToUser'])
            ->orderBy('round_number')
            ->orderBy('id')
            ->get();
    }

    abstract public function feedbackEntityType(): FeedbackEntityType;

    /**
     * Whether this entity has been ruled on and its review therefore closed.
     * Only some entity types can be decided; the default is "not decided".
     */
    protected function feedbackIsDecided(): bool
    {
        return false;
    }
}
