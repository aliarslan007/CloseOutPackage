<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Email\Actions\DeliverCustomerEmailAction;
use Illuminate\Console\Command;

final class ProcessScheduledCustomerEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cop:process-scheduled-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sweep and dispatch scheduled customer emails that are due for delivery.';

    public function handle(DeliverCustomerEmailAction $action): int
    {
        $count = $action->processDue();

        if ($count > 0) {
            $this->info(sprintf('Dispatched %d due customer email(s).', $count));
        }

        return self::SUCCESS;
    }
}
