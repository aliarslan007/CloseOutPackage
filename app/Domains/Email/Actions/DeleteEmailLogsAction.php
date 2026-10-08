<?php

declare(strict_types=1);

namespace App\Domains\Email\Actions;

use App\Domains\Email\Models\EmailLog;

final class DeleteEmailLogsAction
{
    /**
     * Permanently removes the given email log ids. Authorization lives in
     * EmailLogPolicy (admin only), enforced by DestroyEmailLogRequest.
     *
     * @param  list<int>  $ids
     */
    public function execute(array $ids): int
    {
        return EmailLog::query()->whereKey($ids)->delete();
    }
}
