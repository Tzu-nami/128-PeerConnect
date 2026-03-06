<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>{{ config('app.name'), 'UPB LRC | PeerConnect' }}</title>

        {{-- Fonts --}}
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

        {{-- Styles --}}
        @vite(['resources/js/app.js', 'resources/css/app.css'])
    </head>
    <body>
        <header>
            @if(Route::has('login'))
                <livewire:welcome.navigation/>
            @endif
        </header>

        <main>
            {{-- Hero --}}
            <section class="relative min-h-screen flex flex-col justify-center px-20 pt-[83px] bg-up-maroon">
                <div class="max-w-[700px]">
                    {{-- Title --}}
                    <p class="text-[12px] font-medium tracking-[0.15rem] uppercase text-up-yellow mb-6">
                        University of the Philippines Baguio
                    </p>

                    <h1 class="font-serif text-8xl font-bold text-cream mb-2.5 tracking-wide">
                        Learning<br>Resource<br>Center
                    </h1>

                    <h2 class="font-serif text-4xl font-normal text-up-yellow-light mb-8 tracking-[0.06em]">
                        PeerConnect
                    </h2>

                    {{-- Description --}}
                    <p class="font-sans font-light leading-[1.85] text-cream/70 max-w-[480px] mb-12">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                        Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                    </p>

                    {{-- Book Now --}}
                    <a href="#"
                       class="inline-block bg-transparent border border-up-yellow text-up-yellow-light
                       px-10 py-3.5 text-[13px] font-medium tracking-[0.12em] uppercase
                       no-underline transition-colors duration-200
                       hover:bg-up-yellow hover:text-up-maroon-dark">
                        Book Now
                    </a>
                </div>
            </section>

            {{-- What We Offer --}}
            <section class="h-screen flex items-center justify-center bg-white">
                <h2 class="font-serif text-5xl font-bold text-up-maroon-dark">What We Offer</h2>
            </section>

            {{-- How It Works --}}
            <section class="h-screen flex items-center justify-center bg-up-green">
                <h2 class="font-serif text-5xl font-bold text-cream">How It Works</h2>
            </section>

            {{-- Activities --}}
            <section class="h-screen flex items-center justify-center bg-cream">
                <h2 class="font-serif text-5xl font-bold text-up-maroon-dark">Activities</h2>
            </section>

            {{-- Footer --}}
            <footer class="h-10 flex items-center justify-center bg-up-maroon-dark">
                <h2 class="font-serif text-base font-bold text-cream/75">Footer</h2>
            </footer>
        </main>
    </body>
</html>
