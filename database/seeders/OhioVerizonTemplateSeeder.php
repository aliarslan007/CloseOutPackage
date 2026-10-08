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
 * Seeds the standard "Ohio Verizon Macro Cell" close-out template (R=A, V=5).
 */
class OhioVerizonTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $market = Market::where('name', 'Ohio Market')->first()
                ?? Market::where('name', 'Ohio')->first()
                ?? Market::create([
                    'name' => 'Ohio Market',
                    'slug' => Market::generateUniqueSlug('Ohio Market'),
                    'is_active' => true,
                ]);

            $template = Template::firstOrCreate(
                ['market_id' => $market->id, 'name' => 'Ohio Verizon Macro Cell', 'deleted_at' => null],
                [
                    'description' => 'Standard close-out package template for Verizon Macro Cell deployments in Ohio.',
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
                'admin_notes' => 'Capture clear photographs before any construction work begins. Ensure all existing site conditions are visible. Photos should be taken during daylight whenever possible.',
                'categories' => [
                    ['name' => 'Site Overview', 'description' => 'Capture a wide-angle photo showing the entire site before work begins.', 'count' => 1, 'required' => true],
                    ['name' => 'Grounding', 'description' => 'Capture grounding connection and surrounding installation area.', 'count' => 2, 'required' => true],
                    ['name' => 'Equipment Rack', 'description' => 'Capture the equipment rack before installation begins.', 'count' => 1, 'required' => true],
                    ['name' => 'Cable Entry', 'description' => 'Capture all cable entry points before installation.', 'count' => 1, 'required' => true],
                    ['name' => 'GPS Antenna Location', 'description' => 'Capture the planned GPS antenna installation location.', 'count' => 1, 'required' => false],
                ],
            ],
            [
                'stage' => StageType::CONSTRUCTION->value,
                'heading' => 'Construction / Installation',
                'admin_notes' => 'Capture photographs throughout installation. Ensure every major installation activity is documented.',
                'categories' => [
                    ['name' => 'Equipment Installed', 'description' => 'Capture newly installed equipment.', 'count' => 2, 'required' => true],
                    ['name' => 'Power Connections', 'description' => 'Capture all power connections after installation.', 'count' => 2, 'required' => true],
                    ['name' => 'Fiber Connections', 'description' => 'Capture fiber routing and termination.', 'count' => 2, 'required' => true],
                    ['name' => 'Grounding Completed', 'description' => 'Capture completed grounding installation.', 'count' => 1, 'required' => true],
                    ['name' => 'Rack Interior', 'description' => 'Capture internal rack organization after installation.', 'count' => 2, 'required' => false],
                ],
            ],
            [
                'stage' => StageType::POST_COMPLETION->value,
                'heading' => 'Post Completion',
                'admin_notes' => 'Capture final site condition after all work has been completed and the site has been cleaned.',
                'categories' => [
                    ['name' => 'Final Site Overview', 'description' => 'Capture a final wide-angle photograph of the completed site.', 'count' => 1, 'required' => true],
                    ['name' => 'Completed Equipment', 'description' => 'Capture completed equipment installation.', 'count' => 2, 'required' => true],
                    ['name' => 'Site Cleanup', 'description' => 'Capture the cleaned work area before leaving the site.', 'count' => 1, 'required' => true],
                    ['name' => 'Warning Labels', 'description' => 'Capture all installed warning and identification labels.', 'count' => 1, 'required' => true],
                    ['name' => 'Final GPS View', 'description' => 'Capture the installed GPS antenna after completion.', 'count' => 1, 'required' => false],
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
            ['label' => 'Was all work completed according to project specifications?', 'explain' => false],
            ['label' => 'Were all required photographs captured?', 'explain' => false],
            ['label' => 'Was the site cleaned before leaving?', 'explain' => false],
            ['label' => 'Were there any site access issues?', 'explain' => true],
            ['label' => 'Did Verizon provide additional guidance during installation?', 'explain' => true],
            ['label' => 'Were any safety concerns encountered?', 'explain' => true],
            ['label' => 'Is additional follow-up work required?', 'explain' => true],
        ];
    }
}
