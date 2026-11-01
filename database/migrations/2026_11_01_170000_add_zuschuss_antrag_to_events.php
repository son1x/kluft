<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschussanträge Phase 4 (docs/pflichtenheft-zuschuesse.md, ZU-50 ff.):
 * Angaben zum Antragsteller je Veranstaltung (verschlüsselt, enthält Namen
 * und Bankverbindung) und je Zuschussgeber Frist, Einreichung und Bewilligung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('zuschuss_antrag')->nullable()->comment('verschlüsselt: Antragsteller, Bank, Leitung');
            $table->json('zuschuss_stand')->nullable()->comment('je Zuschussgeber: Frist, eingereicht, bewilligt, ausgezahlt');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['zuschuss_antrag', 'zuschuss_stand']));
    }
};
