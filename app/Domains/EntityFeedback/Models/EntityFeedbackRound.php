<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Models;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EntityFeedbackRound extends Model
{
    use HasObfuscatedRouteKey, LogsActivity;

    protected static string $logName = 'entity_feedback';

    protected static bool $logOnlyDirty = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'round_number',
        'status',
        'opened_by',
        'submitted_at',
        'submitted_by',
        'manager_acknowledged_at',
        'manager_acknowledged_by',
        'assigned_to_user_id',
        'assigned_at',
        'assigned_by',
        'technician_submitted_at',
        'technician_submitted_by',
        'manager_instructions',
        'resolved_at',
        'resolved_by',
        'accepted_at',
        'accepted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => FeedbackEntityType::class,
            'status' => FeedbackRoundStatus::class,
            'round_number' => 'integer',
            'submitted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'technician_submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'manager_acknowledged_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_at', 'technician_submitted_at', 'resolved_at', 'accepted_at'])
            ->useLogName(self::$logName);
    }

    /**
     * @return HasMany<EntityFeedbackNote, $this>
     */
    public function notes(): HasMany
    {
        // No default ORDER BY on purpose: callers build on this query with their
        // own direction (notes()->orderByDesc('id') to grab the latest reply), and
        // a relation-level order would silently outrank them. Chronological
        // presentation is enforced in FeedbackRoundPresenter instead.
        return $this->hasMany(EntityFeedbackNote::class, 'feedback_round_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technicianSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isOpen(): bool
    {
        return $this->status === FeedbackRoundStatus::OPEN;
    }

    public function isSubmitted(): bool
    {
        return $this->status === FeedbackRoundStatus::SUBMITTED;
    }

    /**
     * Whether the customer has taken every issue in this round off the table.
     *
     * A round with no messages yet counts as standing work: a review somebody
     * just opened has had nothing dropped from it. Only a round whose every
     * issue was withdrawn has stopped asking for anything, which is what decides
     * whether a locked package may still be edited.
     */
    public function issuesAllWithdrawn(): bool
    {
        return $this->notes->isNotEmpty()
            && $this->notes->every(fn ($note) => $note->isWithdrawn());
    }

    /**
     * Rounds the customer has submitted and nobody has answered yet — the single
     * definition of "feedback awaiting a reply". Every listing flag, the package
     * divider nudge and the projects-table signal all route through here, so the
     * three can never drift apart on what counts as pending.
     *
     * @param  Builder<EntityFeedbackRound>  $query
     * @return Builder<EntityFeedbackRound>
     */
    public function scopeAwaitingReply(Builder $query): Builder
    {
        return $query->where('status', FeedbackRoundStatus::SUBMITTED->value);
    }

    public function isResolved(): bool
    {
        return $this->status === FeedbackRoundStatus::RESOLVED;
    }

    /**
     * The manager has seen the submission and started on it. Tracked as a
     * timestamp rather than a fifth status so "submitted" keeps meaning
     * actionable — forwarding and resolving still apply.
     */
    public function isAcknowledged(): bool
    {
        return $this->manager_acknowledged_at !== null;
    }

    public function managerAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_acknowledged_by');
    }

    public function isAccepted(): bool
    {
        return $this->status === FeedbackRoundStatus::ACCEPTED;
    }

    public function isAssignedToTechnician(): bool
    {
        return $this->assigned_at !== null && $this->technician_submitted_at === null;
    }

    public function isTechnicianSubmitted(): bool
    {
        return $this->technician_submitted_at !== null;
    }
}
