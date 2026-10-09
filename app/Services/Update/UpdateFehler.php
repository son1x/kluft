<?php

namespace App\Services\Update;

use RuntimeException;

/** Fehler beim Prüfen oder Installieren eines Updates; die Meldung ist für Menschen. */
class UpdateFehler extends RuntimeException {}
