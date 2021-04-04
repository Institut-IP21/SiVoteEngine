<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBallotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ballots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('election_id');
            $table->foreign('election_id')->references('id')->on('elections');
            $table->string('title');
            $table->string('email_subject')->nullable();
            $table->text('email_template')->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(false);
            $table->boolean('finished')->default(false);
            $table->softDeletes('deleted_at');
            $table->timestamps();
            $table->index(['election_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ballots');
    }
}
