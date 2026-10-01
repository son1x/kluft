<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ersetzt in den Beispielinhalten den Hinweis "Verzeichnis folgt" durch
 * einen Link auf /bezirke-staemme – nur dort, wo der ursprüngliche Satz
 * noch unverändert steht (redaktionelle Änderungen bleiben unberührt).
 */
return new class extends Migration
{
    private const REPLACEMENTS = [
        ['pages', 'kontakt',
            'Das Leitungsteam findest du auf der Seite <a href="/dioezesanbuero">Diözesanbüro</a>. Ein eigenes Bezirke- und Stämme-Verzeichnis mit Kartenansicht ist für eine spätere Ausbaustufe vorgesehen (Pflichtenheft 6.11).',
            'Alle Bezirke und Stämme mit Kontakt und Karte findest du unter <a href="/bezirke-staemme">Bezirke &amp; Stämme</a>, das Leitungsteam auf der Seite <a href="/dioezesanbuero">Diözesanbüro</a>.'],
        ['pages', 'dioezesanbuero',
            'Ein eigenes Bezirke- und Stämme-Verzeichnis mit Kartenansicht ist in Vorbereitung (Pflichtenheft 6.11).',
            'Alle Bezirke und Stämme mit Kontakt und Karte findest du unter <a href="/bezirke-staemme">Bezirke &amp; Stämme</a>.'],
        ['posts', 'neue-website-im-aufbau',
            'Veranstaltungen, der Georgsbote und ein Verzeichnis aller Bezirke und Stämme mit Kartenansicht folgen.',
            'Neu ist das Verzeichnis aller <a href="/bezirke-staemme">Bezirke und Stämme</a> mit Karte; Veranstaltungen und der Georgsbote folgen.'],
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as [$table, $slug, $old, $new]) {
            $row = DB::table($table)->where('slug', $slug)->first(['id', 'body']);

            if ($row && str_contains($row->body, $old)) {
                DB::table($table)->where('id', $row->id)->update(['body' => str_replace($old, $new, $row->body)]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
