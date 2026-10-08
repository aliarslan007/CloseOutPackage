<?php

declare(strict_types=1);

namespace App\Domains\Approval\Models;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Database\Factories\PhotoApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PhotoApproval extends Model
{
    /** @use HasFactory<PhotoApprovalFactory> */
    use HasFactory, LogsActivity;

    protected static string $logName = 'approval';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'photo_id',
        'reviewed_by',
        'decision',
        'reason',
    ];

    protected static function newFactory(): PhotoApprovalFactory
    {
        return PhotoApprovalFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['decision', 'reason'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
