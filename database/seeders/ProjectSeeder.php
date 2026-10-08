<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Settings\Services\SettingsService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds sample projects for Ohio and Washington D.C. markets,
 * utilizing configured default Verizon engineer information.
 */
class ProjectSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $ohioMarket = Market::query()
                ->where('name', 'Ohio Market')
                ->orWhere('name', 'Ohio')
                ->orWhere('slug', 'ohio-market')
                ->first()
                ?? Market::create([
                    'name' => 'Ohio Market',
                    'slug' => Market::generateUniqueSlug('Ohio Market'),
                    'is_active' => true,
                ]);

            $dcMarket = Market::query()
                ->where('name', 'Washington, D.C.')
                ->orWhere('name', 'Washington D.C.')
                ->orWhere('name', 'Washington DC')
                ->orWhere('slug', 'washington-dc')
                ->first()
                ?? Market::create([
                    'name' => 'Washington, D.C.',
                    'slug' => Market::generateUniqueSlug('Washington, D.C.'),
                    'is_active' => true,
                ]);

            $admin = User::where('email', 'testing@mail.com')->first()
                ?? User::whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN->value))->first()
                ?? User::first();

            if ($admin !== null) {
                $admin->markets()->syncWithoutDetaching([$ohioMarket->id, $dcMarket->id]);
            }

            // Pull configured VZW Engineer defaults from AppSettings, with sensible fallbacks
            $settings = app(SettingsService::class)->getProjectDefaults();

            $vzwEngineerName = $settings['vzw_engineer_name']
                ?: (AppSetting::get('vzw_engineer_name') ?: 'David Vance');
            $vzwEngineerEmail = $settings['vzw_engineer_email']
                ?: (AppSetting::get('vzw_engineer_email') ?: 'david.vance@verizon.com');
            $vzwEngineerPhone = $settings['vzw_engineer_phone']
                ?: (AppSetting::get('vzw_engineer_phone') ?: '+1-614-555-0199');

            $projects = [
                [
                    'market_id' => $ohioMarket->id,
                    'name' => 'Columbus North Macro 5G',
                    'ps_number' => 'PS-OH-44021',
                    'project_number' => 'PRJ-2026-OH-08',
                    'address' => '4500 Polaris Pkwy',
                    'city' => 'Columbus',
                    'state' => 'OH',
                    'zip' => '43240',
                    'completed_date' => now()->addDays(60)->toDateString(),
                    'notes' => 'Macro cell site upgrade for Columbus North sector. 5G antenna swap and fiber demarc extension.',
                ],
                [
                    'market_id' => $dcMarket->id,
                    'name' => 'Constitution Ave Small Cell 04',
                    'ps_number' => 'PS-DC-10982',
                    'project_number' => 'PRJ-2026-DC-19',
                    'address' => '1400 Constitution Ave NW',
                    'city' => 'Washington',
                    'state' => 'DC',
                    'zip' => '20001',
                    'completed_date' => now()->addDays(45)->toDateString(),
                    'notes' => 'Small cell shroud mount installation and demarc cabinet tie-in along Constitution Ave corridor.',
                ],
            ];

            foreach ($projects as $data) {
                $project = Project::firstOrCreate(
                    ['name' => $data['name']],
                    [
                        'market_id' => $data['market_id'],
                        'ps_number' => $data['ps_number'],
                        'project_number' => $data['project_number'],
                        'address' => $data['address'],
                        'city' => $data['city'],
                        'state' => $data['state'],
                        'zip' => $data['zip'],
                        'completed_date' => $data['completed_date'],
                        'vzw_engineer_name' => $vzwEngineerName,
                        'vzw_engineer_email' => $vzwEngineerEmail,
                        'vzw_engineer_phone' => $vzwEngineerPhone,
                        'status' => ProjectStatus::PENDING->value,
                        'progress_percentage' => 0,
                        'manager_id' => $admin?->id,
                        'created_by' => $admin?->id,
                        'notes' => $data['notes'],
                    ],
                );

                // Rows seeded before the demo admin account existed keep a stale
                // created_by (often a since-deleted admin), so the project details
                // page credits the wrong author. Re-point authorship on re-seed
                // without disturbing the project's live status or progress.
                if ($admin !== null && (int) $project->created_by !== (int) $admin->id) {
                    $project->update(['created_by' => $admin->id]);
                }
            }
        });
    }
}
