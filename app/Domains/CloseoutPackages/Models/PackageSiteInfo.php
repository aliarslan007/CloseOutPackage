<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\Users\Models\User;
use Database\Factories\PackageSiteInfoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageSiteInfo extends Model
{
    /** @use HasFactory<PackageSiteInfoFactory> */
    use HasFactory;

    protected $table = 'package_site_info';

    protected $primaryKey = 'closeout_package_id';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'author_user_id',
        'pm_user_id',
        'site_name',
        'ps_number',
        'project_number',
        'address',
        'complete_date',
        'scope_of_work',
        'site_access_notes',
        'vzw_engineer_name',
        'vzw_engineer_phone',
        'vzw_engineer_email',
        'author_company',
        'author_name',
        'author_email',
        'author_phone',
        'pm_company',
        'pm_name',
        'pm_phone',
    ];

    protected static function newFactory(): PackageSiteInfoFactory
    {
        return PackageSiteInfoFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'complete_date' => 'date',
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
    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pmUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_user_id');
    }

    /**
     * Resolve author company dynamically from the user record.
     * Falls back to stored value for backward compatibility.
     */
    public function getAuthorCompanyAttribute(): ?string
    {
        if ($this->relationLoaded('authorUser') && $this->authorUser?->company) {
            return $this->authorUser->company;
        }

        return $this->attributes['author_company'] ?? $this->getRawOriginal('author_company');
    }

    /**
     * Resolve author email dynamically from the user record.
     * Falls back to stored value for backward compatibility.
     */
    public function getAuthorEmailAttribute(): ?string
    {
        if ($this->relationLoaded('authorUser') && $this->authorUser?->email) {
            return $this->authorUser->email;
        }

        return $this->attributes['author_email'] ?? $this->getRawOriginal('author_email');
    }

    /**
     * Resolve PM email dynamically from the user record.
     * Falls back to stored value for backward compatibility.
     */
    public function getPmEmailAttribute(): ?string
    {
        if ($this->relationLoaded('pmUser') && $this->pmUser?->email) {
            return $this->pmUser->email;
        }

        return $this->attributes['pm_email'] ?? $this->getRawOriginal('pm_email');
    }
}
