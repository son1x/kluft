<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beitragsart von Hand (wenn NaMi die Details einer Person verweigert) und
 * Merker, damit die Details erst bei geänderter NaMi-Version erneut abgefragt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beitrags_mitglieder', function (Blueprint $table) {
            $table->boolean('beitragsart_manuell')->default(false)->after('beitragsart');
            $table->boolean('details_verweigert')->default(false)->after('beitragsart_manuell');
        });
    }

    public function down(): void
    {
        Schema::table('beitrags_mitglieder', function (Blueprint $table) {
            $table->dropColumn(['beitragsart_manuell', 'details_verweigert']);
        });
    }
};
