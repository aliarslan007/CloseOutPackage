<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Models;

use App\Domains\ChangeOrders\Enums\CustomerDecision;
use Database\Factories\ChangeOrderItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChangeOrderItem extends Model
{
    /** @use HasFactory<ChangeOrderItemFactory> */
    use HasFactory, LogsActivity;

    protected static string $logName = 'change_order';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'change_order_id',
        'sort_order',
        'description',
        'vendor_name',
        'category',
        'amount',
        'is_included',
        'cost_absorbed_by_solucomp',
        'customer_decision',
        'customer_decision_note',
        'decided_at',
    ];

    protected static function newFactory(): ChangeOrderItemFactory
    {
        return ChangeOrderItemFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
            'is_included' => 'boolean',
            'cost_absorbed_by_solucomp' => 'boolean',
            'customer_decision' => CustomerDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * vendor_name holds a JSON list of company names; pre-existing plain-string
     * rows read back as a single-element list so legacy data keeps working.
     *
     * @return Attribute<list<string>|null, list<string>|string|null>
     */
    protected function vendorName(): Attribute
    {
        return Attribute::make(
            get: function ($value): ?array {
                if ($value === null || $value === '') {
                    return null;
                }

                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    $vendors = array_values(array_filter($decoded, fn ($name): bool => is_string($name) && $name !== ''));

                    return $vendors === [] ? null : $vendors;
                }

                return trim($value) === '' ? null : [$value];
            },
            set: function ($value): ?string {
                if (is_string($value)) {
                    $value = trim($value) === '' ? [] : [$value];
                }

                if (! is_array($value)) {
                    return null;
                }

                $vendors = array_values(array_filter(array_map(
                    fn ($name): string => is_string($name) ? trim($name) : '',
                    $value,
                ), fn (string $name): bool => $name !== ''));

                return $vendors === [] ? null : json_encode($vendors, JSON_UNESCAPED_UNICODE);
            },
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['description', 'amount', 'is_included', 'cost_absorbed_by_solucomp', 'customer_decision'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<ChangeOrder, $this>
     */
    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }
}
