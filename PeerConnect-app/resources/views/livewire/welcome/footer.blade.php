<div class="text-cream/60 bg-up-maroon-dark px-4 sm:px-12 lg:px-20 py-8 mt-auto">
    {{-- Main row --}}
    <div class="flex flex-col lg:flex-row justify-between mb-7 gap-8">
        {{-- Left Side --}}
        <div class="flex flex-col gap-3 max-w-sm">
            <a href="#" class="font-heading font-bold text-lg sm:text-xl text-cream tracking-widest">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                Peer<span class="text-up-yellow">Connect</span>
            </a>
            <div class="text-xs sm:text-sm leading-relaxed">
                Connecting UPB students with peer mentors for enrichment sessions and academic success.
            </div>
            <div class="flex items-center gap-3">
                <a href="https://www.facebook.com/lrc.upbaguio"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-facebook text-xs sm:text-sm"></i>
                </a>
                <a href="https://x.com/lrc_upbaguio"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-twitter text-xs sm:text-sm"></i>
                </a>
                <a href="https://mainlib.upb.edu.ph/" target="_blank" class="flex items-center justify-center w-7 h-7 sm:w-8 sm:h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <span class="material-symbols-outlined text-xs sm:text-sm">captive_portal</span>
                </a>
            </div>
        </div>

        {{-- Right Side --}}
        <div class="flex flex-wrap gap-8 text-xs sm:text-sm">
            {{-- Navigate --}}
            <div class="flex flex-col gap-2 sm:gap-3">
                <div class="font-bold text-up-yellow tracking-widest uppercase mb-1">Navigate</div>
                <a href="{{ request()->is('/') ? '#' : url('/') }}" class="hover:text-cream transition-colors">Home</a>
                <a href="{{ request()->is('mentors') ? '#' : route('public.mentors') }}" class="hover:text-cream transition-colors">Mentors</a>
                <a href="{{ request()->is('staff') ? '#' : route('public.staff') }}" class="hover:text-cream transition-colors">Staff</a>
                <a href="{{ request()->is('services') ? '#' : route('public.services') }}" class="hover:text-cream transition-colors">Services</a>
                <a href="{{ request()->is('about') ? '#' : route('public.about') }}" class="hover:text-cream transition-colors">About Us</a>
            </div>

            {{-- Quick Actions --}}
            <div class="flex flex-col gap-2 sm:gap-3">
                <div class="font-bold text-up-yellow tracking-widest uppercase mb-1">Quick Actions</div>
                @auth
                    @if($shouldShowBookNow)
                        <a href="{{ $bookUrl }}" class="hover:text-cream transition-colors">Book a Session</a>
                        <a href="{{ $historyUrl }}" class="hover:text-cream transition-colors">View Bookings</a>
                    @endif
                    <a href="{{ $dashboardUrl }}" class="hover:text-cream transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('auth.google') }}" class="hover:text-cream transition-colors">Log In</a>
                @endauth
            </div>

            {{-- Contact --}}
            <div class="flex flex-col gap-2 sm:gap-3">
                <div class="font-bold text-up-yellow tracking-widest uppercase mb-1">Contact</div>
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-location-dot mt-0.5 opacity-50 text-xs"></i>
                    <span>2nd Floor, University Library, UPB</span>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fa-regular fa-clock mt-0.5 opacity-50 text-xs"></i>
                    <span>Mon–Fri, 8:00 AM – 5:00 PM</span>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fa-regular fa-envelope mt-0.5 opacity-50 text-xs"></i>
                    <a href="https://mail.google.com/mail/?view=cm&fs=1&to=lrc.upbaguio@up.edu.ph"
                       target="_blank" rel="noopener noreferrer"
                       class="hover:text-cream transition-colors">lrc.upbaguio@up.edu.ph</a>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-phone mt-0.5 opacity-50 text-xs"></i>
                    <span>(074) 444 8720</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom row --}}
    <div class="flex flex-col sm:flex-row justify-between border-t border-cream/20 text-xs pt-5 gap-2">
        <div>&copy; {{ date('Y') }} LRC PeerConnect · University of the Philippines Baguio. All rights reserved.</div>
    </div>
</div>
