<nav class="fixed top-0 left-0 right-0 z-50 flex items-center h-[83px] px-7 bg-up-maroon-dark">

    {{-- Brand --}}
    <div class="flex items-center gap-4 w-1/3">
        <div class="flex items-center gap-3">
            <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/uplogo.png"
                 alt="UPB Logo"
                 class="w-[60px] h-[60px] object-contain" />
            <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/lrclogo.png"
                 alt="UPB LRC Logo"
                 class="w-[60px] h-[60px] object-contain" />
        </div>
        <div class="leading-tight">
            <span class="block text-[15px] font-medium text-cream/70 tracking-widest uppercase">
                UPB LRC
            </span>
            <span class="block text-[20px] font-semibold text-up-yellow-light tracking-wider">
                PeerConnect
            </span>
        </div>
    </div>

    {{-- Nav links --}}
    <ul class="flex gap-10 list-none w-1/3 justify-center">
        @foreach (
        [
            'Mentors'    => '#',
            'Staff'      => '#',
            'Services'   => '#',
            'About Us'   => '#',
            'Contact Us' => '#',
        ] as $label => $href)
            <li>
                <a href="{{ $href }}"
                   class="text-[15px] font-medium text-cream/75 tracking-widest uppercase
                          transition-colors duration-200 hover:text-up-yellow-light no-underline">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- User action --}}
    <div class="flex items-center justify-end gap-3.5 w-1/3">
            <a href="{{ route('login') }}"
               class="bg-up-yellow text-up-maroon-dark px-7 py-2.5 text-[14px]
                      font-semibold tracking-widest uppercase rounded-sm
                      transition-colors duration-200 hover:bg-up-yellow-light no-underline">
                Log In
            </a>
    </div>

</nav>
