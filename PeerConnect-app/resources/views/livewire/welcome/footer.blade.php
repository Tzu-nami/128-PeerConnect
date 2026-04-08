@php
    $bookUrl = auth()->check() ? match(true) {
        auth()->user()->isStudent() => route('student.bookings'),
        auth()->user()->isMentor()  => route('mentor.bookings'),
        default                     => route('login')
    } : route('login');

    $dashboardUrl = auth()->check() ? match(true) {
        auth()->user()->isStudent() => route('student.dashboard',),
        auth()->user()->isMentor()  => route('mentor.dashboard',),
        auth()->user()->isAdmin()   => route('admin.dashboard',),
        default                     => route('login')
    } : route('login');

    $historyUrl = auth()->check() ? match(true) {
        auth()->user()->isStudent() => route('student.history'),
        auth()->user()->isMentor() => route('mentor.history')
    } : route('login');
@endphp

<div class="text-cream/60 bg-up-maroon-dark px-20 py-10">
    {{-- Main row --}}
    <div class="flex justify-between mb-7">
        {{-- Left Side --}}
        <div class="flex flex-col gap-4 max-w-sm">
            <div class="font-heading font-bold text-xl text-cream tracking-widest">
                Peer<span class="text-up-yellow">Connect</span>
            </div>
            <div class="text-sm leading-relaxed">
                Connecting UPB students with peer mentors for enrichment sessions and academic success.
            </div>
            <div class="flex items-center gap-3">
                <a href="#" class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-facebook text-sm"></i>
                </a>
                <a href="#" class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-instagram text-sm"></i>
                </a>
                <a href="#" class="flex items-center justify-center w-8 h-8 rounded-full border border-cream/20 hover:bg-cream/10 hover:text-cream transition-colors">
                    <i class="fa-brands fa-twitter text-sm"></i>
                </a>
            </div>
        </div>

        {{-- Right Side --}}
        <div class="flex gap-16 text-sm">
            {{-- Navigate --}}
            <div class="flex flex-col gap-3">
                <div class="font-bold text-up-yellow-dark tracking-widest uppercase mb-1">Navigate</div>
                <a href="{{ url('/') }}" class="hover:text-cream transition-colors">Home</a>
                <a href="{{ route('public.mentors') }}" class="hover:text-cream transition-colors">Mentors</a>
                <a href="#" class="hover:text-cream transition-colors">Staff</a>
                <a href="{{ route('public.services') }}" class="hover:text-cream transition-colors">Services</a>
                <a href="{{ request()->is('about') ? '#' : route('public.about') }}" class="hover:text-cream transition-colors">About Us</a>
            </div>

            {{-- Quick Actions --}}
            <div class="flex flex-col gap-3">
                <div class="font-bold text-up-yellow-dark tracking-widest uppercase mb-1">Quick Actions</div>
                @auth
                    <a href="{{ $bookUrl }}" class="hover:text-cream transition-colors">Book a Session</a>
                    <a href="{{ $historyUrl }}" class="hover:text-cream transition-colors">View Bookings</a>
                    <a href="{{ $dashboardUrl }}" class="hover:text-cream transition-colors">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-cream transition-colors">Log In</a>
                @endauth
            </div>

            {{-- Contact --}}
            <div class="flex flex-col gap-3">
                <div class="font-bold text-up-yellow-dark tracking-widest uppercase mb-1">Contact</div>
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
                    <a href="mailto:lrc.upbaguio@up.edu.ph" class="hover:text-cream transition-colors">lrc.upbaguio@up.edu.ph</a>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-phone mt-0.5 opacity-50 text-xs"></i>
                    <span>(074) 444 8720</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom row --}}
    <div class="flex justify-between border-t border-cream/20 text-xs pt-5">
        <div>&copy; {{ date('Y') }} LRC PeerConnect · University of the Philippines Baguio. All rights reserved.</div>
        <div class="flex gap-6">
            <a href="#" class="hover:text-cream transition-colors">Privacy Policy</a>
            <a href="#" class="hover:text-cream transition-colors">Terms of Service</a>
        </div>
    </div>
</div>
