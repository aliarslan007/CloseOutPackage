<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_logs') && ! Schema::hasColumn('email_logs', 'provider_message_id')) {
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->string('provider_message_id', 255)->nullable()->after('idempotency_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_logs') && Schema::hasColumn('email_logs', 'provider_message_id')) {
            Schema::table('email_logs', function (Blueprint $table): void {
                $table->dropColumn('provider_message_id');
            });
        }
    }
};
