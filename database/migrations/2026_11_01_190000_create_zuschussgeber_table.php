<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschussgeber je Instanz (docs/pflichtenheft-zuschuesse.md, ZU-60 ff.):
 * die eingebauten Vorlagen (Land Saarland, Kreis und Stadt Saarlouis,
 * Bistum Trier) zum Ein- und Ausschalten und eigene Zuschussgeber mit
 * Sätzen, Gebiet und Frist. Welche Vorlagen anfangs an sind, steht in
 * config('dpsg.zuschuss_geber').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zuschussgeber', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 150);
            $table->string('vorlage', 40)->nullable()->comment('eingebaute Regeln; leer = eigene Regeln');
            $table->boolean('aktiv')->default(false);
            $table->json('regeln')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        $an = (array) config('dpsg.zuschuss_geber', ['bistum_trier']);
        foreach ([
            'land' => 'Landesjugendamt Saarland',
            'kreis_sls' => 'Kreisjugendamt Saarlouis',
            'stadt_sls' => 'Kreisstadt Saarlouis',
            'bistum_trier' => 'Bistum Trier (KJP)',
        ] as $key => $name) {
            DB::table('zuschussgeber')->insert(['key' => $key, 'name' => $name, 'vorlage' => $key, 'aktiv' => in_array($key, $an, true),
                'sort' => ['land' => 10, 'kreis_sls' => 20, 'stadt_sls' => 30, 'bistum_trier' => 40][$key], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zuschussgeber');
    }
};
