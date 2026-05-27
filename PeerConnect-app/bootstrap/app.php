<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

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
        
        // --- THE ULTIMATE INTERCEPTOR RETURNS ---
        $exceptions->render(function (Throwable $e, Request $request) {
            echo "<h1 style='color:red; font-family:sans-serif;'>THE REAL ORIGINAL ERROR:</h1>";
            echo "<h2 style='font-family:sans-serif;'>" . get_class($e) . "</h2>";
            echo "<p style='font-family:sans-serif;'>" . $e->getMessage() . "</p>";
            echo "<p style='font-family:sans-serif;'><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
            die();
        });

    })->create();

// --- VERCEL SERVERLESS PATH OVERRIDES (Keep this!) ---
if (isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $app->useStoragePath('/tmp/storage');
    $app->useBootstrapCachePath('/tmp/bootstrap/cache');
    
    $directories = [
        '/tmp/storage/app',
        '/tmp/storage/logs',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/views',
        '/tmp/bootstrap/cache',
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

return $app;