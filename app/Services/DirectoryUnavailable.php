<?php

namespace App\Services;

use RuntimeException;

/**
 * Verzeichnissuche gerade nicht möglich (nicht verbunden, abgelaufen,
 * Berechtigung fehlt oder Microsoft nicht erreichbar).
 */
class DirectoryUnavailable extends RuntimeException {}
