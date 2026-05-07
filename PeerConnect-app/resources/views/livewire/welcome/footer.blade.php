<div class="text-cream/60 bg-up-maroon-dark px-6 sm:px-12 lg:px-20 py-10 mt-auto">
    {{-- Main row --}}
    <div class="flex flex-col lg:flex-row justify-between mb-7 gap-10">
        {{-- Left Side --}}
        <div class="flex flex-col gap-4 max-w-sm">
            <a href="#" class="font-heading font-bold text-xl text-cream tracking-widest">
                Peer<span class="text-up-yellow">Connect</span>
            </a>
            <div class="text-sm leading-relaxed">
                Connecting UPB students with peer mentors for enrichment sessions and academic success.
            </div>
            <div class="flex items-center gap-3">
                <a href="https://www.facebook.com/lrc.upbaguio"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-facebook text-sm"></i>
                </a>
                <a href="https://x.com/lrc_upbaguio"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-twitter text-sm"></i>
                </a>
                <a href="https://mainlib.upb.edu.ph/" target="_blank" class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <span class="material-symbols-outlined text-sm">captive_portal</span>
                </a>
            </div>
        </div>

        {{-- Right Side --}}
        <div class="flex flex-wrap gap-10 text-sm">
            {{-- Navigate --}}
            <div class="flex flex-col gap-3">
                <div class="font-bold text-up-yellow tracking-widest uppercase mb-1">Navigate</div>
                <a href="{{ request()->is('/') ? '#' : url('/') }}" class="hover:text-cream transition-colors">Home</a>
                <a href="{{ request()->is('mentors') ? '#' : route('public.mentors') }}" class="hover:text-cream transition-colors">Mentors</a>
                <a href="{{ request()->is('staff') ? '#' : route('public.staff') }}" class="hover:text-cream transition-colors">Staff</a>
                <a href="{{ request()->is('services') ? '#' : route('public.services') }}" class="hover:text-cream transition-colors">Services</a>
                <a href="{{ request()->is('about') ? '#' : route('public.about') }}" class="hover:text-cream transition-colors">About Us</a>
            </div>

            {{-- Quick Actions --}}
            <div class="flex flex-col gap-3">
                <div class="font-bold text-up-yellow tracking-widest uppercase mb-1">Quick Actions</div>
                @auth
                    @if($shouldShowBookNow)
                        <a href="{{ $bookUrl }}" class="hover:text-cream transition-colors">Book a Session</a>
                        <a href="{{ $historyUrl }}" class="hover:text-cream transition-colors">View Bookings</a>
                    @endif
                    <a href="{{ $dashboardUrl }}" class="hover:text-cream transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-cream transition-colors">Log In</a>
                @endauth
            </div>

            {{-- Contact --}}
            <div class="flex flex-col gap-3">
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
    <div class="flex flex-col sm:flex-row justify-between border-t border-cream/20 text-xs pt-5 gap-3">
        <div>&copy; {{ date('Y') }} LRC PeerConnect · University of the Philippines Baguio. All rights reserved.</div>
    </div>
</div>
