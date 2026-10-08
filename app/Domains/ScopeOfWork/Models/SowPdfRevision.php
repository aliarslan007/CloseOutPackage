<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Models;

use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SowPdfRevision extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sow_report_id', 'revision_number', 'revision_date',
        'storage_disk', 'storage_path', 'generated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'revision_date' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
