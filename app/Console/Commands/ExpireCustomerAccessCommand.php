<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCustomerAccessCommand extends Command
{
    protected $signature = 'cop:expire-customer-access';

    protected $description = 'Delete customer project access records whose expiry has passed';

    public function handle(): int
    {
        $deleted = CustomerProjectAccess::query()
            ->where('expires_at', '<=', now())
            ->delete();

        Log::info('Expired customer access cleanup completed', ['deleted' => $deleted]);

        activity('system')
            ->event('customer.access.expired')
            ->withProperties(['deleted' => $deleted])
            ->log('Expired customer access cleanup completed');

        return self::SUCCESS;
    }
}
