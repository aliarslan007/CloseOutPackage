<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Services\IdObfuscatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasObfuscatedRouteKey
{
    /**
     * Automatically append hash_id to serialized JSON model arrays.
     */
    public function initializeHasObfuscatedRouteKey(): void
    {
        $this->append('hash_id');
    }

    /**
     * Accessor for hash_id attribute on serialized JSON model.
     */
    public function getHashIdAttribute(): string
    {
        return $this->getRouteKey();
    }

    /**
     * Get the value of the model's route key for URL generation.
     */
    public function getRouteKey(): string
    {
        /** @var int $id */
        $id = $this->getKey();

        return IdObfuscatorService::encode($id);
    }

    /**
     * Retrieve the model for a bound value in Laravel Route Model Binding.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return static|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $decodedId = IdObfuscatorService::decode($value);

        if ($decodedId === null) {
            return null;
        }

        return parent::resolveRouteBinding($decodedId, $this->getKeyName());
    }

    /**
     * Retrieve the model query for a bound value in Laravel Route Model Binding.
     *
     * @param  Builder|Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder|Relation
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $decodedId = IdObfuscatorService::decode($value);

        if ($decodedId === null) {
            return $query->whereKey(-1);
        }

        return parent::resolveRouteBindingQuery($query, $decodedId, $this->getKeyName());
    }

    /**
     * Retrieve the model for a bound value for soft-deleted / trashed models.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return static|null
     */
    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveSoftDeletableRouteBinding($value, $field);
        }

        $decodedId = IdObfuscatorService::decode($value);

        if ($decodedId === null) {
            return null;
        }

        return parent::resolveSoftDeletableRouteBinding($decodedId, $this->getKeyName());
    }

    /**
     * Retrieve the model query for a soft-deleted bound value.
     *
     * @param  Builder|Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder|Relation
     */
    public function resolveSoftDeletableRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveSoftDeletableRouteBindingQuery($query, $value, $field);
        }

        $decodedId = IdObfuscatorService::decode($value);

        if ($decodedId === null) {
            return $query->whereKey(-1);
        }

        return parent::resolveSoftDeletableRouteBindingQuery($query, $decodedId, $this->getKeyName());
    }
}
