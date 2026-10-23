<?php

namespace App\Support;

use App\Models\Umfrage;
use App\Models\UmfrageFrage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Umfragen (docs/umfragen.md): Rechte, Fragetypen, Bedingungen und das
 * Prüfen eingereichter Antworten. Die Teilnahme-Seite und die Auswertung
 * nutzen dieselben Regeln.
 */
class Umfragen
{
    /** Fragetypen mit Bezeichnung im Backend. */
    public const TYPEN = [
        'einfach' => 'Einfachauswahl',
        'mehrfach' => 'Mehrfachauswahl',
        'skala' => 'Skala (z. B. 1–5)',
        'bewertung' => 'Bewertung (sehr gut … gar nicht gut)',
        'janein' => 'Ja/Nein',
        'kurztext' => 'Kurzer Text',
        'langtext' => 'Langer Text',
        'abschnitt' => 'Abschnitt (Überschrift und Text, keine Frage)',
    ];

    /** Typen mit festen Antwortmöglichkeiten (dürfen Bedingungen auslösen). */
    public const AUSWAHL = ['einfach', 'mehrfach', 'bewertung', 'janein'];

    public const BEWERTUNG_STANDARD = ['sehr gut', 'gut', 'nicht so gut', 'gar nicht gut'];

    /** Diözesanleitung und mehr: Stufen- und Fachbereichsleitungen, Vorstand, Verwaltung, Admins. */
    public static function zugriff(?User $user): bool
    {
        if (! Features::enabled(Features::UMFRAGEN) || $user === null) {
            return false;
        }

        return $user->isVerwaltung() || $user->hasRole('vorstand') || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin');
    }

    /**
     * Antwortmöglichkeiten einer Frage (auch für Bedingungen).
     *
     * @return list<string>
     */
    public static function moeglichkeiten(UmfrageFrage $frage): array
    {
        return match ($frage->typ) {
            'janein' => ['Ja', 'Nein'],
            'bewertung' => [...($frage->optionenListe() ?: self::BEWERTUNG_STANDARD), ...(filled($frage->einstellung('nicht_zutreffend')) ? [(string) $frage->einstellung('nicht_zutreffend')] : [])],
            'einfach', 'mehrfach' => [...$frage->optionenListe(), ...($frage->einstellung('sonstiges') ? ['Sonstiges'] : [])],
            default => [],
        };
    }

    /** @return array{0: int, 1: int} */
    public static function skala(UmfrageFrage $frage): array
    {
        $von = max(0, min(1, (int) $frage->einstellung('von', 1)));
        $bis = max($von + 1, min(10, (int) $frage->einstellung('bis', 5)));

        return [$von, $bis];
    }

    /**
     * Ist die Frage bei diesen Antworten sichtbar? Bedingungen beziehen sich
     * auf frühere Fragen; ist die auslösende Frage selbst ausgeblendet, ist
     * es diese auch.
     *
     * @param  Collection<int, UmfrageFrage>  $fragen
     * @param  array<string, array<string, mixed>>  $daten
     */
    public static function sichtbar(UmfrageFrage $frage, Collection $fragen, array $daten, int $tiefe = 0): bool
    {
        $b = $frage->bedingung;
        if (! is_array($b) || blank($b['frage'] ?? null) || $tiefe > 20) {
            return true;
        }
        $quelle = $fragen->firstWhere('schluessel', $b['frage']);
        if (! $quelle || ! self::sichtbar($quelle, $fragen, $daten, $tiefe + 1)) {
            return $quelle === null;
        }
        $wert = $daten[$quelle->schluessel]['wert'] ?? null;

        return array_intersect((array) $wert, (array) ($b['werte'] ?? [])) !== [];
    }

    /**
     * Eingaben aus dem Formular prüfen und in die gespeicherte Form bringen.
     * Liefert [daten, fehler je Feldname].
     *
     * @param  Collection<int, UmfrageFrage>  $fragen
     * @param  array<string, mixed>  $eingabe  [schluessel => wert], kommentar[...], sonstiges[...]
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, string>}
     */
    public static function pruefen(Collection $fragen, array $eingabe): array
    {
        $daten = [];
        $fehler = [];
        $antworten = (array) ($eingabe['a'] ?? []);
        $kommentare = (array) ($eingabe['kommentar'] ?? []);
        $sonstiges = (array) ($eingabe['sonstiges'] ?? []);

        foreach ($fragen->filter->istAntwortfrage() as $frage) {
            $k = $frage->schluessel;
            $roh = $antworten[$k] ?? null;
            $wert = match ($frage->typ) {
                'mehrfach' => array_values(array_intersect(self::moeglichkeiten($frage), array_map('strval', (array) $roh))),
                'einfach', 'bewertung', 'janein' => in_array((string) $roh, self::moeglichkeiten($frage), true) ? (string) $roh : null,
                'skala' => is_numeric($roh) && (int) $roh >= self::skala($frage)[0] && (int) $roh <= self::skala($frage)[1] ? (int) $roh : null,
                'kurztext' => filled($roh) ? mb_substr(trim((string) $roh), 0, 500) : null,
                'langtext' => filled($roh) ? mb_substr(trim((string) $roh), 0, 5000) : null,
                default => null,
            };
            if ($wert === []) {
                $wert = null;
            }
            $eintrag = ['wert' => $wert];
            if ($frage->kommentar && filled($kommentare[$k] ?? null)) {
                $eintrag['kommentar'] = mb_substr(trim((string) $kommentare[$k]), 0, 2000);
            }
            if (in_array('Sonstiges', (array) $wert, true) && filled($sonstiges[$k] ?? null)) {
                $eintrag['sonstiges'] = mb_substr(trim((string) $sonstiges[$k]), 0, 500);
            }
            $daten[$k] = $eintrag;
        }

        // Ausgeblendete Fragen zählen nicht (und werden nicht gespeichert).
        foreach ($fragen->filter->istAntwortfrage() as $frage) {
            $k = $frage->schluessel;
            if (! self::sichtbar($frage, $fragen, $daten)) {
                unset($daten[$k]);

                continue;
            }
            $wert = $daten[$k]['wert'] ?? null;
            if ($frage->pflicht && $wert === null) {
                $fehler['a.'.$k] = 'Bitte beantworte diese Frage.';
            }
            if ($frage->typ === 'mehrfach' && is_array($wert)) {
                $min = (int) $frage->einstellung('min', 0);
                $max = (int) $frage->einstellung('max', 0);
                if ($min > 0 && count($wert) < $min) {
                    $fehler['a.'.$k] = "Bitte wähle mindestens {$min} aus.";
                } elseif ($max > 0 && count($wert) > $max) {
                    $fehler['a.'.$k] = "Bitte wähle höchstens {$max} aus.";
                }
            }
        }

        return [$daten, $fehler];
    }

    /**
     * Mitgelieferte Vorlagen (nach Beispielen des DV Trier), zum Anlegen
     * einer neuen Umfrage.
     *
     * @return array<string, array{titel: string, einleitung: string, fragen: list<array<string, mixed>>}>
     */
    public static function vorlagen(): array
    {
        $bew = fn (string $text, bool $kommentar = true, ?string $na = null) => ['typ' => 'bewertung', 'text' => $text, 'kommentar' => $kommentar, 'einstellungen' => $na ? ['nicht_zutreffend' => $na] : null];
        $skala = fn (string $text, bool $kommentar = true) => ['typ' => 'skala', 'text' => $text, 'kommentar' => $kommentar, 'einstellungen' => ['von' => 1, 'bis' => 5, 'label_von' => 'trifft gar nicht zu', 'label_bis' => 'trifft voll zu']];

        return [
            'veranstaltung' => [
                'titel' => 'Feedback zur Veranstaltung',
                'einleitung' => '<p>Danke, dass du dabei warst! Mit deiner Rückmeldung können wir die nächste Veranstaltung noch besser machen. Die Umfrage ist anonym.</p>',
                'fragen' => [
                    $bew('Die Veranstaltung hat mir im Ganzen … gefallen.'),
                    $bew('Das Programm hat mir … gefallen.', true, 'nicht teilgenommen'),
                    $bew('Unterkunft und Verpflegung fand ich …'),
                    $bew('Die Stimmung habe ich als … erlebt.'),
                    $bew('Einladung und Informationen vorher fand ich …'),
                    ['typ' => 'langtext', 'text' => 'Was möchtest du uns sonst noch mitgeben?'],
                ],
            ],
            'fahrt' => [
                'titel' => 'Feedback zur Fahrt / zum Lager',
                'einleitung' => '<p>Die Fahrt liegt hinter uns, und wir möchten wissen, wie es dir gefallen hat. Die Umfrage ist anonym – es gibt kein Richtig oder Falsch.</p>',
                'fragen' => [
                    $skala('Die Fahrt hat mir insgesamt sehr gut gefallen.'),
                    ['typ' => 'kurztext', 'text' => 'Was hat dir besonders gut gefallen und wird dir in Erinnerung bleiben?'],
                    $skala('Planung, Organisation und Durchführung waren sehr gut.'),
                    $skala('Ich habe mich in der Gruppe sehr wohl gefühlt.'),
                    $skala('Ich würde gerne wieder an einer solchen Fahrt teilnehmen.'),
                    ['typ' => 'langtext', 'text' => 'Was sollten wir beim nächsten Mal anders machen?'],
                ],
            ],
            'leitende' => [
                'titel' => 'Leiter*innenbefragung',
                'einleitung' => '<p>Wir möchten wissen, wie es euch Leiter*innen aktuell geht, was euch beschäftigt und wo ihr euch mehr Unterstützung wünscht.</p>',
                'fragen' => [
                    $skala('Ich fühle mich in meiner Rolle als Leiter*in sehr wohl.'),
                    $skala('Ich engagiere mich aktuell sehr gerne in der DPSG.'),
                    $skala('Die Stimmung in meiner Gruppierung ist aktuell sehr gut.'),
                    ['typ' => 'mehrfach', 'text' => 'Was sind aktuell die größten Herausforderungen in deinem Leiter*innen-Alltag?', 'hilfe' => 'Mehrfachnennungen möglich', 'kommentar' => true,
                        'optionen' => ['Zeitaufwand', 'Gewinnung neuer Leiter*innen', 'Leiter*innenrunden', 'Gruppenstunden', 'Elternarbeit', 'Finanzen', 'Bürokratie/Orga'], 'einstellungen' => ['sonstiges' => true]],
                    ['typ' => 'janein', 'text' => 'Weißt du, an wen du dich im Bezirk oder beim Diözesanverband wenden kannst, wenn du Unterstützung brauchst?', 'kommentar' => true],
                    $skala('Ich fühle mich sehr gut durch meinen Diözesanverband unterstützt.'),
                    ['typ' => 'mehrfach', 'text' => 'Bei welchen Themen würdest du dir mehr Unterstützung wünschen?', 'hilfe' => 'Mehrfachnennungen möglich', 'kommentar' => true,
                        'optionen' => ['Prävention', 'Herausfordernde Kinder und Jugendliche', 'Ausbildung', 'Finanzen/Zuschüsse', 'Rechtliches und Versicherungen', 'Leiter*innengewinnung', 'Beratung/Coaching']],
                ],
            ],
            'nachfrage' => [
                'titel' => 'Nachfrage an die Teilnehmenden',
                'einleitung' => '<p>Hallo, für die Planung brauchen wir noch ein paar Angaben von dir.</p>',
                'fragen' => [
                    ['typ' => 'einfach', 'text' => 'Wie reist du an?', 'pflicht' => true, 'optionen' => ['Eigene An- und Abreise', 'Mit dem Bus des Diözesanverbands']],
                    ['typ' => 'einfach', 'text' => 'Ab wo möchtest du mit dem Bus mitfahren?', 'hilfe' => 'Die genauen Sammelstellen geben wir noch bekannt.',
                        'optionen' => ['Saarbrücken/Saarlouis', 'Trier', 'Koblenz'], 'bedingung' => ['frage' => 0, 'werte' => ['Mit dem Bus des Diözesanverbands']]],
                    ['typ' => 'einfach', 'text' => 'Meine T-Shirt-Größe', 'optionen' => ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'Bitte kein T-Shirt']],
                    ['typ' => 'kurztext', 'text' => 'Welcher Name soll auf dein Namensschild?'],
                    ['typ' => 'langtext', 'text' => 'Hier hast du noch Platz für Fragen und Rückmeldungen.'],
                ],
            ],
        ];
    }

    /** Neue Umfrage aus einer mitgelieferten Vorlage. */
    public static function ausVorlage(string $key, ?int $userId): Umfrage
    {
        $vorlage = self::vorlagen()[$key] ?? throw new \InvalidArgumentException('Unbekannte Vorlage');
        $umfrage = Umfrage::query()->create([
            'titel' => $vorlage['titel'], 'einleitung' => $vorlage['einleitung'], 'status' => 'entwurf',
            'anonym' => $key !== 'nachfrage', 'erstellt_von' => $userId,
        ]);
        $schluessel = [];
        foreach ($vorlage['fragen'] as $i => $f) {
            $schluessel[$i] = (string) Str::uuid();
            $bedingung = isset($f['bedingung']) ? ['frage' => $schluessel[$f['bedingung']['frage']], 'werte' => $f['bedingung']['werte']] : null;
            $umfrage->fragen()->create([
                'schluessel' => $schluessel[$i], 'sort' => $i, 'typ' => $f['typ'], 'text' => $f['text'], 'hilfe' => $f['hilfe'] ?? null,
                'pflicht' => $f['pflicht'] ?? false, 'kommentar' => $f['kommentar'] ?? false, 'optionen' => $f['optionen'] ?? null,
                'einstellungen' => $f['einstellungen'] ?? null, 'bedingung' => $bedingung,
            ]);
        }

        return $umfrage;
    }
}
