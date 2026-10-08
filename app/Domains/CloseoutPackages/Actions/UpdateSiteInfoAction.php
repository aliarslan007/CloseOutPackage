<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\DTOs\UpdateSiteInfoDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageSiteInfo;

final class UpdateSiteInfoAction
{
    public function execute(CloseoutPackage $package, UpdateSiteInfoDTO $dto): PackageSiteInfo
    {
        $siteInfo = $package->siteInfo()->firstOrNew();
        $attributes = $this->resolveAttributes($dto);

        if ($siteInfo->exists) {
            $siteInfo->update($attributes);
        } else {
            $siteInfo->fill(['closeout_package_id' => $package->id] + $attributes)->save();
        }

        return $siteInfo->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttributes(UpdateSiteInfoDTO $dto): array
    {
        return array_filter([
            'site_name' => $dto->siteName,
            'ps_number' => $dto->psNumber,
            'project_number' => $dto->projectNumber,
            'address' => $dto->address,
            'complete_date' => $dto->completeDate,
            'scope_of_work' => $dto->scopeOfWork,
            'site_access_notes' => $dto->siteAccessNotes,
            'vzw_engineer_name' => $dto->vzwEngineerName,
            'vzw_engineer_phone' => $dto->vzwEngineerPhone,
            'vzw_engineer_email' => $dto->vzwEngineerEmail,
            'author_company' => $dto->authorCompany,
            'author_name' => $dto->authorName,
            'author_phone' => $dto->authorPhone,
            'pm_company' => $dto->pmCompany,
            'pm_name' => $dto->pmName,
            'pm_phone' => $dto->pmPhone,
        ], fn (mixed $value): bool => $value !== null);
    }
}
