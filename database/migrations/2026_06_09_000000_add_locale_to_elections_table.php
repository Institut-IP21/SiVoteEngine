<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            // Organizer's locale, captured at creation, so voter-facing ballot
            // and result pages render in the same language the election was
            // organized in. Null for legacy elections (falls back to default).
            $table->string('locale')->nullable()->after('owner');
        });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
