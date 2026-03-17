<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $navItems = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->navItems = match(true) {
            $user->isAdmin() => [
                ['route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge',               'label' => 'Dashboard'],
                ['route' => 'admin.mentors',   'icon' => 'fa-solid fa-chalkboard-user',     'label' => 'Mentor Management'],
                ['route' => 'admin.sessions',  'icon' => 'fa-solid fa-calendar-check',       'label' => 'Session Management'],
                ['route' => 'admin.feedbacks', 'icon' => 'fa-solid fa-comments',            'label' => 'Student Feedback'],
            ],
            $user->isMentor() => [
                ['route' => 'mentor.dashboard', 'icon' => 'fa-solid fa-gauge',              'label' => 'Dashboard'],
                ['route' => 'mentor.sessions',  'icon' => 'fa-solid fa-calendar-check',      'label' => 'My Sessions'],
                ['route' => 'mentor.students',  'icon' => 'fa-solid fa-users',              'label' => 'My Students'],
            ],
            $user->isStudent() => [
                ['route' => 'student.dashboard', 'icon' => 'fa-solid fa-gauge w-5', 'label' => 'Dashboard'],
                ['route' => 'student.bookings', 'icon' => 'fa-solid fa-calendar-check w-5', 'label' => 'Book a Session'],
                ['route' => 'student.history', 'icon' => 'fa-solid fa-clock-rotate-left w-5', 'label' => 'History'],
                ['route' => 'student.mentors', 'icon' => 'fa-solid fa-chalkboard-user w-5', 'label' => 'Mentors'],
                ['route' => 'student.about', 'icon' => 'fa-solid fa-circle-info w-5', 'label' => 'About Us'],
            ],
            default => [],
        };
    }
};
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo-container">
        <button id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="logo-content">
            <i class="fa-solid fa-graduation-cap text-xl"></i>
            <span class="logo-text">LRC PeerConnect</span>
        </div>
    </div>

    <nav class="flex-grow">
        @foreach ($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
               data-tooltip="{{ $item['label'] }}">
                <i class="{{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="p-4 border-t border-white/10">
        <button wire:click="$dispatch('logout')" class="nav-item w-full bg-transparent border-none text-left" data-tooltip="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </button>
    </div>
</aside>
