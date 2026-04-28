<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'UPB LRC - PeerConnect') }}</title>

    {{-- Fonts and Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    {{-- Scripts --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased flex flex-col min-h-screen">
    <header>
        <livewire:welcome.navigation/>
    </header>

    <main class="flex-1 {{ isset($noMargin) ? '' : 'mt-[60px] md:mt-[83px]' }}">
        {{ $slot }}
    </main>

    <footer>
        <livewire:welcome.footer/>
    </footer>
</body>
</html>
