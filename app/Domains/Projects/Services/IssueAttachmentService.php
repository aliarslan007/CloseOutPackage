<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Shared\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

final class IssueAttachmentService
{
    public const MAX_FILES = 10;

    public const MAX_SIZE_MB = 5;

    public const MAX_SIZE_KB = self::MAX_SIZE_MB * 1024;

    public const MAX_SIZE_BYTES = self::MAX_SIZE_MB * 1024 * 1024;

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/webm',
        'video/x-matroska',
    ];

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'mp4', 'mov', 'avi', 'webm', 'mkv'];

    public function __construct(
        private readonly ImageCompressionService $compressionService = new ImageCompressionService,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function attach(HasMedia $model, array $files, string $collection): void
    {
        foreach ($files as $file) {
            $mime = $file->getMimeType() ?? '';

            // Downscale and compress images (<=2560px / q85); videos bypass untouched.
            $fileToStore = str_starts_with($mime, 'image/')
                ? $this->compressionService->compressUploadedFile($file)
                : $file;

            $model->addMedia($fileToStore)->toMediaCollection($collection);
        }
    }

    /**
     * Validation rules for issue attachments (image + video, 5MB each).
     *
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,heic,heif,mp4,mov,avi,webm,mkv',
                'max:'.self::MAX_SIZE_KB,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'attachments.*.mimes' => 'Each attachment must be an image (JPG, PNG, WEBP, HEIC) or video (MP4, MOV, AVI, WEBM, MKV).',
            'attachments.*.max' => 'Each attachment may not be greater than '.self::MAX_SIZE_MB.'MB.',
        ];
    }
}
