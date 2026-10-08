<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models\Concerns;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eloquent relationships for the Project model.
 *
 * @mixin Project
 */
trait HasProjectRelationships
{
    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The customer engineer / PM of record (name lands on the PDF cover page).
     *
     * @return BelongsTo<CustomerEngineer, $this>
     */
    public function customerEngineer(): BelongsTo
    {
        return $this->belongsTo(CustomerEngineer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The project's single primary field technician — the one a manager marks
     * with the star, and the one teammates are pointed at when they need to
     * reach whoever is running the site.
     *
     * @return BelongsTo<User, $this>
     */
    public function primarySupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_supervisor_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function fieldSupervisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_field_supervisors')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * @return HasMany<IssueHistory, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(IssueHistory::class);
    }

    /**
     * @return HasMany<ChangeOrder, $this>
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /**
     * @return HasMany<CloseoutPackage, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(CloseoutPackage::class);
    }

    /**
     * @return HasMany<CloseoutPackage, $this>
     */
    public function closeoutPackages(): HasMany
    {
        return $this->hasMany(CloseoutPackage::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return HasMany<CustomerProjectAccess, $this>
     */
    public function customerAccesses(): HasMany
    {
        return $this->hasMany(CustomerProjectAccess::class);
    }

    /**
     * @return HasOne<CloseoutPackage, $this>
     */
    public function latestPackage(): HasOne
    {
        return $this->hasOne(CloseoutPackage::class)->latestOfMany();
    }

    /**
     * @return HasMany<CrewAssignment, $this>
     */
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    /**
     * Library uploads tagged to this project.
     *
     * @return HasMany<MediaAsset, $this>
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /**
     * @return HasMany<SowReport, $this>
     */
    public function sowReports(): HasMany
    {
        return $this->hasMany(SowReport::class);
    }

    /**
     * @return HasOne<SowReport, $this>
     */
    public function latestSowReport(): HasOne
    {
        return $this->hasOne(SowReport::class)->latestOfMany();
    }
}
