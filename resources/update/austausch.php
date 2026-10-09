<?php

/*
| Kluft-Update: Dateien der Installation gegen ein entpacktes Release
| tauschen – oder einen Tausch rückgängig machen. Bewusst ohne Laravel und
| ohne Autoloader: Während des Tauschs wechseln vendor/ und app/, das
| laufende Programm darf davon nichts nachladen.
|
| Aufruf:  php austausch.php tauschen   <installation> <release> <rücksicherung>
|          php austausch.php zurueck    <installation> <rücksicherung>
|
| Getauscht wird je Eintrag der obersten Ebene des Releases per rename()
| (schnell, im selben Dateisystem atomar). In database/, public/ und
| instanzen/ wird eine Ebene tiefer getauscht, damit Daten der Installation
| (SQLite-Datei, Uploads, eigene Instanz) liegen bleiben. Nie angefasst:
| .env, storage/ und die Pfade in BEHALTEN. Jeder Schritt steht in
| <rücksicherung>/journal.json; schlägt etwas fehl, wird zurückgetauscht.
*/

const BEHALTEN = ['.env', 'storage', 'database/database.sqlite', 'public/uploads', 'public/instanz'];
const EINE_EBENE_TIEFER = ['database', 'public', 'instanzen'];

function ausgabe(bool $ok, string $text, array $extra = []): never
{
    echo json_encode(['ok' => $ok, 'text' => $text] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit($ok ? 0 : 1);
}

function verschieben(string $von, string $nach): void
{
    if (! is_dir(dirname($nach)) && ! mkdir(dirname($nach), 0775, true) && ! is_dir(dirname($nach))) {
        throw new RuntimeException('Ordner nicht anlegbar: '.dirname($nach));
    }
    if (! @rename($von, $nach)) {
        throw new RuntimeException('Verschieben fehlgeschlagen: '.$von.' → '.$nach);
    }
}

function loeschen(string $pfad): void
{
    if (is_link($pfad) || is_file($pfad)) {
        @unlink($pfad);

        return;
    }
    if (! is_dir($pfad)) {
        return;
    }
    foreach (scandir($pfad) ?: [] as $eintrag) {
        if ($eintrag !== '.' && $eintrag !== '..') {
            loeschen($pfad.'/'.$eintrag);
        }
    }
    @rmdir($pfad);
}

/** @return list<string> relative Pfade, die getauscht werden */
function tauschliste(string $release): array
{
    $liste = [];
    foreach (scandir($release) ?: [] as $eintrag) {
        if ($eintrag === '.' || $eintrag === '..') {
            continue;
        }
        if (in_array($eintrag, EINE_EBENE_TIEFER, true) && is_dir($release.'/'.$eintrag)) {
            foreach (scandir($release.'/'.$eintrag) ?: [] as $kind) {
                if ($kind !== '.' && $kind !== '..') {
                    $liste[] = $eintrag.'/'.$kind;
                }
            }

            continue;
        }
        $liste[] = $eintrag;
    }

    return array_values(array_filter($liste, fn (string $pfad) => ! in_array($pfad, BEHALTEN, true)));
}

/** @param list<array{pfad: string, alt: bool}> $journal */
function zuruecktauschen(string $installation, string $rueck, array $journal): void
{
    foreach (array_reverse($journal) as $schritt) {
        $live = $installation.'/'.$schritt['pfad'];
        if ($schritt['neu'] ?? false) {
            loeschen($live);
        }
        if ($schritt['alt'] && file_exists($rueck.'/alt/'.$schritt['pfad'])) {
            verschieben($rueck.'/alt/'.$schritt['pfad'], $live);
        }
    }
}

$aktion = $argv[1] ?? '';
try {
    if ($aktion === 'tauschen' && count($argv) === 5) {
        [, , $installation, $release, $rueck] = $argv;
        foreach ([$installation, $release] as $ordner) {
            if (! is_dir($ordner)) {
                ausgabe(false, 'Ordner fehlt: '.$ordner);
            }
        }
        if (! is_file($release.'/artisan') || ! is_file($release.'/vendor/autoload.php')) {
            ausgabe(false, 'Das Release ist unvollständig (artisan oder vendor fehlt).');
        }
        if (file_exists($rueck)) {
            loeschen($rueck);
        }
        mkdir($rueck.'/alt', 0775, true);

        $journal = [];
        try {
            foreach (tauschliste($release) as $pfad) {
                $live = $installation.'/'.$pfad;
                $alt = file_exists($live) || is_link($live);
                if ($alt) {
                    verschieben($live, $rueck.'/alt/'.$pfad);
                }
                $journal[] = ['pfad' => $pfad, 'alt' => $alt, 'neu' => false];
                file_put_contents($rueck.'/journal.json', json_encode($journal));
                verschieben($release.'/'.$pfad, $live);
                $journal[array_key_last($journal)]['neu'] = true;
                file_put_contents($rueck.'/journal.json', json_encode($journal));
            }
        } catch (Throwable $e) {
            zuruecktauschen($installation, $rueck, $journal);
            ausgabe(false, 'Tausch abgebrochen und zurückgenommen: '.$e->getMessage());
        }
        ausgabe(true, count($journal).' Einträge getauscht.', ['anzahl' => count($journal)]);
    }

    if ($aktion === 'zurueck' && count($argv) === 4) {
        [, , $installation, $rueck] = $argv;
        $journal = json_decode((string) @file_get_contents($rueck.'/journal.json'), true);
        if (! is_array($journal)) {
            ausgabe(false, 'Keine Rücksicherung gefunden: '.$rueck);
        }
        zuruecktauschen($installation, $rueck, $journal);
        ausgabe(true, 'Vorherige Version wiederhergestellt.');
    }

    ausgabe(false, 'Aufruf: php austausch.php tauschen <installation> <release> <rücksicherung> | zurueck <installation> <rücksicherung>');
} catch (Throwable $e) {
    ausgabe(false, $e->getMessage());
}
