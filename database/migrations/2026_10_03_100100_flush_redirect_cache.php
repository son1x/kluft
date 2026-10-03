<?php

use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * Die Migration zur Diözesanversammlung hat eine Weiterleitung direkt in
 * die Datenbank geschrieben, ohne den dauerhaft gecachten Weiterleitungs-
 * Plan zu leeren – das holt diese Migration nach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Cache::forget(Redirect::CACHE_KEY);
    }

    public function down(): void
    {
        //
    }
};
