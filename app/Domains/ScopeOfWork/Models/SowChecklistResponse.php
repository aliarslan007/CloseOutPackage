<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SowChecklistResponse extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['sow_report_id', 'sow_checklist_item_id', 'response', 'explanation'];

    /**
     * @return BelongsTo<SowChecklistItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SowChecklistItem::class, 'sow_checklist_item_id');
    }
}
