<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Models;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EntityFeedbackNote extends Model implements HasMedia
{
    use HasObfuscatedRouteKey, InteractsWithMedia, LogsActivity;

    public const MEDIA_COLLECTION = 'feedback_annotations';

    public const REPLACEMENT_COLLECTION = 'feedback_replacements';

    /**
     * Longest message — a customer's query or a manager's answer — the review
     * accepts. Shared with the client through the `feedbackMessageLimit` Inertia
     * prop so the counter and the rule never drift apart.
     */
    public const MAX_BODY_LENGTH = 3000;

    protected static string $logName = 'entity_feedback';

    protected static bool $logOnlyDirty = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feedback_round_id',
        'reply_to_note_id',
        'entity_type',
        'entity_id',
        'source_photo_id',
        'source_photo_type',
        'requested_action',
        'original_image_url',
        'annotated_media_id',
        'annotation_layer',
        'body',
        'edited_at',
        'created_by',
        'manager_response',
        'replacement_media_id',
        'resolved_at',
        'resolved_by',
        'response_sent_at',
        'withdrawn_at',
        'withdrawn_by',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'is_resolved',
        'is_withdrawn',
        'annotated_image_url',
        'replacement_image_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => FeedbackEntityType::class,
            'annotation_layer' => 'array',
            'edited_at' => 'datetime',
            'resolved_at' => 'datetime',
            'response_sent_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function getIsResolvedAttribute(): bool
    {
        return $this->isResolved();
    }

    public function getIsWithdrawnAttribute(): bool
    {
        return $this->isWithdrawn();
    }

    /**
     * Both accessors return an authorized application route rather than a disk
     * URL. The annotation lives on the private local disk, so MediaLibrary's
     * getUrl() produced a /storage/... path that the web server refuses with a
     * 403. Reading the foreign key instead of the relation also keeps this
     * lazy-load safe: serializing a note never triggers a query.
     */
    public function getAnnotatedImageUrlAttribute(): ?string
    {
        return $this->imageUrl('annotated_media_id', 'annotated');
    }

    public function getReplacementImageUrlAttribute(): ?string
    {
        return $this->imageUrl('replacement_media_id', 'replacement');
    }

    public function mediaIdFor(string $kind): ?int
    {
        $mediaId = match ($kind) {
            'annotated' => $this->annotated_media_id,
            'replacement' => $this->replacement_media_id,
            default => null,
        };

        return is_int($mediaId) ? $mediaId : null;
    }

    private function imageUrl(string $column, string $kind): ?string
    {
        if ($this->{$column} === null) {
            return null;
        }

        return route('entity-feedback.notes.image', [
            'note' => $this->getRouteKey(),
            'kind' => $kind,
        ]);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * True when the customer took this message off the list - "I don't want that
     * problem resolved". The note itself is untouched: only the ask is withdrawn,
     * so the conversation stays readable for everyone who could read it before.
     */
    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /** True once the author has corrected their own message. */
    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /** How long an author may correct a message, from when they wrote it. */
    public static function editWindowMinutes(): int
    {
        $minutes = config('entity-feedback.edit_window_minutes', 5);

        return is_numeric($minutes) ? max(0, (int) $minutes) : 0;
    }

    /**
     * Whether this user may still correct the message they wrote on this note.
     *
     * Anchored on when the text was written, not on the last edit, so fixing a
     * typo does not buy another window — otherwise "five minutes" would mean
     * "forever, five minutes at a time". Only the author may edit: rewriting
     * somebody else's words in a review is a different act entirely. A withdrawn
     * issue is closed, so its messages are frozen for everybody - the reader sees
     * the exchange exactly as the customer left it.
     */
    public function messageEditableBy(?int $userId): bool
    {
        return $userId !== null
            && ! $this->isWithdrawn()
            && $this->created_by === $userId
            && $this->withinEditWindow($this->created_at);
    }

    /**
     * Whether this user may still correct the answer they wrote on this note.
     * A manager edits their own reply, and only for the same short window.
     */
    public function answerEditableBy(?int $userId): bool
    {
        return $this->manager_response !== null
            && $userId !== null
            && $this->resolved_by === $userId
            && $this->withinEditWindow($this->resolved_at);
    }

    private function withinEditWindow(?CarbonInterface $writtenAt): bool
    {
        if ($writtenAt === null) {
            return false;
        }

        return $writtenAt->greaterThan(now()->subMinutes(self::editWindowMinutes()));
    }

    /**
     * True once the manager has published this answer to the customer. A reply
     * that is only saved on the note is still a draft.
     */
    public function isResponseSent(): bool
    {
        return $this->response_sent_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['resolved_at', 'manager_response', 'replacement_media_id'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<EntityFeedbackRound, $this>
     */
    /**
     * The message this one replies to. Two notes on the same photo are a
     * conversation on that photo; this makes the link explicit rather than
     * leaving the reader to infer it from matching ids.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_note_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_note_id')->orderBy('created_at');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EntityFeedbackRound::class, 'feedback_round_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function annotatedMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'annotated_media_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function replacementMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'replacement_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The manager who answered this turn. Named `resolved_by_user` on the wire
     * so it never collides with the bare `resolved_by` foreign key.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Erase the images this note carries. Which collections those are is the
     * note's own fact (the constants above), so deleting a message — one note or
     * a whole round — never re-lists them at the call site.
     */
    public function clearFeedbackMedia(): void
    {
        $this->clearMediaCollection(self::MEDIA_COLLECTION);
        $this->clearMediaCollection(self::REPLACEMENT_COLLECTION);
    }
}
