<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var string $email */
        $email = config('cop.admin.email', 'admin@example.com');
        /** @var string $name */
        $name = config('cop.admin.name', 'Administrator');
        /** @var string|null $password */
        $password = config('cop.admin.password');

        $this->ensureAdminUser($email, $name, $password ?? Str::password());
        $this->ensureAdminUser('testing@mail.com', 'Testing Admin', 'password123');
    }

    private function ensureAdminUser(string $email, string $name, string $password): void
    {
        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        if (! $admin->hasRole(Role::ADMIN->value)) {
            $admin->syncRoles([Role::ADMIN->value]);
        }
    }
}
