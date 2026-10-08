<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models;

use App\Domains\CloseoutPackages\Enums\PackageSiteDetailSource;
use App\Domains\CloseoutPackages\Enums\PackageSiteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageSiteDetail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'closeout_package_id',
        'source',
        'site_type',
        'auth_site_id',
        'name',
        'region',
        'state',
        'sort_order',
    ];

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'source' => PackageSiteDetailSource::class,
            'site_type' => PackageSiteType::class,
            'auth_site_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CloseoutPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CloseoutPackage::class, 'closeout_package_id');
    }
}
