<nav class="fixed top-0 left-0 right-0 z-50 flex items-center h-[83px] px-7 bg-up-maroon-dark ">
    {{-- Brand --}}
    <a href="{{ request()->is('/') ? '#' : url('/') }}" class="flex items-center gap-4 w-1/4">
        <div class="flex items-center gap-3">
            <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/uplogo.png"
                 alt="UPB Logo"
                 class="w-[60px] h-[60px] object-contain" />
            <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/logo_white_transparent.png"
                 alt="UPB LRC Logo"
                 class="w-[70px] h-[70px] object-contain" />
        </div>
        <div class="leading-tight">
            <span class="block text-[20px] font-body font-medium text-cream/70 tracking-widest uppercase">
                UPB LRC
            </span>
            <span class="block text-[15px] font-semibold text-up-yellow-light tracking-wider">
                PeerConnect
            </span>
        </div>
    </a>

    {{-- Nav links --}}
    <ul class="flex gap-20 list-none flex-1 justify-center">
        @foreach (
        [
            'Mentors'    => route('public.mentors'),
            'Staff'      => '#',
            'Services'   => request()->is('/') ? url('/').'#services' : route('public.services'),
            'About Us'   => route('public.about'),
            'Contact Us' => '#',
        ] as $label => $href)
            <li>
                <a href="{{ $href }}"
                   class="whitespace-nowrap text-[15px] font-medium text-cream/75 tracking-widest uppercase
                          transition-colors duration-200 hover:text-up-yellow-light no-underline">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- User action --}}
    <div class="flex items-center justify-end gap-3.5 w-1/4">
        @auth
            @php
                $dashboardUrl = match(true) {
                    auth()->user()->isStudent() => route('student.dashboard'),
                    auth()->user()->isMentor() => route('mentor.dashboard'),
                    auth()->user()->isAdmin() => route('admin.dashboard'),
                    default => route('login')
                };
            @endphp

            <a href="{{ $dashboardUrl }}"
               class="bg-up-yellow text-up-maroon-dark px-7 py-2.5 text-[14px]
                      font-semibold tracking-widest uppercase rounded-sm
                      transition-colors duration-200 hover:bg-up-yellow-light no-underline">
                Dashboard
            </a>
        @else
            <a href="{{ route('login') }}"
               class="bg-up-yellow text-up-maroon-dark px-7 py-2.5 text-[14px]
                      font-semibold tracking-widest uppercase rounded-sm
                      transition-colors duration-200 hover:bg-up-yellow-light no-underline">
                Log In
            </a>
        @endauth
    </div>
</nav>
