<?php

use App\Support\Einrichtung\StammVorlage;

/*
| Kluft-Instanz eines Stamms, angelegt mit dem Einrichtungsassistenten.
| Die Angaben des Stamms stehen in angaben.json daneben; daraus baut
| App\Support\Einrichtung\StammVorlage die ganze Konfiguration. Diese Datei
| bleibt bei Updates erhalten und wird nicht von Hand geändert.
*/

return StammVorlage::config(
    (array) json_decode((string) @file_get_contents(__DIR__.'/angaben.json'), true)
);
