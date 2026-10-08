<?php

declare(strict_types=1);

use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site notes moved from per-question answers to a single package-level
     * section. Response media is cleared first so no orphaned files remain.
     */
    public function up(): void
    {
        PackageChecklistResponse::query()
            ->with('media')
            ->chunkById(200, function ($responses): void {
                foreach ($responses as $response) {
                    $response->clearMediaCollection(PackageChecklistResponse::SITE_NOTES_COLLECTION);
                }
            });

        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->dropColumn('site_notes');
        });
    }

    public function down(): void
    {
        Schema::table('package_checklist_responses', function (Blueprint $table): void {
            $table->text('site_notes')->nullable()->after('quantity');
        });
    }
};
