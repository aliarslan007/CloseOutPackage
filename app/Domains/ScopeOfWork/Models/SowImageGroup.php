<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SowImageGroup extends Model
{
    /**
     * Heading the PDF uses for photos that belong to no group. Reserved so a
     * real group can never collide with it and print the same heading twice.
     */
    public const UNGROUPED_HEADING = 'Other Photos';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['sow_report_id', 'name', 'sort_order'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sow_report_id' => 'integer',
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
     * @return HasMany<SowAnnotatedImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(SowAnnotatedImage::class, 'sow_image_group_id')->orderBy('sort_order');
    }
}
