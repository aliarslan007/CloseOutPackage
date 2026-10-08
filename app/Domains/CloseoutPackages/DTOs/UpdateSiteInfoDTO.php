<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

final readonly class UpdateSiteInfoDTO
{
    public function __construct(
        public ?string $siteName = null,
        public ?string $psNumber = null,
        public ?string $projectNumber = null,
        public ?string $address = null,
        public ?string $completeDate = null,
        public ?string $scopeOfWork = null,
        public ?string $siteAccessNotes = null,
        public ?string $vzwEngineerName = null,
        public ?string $vzwEngineerPhone = null,
        public ?string $vzwEngineerEmail = null,
        public ?string $authorCompany = null,
        public ?string $authorName = null,
        public ?string $authorEmail = null,
        public ?string $authorPhone = null,
        public ?string $pmCompany = null,
        public ?string $pmName = null,
        public ?string $pmEmail = null,
        public ?string $pmPhone = null,
    ) {}
}
