<?php

declare(strict_types=1);

namespace App\Domains\Customers\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Patch semantics: null = leave unchanged. A non-null password resets the
 * linked portal account's credentials (and provisions one for legacy engineers
 * that predate portal accounts); first/last name arrive together with it.
 */
final readonly class UpdateCustomerEngineerDTO
{
    public function __construct(
        public ?int $marketId = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $title = null,
        public ?string $company = null,
        public ?string $password = null,
        public ?bool $isDefault = null,
        public ?bool $isActive = null,
        public ?UploadedFile $profilePhoto = null,
    ) {}
}
