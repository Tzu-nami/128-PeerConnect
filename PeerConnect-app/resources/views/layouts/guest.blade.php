<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'LRC PeerConnect') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="icon" href="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/LRC_logo.png">
        <link rel="apple-touch-icon" href="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/LRC_logo.png">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Custom Scrollbar for a cleaner look */
            ::-webkit-scrollbar { width: 5px; }
            ::-webkit-scrollbar-thumb { background: #7B1113; border-radius: 10px; }
            
            /* Background pattern for the maroon side */
            .bg-pattern {
                background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px);
                background-size: 30px 30px;
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col md:flex-row w-full overflow-hidden">
            
            <div class="hidden md:flex w-1/2 bg-up-maroon relative flex-col justify-between p-12 lg:p-16 overflow-hidden">
                <div class="absolute inset-0 bg-pattern opacity-20 pointer-events-none"></div>
                
                <div class="relative z-10 flex flex-col items-center text-center animate-fade-in-down">
                    <x-application-logo class="w-20 h-20 mb-8 drop-shadow-xl transition-transform hover:scale-110 duration-500"/>
                    
                    <h1 class="text-4xl lg:text-5xl font-extrabold text-white tracking-tight leading-tight">
                        LRC <span class="text-yellow-400">PeerConnect</span>
                    </h1>
                    
                    <div class="h-1.5 w-16 bg-yellow-500 my-6 rounded-full"></div>
                </div>

                <div class="relative z-10 mt-12 group perspective-1000">
                    <div class="absolute -inset-2 bg-gradient-to-r from-yellow-500/30 to-white/10 rounded-2xl blur-xl opacity-0 group-hover:opacity-100 transition duration-700"></div>
                    
                    <div class="relative bg-white/5 backdrop-blur-sm p-3 rounded-2xl border border-white/20 shadow-2xl transform transition-all duration-700 group-hover:rotate-1 group-hover:scale-[1.03]">
                        <img 
                            src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/FB_IMG_1772857583193.jpg" 
                            alt="Student Peer Session" 
                            class="rounded-xl w-full h-80 object-cover grayscale-[20%] group-hover:grayscale-0 transition duration-700 shadow-inner"
                        >
                        
                    </div>
                </div>

                <div class="relative z-10 pt-10 border-t border-white/10 flex justify-between items-center text-white/50 text-[10px] uppercase tracking-widest font-bold">
                    <span>University of the Philippines Baguio</span>
                    <span class="h-1 w-1 bg-white/30 rounded-full"></span>
                    <span>Learning Resource Center</span>
                </div>
            </div>

            <div class="w-full md:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white relative">
                <div class="absolute top-8 left-0 right-0 flex flex-col items-center md:hidden">
                     <x-application-logo class="w-16 h-16 fill-current text-up-maroon mb-2"/>
                     <h2 class="text-xl font-bold text-up-maroon">LRC PeerConnect</h2>
                </div>

                <div class="w-full max-w-md">
                    <div class="transition-all duration-500">
                        {{ $slot }}
                    </div>
                </div>
            </div>

        </div>
    </body>
</html>
