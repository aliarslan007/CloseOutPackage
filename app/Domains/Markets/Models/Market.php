<?php

declare(strict_types=1);

namespace App\Domains\Markets\Models;

use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\MarketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Market extends Model
{
    /** @use HasFactory<MarketFactory> */
    use HasFactory, HasObfuscatedRouteKey, LogsActivity, SoftDeletes;

    protected static string $logName = 'project';

    protected static bool $logOnlyDirty = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'created_by',
    ];

    protected static function newFactory(): MarketFactory
    {
        return MarketFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description', 'is_active'])
            ->useLogName(self::$logName);
    }

    /**
     * Restrict a query to markets an admin has left active.
     *
     * Deactivating a market is not a delete: its projects, templates and
     * history stay readable. What it must stop is being *offered* — an inactive
     * market must never appear in a create/edit dropdown, because nothing new
     * should be raised against a region the business has closed. Every
     * selectable list goes through this scope so the rule lives in one place.
     *
     * Admin market management deliberately does NOT use it, so an admin can
     * still see and reactivate a deactivated market.
     *
     * @param  Builder<Market>  $query
     * @return Builder<Market>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function userHasAccess(User $user): bool
    {
        return $this->users()->whereKey($user->id)->exists();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Customer engineers covering this market (region).
     *
     * @return HasMany<CustomerEngineer, $this>
     */
    public function customerEngineers(): HasMany
    {
        return $this->hasMany(CustomerEngineer::class);
    }

    /**
     * @return HasMany<Template, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
