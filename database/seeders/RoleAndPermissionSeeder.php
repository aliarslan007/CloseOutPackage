<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Shared\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

class RoleAndPermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'markets.view',
        'markets.create',
        'markets.update',
        'markets.delete',
        'markets.grant-access',
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'templates.view',
        'templates.create',
        'templates.update',
        'templates.delete',
        'packages.create',
        'packages.view',
        'packages.save-draft',
        'packages.submit-stage',
        'packages.approve',
        'packages.reject',
        'packages.delete',
        'photos.upload',
        'photos.view',
        'photos.replace',
        'photos.view-gps',
        'pdf.generate',
        'pdf.download',
        'chat.send',
        'email-logs.view',
        'portal.view',
    ];

    public function run(): void
    {
        foreach (Role::values() as $roleName) {
            SpatieRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $admin = SpatieRole::findByName(Role::ADMIN->value);
        $admin->syncPermissions(self::PERMISSIONS);
    }
}
