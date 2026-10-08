<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MediaAsset>
     */
    protected $model = MediaAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'market_id' => Market::factory(),
            'project_id' => Project::factory(),
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 2048,
            'total_chunks' => 1,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'gps_accuracy' => null,
            'captured_at' => null,
            'status' => MediaAssetStatus::PROCESSING,
            'validation_error' => null,
        ];
    }
}
