<?php

declare(strict_types=1);

namespace App\Domains\Customers\DTOs;

use Illuminate\Http\UploadedFile;

/**
 * Creating an engineer ALWAYS provisions a Customer Portal account (role
 * "customer") for the given password — the credentials Edwin forwards to the
 * engineer so they can sign in. The engineer row links that user.
 */
final readonly class CreateCustomerEngineerDTO
{
    public function __construct(
        public int $customerId,
        public int $marketId,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public ?string $phone = null,
        public ?string $title = null,
        public ?string $company = null,
        public bool $isDefault = false,
        public bool $isActive = true,
        public ?UploadedFile $profilePhoto = null,
    ) {}
}
