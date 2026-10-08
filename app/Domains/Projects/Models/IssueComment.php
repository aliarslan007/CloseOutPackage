<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\IssueCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class IssueComment extends Model implements HasMedia
{
    /** @use HasFactory<IssueCommentFactory> */
    use HasFactory, HasObfuscatedRouteKey, InteractsWithMedia, LogsActivity;

    public const MEDIA_COLLECTION = 'comment_attachments';

    public const MEDIA_THUMB_CONVERSION = 'thumb';

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'issue_history_id',
        'user_id',
        'body',
    ];

    protected static function newFactory(): IssueCommentFactory
    {
        return IssueCommentFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['body'])
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
     * @return BelongsTo<IssueHistory, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(IssueHistory::class, 'issue_history_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
