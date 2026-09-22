<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table): void {
            $table->softDeletes('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table): void {
            $table->dropSoftDeletes('deleted_at');
        });
    }
};
