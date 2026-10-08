<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
            AppSettingSeeder::class,
            OhioVerizonTemplateSeeder::class,
            WashingtonDcTemplateSeeder::class,
            CategoryPresetSeeder::class,
            DefaultEmailTemplateSeeder::class,
            ProjectSeeder::class,
            SowReportSeeder::class,
        ]);
    }
}
