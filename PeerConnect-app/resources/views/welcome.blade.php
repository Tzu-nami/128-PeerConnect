<x-layout.landing :noMargin="true">
        {{-- Hero --}}
        <section class="relative min-h-screen flex flex-col justify-center px-20 bg-cover bg-top animate-fade-up"
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
                <h1 class="font-heading text-8xl font-bold text-cream mb-4 tracking-wide">
                    Learning<br>Resource<br>Center
                </h1>

                {{-- Description --}}
                <p class="font-light leading-[1.85] text-cream/70 max-w-[480px] mb-10">
                    The UPB Learning Resource Center connects you with dedicated peer mentors ready to support your academic journey.
                    Whether you're keeping up, catching up, or getting ahead, our mentors are here to guide you every step of the way.
                </p>

                {{-- Book Now --}}
                @if($shouldShowBookNow)
                    <a href="{{ $bookUrl }}"
                       class="inline-block bg-transparent border border-up-yellow text-up-yellow-light
                               px-10 py-3.5 text-[13px] font-medium tracking-[0.12em] uppercase
                               no-underline transition-colors duration-200
                               hover:bg-up-yellow hover:text-up-maroon-dark">
                        Book Now
                    </a>
                @endif
            </div>

            {{-- Scroll indicator --}}
            <a href="#services" class="flex flex-col gap-2 items-center justify-center absolute z-20 left-1/2 -translate-x-1/2 bottom-7 cursor-pointer text-up-yellow">
                <p class=" tracking-[0.2rem] font-bold opacity-90 mb-2">SCROLL</p>
                <div class="animate-bounce">
                    <span class="material-symbols-outlined rotate-90 leading-none">arrow_forward_ios</span>
                </div>
            </a>
        </section>

        {{-- What We Offer --}}
        <section id="services" class="h-auto px-52 py-20 bg-white scroll-mt-20">
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
                <a href="{{ route('public.services') }}"
                   class="group flex flex-col px-12 py-10 border-r border-cream-dark transition-colors hover:bg-cream-dark/30">
                    <img
                        src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-7.jpeg"
                        alt="One on One Tutorial Session"
                        class="w-full h-48 object-cover rounded-sm mb-5 border border-cream-border">
                    <div class="text-xl text-up-maroon font-medium mb-2">One-on-One Sessions</div>
                    <div class="text-base leading-7 font-light mb-3">
                        Get personalized support from one of our experienced mentors.
                        Work through challenging concepts, review course materials, and build confidence at your own pace, all in a focused and supportive environment.
                    </div>
                    <div class="flex items-center gap-1 text-up-maroon opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
                        Read more
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </div>
                </a>

                {{-- Column 2 --}}
                <a href="{{ route('public.services') }}#group-session"
                   class="group flex flex-col px-12 py-10 border-r border-cream-dark transition-colors hover:bg-cream-dark/30">
                    <img
                        src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-8.jpg"
                        alt="Group Tutorial Session"
                        class="w-full h-48 object-cover rounded-sm mb-5 border border-cream-border">
                    <div class="text-xl text-up-maroon font-medium mb-2">Group Sessions</div>
                    <div class="text-base leading-7 font-light mb-3">
                        Gather with a group of friends in a guided session led by a peer mentor.
                        Ideal for tackling challenging subjects together, sharing different perspectives, and learning from one another.
                    </div>
                    <div class="flex items-center gap-1 text-up-maroon opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
                        Read more
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </div>
                </a>

                {{-- Column 3 --}}
                <a href="{{ route('public.services') }}#review-classes"
                   class="group flex flex-col px-12 py-10 border-r border-cream-dark transition-colors hover:bg-cream-dark/30">
                    <img
                        src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-6.jpg"
                        alt="Review Class"
                        class="w-full h-48 object-cover rounded-sm mb-5 border border-cream-border">
                    <div class="text-xl text-up-maroon font-medium mb-2">Review Classes</div>
                    <div class="text-base leading-7 font-light mb-3">
                        Prepare for major exams through review sessions led by experienced peer mentors.
                        Review key topics, tackle common problem areas, and build effective exam strategies.
                    </div>
                    <div class="flex items-center gap-1 text-up-maroon opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
                        Read more
                        <span class="material-symbols-outlined">arrow_right_alt</span>
                    </div>
                </a>
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

                <a href="{{ $dashboardUrl }}"
                   class="group flex-1 flex flex-col max-w-xs items-center px-10 py-12 border border-up-yellow/25 no-underline transition-all duration-300 hover:border-up-yellow hover:bg-white/5">
                    <span class="material-symbols-outlined text-5xl text-up-yellow/70 mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">login</span>
                    <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 1</div>
                    <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-4">Login</div>
                    <span class="block w-8 h-px bg-up-yellow/40 mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                    <div class="text-sm leading-7 font-light text-cream/60 text-center">
                        Sign in with your UP email to access the booking system, browse available mentors and check session schedules.
                    </div>
                </a>

                {{-- Arrow --}}
                <span class="material-symbols-outlined text-up-yellow/40 text-3xl flex-shrink-0">arrow_forward</span>

                {{-- Step 2 --}}
                <a href="{{ $bookUrl }}"
                   class="group flex-1 flex flex-col max-w-xs items-center px-10 py-12 border border-up-yellow/25 no-underline transition-all duration-300 hover:border-up-yellow hover:bg-white/5">
                    <span class="material-symbols-outlined text-5xl text-up-yellow/70 mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">schedule</span>
                    <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 2</div>
                    <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-4">Schedule</div>
                    <span class="block w-8 h-px bg-up-yellow/40 mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                    <div class="text-sm leading-7 font-light text-cream/60 text-center">
                        Choose a session type, select your preferred mentor and subject, and pick a date and time that works for you.
                    </div>
                </a>

                {{-- Arrow --}}
                <span class="material-symbols-outlined text-up-yellow/40 text-3xl flex-shrink-0">arrow_forward</span>

                {{-- Step 3 --}}
                <div class="group flex-1 flex flex-col max-w-xs items-center px-10 py-12 border border-up-yellow/25 no-underline transition-all duration-300 hover:border-up-yellow hover:bg-white/5">
                    <span class="material-symbols-outlined text-5xl text-up-yellow/70 mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">person_raised_hand</span>
                    <div class="text-xs text-up-yellow tracking-[0.2em] font-semibold uppercase mb-2">Step 3</div>
                    <div class="font-heading text-2xl text-cream font-medium tracking-wider mb-4">Attend</div>
                    <span class="block w-8 h-px bg-up-yellow/40 mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                    <div class="text-sm leading-7 font-light text-cream/60 text-center">
                        Attend your scheduled session and make the most of your time. Engage, ask questions, and learn actively.
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
            <div class="swiper-outer">
                <button class="swiper-nav-btn" id="btn-prev">&#8249;</button>
                <div class="swiper" id="activities-swiper">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-1.jpg" alt="Activity 1"></div>
                        <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-2.jpg" alt="Activity 2"></div>
                        <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-3.jpg" alt="Activity 3"></div>
                        <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-4.jpg" alt="Activity 4"></div>
                        <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-5.jpg" alt="Activity 5"></div>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
                <button class="swiper-nav-btn" id="btn-next">&#8250;</button>
            </div>
        </section>
</x-layout.landing>
