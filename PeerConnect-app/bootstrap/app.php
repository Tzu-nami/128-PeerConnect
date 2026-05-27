<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // --- THE ULTIMATE INTERCEPTOR ---
        $exceptions->render(function (Throwable $e, Request $request) {
            echo "<h1 style='color:red; font-family:sans-serif;'>THE REAL ORIGINAL ERROR:</h1>";
            echo "<h2 style='font-family:sans-serif;'>" . get_class($e) . "</h2>";
            echo "<p style='font-family:sans-serif;'>" . $e->getMessage() . "</p>";
            echo "<p style='font-family:sans-serif;'><strong>File:</strong> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
            die();
        });

    })->create();