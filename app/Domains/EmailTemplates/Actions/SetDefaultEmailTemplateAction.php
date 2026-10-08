<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Actions;

use App\Domains\EmailTemplates\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Single owner of the "one default per email_type" invariant. Clearing the
 * previous default and promoting the new one happens in one transaction; the
 * composite index on (email_type, status, is_default) keeps the lookup fast.
 */
final class SetDefaultEmailTemplateAction
{
    public function execute(EmailTemplate $template): void
    {
        if ($template->is_default) {
            return;
        }

        DB::transaction(function () use ($template): void {
            EmailTemplate::query()
                ->where('email_type', $template->email_type->value)
                ->where('is_default', true)
                ->whereKeyNot($template->getKey())
                ->update(['is_default' => false]);

            $template->is_default = true;
            $template->save();
        });
    }
}
