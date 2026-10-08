<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

final class AssignAdminRoleCommand extends Command
{
    protected $signature = 'user:make-admin {email}';

    protected $description = 'Assign admin role to a user by email';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User with email {$email} not found.");

            return self::FAILURE;
        }

        $user->syncRoles([Role::ADMIN->value]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Assigned admin role to {$user->email} (id: {$user->id}).");

        return self::SUCCESS;
    }
}
