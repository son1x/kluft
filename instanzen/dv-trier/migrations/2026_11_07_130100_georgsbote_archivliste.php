<?php

use App\Models\GeorgsboteIssue;
use App\Support\GeorgsboteArchiv;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
| Titel und Kurzinhalt aus der Archivliste (dateien/georgsbote-archiv.php)
| für schon hochgeladene Ausgaben übernehmen. Nur Vorgabe-Titel und leere
| Inhalte werden gesetzt; Veröffentlichung und Prüfung bleiben unberührt.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('georgsbote_issues', 'inhalt')) {
            return;
        }

        GeorgsboteIssue::query()->get()->each(fn (GeorgsboteIssue $issue) => GeorgsboteArchiv::anwenden($issue));
    }

    public function down(): void
    {
        // Inhalte bleiben.
    }
};
