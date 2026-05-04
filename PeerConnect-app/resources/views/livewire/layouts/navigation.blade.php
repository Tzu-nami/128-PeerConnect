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

<header class="top-header relative">

    {{-- Left: greeting --}}
    <div class="text-lg">
        Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
    </div>

    {{-- Right: notifications + avatar --}}
    <div class="flex items-center gap-2">

        {{-- Role-aware notification bell --}}
        @if (auth()->user()->isStudent())
            <x-student-notifications />
        @elseif (auth()->user()->isMentor())
            <x-mentor-notifications />
        @endif

        {{-- Profile Dropdown --}}
        <div class="relative"
             x-data="{
                 get open() { return $store.dropdowns.active === 'profile' }
             }"
             @click.outside="$store.dropdowns.close()">

            {{-- Trigger --}}
            <button type="button"
                    @click.stop="$store.dropdowns.toggle('profile')"
                    class="flex items-center gap-2 px-2 py-1 bg-white/15 rounded-full hover:bg-red-800 transition border border-white/50 group">
                <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                    @if(auth()->user()->avatar)
                        <img src="{{ auth()->user()->avatar }}" alt="Profile Picture" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-100">
                    @else
                        <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                    @endif
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] text-white/60 group-hover:text-white transition-transform duration-200"
                   :class="open ? 'rotate-180' : ''"></i>
            </button>

            {{-- Dropdown Panel --}}
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

                {{-- Logout --}}
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
