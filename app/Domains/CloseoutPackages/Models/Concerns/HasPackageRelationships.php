<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models\Concerns;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageSiteDetail;
use App\Domains\CloseoutPackages\Models\PackageSiteInfo;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eloquent relationships for the CloseoutPackage model.
 *
 * @mixin CloseoutPackage
 */
trait HasPackageRelationships
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * @return HasMany<PackageStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PackageStage::class);
    }

    /**
     * @return HasMany<PackageChecklistResponse, $this>
     */
    public function checklistResponses(): HasMany
    {
        return $this->hasMany(PackageChecklistResponse::class);
    }

    /**
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Hub/site details captured during final approval (Auth Center picks + manual entries).
     *
     * @return HasMany<PackageSiteDetail, $this>
     */
    public function siteDetails(): HasMany
    {
        return $this->hasMany(PackageSiteDetail::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<PackageSiteInfo, $this>
     */
    public function siteInfo(): HasOne
    {
        return $this->hasOne(PackageSiteInfo::class, 'closeout_package_id');
    }

    /**
     * @return HasMany<PackageRevision, $this>
     */
    public function packageRevisions(): HasMany
    {
        return $this->hasMany(PackageRevision::class);
    }

    /**
     * @return HasOne<PackageRevision, $this>
     */
    public function latestFinalRevision(): HasOne
    {
        return $this->hasOne(PackageRevision::class)
            ->where('type', PdfType::FINAL->value)
            ->latestOfMany();
    }

    /**
     * @return HasOne<PackageRevision, $this>
     */
    public function latestPartialRevision(): HasOne
    {
        return $this->hasOne(PackageRevision::class)
            ->where('type', PdfType::PARTIAL->value)
            ->latestOfMany();
    }
}
