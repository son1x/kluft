<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Früher: Weiterleitungs-Cache nach einer Inhaltsänderung des DV leeren.
 * Auf neuen Installationen ohne Bedeutung. Die leere Migration bleibt, weil
 * bestehende Installationen sie schon ausgeführt haben.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
