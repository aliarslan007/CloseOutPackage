<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\Concerns\HasCustomerFeedback;
use App\Domains\Projects\Enums\IssueType;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\IssueHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class IssueHistory extends Model implements HasMedia
{
    /** @use HasFactory<IssueHistoryFactory> */
    use HasCustomerFeedback, HasFactory, HasObfuscatedRouteKey, InteractsWithMedia, LogsActivity;

    public const MEDIA_COLLECTION = 'issue_attachments';

    public const MEDIA_THUMB_CONVERSION = 'thumb';

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'reported_by',
        'resolved_by',
        'type',
        'description',
        'verizon_guidance',
        'resolved_at',
    ];

    protected static function newFactory(): IssueHistoryFactory
    {
        return IssueHistoryFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'description', 'resolved_at'])
            ->useLogName(self::$logName);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::MEDIA_THUMB_CONVERSION)
            ->width(1000)
            ->height(1000)
            ->sharpen(10)
            ->nonQueued();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<IssueComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    public function feedbackEntityType(): FeedbackEntityType
    {
        return FeedbackEntityType::ISSUE;
    }
}
