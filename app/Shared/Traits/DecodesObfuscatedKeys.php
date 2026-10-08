<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use App\Shared\Services\IdObfuscatorService;

trait DecodesObfuscatedKeys
{
    /**
     * Decode any obfuscated hash string key in the request payload
     * into its integer primary key before validation and authorization run.
     */
    protected function prepareForValidation(): void
    {
        /** @var list<string> $keys */
        $keys = property_exists($this, 'obfuscatedKeys') && is_array($this->obfuscatedKeys)
            ? $this->obfuscatedKeys
            : ['closeout_package_id', 'project_id', 'market_id', 'template_id', 'favorite_id', 'user_id', 'photo_id'];

        $merged = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                /** @var mixed $raw */
                $raw = $this->input($key);

                if (is_string($raw)) {
                    $decoded = IdObfuscatorService::decode($raw);
                    if ($decoded !== null) {
                        $merged[$key] = $decoded;
                    }
                }
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }
}
