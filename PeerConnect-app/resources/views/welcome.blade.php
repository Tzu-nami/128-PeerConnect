<x-layouts.landing :noMargin="true">
    {{-- Hero section --}}
    <section class="relative w-full min-h-[520px] sm:min-h-[600px] xl:h-screen flex flex-col justify-between overflow-hidden animate-fade-up"
             style="background-image: url('https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/library.jpeg');
         background-position: center 30%; background-size: cover;">

        <div class="pt-[60px] md:pt-[83px]"></div>

        {{-- Overlay --}}
        <div class="absolute inset-0 z-0"
             style="background: linear-gradient(
         110deg,
         rgba(78,10,12,0.92) 0%,
         rgba(78,10,12,0.80) 40%,
         rgba(78,10,12,0.45) 70%,
         rgba(78,10,12,0.15) 100%);">
        </div>

        {{-- Content --}}
        <div class="relative z-10 flex flex-col justify-center flex-1
                px-5 sm:px-10 md:px-12 lg:px-14 xl:px-20
                pt-4 sm:pt-8 md:pt-10
                pb-4 sm:pb-8 md:pb-10">

            <h2 class="text-[10px] sm:text-[13px] md:text-[14px] lg:text-[18px] xl:text-[22px]
                   font-heading tracking-[0.12rem] sm:tracking-[0.15rem] uppercase text-up-yellow
                   mb-2 md:mb-4 lg:mb-6 leading-snug">
                University of the Philippines Baguio
            </h2>

            <h1 class="font-heading font-bold text-cream tracking-wide
                   text-[1.75rem] sm:text-4xl md:text-5xl lg:text-6xl xl:text-8xl
                   leading-[1.05] mb-3 sm:mb-4 md:mb-6">
                Learning<span class="hidden sm:inline"><br></span>
                Resource<br>
                Center
            </h1>

            <p class="font-light leading-relaxed sm:leading-loose text-cream/70
                  text-[11px] sm:text-sm md:text-sm lg:text-base xl:text-lg
                  max-w-[95%] sm:max-w-[380px] md:max-w-[440px] lg:max-w-[500px]
                  mb-4 sm:mb-6 md:mb-8">
                The UPB Learning Resource Center connects you with dedicated peer mentors ready to support your academic journey.
                Whether you're keeping up, catching up, or getting ahead, our mentors are here to guide you every step of the way.
            </p>

            @if($shouldShowBookNow)
                <div>
                    <a href="{{ $bookUrl }}"
                       class="inline-block bg-transparent border border-up-yellow text-up-yellow-light
                          px-5 sm:px-8 md:px-10 py-2 sm:py-3
                          text-[10px] sm:text-xs md:text-[13px]
                          font-medium tracking-[0.12em] uppercase
                          no-underline transition-colors duration-200
                          hover:bg-up-yellow hover:text-up-maroon-dark
                          active:bg-up-yellow active:text-up-maroon-dark">
                        Book Now
                    </a>
                </div>
            @endif
        </div>

        {{-- Scroll indicator --}}
        <a href="#services"
           class="hidden md:flex flex-col gap-1 items-center text-center justify-center z-20
              pb-4 cursor-pointer text-up-yellow self-center">
            <p class="tracking-[0.2rem] text-xs md:text-sm xl:text-lg font-bold opacity-90 mb-1">SCROLL</p>
            <div class="animate-bounce">
                <span class="material-symbols-outlined rotate-90 leading-none text-base md:text-lg xl:text-2xl">arrow_forward_ios</span>
            </div>
        </a>
    </section>

    {{-- What We Offer --}}
    <section id="services" class="w-full px-4 sm:px-10 md:px-12 lg:px-20 xl:px-32 py-6 sm:py-12 md:py-16 xl:py-24 bg-white scroll-mt-20">
        {{-- Header --}}
        <div class="flex flex-col gap-1 sm:gap-2 md:gap-4 mb-4 sm:mb-6 md:mb-8">
            <div class="flex items-center gap-2 md:gap-3 text-up-yellow text-[9px] sm:text-[10px] md:text-xs tracking-widest font-medium uppercase">
                <span class="block w-6 md:w-8 h-px bg-up-yellow"></span>
                Our Services
            </div>
            <h1 class="font-heading text-up-maroon text-xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold tracking-wider">
                What We Offer
            </h1>
        </div>

        {{-- Content --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-0">
            {{-- Column 1 --}}
            <a href="{{ route('public.services') }}"
               class="group flex flex-col px-4 sm:px-8 md:px-10 lg:px-12 py-6 sm:py-10
                      border-b sm:border-b-0 sm:border-r border-cream-dark
                      transition-colors hover:bg-cream-dark/30">
                <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/services/one-on-one.jpeg"
                     alt="One on One Tutorial Session"
                     class="w-full h-36 sm:h-44 md:h-48 object-cover rounded-sm mb-4 sm:mb-5 border border-cream-border">
                <div class="text-sm sm:text-base md:text-[15px] lg:text-[18px] xl:text-[22px] text-up-maroon font-medium mb-1 md:mb-2">One-on-One Sessions</div>
                <div class="text-[11px] sm:text-sm lg:text-base leading-6 sm:leading-7 font-light mb-1 md:mb-3">
                    Get personalized support from one of our experienced mentors.
                    Work through challenging concepts, review course materials, and build confidence at your own pace.
                </div>
                <div class="flex items-center gap-1 text-up-maroon text-sm">
                    Read more
                    <span class="hidden lg:flex opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200 material-symbols-outlined">arrow_right_alt</span>
                </div>
            </a>

            {{-- Column 2 --}}
            <a href="{{ route('public.services') }}#group-session"
               class="group flex flex-col px-4 sm:px-8 md:px-10 lg:px-12 py-6 sm:py-10
                      border-b sm:border-b-0 lg:border-r border-cream-dark
                      transition-colors hover:bg-cream-dark/30">
                <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/services/group-session.jpg"
                     alt="Group Tutorial Session"
                     class="w-full h-36 sm:h-44 md:h-48 object-cover rounded-sm mb-4 sm:mb-5 border border-cream-border">
                <div class="text-sm sm:text-base md:text-[15px] lg:text-[18px] xl:text-[22px] text-up-maroon font-medium mb-1 md:mb-2">Group Sessions</div>
                <div class="text-[11px] sm:text-sm lg:text-base leading-6 sm:leading-7 font-light mb-1 md:mb-3">
                    Gather with a group of friends in a guided session led by a peer mentor.
                    Ideal for tackling challenging subjects together and learning from one another.
                </div>
                <div class="flex items-center gap-1 text-up-maroon text-sm">
                    Read more
                    <span class="hidden lg:flex opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200 material-symbols-outlined">arrow_right_alt</span>
                </div>
            </a>

            {{-- Column 3 --}}
            <a href="{{ route('public.services') }}#review-classes"
               class="group flex flex-col px-4 sm:px-8 md:px-10 lg:px-12 py-6 sm:py-10
                      transition-colors hover:bg-cream-dark/30">
                <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/services/review-classes.jpg"
                     alt="Review Class"
                     class="w-full h-36 sm:h-44 md:h-48 object-cover rounded-sm mb-4 sm:mb-5 border border-cream-border">
                <div class="text-sm sm:text-base md:text-[15px] lg:text-[18px] xl:text-[22px] text-up-maroon font-medium mb-1 md:mb-2">Review Classes</div>
                <div class="text-[11px] sm:text-sm lg:text-base leading-6 sm:leading-7 font-light mb-1 md:mb-3">
                    Prepare for major exams through review sessions led by experienced peer mentors.
                    Review key topics and build effective exam strategies.
                </div>
                <div class="flex items-center gap-1 text-up-maroon text-sm">
                    Read more
                    <span class="hidden lg:flex opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200 material-symbols-outlined">arrow_right_alt</span>
                </div>
            </a>
        </div>
    </section>

    {{-- How It Works --}}
    <section class="w-full px-5 sm:px-10 md:px-16 lg:px-28 xl:px-52 py-10 sm:py-16 md:py-20 bg-up-green">
        {{-- Header --}}
        <div class="flex flex-col gap-2 sm:gap-4 mb-8 sm:mb-12 text-center">
            <div class="flex items-center justify-center gap-3 text-up-yellow text-[9px] sm:text-[10px] md:text-xs tracking-widest font-medium uppercase">
                <span class="block w-6 md:w-8 h-px bg-up-yellow"></span>
                How It Works
                <span class="block w-6 md:w-8 h-px bg-up-yellow"></span>
            </div>
            <h2 class="font-heading text-cream text-xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold tracking-wider">
                Three Simple Steps
            </h2>
        </div>

        {{-- Steps --}}
        <div class="flex flex-col sm:flex-row items-stretch justify-center gap-4 sm:gap-4 md:gap-6 text-white">

            {{-- Step 1 --}}
            <a href="{{ $dashboardUrl }}"
               class="group w-full sm:flex-1 max-w-sm mx-auto flex flex-col items-center
                      px-5 sm:px-6 md:px-8 lg:px-10 py-7 sm:py-12
                      border border-up-yellow/25 no-underline transition-all duration-300
                      hover:border-up-yellow hover:bg-white/5">
                <span class="material-symbols-outlined text-3xl sm:text-5xl text-up-yellow/70 mb-4 sm:mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">login</span>
                <div class="text-[9px] sm:text-xs md:text-sm text-up-yellow tracking-[0.15em] sm:tracking-[0.2em] font-semibold uppercase mb-2">Step 1</div>
                <div class="font-heading text-lg sm:text-xl md:text-2xl text-cream font-medium tracking-wider mb-3 sm:mb-4">Login</div>
                <span class="block w-8 h-px bg-up-yellow/40 mb-3 sm:mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                <div class="text-[11px] sm:text-sm md:text-base leading-6 sm:leading-7 font-light text-cream/60 text-center">
                    Sign in with your UP email to access the booking system, browse available mentors and check session schedules.
                </div>
            </a>

            {{-- Arrow --}}
            <span class="material-symbols-outlined text-up-yellow/40 text-2xl sm:text-3xl flex-shrink-0 rotate-90 sm:rotate-0 self-center">arrow_forward</span>

            {{-- Step 2 --}}
            <a href="{{ $bookUrl }}"
               class="group w-full sm:flex-1 max-w-sm mx-auto flex flex-col items-center
                      px-5 sm:px-6 md:px-8 lg:px-10 py-7 sm:py-12
                      border border-up-yellow/25 no-underline transition-all duration-300
                      hover:border-up-yellow hover:bg-white/5">
                <span class="material-symbols-outlined text-3xl sm:text-5xl text-up-yellow/70 mb-4 sm:mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">schedule</span>
                <div class="text-[9px] sm:text-xs md:text-sm text-up-yellow tracking-[0.15em] sm:tracking-[0.2em] font-semibold uppercase mb-2">Step 2</div>
                <div class="font-heading text-lg sm:text-xl md:text-2xl text-cream font-medium tracking-wider mb-3 sm:mb-4">Schedule</div>
                <span class="block w-8 h-px bg-up-yellow/40 mb-3 sm:mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                <div class="text-[11px] sm:text-sm md:text-base leading-6 sm:leading-7 font-light text-cream/60 text-center">
                    Choose a session type, select your preferred mentor and subject, and pick a date and time that works for you.
                </div>
            </a>

            {{-- Arrow --}}
            <span class="material-symbols-outlined text-up-yellow/40 text-2xl sm:text-3xl flex-shrink-0 rotate-90 sm:rotate-0 self-center">arrow_forward</span>

            {{-- Step 3 --}}
            <div class="group w-full sm:flex-1 max-w-sm mx-auto flex flex-col items-center
                        px-5 sm:px-6 md:px-8 lg:px-10 py-7 sm:py-12
                        border border-up-yellow/25 transition-all duration-300
                        hover:border-up-yellow hover:bg-white/5">
                <span class="material-symbols-outlined text-3xl sm:text-5xl text-up-yellow/70 mb-4 sm:mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:text-up-yellow">person_raised_hand</span>
                <div class="text-[9px] sm:text-xs md:text-sm text-up-yellow tracking-[0.15em] sm:tracking-[0.2em] font-semibold uppercase mb-2">Step 3</div>
                <div class="font-heading text-lg sm:text-xl md:text-2xl text-cream font-medium tracking-wider mb-3 sm:mb-4">Attend</div>
                <span class="block w-8 h-px bg-up-yellow/40 mb-3 sm:mb-4 transition-all duration-300 group-hover:w-12 group-hover:bg-up-yellow/60"></span>
                <div class="text-[11px] sm:text-sm md:text-base leading-6 sm:leading-7 font-light text-cream/60 text-center">
                    Attend your scheduled session and make the most of your time. Engage, ask questions, and learn actively.
                </div>
            </div>

        </div>
    </section>

    {{-- Activities --}}
    <section class="w-full px-4 sm:px-10 md:px-16 lg:px-28 xl:px-52 py-10 sm:py-16 md:py-20 bg-white1">
        {{-- Header --}}
        <div class="flex flex-col gap-4 mb-8 sm:mb-12">
            <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-medium pb-4 sm:pb-5 border-b border-b-cream-dark">
                <h1 class="font-heading text-up-maroon text-2xl sm:text-4xl md:text-5xl font-semibold tracking-wider">
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
                    <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/carousel/Image-1.jpg" alt="Activity 1"></div>
                    <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/carousel/Image-2.jpg" alt="Activity 2"></div>
                    <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/carousel/Image-3.jpg" alt="Activity 3"></div>
                    <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/carousel/Image-4.jpg" alt="Activity 4"></div>
                    <div class="swiper-slide"><img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/carousel/Image-5.jpg" alt="Activity 5"></div>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <button class="swiper-nav-btn" id="btn-next">&#8250;</button>
        </div>
    </section>
</x-layouts.landing>
