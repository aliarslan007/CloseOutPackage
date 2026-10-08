<?php

declare(strict_types=1);

namespace App\Domains\Users\Contracts;

use App\Domains\Users\Models\User;

interface ShouldSyncUserToSso
{
    public function user(): User;

    public function ssoAction(): string;
}
