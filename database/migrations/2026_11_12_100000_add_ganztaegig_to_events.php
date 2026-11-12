<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganztägige Veranstaltungen (Schalter wie im Apple-Kalender). Bisher half
 * nur der Trick „Uhrzeit 00:00“: solche Veranstaltungen werden als
 * ganztägig übernommen, ein Ende um 00:00 wird zu 23:59 desselben Tages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('ganztaegig')->default(false)->after('ends_at');
        });

        DB::table('events')->orderBy('id')->get(['id', 'starts_at', 'ends_at'])->each(function ($e) {
            $start = Carbon::parse($e->starts_at);
            $ende = $e->ends_at ? Carbon::parse($e->ends_at) : null;
            if ($start->format('H:i') !== '00:00' || ($ende && ! in_array($ende->format('H:i'), ['00:00', '23:59'], true))) {
                return;
            }
            DB::table('events')->where('id', $e->id)->update([
                'ganztaegig' => true,
                'ends_at' => ($ende ?? $start)->copy()->setTime(23, 59)->format('Y-m-d H:i:s'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('ganztaegig');
        });
    }
};
