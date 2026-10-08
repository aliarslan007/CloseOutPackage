<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Models;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Services\ChangeOrderStatusService;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\Concerns\HasCustomerFeedback;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\ChangeOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChangeOrder extends Model
{
    /** @use HasFactory<ChangeOrderFactory> */
    use HasCustomerFeedback, HasFactory, HasObfuscatedRouteKey, LogsActivity, SoftDeletes;

    protected static string $logName = 'change_order';

    protected static bool $logOnlyDirty = true;

    /** Supporting photos a single change order may carry. Enforced server-side. */
    public const MAX_PHOTOS = 20;

    /** Title length cap. Shared with the client through the `titleLimit` Inertia
     *  prop so the two never drift apart. The `title` column stays varchar(255):
     *  validation is the contract, and a wider column is harmless headroom. */
    public const MAX_TITLE_LENGTH = 100;

    /** Cover note / description length cap. Shared with the client through the
     *  `descriptionLimit` Inertia prop so the two never drift apart. */
    public const MAX_DESCRIPTION_LENGTH = 5000;

    /** Description cap for a single itemized cost line. Shared with the client
     *  through the `itemDescriptionLimit` Inertia prop so the two never drift
     *  apart. The `change_order_items.description` column is `text`, so this cap
     *  is enforced by validation alone. */
    public const MAX_ITEM_DESCRIPTION_LENGTH = 2000;

    /** Vendors a single itemized cost line may list. Shared with the client
     *  through the `itemVendorLimit` Inertia prop so the two never drift apart. */
    public const MAX_ITEM_VENDORS = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'description',
        'change_order_date',
        'status',
        'sent_at',
        'decision_deadline_at',
        'total_amount',
        'sort_order',
        'global_sort_order',
    ];

    protected static function newFactory(): ChangeOrderFactory
    {
        return ChangeOrderFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_order_date' => 'date:Y-m-d',
            'status' => ChangeOrderStatus::class,
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'decision_deadline_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'change_order_date', 'status', 'total_amount'])
            ->useLogName(self::$logName);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ChangeOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChangeOrderItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ChangeOrderRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ChangeOrderRecipient::class);
    }

    /**
     * @return HasMany<ChangeOrderPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ChangeOrderPhoto::class)->latest();
    }

    /** Sum of included, non-absorbed item amounts — the customer-facing total. */
    public function recomputeTotal(): void
    {
        $this->update([
            'total_amount' => (float) $this->items()
                ->where('is_included', true)
                ->where('cost_absorbed_by_solucomp', false)
                ->sum('amount'),
        ]);
    }

    /** Item decisions drive the parent status via ChangeOrderStatusService. */
    public function recomputeStatus(): void
    {
        app(ChangeOrderStatusService::class)->recompute($this);
    }

    /**
     * Approving, partially approving or rejecting settles the item, so its
     * review is over: the conversation stays as history but nothing more can be
     * added to it.
     */
    protected function feedbackIsDecided(): bool
    {
        return in_array($this->status->value, [
            ChangeOrderStatus::APPROVED->value,
            ChangeOrderStatus::PARTIALLY_APPROVED->value,
            ChangeOrderStatus::REJECTED->value,
        ], true);
    }

    public function feedbackEntityType(): FeedbackEntityType
    {
        return FeedbackEntityType::CHANGE_ORDER;
    }
}
