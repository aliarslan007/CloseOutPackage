<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Support\Facades\DB;

final class IncrementRevisionAction
{
    public function execute(CloseoutPackage $package): CloseoutPackage
    {
        return DB::transaction(function () use ($package): CloseoutPackage {
            /** @var int $threshold */
            $threshold = config('cop.revision_minor_threshold', 10);

            $minor = $package->revision_minor + 1;
            $major = $package->revision_major;

            if ($minor >= $threshold) {
                $major++;
                $minor = 0;
            }

            $package->update([
                'revision_major' => $major,
                'revision_minor' => $minor,
                'revision_date' => today(),
            ]);

            return $package;
        });
    }
}
