<?php

declare(strict_types=1);

namespace App\Domains\Email\Models;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'project_id',
        'change_order_id',
        'recipient_email',
        'recipient_name',
        'sender_email',
        'sender_name',
        'cc_emails',
        'subject',
        'body_preview',
        'type',
        'status',
        'sent_at',
        'scheduled_at',
        'cancelled_at',
        'created_by',
        'context_id',
        'payload',
        'failed_reason',
        'idempotency_key',
        'provider_message_id',
    ];

    protected static function newFactory(): EmailLogFactory
    {
        return EmailLogFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmailType::class,
            'status' => EmailStatus::class,
            'cc_emails' => 'array',
            'payload' => 'array',
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CloseoutPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CloseoutPackage::class, 'closeout_package_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ChangeOrder, $this>
     */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    /**
     * The manager who released (sent/scheduled) or cancelled this email.
     * Null when the intent was recorded by a queued notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this row is still actionable in the outbox.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Whether a queued job may still deliver this row.
     */
    public function isDeliverable(): bool
    {
        return $this->status->isDeliverable();
    }
}
