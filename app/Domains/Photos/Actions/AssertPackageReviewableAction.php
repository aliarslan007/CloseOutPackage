<?php

declare(strict_types=1);

namespace App\Domains\Photos\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Shared\Exceptions\InvalidStateTransitionException;

/**
 * Guards manager review flows against packages the supervisor has not
 * submitted yet. Sibling of AssertPackagePhotosEditableAction: while the
 * package is a draft, its photos must not be approved, flagged, or
 * replacement-requested — only submission starts the review window.
 */
final class AssertPackageReviewableAction
{
    public const MESSAGE = 'This package has not been submitted for review yet; its photos cannot be approved, flagged, or replaced while it is a draft.';

    public function execute(?CloseoutPackage $package): void
    {
        if ($package === null || $package->status !== PackageStatus::DRAFT) {
            return;
        }

        throw new InvalidStateTransitionException(self::MESSAGE);
    }
}
