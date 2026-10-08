<?php

declare(strict_types=1);

namespace App\Domains\Photos\Requests;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\DTOs\ReplacePhotoDTO;
use App\Domains\Photos\Models\Photo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReplacePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Photo $photo */
        $photo = $this->route('photo');

        return Gate::allows('replace', $photo);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');
        /** @var Photo $routePhoto */
        $routePhoto = $this->route('photo');

        return [
            'photo' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,heic',
                'min:'.$this->minPhotoSizeKb(),
                'max:'.$this->maxPhotoSizeKb(),
            ],
            'photo_id' => [
                'required',
                'integer',
                Rule::exists('photos', 'id')->where('closeout_package_id', $package->id),
                Rule::in([$routePhoto->id]),
            ],
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
            'photo.required' => 'Please select an image file to upload.',
            'photo.file' => 'The uploaded file is not a valid image file.',
            'photo.mimes' => 'The image must be a JPG, JPEG, PNG, WEBP, or HEIC file.',
            'photo.min' => 'The image file is too small (under 10 KB). Please select a higher quality photo.',
            'photo.max' => 'The image exceeds the maximum allowed size of 20 MB.',
            'photo_id.required' => 'Target photo ID is missing.',
            'photo_id.exists' => 'The selected photo does not belong to this closeout package.',
            'photo_id.in' => 'Target photo ID mismatch.',
        ];
    }

    public function toDTO(): ReplacePhotoDTO
    {
        $photoId = $this->integer('photo_id');
        $capturedAt = $this->validated('captured_at');
        $notesVal = $this->input('notes');

        return new ReplacePhotoDTO(
            photoId: $photoId,
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

        /** @var int $minKb */
        $minKb = config('cop.min_photo_size_kb', 10);

        return $minKb;
    }

    private function maxPhotoSizeKb(): int
    {
        /** @var int $maxMb */
        $maxMb = config('cop.max_photo_size_mb', 20);

        return $maxMb * 1024;
    }
}
