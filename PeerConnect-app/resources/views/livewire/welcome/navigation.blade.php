<div x-data="{ open: false }">
    <nav class="flex fixed top-0 left-0 right-0 z-50 items-center h-[60px] md:h-[83px] px-7 bg-up-maroon-dark">
        {{-- Logo and Title --}}
        <div class="xl:w-1/4 min-w-fit">
            <a href="{{ request()->is('/') ? '#' : url('/') }}" class="inline-flex items-center gap-1">
                <div class="flex items-center gap-[1px]">
                    <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/uplogo.png"
                         alt="UPB Logo"
                         class="w-[40px] md:w-[60px] h-[40px] md:h-[60px] object-contain" />
                    <img src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/logos/logo_white_transparent.png"
                         alt="UPB LRC Logo"
                         class="w-[50px] md:w-[70px] h-[50px] md:h-[70px] object-contain" />
                </div>
                <div class="leading-tight">
                    <span class="block text-[13px] md:text-[20px] font-body font-medium text-cream/70 tracking-widest uppercase">
                        UPB LRC
                    </span>
                    <span class="block text-[8px] md:text-[15px] font-semibold text-up-yellow-light tracking-wider">
                        PeerConnect
                    </span>
                </div>
            </a>
        </div>

        {{-- Nav links --}}
        <ul class="hidden lg:flex flex-1 gap-6 xl:gap-14 2xl:gap-20 list-none justify-center min-w-0">
            @foreach (
            [
                'Mentors'    => route('public.mentors'),
                'Staff'      => route('public.staff'),
                'Services'   => request()->is('/') ? url('/').'#services' : route('public.services'),
                'About Us'   => route('public.about'),
                'Contact Us' => route('public.contact')
            ] as $label => $href)
                @php
                    $isActive = request()->url() === $href || (request()->is('/') && $label === 'Home');
                @endphp
                <li>
                    <a href="{{ $href }}"
                       class="whitespace-nowrap lg:text-sm xl:text-base font-medium tracking-widest uppercase transition-colors duration-200 no-underline
                          {{ $isActive ? 'text-up-yellow-light font-bold' : 'text-cream/75 hover:text-up-yellow-light' }}">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- User action --}}
        <div class="hidden lg:flex items-center justify-end gap-3.5 min-w-fit xl:w-1/4">
                <a href="{{ auth()->check() ? $dashboardUrl : route('auth.google') }}"
                   class="bg-up-yellow text-up-maroon-dark px-7 py-2.5 text-[14px] font-semibold tracking-widest uppercase rounded-sm
                   transition-colors duration-200 hover:bg-up-yellow-light no-underline">
                    {{ auth()->check() ? 'Dashboard' : 'Log In with UP Mail' }}
                </a>
        </div>

        {{-- Hamburger button --}}
        <div class="flex lg:hidden items-center justify-end flex-1">
            <button class="group flex flex-col justify-center items-center w-10 h-10 gap-[6px] focus:outline-none"
                    @click="open = !open">
                <span class="block w-6 h-[1px] md:h-[2px] bg-cream/70 transition-all duration-300"
                      :class="open ? 'rotate-45 translate-y-[7px] md:translate-y-[8px]' : ''"></span>
                <span class="block w-6 h-[1px] md:h-[2px] bg-cream/70 transition-all duration-300"
                      :class="open ? 'opacity-0' : ''"></span>
                <span class="block w-6 h-[1px] md:h-[2px] bg-cream/70 transition-all duration-300"
                      :class="open ? '-rotate-45 -translate-y-[7px] md:-translate-y-[8px]' : ''"></span>
            </button>
        </div>
    </nav>

    {{-- Hamburger menu dropdown --}}
    <div class="lg:hidden fixed top-[60px] md:top-[83px] left-0 right-0 z-40 bg-up-maroon-dark border-t border-cream/10"
         x-show="open" x-transition
         @click.outside="open = false"
         style="display: none">
        <ul class="flex flex-col gap-2 px-6 md:px-7 py-2 md:py-4 list-none">
            @foreach (
            [
                'Mentors'    => route('public.mentors'),
                'Staff'      => route('public.staff'),
                'Services'   => request()->is('/') ? url('/').'#services' : route('public.services'),
                'About Us'   => route('public.about'),
                'Contact Us' => route('public.contact')
            ] as $label => $href)
                @php
                    // Check if the current URL matches the link
                    $isActive = request()->url() === $href || (request()->is('/') && $label === 'Home');
                @endphp
            <li>
                <a href="{{ $href }}"
                   class="block text-[10px] md:text-[15px] py-0.5 md:py-3 font-medium tracking-widest uppercase transition-colors duration-200 no-underline border-b border-cream/10
                      {{ $isActive ? 'text-up-yellow-light font-bold' : 'text-cream/75 hover:text-up-yellow-light' }}">
                    {{ $label }}
                </a>
            </li>
            @endforeach
            <li class="md:pt-4 pb-1 md:pb-2">
                <a href="{{ $dashboardUrl }}"
                   class="bg-up-yellow text-up-maroon-dark px-3 md:px-7 py-1 md:py-2.5 text-[10px] md:text-[14px] font-semibold tracking-widest uppercase
                   transition-colors duration-200 hover:bg-up-yellow-light no-underline">
                    {{ auth()->check() ? 'Dashboard' : 'Log In with UP Mail' }}
                </a>
            </li>
        </ul>
    </div>
</div>

