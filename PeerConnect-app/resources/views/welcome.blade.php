<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>{{ config('app.name', 'UPB LRC | PeerConnect') }}</title>

        {{-- Fonts --}}
        <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Marcellus&display=swap" rel="stylesheet">

        {{-- Icons --}}
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=arrow_forward_ios" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=arrow_forward,arrow_forward_ios" />

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
            <section class="relative min-h-screen flex flex-col justify-center px-20 bg-cover bg-top"
                     style="background-image: url('https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/lrc-session.png'); background-position: center 83px">

                {{-- Overlay --}}
                <div class="absolute inset-0 z-0"
                     style="background: linear-gradient(
                     100deg,
                     rgba(78,10,12,0.88) 0%,
                     rgba(78,10,12,0.75) 45%,
                     rgba(78,10,12,0.35) 75%,
                     rgba(78,10,12,0.15) 100%);">
                </div>

                {{-- Content --}}
                <div class="relative z-10 max-w-[800px]">
                    {{-- Title --}}
                    <h2 class="text-[33px] font-heading tracking-[0.15rem] uppercase text-up-yellow mb-6">
                        University of the Philippines Baguio
                    </h2>
                    <h1 class="font-heading text-8xl font-bold text-cream mb-2.5 tracking-wide">
                        Learning<br>Resource<br>Center
                    </h1>

                    {{-- Description --}}
                    <p class="font-light leading-[1.85] text-cream/70 max-w-[480px] mb-12">
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

                {{-- Scroll indicator --}}
                <div class="flex flex-col gap-2 items-center justify-center absolute z-20 left-1/2 -translate-x-1/2 bottom-10 cursor-pointer text-up-yellow">
                    <p class="text-[12px] tracking-[0.2rem] opacity-90">SCROLL</p>
                    <span class="material-symbols-outlined rotate-90 leading-none">arrow_forward_ios</span>
                </div>
            </section>

            {{-- What We Offer --}}
            <section class="h-auto px-52 py-20 bg-white">
                {{-- Header --}}
                <div class="flex flex-col gap-4 mb-12">
                    <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-medium uppercase">
                        <span class="block w-8 h-px bg-up-yellow"></span>
                        Our Services
                    </div>
                    <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider">
                        What We Offer
                    </h1>
                </div>

                {{-- Content --}}
                <div class="grid grid-cols-3">
                    {{-- Column 1 --}}
                    <div class="px-12 py-10 border-r border-cream-dark">
                        <div class="w-full h-48 bg-cream-dark rounded-sm mb-5"></div>
                        <div class="text-xl text-up-maroon font-medium mb-3">One-on-One Sessions</div>
                        <div class="text-base leading-7 font-light">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation
                            ullamco laboris nisi ut aliquip ex ea commodo consequat.
                        </div>
                    </div>

                    {{-- Column 2 --}}
                    <div class="px-12 py-10 border-r border-cream-dark">
                        <div class="w-full h-48 bg-cream-dark rounded-sm mb-5"></div>
                        <div class="text-xl text-up-maroon font-medium mb-3">Group Sessions</div>
                        <div class="text-base leading-7 font-light">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation
                            ullamco laboris nisi ut aliquip ex ea commodo consequat.
                        </div>
                    </div>

                    {{-- Column 3 --}}
                    <div class="px-12 py-10">
                        <div class="w-full h-48 bg-cream-dark rounded-sm mb-5"></div>
                        <div class="text-xl text-up-maroon font-medium mb-3">Review Classes</div>
                        <div class="text-base leading-7 font-light">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation
                            ullamco laboris nisi ut aliquip ex ea commodo consequat.
                        </div>
                    </div>
                </div>
            </section>

            {{-- How It Works --}}
            <section class="h-auto px-52 py-20 bg-up-green">
                {{-- Header --}}
                <div class="flex flex-col gap-4 mb-12 text-center">
                    <div class="flex items-center justify-center gap-3 text-up-yellow text-xs tracking-widest font-medium uppercase">
                        <span class="block w-8 h-px bg-up-yellow"></span>
                        How It Works
                        <span class="block w-8 h-px bg-up-yellow"></span>
                    </div>
                    <h2 class="font-heading text-cream text-5xl font-semibold tracking-wider">Three Simple Steps</h2>
                </div>

                {{-- Content --}}
                <div class="flex items-center justify-center gap-4 text-white">
                    <div class="flex-1 flex flex-col max-w-xs h-[500px] items-center px-10 py-12 border border-up-yellow/25">
                        <div class="font-heading text-6xl text-cream/10 mb-10 mt-5">01</div>
                        <div class="w-14 h-14 bg-up-yellow mb-5"></div>
                        <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 1 · Access</div>
                        <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-3">Login</div>
                        <span class="block w-8 h-px bg-up-yellow/40 mb-4"></span>
                        <div class="text-sm leading-7 font-light text-cream/60">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua.
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <span class="material-symbols-outlined text-up-yellow/40 text-3xl flex-shrink-0">arrow_forward</span>

                    {{-- Step 2 --}}
                    <div class="flex-1 flex flex-col max-w-xs h-[500px] items-center px-10 py-12 border border-up-yellow/25">
                        <div class="font-heading text-6xl text-cream/10 mb-10 mt-5">02</div>
                        <div class="w-14 h-14 bg-up-yellow mb-5"></div>
                        <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 2 · Schedule</div>
                        <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-3">Select</div>
                        <span class="block w-8 h-px bg-up-yellow/40 mb-4"></span>
                        <div class="text-sm leading-7 font-light text-cream/60">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua.
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <span class="material-symbols-outlined text-up-yellow/40 text-3xl flex-shrink-0">arrow_forward</span>

                    {{-- Step 3 --}}
                    <div class="flex-1 flex flex-col max-w-xs h-[500px] items-center px-10 py-12 border border-up-yellow/25">
                        <div class="font-heading text-6xl text-cream/10 mb-10 mt-5">03</div>
                        <div class="w-14 h-14 bg-up-yellow mb-5"></div>
                        <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 3 · Connect</div>
                        <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-3">Attend</div>
                        <span class="block w-8 h-px bg-up-yellow/40 mb-4"></span>
                        <div class="text-sm leading-7 font-light text-cream/60">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                            incididunt ut labore et dolore magna aliqua.
                        </div>
                    </div>
                </div>
            </section>

            {{-- Activities --}}
            <section class="h-auto px-52 py-20 bg-white1">
                {{-- Header --}}
                <div class="flex flex-col gap-4 mb-12">
                    <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-medium pb-5 border-b border-b-cream-dark">
                        <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider">
                            Activities
                        </h1>
                        <span class="block w-8 h-px bg-up-yellow"></span>
                    </div>
                </div>

                {{-- Image carousel --}}
                <div class="">
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                            <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/placeholder.jpg" alt="Image"></div>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            </section>

            {{-- Footer --}}
            <footer class="h-10 flex items-center justify-center bg-up-maroon-dark">
                <h2 class="font-serif text-base font-bold text-cream/75">Footer</h2>
            </footer>
        </main>
    </body>
</html>
