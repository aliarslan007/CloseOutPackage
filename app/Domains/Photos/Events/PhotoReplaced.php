<?php

declare(strict_types=1);

namespace App\Domains\Photos\Events;

use App\Domains\Photos\Models\Photo;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoReplaced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Photo $oldPhoto,
        public readonly Photo $newPhoto,
    ) {}
}
