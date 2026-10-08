<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SowChecklistItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sow_report_id',
        'market_id',
        'label',
        'has_explain_field',
        'is_required',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sow_report_id' => 'integer',
            'has_explain_field' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SowReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(SowReport::class, 'sow_report_id');
    }

    /**
     * @return HasOne<SowChecklistResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(SowChecklistResponse::class, 'sow_checklist_item_id');
    }
}
