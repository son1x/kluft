<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Seitenaufrufe ohne Personenbezug (docs/aufrufe.md): nur Tag, Pfad und
| Anzahl. Bewusst keine weiteren Spalten (keine IP, kein User-Agent, keine
| Kennung, nicht einmal eine laufende Nummer).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seitenaufrufe', function (Blueprint $table) {
            $table->date('datum');
            $table->string('pfad', 255);
            $table->unsignedInteger('aufrufe')->default(0);

            $table->unique(['datum', 'pfad']);
            $table->index('pfad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seitenaufrufe');
    }
};
