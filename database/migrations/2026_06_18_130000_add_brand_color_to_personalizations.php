<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-owner ballot accent color (hex, e.g. #34b6df). Null → eGlasovanje default.
     */
    public function up(): void
    {
        Schema::table('personalizations', function (Blueprint $table): void {
            $table->string('brand_color', 7)->nullable()->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('personalizations', function (Blueprint $table): void {
            $table->dropColumn('brand_color');
        });
    }
};
