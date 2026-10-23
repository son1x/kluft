<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelbestellung: eigene Artikel des Stamms (z. B. Stammesaufnäher,
 * T-Shirts), die nicht beim Rüsthaus bestellt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_artikel', function (Blueprint $table) {
            $table->boolean('eigen')->default(false)->after('nummer')->comment('eigener Artikel des Stamms, nicht beim Rüsthaus');
        });
    }

    public function down(): void
    {
        Schema::table('shop_artikel', fn (Blueprint $table) => $table->dropColumn('eigen'));
    }
};
