<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/*
| Datenschutzerklärung: Absätze zu „Vor Ort“ (Nachmelden, digitale Unterschrift,
| verschlüsselter Gerätespeicher, docs/vor-ort.md) hinter den Absatz zu den
| Teilnahmebescheinigungen setzen. Nur, wenn dieser Absatz noch wörtlich auf der
| Seite steht und die Ergänzung fehlt; eine im Backend bearbeitete Seite bleibt
| unverändert. Der Wortlaut ist mit der bzw. dem Datenschutzbeauftragten abzustimmen.
*/
return new class extends Migration
{
    public const ALT = '<p>Teilnahmebescheinigungen tragen einen Prüfcode. Über diesen Code kann eine Stelle, der Sie die Bescheinigung vorlegen, deren Echtheit auf unserer Website prüfen.</p>';

    public const NEU = '<p>Wer bei einer Veranstaltung vor Ort ist, aber nicht angemeldet war, kann vom Team der Veranstaltung nachträglich eingetragen werden. Dabei erfassen wir dieselben Angaben wie im Anmeldeformular, ohne Bestätigungs-E-Mail; stattdessen bestätigt das Team, dass die Person (bei Minderjährigen die Sorgeberechtigten) mit der Anmeldung und der Speicherung der Angaben einverstanden ist. Wer die Eintragung vorgenommen hat, wird protokolliert. Rechtsgrundlage und Löschfristen sind wie bei der Anmeldung über das Formular.</p>
<p>Für Zuschussabrechnungen unterschreiben Teilnehmende die Teilnahmeliste auf Wunsch digital auf dem Tablet des Teams. Das Bild der Unterschrift speichern wir verschlüsselt mit Zeitpunkt und Prüfsumme und setzen es in die Teilnahmeliste ein. Die einzelne Unterschrift löschen wir 14 Tage nach dem Festschreiben der Liste; die Liste bewahren wir nach den Vorgaben des Zuschussgebers auf.</p>
<p>Das Team nutzt vor Ort ein Tablet oder Handy. Damit auch ohne Internet gearbeitet werden kann, können die Daten der Veranstaltung auf diesem Gerät zeitweise zwischengespeichert werden, ausschließlich verschlüsselt und durch einen Code geschützt. Besonders geschützte Angaben (z. B. zu Gesundheit oder Unverträglichkeiten) werden nur nach ausdrücklicher Freigabe durch das Team zwischengespeichert. Wir löschen diese Daten beim Abmelden, nach mehrfach falscher Code-Eingabe und spätestens sieben Tage nach dem Ende der Veranstaltung.</p>';

    public function up(): void
    {
        $seite = Page::query()->where('slug', 'datenschutz')->first();
        $body = (string) $seite?->body;
        if (! $seite || ! str_contains($body, self::ALT) || str_contains($body, self::NEU)) {
            return;
        }

        $seite->forceFill(['body' => str_replace(self::ALT, self::ALT."\n".self::NEU, $body)])->save();
    }

    public function down(): void
    {
        // Inhalt bleibt.
    }
};
