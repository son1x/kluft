<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Startseiten-Hinweise: Abstufung Rot › Beige › Weiß statt Blau.
 * Bisher blaue Hinweise werden weiß (dezent).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('startseiten_hinweise')->where('farbe', 'blau')->update(['farbe' => 'weiss']);
    }

    public function down(): void
    {
        DB::table('startseiten_hinweise')->where('farbe', 'weiss')->update(['farbe' => 'blau']);
    }
};
