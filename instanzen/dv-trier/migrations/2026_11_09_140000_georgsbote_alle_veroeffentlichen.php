<?php

use App\Models\GeorgsboteIssue;
use App\Support\AuditLogger;
use Illuminate\Database\Migrations\Migration;

/*
| Auftrag der Projektleitung (09.11.2026): alle hochgeladenen Georgsboten
| und Georgsbriefe als „Personenbezug geprüft“ markieren und im Archiv
| veröffentlichen. Nur Ausgaben mit PDF; ein gesetztes „Sichtbar ab“ bleibt.
| Geprüft-von bleibt leer (kein Konto), der Vorgang steht im Protokoll.
*/
return new class extends Migration
{
    public function up(): void
    {
        $ausgaben = GeorgsboteIssue::query()
            ->whereNotNull('pdf_path')->where('pdf_path', '!=', '')
            ->where(fn ($q) => $q->where('privacy_checked', false)->orWhere('published', false))
            ->get();

        foreach ($ausgaben as $issue) {
            if (! $issue->privacy_checked) {
                $issue->privacy_checked = true;
                $issue->privacy_checked_at = now();
            }
            $issue->published = true;
            $issue->save();
        }

        if ($ausgaben->isNotEmpty()) {
            AuditLogger::log('georgsbote.alle_freigegeben', null, 'georgsbote_issues');
        }
    }

    public function down(): void
    {
        // Freigaben bleiben.
    }
};
