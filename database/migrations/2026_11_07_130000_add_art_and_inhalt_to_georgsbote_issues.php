<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Georgsbote-Archiv mit zweitem Reiter „Georgsbrief“ (Newsletter als PDF,
 * issue_no = Monat) und Kurzinhalt je Ausgabe (docs/georgsbote.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('georgsbote_issues', function (Blueprint $table) {
            $table->string('art', 10)->default('bote')->after('id')->comment('bote = Verbandsheft, brief = Newsletter (issue_no = Monat)');
            $table->text('inhalt')->nullable()->after('title');
        });

        Schema::table('georgsbote_issues', function (Blueprint $table) {
            $table->dropUnique(['year', 'issue_no']);
            $table->unique(['art', 'year', 'issue_no']);
        });
    }

    public function down(): void
    {
        Schema::table('georgsbote_issues', function (Blueprint $table) {
            $table->dropUnique(['art', 'year', 'issue_no']);
            $table->unique(['year', 'issue_no']);
            $table->dropColumn(['art', 'inhalt']);
        });
    }
};
