<?php

/*
| Seiten für die Web-Checks (scripts/web-checks.mjs) aus der Konfiguration
| der Instanz (KLUFT_INSTANZ). Ausgabe: zwei Zeilen, kommagetrennt –
| axe-Seiten und Lighthouse-Seiten.
*/
if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}

$c = require dirname(__DIR__).'/config/dpsg.php';
$verzeichnis = '/'.$c['directory']['path'];
$stufe = '/stufe-woelflinge';

$seiten = ['/', '/aktuelles', '/veranstaltungen', '/kalender', $verzeichnis, $stufe, '/'.$c['site']['leitung_page'], '/kontakt', '/meine-anmeldungen'];
if (! empty($c['features']['georgsbote'])) {
    $seiten[] = '/georgsbote';
}
if ($sektion = array_key_first($c['sections'] ?? [])) {
    $seiten[] = '/'.$sektion;
}
$seiten[] = '/status';

echo implode(',', $seiten), PHP_EOL;
echo implode(',', ['/', '/aktuelles', '/veranstaltungen', $verzeichnis, $stufe]), PHP_EOL;
