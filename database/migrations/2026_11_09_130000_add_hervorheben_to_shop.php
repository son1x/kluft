<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelbestellung, neue Bestellseite (SB-114): Artikel und Sets, die oben
 * unter „Für den Start“ stehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_artikel', function (Blueprint $table) {
            $table->boolean('hervorheben')->default(false)->after('freigegeben');
        });
        Schema::table('shop_sets', function (Blueprint $table) {
            $table->boolean('hervorheben')->default(false)->after('freigegeben');
        });
    }

    public function down(): void
    {
        Schema::table('shop_artikel', fn (Blueprint $table) => $table->dropColumn('hervorheben'));
        Schema::table('shop_sets', fn (Blueprint $table) => $table->dropColumn('hervorheben'));
    }
};
