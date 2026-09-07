<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Shared-hosting layout: the project root (not public/) is inside the web root and a
// root .htaccess rewrites every request into public/. PHP then reports the script as
// ".../public/index.php" while the browser asked for a URL without "/public/", so the
// framework cannot find the common prefix and mis-detects the base URL — fatal when the
// app lives in a subfolder such as https://host/pathwaytt. Present the script as living
// in the project root so the base URL resolves to "/pathwaytt" (or "" at a domain root).
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($scriptName, '/public/index.php')) {
    $publicDir = substr($scriptName, 0, -strlen('index.php'));   // e.g. "/pathwaytt/public/"
    $requestPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

    if (! str_starts_with($requestPath, $publicDir)) {
        $_SERVER['SCRIPT_NAME'] = substr($scriptName, 0, -strlen('/public/index.php')).'/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    }
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
