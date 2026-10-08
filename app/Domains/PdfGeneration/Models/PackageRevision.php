<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Models;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\PackageRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PackageRevision extends Model
{
    /** @use HasFactory<PackageRevisionFactory> */
    use HasFactory, HasObfuscatedRouteKey, LogsActivity;

    protected static string $logName = 'media';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'type',
        'status',
        'photo_count',
        'chunks_total',
        'chunks_completed',
        'revision_major',
        'revision_minor',
        'revision_label',
        'revision_date',
        'total_pages',
        'storage_path',
        'generated_by',
        'generated_at',
        'generation_duration_ms',
        'renderer_version',
        'emailed_at',
        'emailed_to',
    ];

    protected static function newFactory(): PackageRevisionFactory
    {
        return PackageRevisionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PdfType::class,
            'status' => PdfRevisionStatus::class,
            'photo_count' => 'integer',
            'chunks_total' => 'integer',
            'chunks_completed' => 'integer',
            'revision_major' => 'integer',
            'revision_minor' => 'integer',
            'revision_date' => 'date',
            'total_pages' => 'integer',
            'generated_at' => 'datetime',
            'generation_duration_ms' => 'integer',
            'emailed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'revision_label', 'total_pages', 'storage_path'])
            ->useLogName(self::$logName);
    }

    /**
     * @return BelongsTo<CloseoutPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CloseoutPackage::class, 'closeout_package_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
