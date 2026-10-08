<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Users\Events\UserUpdated;
use App\Domains\Users\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-time repair for the SSO name round-trip bug: users whose last name was
 * appended repeatedly by the AuthCenter sync ("John Lime Lime") are restored
 * to a single copy ("John Lime") and pushed back to AuthCenter so both sides
 * converge on clean first/last names.
 */
class NormalizeUserNamesCommand extends Command
{
    protected $signature = 'users:normalize-names {--dry-run : Show what would change without writing}';

    protected $description = 'Deduplicate repeated last names in user records (SSO name round-trip repair)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        User::query()->eachById(function (User $user) use ($dryRun, &$changed): void {
            $name = trim((string) $user->name);

            // Recomposing through the guarded composer drops any repeated
            // trailing last-name tokens, leaving exactly one copy. An empty
            // name recomposes to itself, so the no-change check below already
            // covers empty-name rows.
            $normalized = User::fullName(
                (string) Str::beforeLast($name, ' '),
                (string) Str::afterLast($name, ' '),
            );

            if ($normalized === $name) {
                return;
            }

            $changed++;

            if ($dryRun) {
                $this->line(sprintf('  [dry-run] %s: "%s" -> "%s"', (string) $user->email, $name, $normalized));

                return;
            }

            $user->update(['name' => $normalized]);

            // Push the clean name back so AuthCenter stops serving the
            // polluted first name on the next login.
            UserUpdated::dispatch($user);

            $this->line(sprintf('  %s: "%s" -> "%s"', (string) $user->email, $name, $normalized));
        });

        $this->info($dryRun
            ? sprintf('Dry run complete: %d user(s) would be normalized.', $changed)
            : sprintf('Normalized %d user name(s) and queued the AuthCenter sync.', $changed));

        return self::SUCCESS;
    }
}
