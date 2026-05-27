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
        
        // --- KEEP THE INTERCEPTOR FOR NOW ---
        $exceptions->render(function (Throwable $e, Request $request) {
            echo "<h1 style='color:red; font-family:sans-serif;'>THE REAL ORIGINAL ERROR:</h1>";
            echo "<h2 style='font-family:sans-serif;'>" . get_class($e) . "</h2>";
            echo "<p style='font-family:sans-serif;'>" . $e->getMessage() . "</p>";
            echo "<p style='font-family:sans-serif;'><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
            die();
        });

    })->create();

// --- CREATE VIEW DIRECTORY (Safe for L11) ---
if (isset($_SERVER['VERCEL']) || isset($_ENV['VERCEL'])) {
    $viewPath = '/tmp/storage/framework/views';
    if (!is_dir($viewPath)) {
        mkdir($viewPath, 0755, true);
    }
}

return $app;