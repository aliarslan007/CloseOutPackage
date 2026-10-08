<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Models;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\Users\Models\User;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email_type',
        'name',
        'subject_template',
        'body_template',
        'status',
        'is_default',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_type' => EmailType::class,
            'status' => EmailTemplateStatus::class,
            'is_default' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'subject_template', 'body_template', 'status', 'is_default'])
            ->useLogName('email_template');
    }

    protected static function newFactory(): EmailTemplateFactory
    {
        return EmailTemplateFactory::new();
    }

    /**
     * Only active templates appear in the send-flow picker.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmailTemplateStatus::ACTIVE);
    }

    public function scopeForType(Builder $query, EmailType $type): void
    {
        $query->where('email_type', $type);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
