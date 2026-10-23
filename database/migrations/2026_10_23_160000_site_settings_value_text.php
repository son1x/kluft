<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einstellungen dürfen länger als 255 Zeichen sein (z. B. der Stand des
 * NaMi-Beitragsabrufs mit der Meldung von NaMi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->text('value')->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('value', 255)->change();
        });
    }
};
