<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
};
?>

<div x-data="{ mobileNavOpen: false }">

    <header class="top-header relative">

        {{-- Left side --}}
        <div class="flex items-center gap-3">

            {{-- Hamburger: mobile only --}}
            <button class="lg:hidden flex flex-col justify-center items-center w-9 h-9 gap-[5px] focus:outline-none"
                    @click="mobileNavOpen = !mobileNavOpen"
                    aria-label="Toggle menu">
                <span class="block w-5 h-[2px] bg-white/80 transition-all duration-300"
                      :class="mobileNavOpen ? 'rotate-45 translate-y-[7px]' : ''"></span>
                <span class="block w-5 h-[2px] bg-white/80 transition-all duration-300"
                      :class="mobileNavOpen ? 'opacity-0' : ''"></span>
                <span class="block w-5 h-[2px] bg-white/80 transition-all duration-300"
                      :class="mobileNavOpen ? '-rotate-45 -translate-y-[7px]' : ''"></span>
            </button>

            {{-- Greeting: hidden on mobile --}}
            <div class="hidden lg:block text-lg">
                Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
            </div>

        </div>

        {{-- Right side --}}
        <div class="flex items-center gap-2">

            @if (auth()->user()->isStudent())
                <x-student-notifications />
            @elseif (auth()->user()->isMentor())
                <x-mentor-notifications />
            @elseif (auth()->user()->isAdmin())
                <x-admin-notifications />
            @endif

            {{-- Profile Dropdown --}}
            <div class="relative"
                 x-data="{ get open() { return $store.dropdowns.active === 'profile' } }"
                 @click.outside="$store.dropdowns.close()">

                <button type="button"
                        @click.stop="$store.dropdowns.toggle('profile')"
                        class="flex items-center gap-2 px-2 py-1 bg-white/15 rounded-full hover:bg-red-800 transition border border-white/50 group">
                    @if(auth()->user()->avatar)
                        <img src="{{ auth()->user()->avatar }}" alt="Profile Picture" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-100">
                    @else
                        <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                    @endif
                    <i class="fa-solid fa-chevron-down text-[10px] text-white/60 group-hover:text-white transition-transform duration-200"
                       :class="open ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="open"
                     x-cloak
                     x-transition
                     @click.stop
                     class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">

                    <div class="p-4 border-b border-gray-100 bg-slate-50">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
                        <p class="text-sm font-bold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit"
                                class="dropdown-item w-full text-left border-t border-slate-100 font-semibold text-slate-600 hover:text-red-600">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </header>

    {{-- Mobile nav dropdown --}}
    @php
        $user = auth()->user();
        $navItems = match(true) {
            $user->isAdmin() => [
                ['route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge',           'label' => 'Dashboard'],
                ['route' => 'admin.mentors',   'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Mentor Management'],
                ['route' => 'admin.staff',     'icon' => 'fa-solid fa-user-tie',        'label' => 'Staff Management'],
                ['route' => 'admin.courses',   'icon' => 'fa-solid fa-book-open',       'label' => 'Course Management'],
                ['route' => 'admin.sessions',  'icon' => 'fa-solid fa-calendar-check',  'label' => 'Session Management'],
                ['route' => 'admin.feedbacks', 'icon' => 'fa-solid fa-comments',        'label' => 'Student Feedback'],
            ],
            $user->isMentor() => [
                ['route' => 'mentor.dashboard', 'icon' => 'fa-solid fa-gauge',             'label' => 'Dashboard'],
                ['route' => 'mentor.bookings',  'icon' => 'fa-solid fa-calendar-check',    'label' => 'Booking Form'],
                ['route' => 'mentor.history',   'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Booking History'],
                ['route' => 'mentor.mentors',   'icon' => 'fa-solid fa-chalkboard-user',   'label' => 'Mentors'],
                ['route' => 'mentor.sessions',  'icon' => 'fa-solid fa-clock',             'label' => 'Tutorial Sessions'],
                ['route' => 'mentor.feedbacks', 'icon' => 'fa-solid fa-comment-dots',      'label' => 'Student Feedbacks'],
            ],
            $user->isStudent() => [
                ['route' => 'student.dashboard', 'icon' => 'fa-solid fa-gauge',             'label' => 'Dashboard'],
                ['route' => 'student.bookings',  'icon' => 'fa-solid fa-calendar-check',    'label' => 'Booking Form'],
                ['route' => 'student.history',   'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Booking History'],
                ['route' => 'student.mentors',   'icon' => 'fa-solid fa-chalkboard-user',   'label' => 'Mentors'],
            ],
            default => [],
        };
    @endphp

    {{-- Mobile nav dropdown --}}
    <div class="lg:hidden fixed top-[70px] left-0 right-0 z-40 bg-up-maroon  border-t border-white/10"
         x-show="mobileNavOpen"
         x-transition
         x-cloak
         @click.outside="mobileNavOpen = false">
        <ul class="flex flex-col list-none m-0 p-0">
            @foreach ($navItems as $item)
                <li>
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-6 py-3 text-[13px] font-medium tracking-wide uppercase
                          border-b border-white/10 no-underline transition-colors duration-200
                          {{ request()->routeIs($item['route'])
                              ? 'text-white font-bold border-l-[3px] border-l-white pl-[21px]'
                              : 'text-white/75 hover:text-white' }}"
                       @click="mobileNavOpen = false">
                        <i class="{{ $item['icon'] }} w-5 text-center flex-shrink-0"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
            <li class="px-6 py-4">
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-3 text-[13px] font-medium tracking-wide uppercase
                               text-white/75 hover:text-white transition-colors duration-200 bg-transparent border-none cursor-pointer p-0"
                            @click="mobileNavOpen = false">
                        <i class="fa-solid fa-right-from-bracket w-5 text-center flex-shrink-0"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </li>
        </ul>
    </div>

</div>
