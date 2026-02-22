<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_session_voters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ballot_id')->index();
            $table->string('code');
            $table->timestamp('last_seen_at');

            $table->unique(['ballot_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_session_voters');
    }
};
