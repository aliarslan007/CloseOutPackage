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
            // One primary field technician per project, picked from the project's
            // assigned supervisors. Nullable: a project may have no primary.
            $table->foreignId('primary_supervisor_id')
                ->nullable()
                ->after('manager_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_supervisor_id');
        });
    }
};
