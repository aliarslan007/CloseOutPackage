<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use Illuminate\Support\Facades\Storage;

final class DetachChangeOrderPhotoAction
{
    public function execute(ChangeOrderPhoto $photo): void
    {
        Storage::disk('private')->delete($photo->path);

        $photo->delete();
    }
}
