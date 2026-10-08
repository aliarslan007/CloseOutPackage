<?php

declare(strict_types=1);

namespace App\Domains\Photos\Requests;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\Photos\DTOs\UploadPhotoDTO;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Rules\CategoryCapacityNotExceeded;
use App\Domains\Photos\Rules\CategorySelectionNotDeleted;
use App\Domains\Templates\Enums\StageType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Photo::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');
        /** @var StageType $stageType */
        $stageType = $this->route('stage');

        $isFeedbackLoop = in_array($package->status, [
            PackageStatus::FEEDBACK_RECEIVED,
            PackageStatus::REVISION_IN_PROGRESS,
        ], true);

        $stage = $package->stages()->where('stage', $stageType)->first();
        $selection = is_numeric($this->input('category_selection_id'))
            ? PackageCategorySelection::query()->find((int) $this->input('category_selection_id'))
            : null;
        $maxPosition = $selection !== null ? ($isFeedbackLoop ? 99 : $selection->picture_count) : 99;
        $position = is_numeric($this->input('position')) ? (int) $this->input('position') : null;

        return [
            // php:S5693 (reviewed): 20 MB is an intentional server-side backstop. The enforced
            // product cap is 7 MB client-side (compression.service.ts MAX_PHOTO_UPLOAD_MB).
            'photo' => [ // NOSONAR
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,heic',
                'min:'.$this->minPhotoSizeKb(),
                'max:'.$this->maxPhotoSizeKb(),
            ],
            'category_selection_id' => [
                'required',
                'integer',
                $stage !== null
                    ? Rule::exists('package_category_selections', 'id')->where('package_stage_id', $stage->id)
                    : Rule::exists('package_category_selections', 'id'),
                new CategorySelectionNotDeleted,
                new CategoryCapacityNotExceeded($selection, $position, $isFeedbackLoop),
            ],
            'position' => ['required', 'integer', 'min:1', 'max:'.$maxPosition],
            'gps_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:gps_longitude'],
            'gps_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:gps_latitude'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0'],
            'captured_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_selection_id.required' => 'Please select a photo category.',
            'category_selection_id.exists' => 'This photo category was removed from the template by an administrator. Please refresh your page.',
        ];
    }

    public function toDTO(): UploadPhotoDTO
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');
        /** @var StageType $stageType */
        $stageType = $this->route('stage');
        $categorySelectionId = $this->integer('category_selection_id');
        $position = $this->integer('position');
        $capturedAt = $this->validated('captured_at');
        /** @var int $stageId */
        $stageId = $package->stages()->where('stage', $stageType)->value('id');

        $notesVal = $this->input('notes');

        return new UploadPhotoDTO(
            closeoutPackageId: (int) $package->id,
            packageStageId: $stageId,
            categorySelectionId: $categorySelectionId,
            position: $position,
            gpsLatitude: $this->nullableFloat('gps_latitude'),
            gpsLongitude: $this->nullableFloat('gps_longitude'),
            gpsAccuracy: $this->nullableFloat('gps_accuracy'),
            capturedAt: is_string($capturedAt) ? CarbonImmutable::parse($capturedAt) : null,
            notes: is_string($notesVal) && trim($notesVal) !== '' ? trim($notesVal) : null,
        );
    }

    private function nullableFloat(string $key): ?float
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (float) $value : null;
    }

    private function minPhotoSizeKb(): int
    {
        if (app()->runningUnitTests()) {
            return 0;
        }

        return (int) config('cop.min_photo_size_kb', 10);
    }

    private function maxPhotoSizeKb(): int
    {
        return ((int) config('cop.max_photo_size_mb', 20)) * 1024;
    }
}
