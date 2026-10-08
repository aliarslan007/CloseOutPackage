<?php

declare(strict_types=1);

use App\Domains\Settings\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Upgrades an existing installation to the customer hierarchy: creates the
 * default Verizon customer, one default engineer per used market (region), and
 * links every existing project to both. A fresh database has no projects, so
 * this is a no-op there and test databases stay clean.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('projects')->exists()) {
            return;
        }

        $customerId = $this->defaultCustomerId();

        DB::table('projects')
            ->whereNull('customer_id')
            ->update(['customer_id' => $customerId]);

        $email = trim((string) AppSetting::get('vzw_engineer_email'));

        if ($email === '') {
            return;
        }

        $name = trim((string) AppSetting::get('vzw_engineer_name'));
        $phone = trim((string) AppSetting::get('vzw_engineer_phone'));

        /** @var list<int> $marketIds */
        $marketIds = DB::table('projects')
            ->whereNotNull('market_id')
            ->distinct()
            ->pluck('market_id')
            ->all();

        foreach ($marketIds as $marketId) {
            $engineerId = $this->defaultEngineerId($customerId, $marketId, $email, $name, $phone);

            DB::table('projects')
                ->whereNull('customer_engineer_id')
                ->where('market_id', $marketId)
                ->update(['customer_engineer_id' => $engineerId]);
        }
    }

    /**
     * Data-only migration: the columns and tables it fills are dropped by the
     * migrations that own them, so there is nothing to reverse here.
     */
    public function down(): void {}

    private function defaultCustomerId(): int
    {
        $existing = DB::table('customers')->where('code', 'VZW')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('customers')->insertGetId([
            'name' => 'Verizon',
            'code' => 'VZW',
            'description' => 'Default customer created during the customer hierarchy upgrade.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function defaultEngineerId(int $customerId, int $marketId, string $email, string $name, string $phone): int
    {
        $existing = DB::table('customer_engineers')
            ->where('customer_id', $customerId)
            ->where('market_id', $marketId)
            ->where('email', $email)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('customer_engineers')->insertGetId([
            'customer_id' => $customerId,
            'market_id' => $marketId,
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'title' => null,
            'is_default' => true,
            'user_id' => null,
            'is_active' => true,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
