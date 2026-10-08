<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\PackageStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageStage extends Model
{
    /** @use HasFactory<PackageStageFactory> */
    use HasFactory, HasObfuscatedRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'stage',
        'status',
        'submitted_at',
        'submitted_by',
        'draft_saved_at',
    ];

    protected static function newFactory(): PackageStageFactory
    {
        return PackageStageFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => StageType::class,
            'status' => StageStatus::class,
            'submitted_at' => 'datetime',
            'draft_saved_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<PackageCategorySelection, $this>
     */
    public function categorySelections(): HasMany
    {
        return $this->hasMany(PackageCategorySelection::class);
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'package_stage_id');
    }
}
