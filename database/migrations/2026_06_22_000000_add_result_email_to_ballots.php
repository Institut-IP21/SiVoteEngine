<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-ballot results-email template, mirroring the invite-email fields
     * (email_subject / email_template). Editable in the builder; null/empty falls
     * back to the localized default at send time.
     */
    public function up(): void
    {
        Schema::table('ballots', function (Blueprint $table): void {
            $table->string('result_email_subject')->nullable()->after('email_template');
            $table->text('result_email_template')->nullable()->after('result_email_subject');
        });
    }

    public function down(): void
    {
        Schema::table('ballots', function (Blueprint $table): void {
            $table->dropColumn(['result_email_subject', 'result_email_template']);
        });
    }
};
