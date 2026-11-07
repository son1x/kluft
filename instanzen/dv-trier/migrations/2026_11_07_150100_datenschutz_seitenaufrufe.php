<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/*
| Datenschutzerklärung: Absatz zur Webanalyse um die Zählung der
| Seitenaufrufe ergänzen (Funktion „aufrufe“, docs/aufrufe.md). Ersetzt den
| alten Absatz nur, wenn er noch wörtlich auf der Seite steht; eine im
| Backend bearbeitete Seite bleibt unverändert.
*/
return new class extends Migration
{
    public const ALT = '<p>Wir setzen keine Werkzeuge zur Webanalyse oder Reichweitenmessung ein. Schriftarten, Karten und alle übrigen Bestandteile der Website liefern wir von unserem eigenen Server aus; beim bloßen Aufruf werden keine Daten an fremde Anbieter übermittelt.</p>';

    public const NEU = '<p>Um zu erfahren, welche Inhalte gelesen werden, zählt die Website Seitenaufrufe. Gespeichert werden nur die aufgerufene Seite, der Tag und die Anzahl, keine IP-Adresse, kein Cookie und keine Kennung Ihres Geräts; eine Zuordnung zu Ihrer Person ist nicht möglich. Aufrufe mit aktiviertem „Do Not Track“ oder „Global Privacy Control“ zählen wir nicht. Die Zähler werden nach 25 Monaten gelöscht. Weitere Werkzeuge zur Webanalyse setzen wir nicht ein. Schriftarten, Karten und alle übrigen Bestandteile der Website liefern wir von unserem eigenen Server aus; beim bloßen Aufruf werden keine Daten an fremde Anbieter übermittelt.</p>';

    public function up(): void
    {
        $seite = Page::query()->where('slug', 'datenschutz')->first();
        if (! $seite || ! str_contains((string) $seite->body, self::ALT)) {
            return;
        }

        $seite->forceFill(['body' => str_replace(self::ALT, self::NEU, (string) $seite->body)])->save();
    }

    public function down(): void
    {
        // Inhalt bleibt.
    }
};
