<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBallotComponentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ballot_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ballot_id');
            $table->foreign('ballot_id')->references('id')->on('ballots');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('type');
            $table->string('options');
            $table->softDeletes('deleted_at');
            $table->string('version');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ballot_components');
    }
}
