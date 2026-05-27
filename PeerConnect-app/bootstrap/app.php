<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class,]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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
