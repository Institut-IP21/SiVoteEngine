<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * User-entered text columns that were VARCHAR(255) with no max-length
     * validation, so overflowing one was a 500 instead of a save. `options`
     * is the sharpest case: a JSON array with non-ASCII escaped (`č` ->
     * `č`), which overflowed at ~13 Slovenian candidate names.
     * Widening only; nullability is kept as originally defined.
     */
    public function up(): void
    {
        Schema::table('ballot_components', function (Blueprint $table): void {
            $table->text('title')->change();
            $table->text('description')->nullable()->change();
            $table->text('options')->change();
        });

        Schema::table('ballots', function (Blueprint $table): void {
            $table->text('title')->change();
            $table->text('email_subject')->nullable()->change();
            $table->text('result_email_subject')->nullable()->change();
        });

        Schema::table('elections', function (Blueprint $table): void {
            $table->text('title')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ballot_components', function (Blueprint $table): void {
            $table->string('title')->change();
            $table->string('description')->nullable()->change();
            $table->string('options')->change();
        });

        Schema::table('ballots', function (Blueprint $table): void {
            $table->string('title')->change();
            $table->string('email_subject')->nullable()->change();
            $table->string('result_email_subject')->nullable()->change();
        });

        Schema::table('elections', function (Blueprint $table): void {
            $table->string('title')->change();
        });
    }
};
