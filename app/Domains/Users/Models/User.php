<?php

declare(strict_types=1);

namespace App\Domains\Users\Models;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Shared\Enums\Role;
use App\Shared\Traits\HasObfuscatedRouteKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasObfuscatedRouteKey, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    public const PROFILE_PHOTO_COLLECTION = 'profile_photo';

    /**
     * Compose the display name from separate first/last name inputs.
     *
     * The trailing last-name dedup guard keeps the SSO name round-trip
     * idempotent: AuthCenter can return a first name that already ends with
     * the last name (it persisted our `full_name` push into its first-name
     * field), so a naive concat would append another copy on every login
     * ("John Lime" -> "John Lime Lime"). Dropping the trailing copy before
     * composing makes login, inbound sync and the cleanup command self-healing.
     *
     * ponytail: a legitimate name whose first name literally ends with the
     * surname ("Mary Jo" / "Jo") would be wrongly shortened; upgrade path is
     * storing structured first/last name columns instead of one `name` string.
     */
    public static function fullName(string $firstName, string $lastName): string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        // Collapse a last-name field that was itself polluted by repeated
        // copies ("Lime Lime" -> "Lime"). Compound surnames like
        // "van der Berg" are untouched because their tokens differ.
        $lastTokens = explode(' ', $lastName);
        if (count($lastTokens) > 1 && count(array_unique($lastTokens)) === 1) {
            $lastName = $lastTokens[0];
        }

        while ($lastName !== '' && str_ends_with($firstName, ' '.$lastName)) {
            $firstName = trim(substr($firstName, 0, -strlen(' '.$lastName)));
        }

        return trim($firstName.' '.$lastName);
    }

    /**
     * Free an email still held by a soft-deleted account (users.email has a
     * global unique index, so a trashed row would otherwise block re-creating
     * the same address). The suffix keeps the old row traceable.
     */
    public static function releaseTrashedEmail(string $email): void
    {
        $trashed = static::onlyTrashed()->where('email', $email)->first();

        if ($trashed !== null) {
            $suffix = '-del-'.$trashed->id;
            $trashed->email = substr($email, 0, 255 - strlen($suffix)).$suffix;
            $trashed->save();
        }
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uuid',
        'company',
        'phone',
        'is_active',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PROFILE_PHOTO_COLLECTION)->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(240)
            ->height(240)
            ->sharpen(10);
    }

    /**
     * Virtual attributes appended to serialized User models.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
        'role_name',
    ];

    /**
     * Cache-busted URL of the profile photo, or null when none is uploaded.
     */
    public function profilePhotoUrl(): ?string
    {
        $media = $this->getFirstMedia(self::PROFILE_PHOTO_COLLECTION);

        if ($media === null) {
            return null;
        }

        return route('users.profile-photo.show', ['user' => $this->getRouteKey()]).'?v='.$media->id;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profilePhotoUrl();
    }

    public function getRoleNameAttribute(): ?string
    {
        if (! $this->relationLoaded('roles')) {
            return null;
        }

        /** @var \Spatie\Permission\Models\Role|null $role */
        $role = $this->roles->first();

        return $role?->name;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Where the user lands right after SSO login, per role.
     */
    public function landingRoute(): string
    {
        return $this->hasRole(Role::CUSTOMER->value)
            ? 'portal.index'
            : 'dashboard';
    }

    /**
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class)
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    /**
     * @return HasMany<CustomerProjectAccess, $this>
     */
    public function customerAccesses(): HasMany
    {
        return $this->hasMany(CustomerProjectAccess::class);
    }
}
