<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teilnahmeliste mit digitalen Unterschriften (Vor Ort) auch ohne
 * Zuschussantrag: Schalter je Veranstaltung. Mit Zuschussantrag ist sie immer an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->boolean('unterschriftenliste')->default(false));
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('unterschriftenliste'));
    }
};
