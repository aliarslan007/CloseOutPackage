<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'county')) {
                $table->string('county', 120)->nullable()->after('city');
            }
            if (! Schema::hasColumn('projects', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('zip');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('projects', 'description')) {
                $table->text('description')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            foreach (['county', 'latitude', 'longitude', 'description'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
