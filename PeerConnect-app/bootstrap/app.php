<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();

// --- CREATE VERCEL VIEW DIRECTORY ---
if (isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $viewPath = '/tmp/storage/framework/views';
    if (!is_dir($viewPath)) {
        mkdir($viewPath, 0755, true);
    }
}

return $app;