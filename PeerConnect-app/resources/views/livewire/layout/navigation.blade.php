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

    {{-- Left: Welcome --}}
    <div class="welcome-text">
        Welcome, <strong>{{ auth()->user()->name }}</strong>
    </div>

    {{-- Right: Profile trigger --}}
    <button id="profileTrigger" type="button">
        <div class="profile-avatar">
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </div>
        <i class="fa-solid fa-chevron-down" id="dropdownArrow"></i>
    </button>

    {{-- Dropdown --}}
    <div id="profileDropdown" class="profile-dropdown">
        <div style="padding: 14px 18px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
            <p style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 4px;">Signed in as</p>
            <p style="font-size: 13px; font-weight: 700; color: #1e293b; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ auth()->user()->name }}</p>
            <p style="font-size: 11px; color: #64748b; margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ auth()->user()->email }}</p>
        </div>
        <a href="#" class="dropdown-item">
            <i class="fa-solid fa-user-gear"></i> Account Settings
        </a>
        <button wire:click="logout" class="dropdown-item" style="border-top: 1px solid #f1f5f9; color: #dc2626; font-weight: 600;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
    </div>

</header>
