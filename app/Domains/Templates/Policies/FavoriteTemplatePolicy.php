<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Users\Models\User;

class FavoriteTemplatePolicy
{
    public function view(User $user, FavoriteTemplate $favorite): bool
    {
        return $favorite->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, FavoriteTemplate $favorite): bool
    {
        return $favorite->user_id === $user->id;
    }
}
