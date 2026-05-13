<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $navItems = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->navItems = match(true) {
            $user->isAdmin() => [
                ['route' => 'admin.dashboard', 'icon' => 'fa-solid fa-gauge',           'label' => 'Dashboard'],
                ['route' => 'admin.mentors',   'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Mentor Management'],
                ['route' => 'admin.staff',   'icon' => 'fa-solid fa-user-tie',       'label' => 'Staff Management'],
                ['route' => 'admin.courses',   'icon' => 'fa-solid fa-book-open',       'label' => 'Course Management'],
                ['route' => 'admin.sessions',  'icon' => 'fa-solid fa-calendar-check',  'label' => 'Session Management'],
                ['route' => 'admin.feedbacks', 'icon' => 'fa-solid fa-comments',        'label' => 'Student Feedback'],
            ],
            $user->isMentor() => [
                ['route' => 'mentor.dashboard', 'icon' => 'fa-solid fa-gauge',          'label' => 'Dashboard'],
                ['route' => 'mentor.bookings',  'icon' => 'fa-solid fa-calendar-check', 'label' => 'Booking Form'],
                ['route' => 'mentor.history',   'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Booking History'],
                ['route' => 'mentor.mentors',   'icon' => 'fa-solid fa-chalkboard-user',   'label' => 'Mentors'],
                ['route' => 'mentor.sessions',  'icon' => 'fa-solid fa-clock',          'label' => 'Tutorial Sessions'],
                ['route' => 'mentor.feedbacks', 'icon' => 'fa-solid fa-comment-dots',   'label' => 'Student Feedbacks'],

            ],
            $user->isStudent() => [
                ['route' => 'student.dashboard', 'icon' => 'fa-solid fa-gauge',             'label' => 'Dashboard'],
                ['route' => 'student.bookings',  'icon' => 'fa-solid fa-calendar-check',    'label' => 'Booking Form'],
                ['route' => 'student.history',   'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'Booking History'],
                ['route' => 'student.mentors',   'icon' => 'fa-solid fa-chalkboard-user',   'label' => 'Mentors'],

            ],
            default => [],
        };
    }
};
?>

<div>
    <div
        x-data
        x-show="$store.sidebar.open"
        x-transition.opacity
        @click="$store.sidebar.close()"
        class="fixed inset-0 bg-black/50 z-20 lg:hidden"
        x-cloak>
    </div>

    <aside class="sidebar hidden lg:flex lg:flex-col" id="sidebar">

        {{-- Logo --}}
        <div class="sidebar-logo-container">
            <a href="{{ route('home')  }}" class="logo-content">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </a>
        </div>

        {{-- Collapse toggle --}}
        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">
                <i class="fa-solid fa-chevron-right"></i>
            </span>
        </button>

        {{-- Nav links --}}
        <nav class="flex-grow">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}"
                   data-tooltip="{{ $item['label'] }}"
                   @click="$store.sidebar.close()">
                    <i class="{{ $item['icon'] }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Logout --}}
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <button type="submit" class="nav-item" data-tooltip="Logout" @click="$store.sidebar.close()">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>

    </aside>
</div>
