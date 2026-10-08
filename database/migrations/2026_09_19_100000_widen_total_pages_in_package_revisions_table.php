<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // total_pages was unsignedTinyInteger (max 255). A COP with ~1,000
        // photos at 4 photos per page exceeds 255 pages, silently overflowing
        // the column. Widen to unsignedSmallInteger (max 65,535).
        Schema::table('package_revisions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('total_pages')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('package_revisions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('total_pages')->default(0)->change();
        });
    }
};
