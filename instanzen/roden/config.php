<?php

use App\Support\Einrichtung\StammVorlage;

/*
| DPSG Stamm Graf Folke Bernadotte, Saarlouis-Roden (www.dpsg-roden.de).
| Stammes-Website wie aus dem Einrichtungsassistenten, aber im Repository
| und per GitHub-Environment „roden“ deployt (docs/deploy.md). Die Angaben
| stehen in angaben.json; StammVorlage baut daraus die Konfiguration.
| Secrets (Datenbank, Mail, SSH) kommen nur aus dem Environment.
*/

return StammVorlage::config(
    (array) json_decode((string) file_get_contents(__DIR__.'/angaben.json'), true)
);
