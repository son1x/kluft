<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Neue Installation ohne .env: zum Einrichtungsassistenten (docs/einrichtung.md).
if (! file_exists(__DIR__.'/../.env') && file_exists(__DIR__.'/einrichtung.php') && PHP_SAPI !== 'cli') {
    header('Location: /einrichtung.php', true, 302);
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
