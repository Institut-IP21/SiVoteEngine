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
        Schema::table('ballot_components', function (Blueprint $table) {
            // Per-component settings payload (e.g. YesNo's `pass_threshold`).
            // Nullable: absent settings -> component-type defaults.
            $table->json('settings')->nullable()->after('options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ballot_components', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
