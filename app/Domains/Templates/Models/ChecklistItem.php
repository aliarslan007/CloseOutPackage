<?php

declare(strict_types=1);

namespace App\Domains\Templates\Models;

use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory, HasObfuscatedRouteKey, LogsActivity, SoftDeletes;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'label',
        'has_explain_field',
        'allows_na',
        'sort_order',
    ];

    protected static function newFactory(): ChecklistItemFactory
    {
        return ChecklistItemFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_explain_field' => 'boolean',
            'allows_na' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'has_explain_field', 'allows_na', 'sort_order'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
