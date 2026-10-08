<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WashingtonDcPackageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            /** @var User|null $supervisor */
            $supervisor = User::where('email', 'testing@mail.com')->first();
            if (! $supervisor) {
                $this->command?->error("Supervisor with email 'testing@mail.com' not found.");

                return;
            }

            /** @var Project|null $project */
            $project = Project::where('name', 'LIKE', '%1F ROOM READY%')->first();
            if (! $project) {
                $this->command?->error("Project '1F ROOM READY CLOSE-OUT-PACKAGE' not found.");

                return;
            }

            /** @var CloseoutPackage|null $package */
            $package = CloseoutPackage::where('project_id', $project->id)->first();
            if (! $package) {
                $this->command?->error("Package for project '{$project->name}' not found.");

                return;
            }

            /** @var Template $template */
            $template = $package->template;
            $template->load(['sections.categories', 'checklistItems']);

            // Find an existing sample image file to use for photos
            $sampleImagePath = $this->getSampleImagePath();

            // 1. Process Stages & Categories
            foreach ($template->sections as $section) {
                /** @var PackageStage $stage */
                $stage = PackageStage::firstOrCreate(
                    [
                        'closeout_package_id' => $package->id,
                        'stage' => $section->stage,
                    ],
                    [
                        'user_heading' => $section->heading,
                        'user_notes' => $section->admin_notes,
                    ]
                );

                foreach ($section->categories as $category) {
                    /** @var PackageCategorySelection $selection */
                    $selection = PackageCategorySelection::firstOrCreate(
                        [
                            'package_stage_id' => $stage->id,
                            'template_picture_category_id' => $category->id,
                        ],
                        [
                            'is_selected' => true,
                            'picture_count' => $category->default_picture_count,
                            'user_heading' => $category->name,
                            'user_notes' => "Category note for {$category->name}: Verified compliant with Verizon Macro specifications.",
                        ]
                    );

                    // Determine required photo count
                    $targetCount = max(1, $category->default_picture_count);

                    // Count existing photos
                    $existingCount = Photo::where('package_category_selection_id', $selection->id)->count();

                    for ($i = $existingCount; $i < $targetCount; $i++) {
                        /** @var Photo $photo */
                        $photo = Photo::create([
                            'closeout_package_id' => $package->id,
                            'package_stage_id' => $stage->id,
                            'package_category_selection_id' => $selection->id,
                            'uploaded_by' => $supervisor->id,
                            'position' => $i + 1,
                            'status' => PhotoStatus::PENDING,
                            'is_approved' => false,
                            'approved_by' => null,
                            'approved_at' => null,
                            'gps_latitude' => 38.8951,
                            'gps_longitude' => -77.0364,
                            'gps_accuracy' => 4.5,
                            'captured_at' => now()->subHours(rand(1, 48)),
                        ]);

                        if ($sampleImagePath && file_exists($sampleImagePath)) {
                            $photo->addMedia($sampleImagePath)
                                ->preservingOriginal()
                                ->toMediaCollection(Photo::COLLECTION);
                        } else {
                            // Generate a simple colored placeholder image if no sample image exists
                            $tempFile = $this->createTemporaryImage('Photo '.($i + 1).' - '.$category->name);
                            $photo->addMedia($tempFile)
                                ->toMediaCollection(Photo::COLLECTION);
                            if (file_exists($tempFile)) {
                                @unlink($tempFile);
                            }
                        }
                    }
                }
            }

            // 2. Process Checklist Responses
            $checklistExplanations = [
                'Were fiber optic lines sweep tested and verified within attenuation limits?' => 'Fiber optic insertion loss verified at 0.18 dB within acceptable limits.',
                'Were there any site access or gate lock issues during construction?' => 'Minor landlord access gate lock delay resolved on day 1.',
                'Did Verizon Operations provide any site-specific installation deviations?' => 'Verizon engineering approved alternate grounding bar offset positioning.',
                'Were any safety or environmental hazards encountered during installation?' => 'Overhead power line safety clearance verified prior to boom crane operation.',
                'Is the main ground ring resistance reading below 5 ohms?' => 'Ground ring resistance measured at 3.2 ohms using 3-point fall-of-potential test.',
                'Did utility company complete final power meter connection?' => 'PEPCO power meter connected and energized on 08/10/2026.',
                'Were any existing site structures damaged during construction?' => 'No structural damage encountered during equipment placement.',
                'Were final PIM (Passive Intermodulation) test results accepted?' => 'PIM testing passed with -158 dBc across all sectors.',
                'Is any follow-up punch-list work required for full closeout acceptance?' => 'All primary and secondary punch list items completed successfully.',
            ];

            foreach ($template->checklistItems as $item) {
                $hasExplain = $item->has_explain_field;
                $answer = true;
                $explainText = $checklistExplanations[$item->label] ?? ($hasExplain ? 'Standard verification completed.' : null);

                PackageChecklistResponse::updateOrCreate(
                    [
                        'closeout_package_id' => $package->id,
                        'checklist_item_id' => $item->id,
                    ],
                    [
                        'answer' => $answer,
                        'explain' => $explainText,
                    ]
                );
            }

            $this->command?->info("Package #{$package->id} for project '{$project->name}' successfully seeded with photos and checklist responses!");
        });
    }

    private function getSampleImagePath(): ?string
    {
        $existingMedia = Media::latest()->first();
        if ($existingMedia && file_exists($existingMedia->getPath())) {
            return $existingMedia->getPath();
        }

        return null;
    }

    private function createTemporaryImage(string $text): string
    {
        $tempPath = sys_get_temp_dir().'/sample_'.md5($text).'.jpg';
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(800, 600);
            $bgColor = imagecolorallocate($image, 240, 240, 240);
            $textColor = imagecolorallocate($image, 40, 40, 40);
            imagefill($image, 0, 0, $bgColor);
            imagestring($image, 5, 50, 280, $text, $textColor);
            imagejpeg($image, $tempPath, 85);
            imagedestroy($image);
        } else {
            file_put_contents($tempPath, 'placeholder');
        }

        return $tempPath;
    }
}
