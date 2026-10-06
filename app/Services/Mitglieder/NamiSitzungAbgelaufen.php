<?php

namespace App\Services\Mitglieder;

/**
 * Persönliche NaMi-Sitzung ist abgelaufen; ohne gespeichertes Passwort kann
 * sich das CMS nicht selbst neu anmelden.
 */
class NamiSitzungAbgelaufen extends MitgliederverzeichnisException {}
