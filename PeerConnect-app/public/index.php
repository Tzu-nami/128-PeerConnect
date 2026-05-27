<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

try {
    $app->handleRequest(Request::capture());
} catch (\Throwable $e) {
    echo "<h1 style='color:red; font-family:sans-serif;'>THE REAL ERROR:</h1>";
    echo "<h2 style='font-family:sans-serif;'>" . $e->getMessage() . "</h2>";
    echo "<p style='font-family:sans-serif;'><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border:1px solid #ccc; overflow-x:auto;'>" . $e->getTraceAsString() . "</pre>";
    die();
}
