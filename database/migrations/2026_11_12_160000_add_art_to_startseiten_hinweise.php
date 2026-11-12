<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Startseiten-Hinweise: zweite, dezente Art „Zeile“ neben dem großen Kasten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('startseiten_hinweise', function (Blueprint $table) {
            $table->string('art', 20)->default('kasten')->after('titel');
        });
    }

    public function down(): void
    {
        Schema::table('startseiten_hinweise', function (Blueprint $table) {
            $table->dropColumn('art');
        });
    }
};
