<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Templates\Queries\TemplateQuery;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the standard "Washington D.C. Verizon Macro Cell" close-out template (3 stages, 30 categories, 20 checklist items).
 */
class WashingtonDcTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $market = Market::where('name', 'Washington, D.C.')->first()
                ?? Market::where('name', 'Washington D.C.')->first()
                ?? Market::where('name', 'Washington DC')->first()
                ?? Market::create([
                    'name' => 'Washington, D.C.',
                    'slug' => Market::generateUniqueSlug('Washington, D.C.'),
                    'is_active' => true,
                ]);

            $template = Template::firstOrCreate(
                ['market_id' => $market->id, 'name' => 'Washington D.C. Verizon Macro Cell', 'deleted_at' => null],
                [
                    'description' => 'Comprehensive close-out package template for Verizon Macro Cell deployments in the Washington, D.C. market.',
                    'is_active' => true,
                ],
            );

            foreach ($this->sections() as $index => $section) {
                $sectionModel = TemplateSection::firstOrCreate(
                    ['template_id' => $template->id, 'stage' => $section['stage']],
                    [
                        'heading' => $section['heading'],
                        'admin_notes' => $section['admin_notes'],
                        'sort_order' => $index + 1,
                    ],
                );

                foreach ($section['categories'] as $categoryIndex => $category) {
                    TemplatePictureCategory::firstOrCreate(
                        ['template_section_id' => $sectionModel->id, 'name' => $category['name']],
                        [
                            'description' => $category['description'],
                            'default_picture_count' => $category['count'],
                            'is_required' => $category['required'],
                            'sort_order' => $categoryIndex + 1,
                        ],
                    );
                }
            }

            foreach ($this->checklistItems() as $index => $item) {
                ChecklistItem::firstOrCreate(
                    ['template_id' => $template->id, 'label' => $item['label']],
                    [
                        'has_explain_field' => $item['explain'],
                        'sort_order' => $index + 1,
                    ],
                );
            }
        });

        TemplateQuery::flushCache();
        MarketQuery::flushCache();
    }

    /**
     * @return list<array{stage: string, heading: string, admin_notes: string, categories: list<array{name: string, description: string, count: int, required: bool}>}>
     */
    private function sections(): array
    {
        return [
            [
                'stage' => StageType::PRE_CONSTRUCTION->value,
                'heading' => 'Pre Construction',
                'admin_notes' => 'Capture clear photographs documenting all initial site conditions prior to starting construction work in the Washington D.C. area. All photos must be taken in daylight with clear visibility.',
                'categories' => [
                    ['name' => 'Site Overview & Access Path', 'description' => 'Capture a wide-angle overview of the site compound and access road before work starts.', 'count' => 1, 'required' => true],
                    ['name' => 'Compound Perimeter & Gate', 'description' => 'Capture compound fencing, gate security, and surrounding perimeter.', 'count' => 2, 'required' => true],
                    ['name' => 'Existing Tower Base & Mounting Area', 'description' => 'Capture existing tower structure base and planned antenna mounting areas.', 'count' => 2, 'required' => true],
                    ['name' => 'Pre-Existing Equipment Cabinet', 'description' => 'Capture existing equipment shelter or cabinet exterior prior to modification.', 'count' => 2, 'required' => true],
                    ['name' => 'Existing Grounding Busbar', 'description' => 'Capture existing ground bar connections and conductor status.', 'count' => 2, 'required' => true],
                    ['name' => 'Telco Demarcation & Utility Meter', 'description' => 'Capture utility power meter and telco demarcation enclosure before work.', 'count' => 2, 'required' => true],
                    ['name' => 'Cable Bridge & Entry Port', 'description' => 'Capture ice bridge, cable ladder, and hatch entry ports before new cable runs.', 'count' => 2, 'required' => true],
                    ['name' => 'Planned Antenna Mount Positions', 'description' => 'Capture close-up view of planned sector mount locations on tower/rooftop.', 'count' => 3, 'required' => true],
                    ['name' => 'Pre-Construction Power Disconnect Box', 'description' => 'Capture main power disconnect box before electrical integration.', 'count' => 1, 'required' => true],
                    ['name' => 'Initial GPS Antenna Site Location', 'description' => 'Capture designated roof or wall mounting spot for GPS antenna.', 'count' => 1, 'required' => false],
                ],
            ],
            [
                'stage' => StageType::CONSTRUCTION->value,
                'heading' => 'Construction / Installation',
                'admin_notes' => 'Document all active installation steps, equipment mounting, fiber routing, and electrical connections throughout construction.',
                'categories' => [
                    ['name' => 'Newly Installed Base Station Cabinet', 'description' => 'Capture newly placed equipment cabinet or outdoor base station unit.', 'count' => 2, 'required' => true],
                    ['name' => 'RRH (Remote Radio Head) Mounting', 'description' => 'Capture mounted Remote Radio Heads (RRHs) with bracket hardware.', 'count' => 3, 'required' => true],
                    ['name' => 'Sector Antenna Installation & Tilts', 'description' => 'Capture installed panel antennas showing mechanical and electrical tilt angles.', 'count' => 4, 'required' => true],
                    ['name' => 'Fiber Jumper & Trunk Cabling', 'description' => 'Capture hybrid fiber trunk cable routing, breakout kits, and jumper connections.', 'count' => 3, 'required' => true],
                    ['name' => 'Coaxial Jumper Connections & Weatherproofing', 'description' => 'Capture RF jumper connections showing weatherproofing boots/tape.', 'count' => 4, 'required' => true],
                    ['name' => 'New Grounding Bar & Cadweld Connections', 'description' => 'Capture newly installed ground bar, cadweld connections, and 2-hole lugs.', 'count' => 3, 'required' => true],
                    ['name' => 'Power Supply & Rectifier Wiring', 'description' => 'Capture DC power plant, rectifier modules, and circuit breaker connections.', 'count' => 2, 'required' => true],
                    ['name' => 'GPS Antenna & Surge Protector Install', 'description' => 'Capture installed GPS antenna unit and inline surge protection device.', 'count' => 2, 'required' => true],
                    ['name' => 'Cable Tray Routing & Clamping', 'description' => 'Capture cable ladder attachment clips, hoist grips, and cable bundle straps.', 'count' => 2, 'required' => true],
                    ['name' => 'Internal Rack Cable Management & Labels', 'description' => 'Capture internal rack wiring organization and printed circuit identifiers.', 'count' => 2, 'required' => false],
                ],
            ],
            [
                'stage' => StageType::POST_COMPLETION->value,
                'heading' => 'Post Completion',
                'admin_notes' => 'Capture final post-completion photographs showing fully completed installation, site signage, and cleaned site compound.',
                'categories' => [
                    ['name' => 'Final Site Overview & Compound Status', 'description' => 'Capture final wide-angle photograph of the completed cell site compound.', 'count' => 1, 'required' => true],
                    ['name' => 'Completed Tower Mount & Antenna Sector View', 'description' => 'Capture full view of completed antenna sectors on tower mount.', 'count' => 3, 'required' => true],
                    ['name' => 'Final Equipment Cabinet Exterior & Lock Status', 'description' => 'Capture closed equipment cabinet exterior showing secure padlocks.', 'count' => 2, 'required' => true],
                    ['name' => 'Final Internal Equipment Rack View', 'description' => 'Capture clean interior equipment rack view showing operating LEDs.', 'count' => 2, 'required' => true],
                    ['name' => 'Completed Grounding System Audit Photo', 'description' => 'Capture master ground bar with all green ground leads connected.', 'count' => 2, 'required' => true],
                    ['name' => 'Final Fiber Routing & Junction Box', 'description' => 'Capture sealed fiber optic junction box and clean slack loops.', 'count' => 2, 'required' => true],
                    ['name' => 'Verizon Site Signage & Identification', 'description' => 'Capture official Verizon site identification placard on gate/shelter.', 'count' => 1, 'required' => true],
                    ['name' => 'Hazard Warning Labels & Caution Signs', 'description' => 'Capture posted RF radiation warning signs and high voltage placards.', 'count' => 1, 'required' => true],
                    ['name' => 'Site Cleanup & Trash Disposal Verification', 'description' => 'Capture clean compound grounds confirming all scrap materials were removed.', 'count' => 1, 'required' => true],
                    ['name' => 'Final GPS Antenna Installed View', 'description' => 'Capture final installed GPS antenna with un-obscured view to sky.', 'count' => 1, 'required' => false],
                ],
            ],
        ];
    }

    /**
     * @return list<array{label: string, explain: bool}>
     */
    private function checklistItems(): array
    {
        return [
            ['label' => 'Was all work completed according to Verizon engineering specifications?', 'explain' => false],
            ['label' => 'Were all required pre-construction photos captured prior to installation?', 'explain' => false],
            ['label' => 'Are all antenna mount bolts torqued to specified manufacturer values?', 'explain' => false],
            ['label' => 'Is all coaxial weatherproofing properly sealed and free of tears?', 'explain' => false],
            ['label' => 'Are all Remote Radio Heads (RRHs) securely grounded?', 'explain' => false],
            ['label' => 'Were fiber optic lines sweep tested and verified within attenuation limits?', 'explain' => true],
            ['label' => 'Is the equipment cabinet locked and secure upon site departure?', 'explain' => false],
            ['label' => 'Was the site compound cleaned and free of construction debris?', 'explain' => false],
            ['label' => 'Were there any site access or gate lock issues during construction?', 'explain' => true],
            ['label' => 'Did Verizon Operations provide any site-specific installation deviations?', 'explain' => true],
            ['label' => 'Were any safety or environmental hazards encountered during installation?', 'explain' => true],
            ['label' => 'Is the main ground ring resistance reading below 5 ohms?', 'explain' => true],
            ['label' => 'Are all RF hazard warning signs clearly posted on compound entrance?', 'explain' => false],
            ['label' => 'Is the GPS antenna installed with clear line-of-sight to sky?', 'explain' => false],
            ['label' => 'Were all unused ports properly capped and weatherproofed?', 'explain' => false],
            ['label' => 'Did utility company complete final power meter connection?', 'explain' => true],
            ['label' => 'Were any existing site structures damaged during construction?', 'explain' => true],
            ['label' => 'Is all cable labeling clear, legibly printed, and compliant with standards?', 'explain' => false],
            ['label' => 'Were final PIM (Passive Intermodulation) test results accepted?', 'explain' => true],
            ['label' => 'Is any follow-up punch-list work required for full closeout acceptance?', 'explain' => true],
        ];
    }
}
