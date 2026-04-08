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

    <div class="text-lg">
        Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
    </div>

    {{-- Profile trigger --}}
    <button id="profileTrigger" type="button"
            class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
        <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </div>
        <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition"></i>
    </button>

    {{-- Dropdown --}}
    <div id="profileDropdown" class="profile-dropdown">
        <div class="p-4 border-b border-gray-100 bg-slate-50">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
            <p class="text-sm font-bold text-slate-800 truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
        </div>
        <button wire:click="logout"
                class="dropdown-item w-full text-left"
                style="border-top: 1px solid #f1f5f9; color: #dc2626; font-weight: 600;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
    </div>

</header>
