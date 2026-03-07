<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-row w-full">
            <div class="w-1/2 bg-up-maroon flex flex-col items-center justify-center text-center p-10">
                <x-application-logo class="w-32 h-32 mx-auto fill-current text-white mb-6"/>
                <h1 class="text-4xl text-white font-bold ">LRC PeerConnect</h1>
                <p class="text-lg text-white">Book an enrichment session now!</p>
            <div>
                <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/FB_IMG_1772857583193.jpg" alt="Placeholder 1" class="w-full h-auto object-cover">
            </div>
            </div>
            <div class="w-1/2 flex items-center justify-center px-6 py-12">
                <div class="w-full max-w-md bg-white px-8 py-10 shadow-lg sm:rounded-xl">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
