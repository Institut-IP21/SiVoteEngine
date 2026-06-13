<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCastByToVotes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table): void {
            $table->string('cast_by')->nullable(); // String, not int, to give more flexibility of 3rd party UI implementation.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table): void {
            $table->dropColumn('cast_by');
        });
    }
}
