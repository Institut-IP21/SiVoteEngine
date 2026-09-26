<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ballot_components', function (Blueprint $table): void {
            // Post-close runner tie resolutions for OrderedList (and any future
            // component needing the same escape hatch). Nullable: no resolutions
            // recorded yet -> the calc classes surface every unresolved band/cutoff
            // as-is.
            $table->json('runner_resolutions')->nullable()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ballot_components', function (Blueprint $table): void {
            $table->dropColumn('runner_resolutions');
        });
    }
};
