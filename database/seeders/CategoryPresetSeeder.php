<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Templates\Models\CategoryPreset;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds standard reusable subcategory headings with photo guidance,
 * different photo bucket sizes, mandatory/optional status, and active state.
 */
class CategoryPresetSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{name: string, description: string, count: int, required: bool}>
     */
    private const PRESETS = [
        [
            'name' => 'Overall Site & Compound Access',
            'description' => 'Wide-angle overview of the compound entrance, access road, security gate, and perimeter fencing.',
            'count' => 2,
            'required' => true,
        ],
        [
            'name' => 'Equipment Shelter & Foundation Pad',
            'description' => 'Exterior view of equipment shelter and concrete pad showing foundation condition, anchor bolts, and grounding halo.',
            'count' => 3,
            'required' => true,
        ],
        [
            'name' => 'Fiber Demarcation & JTP Termination',
            'description' => 'Close-up photo of the fiber optic demarcation box, Jacket Termination Point (JTP), and carrier circuit tag.',
            'count' => 1,
            'required' => true,
        ],
        [
            'name' => 'Antenna Sector A - Mount & Azimuth',
            'description' => 'Photos showing Alpha sector antenna mounting brackets, mechanical down-tilt, pipe clamps, and compass azimuth orientation.',
            'count' => 5,
            'required' => true,
        ],
        [
            'name' => 'Antenna Sector B - Mount & Azimuth',
            'description' => 'Photos showing Beta sector antenna mounting brackets, mechanical down-tilt, pipe clamps, and compass azimuth orientation.',
            'count' => 5,
            'required' => false,
        ],
        [
            'name' => 'Antenna Sector C - Mount & Azimuth',
            'description' => 'Photos showing Gamma sector antenna mounting brackets, mechanical down-tilt, pipe clamps, and compass azimuth orientation.',
            'count' => 5,
            'required' => false,
        ],
        [
            'name' => 'Main DC Power Plant & Battery Strings',
            'description' => 'Rectifier panel, DC distribution breakers, battery string arrangement, terminal torque seals, and connections.',
            'count' => 7,
            'required' => true,
        ],
        [
            'name' => 'Grounding System & Master Bus Bar Bonds',
            'description' => 'Tower base ground bar, shelter exterior Master Ground Bar (MGB), two-hole inspection lugs, and exothermic cadwelds.',
            'count' => 10,
            'required' => true,
        ],
        [
            'name' => 'Cable Bridge, Tray & Coaxial Feeder Run',
            'description' => 'Coaxial and hybrid trunk cable routing along the ice bridge, weatherproofing boots, snap-in hangers, and bridge transition.',
            'count' => 20,
            'required' => false,
        ],
        [
            'name' => 'Signage & Safety Compliance Placards',
            'description' => 'FCC tower registration sign, RF hazard warning signs, emergency contact placards, and high-voltage warning labels.',
            'count' => 2,
            'required' => false,
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $creator = User::where('email', 'testing@mail.com')->first()
                ?? User::whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN->value))->first()
                ?? User::first();

            $creatorId = $creator?->id;

            foreach (self::PRESETS as $preset) {
                CategoryPreset::firstOrCreate(
                    ['name' => $preset['name']],
                    [
                        'description' => $preset['description'],
                        'default_picture_count' => $preset['count'],
                        'is_required' => $preset['required'],
                        'is_active' => true,
                        'created_by' => $creatorId,
                    ],
                );
            }
        });
    }
}
