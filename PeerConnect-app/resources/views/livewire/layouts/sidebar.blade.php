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
                ['route' => 'admin.sessions',  'icon' => 'fa-solid fa-calendar-check',  'label' => 'Session Management'],
                ['route' => 'admin.feedbacks', 'icon' => 'fa-solid fa-comments',        'label' => 'Student Feedback'],
            ],
            $user->isMentor() => [
                ['route' => 'mentor.dashboard', 'icon' => 'fa-solid fa-gauge',          'label' => 'Dashboard'],
                ['route' => 'mentor.bookings',  'icon' => 'fa-solid fa-calendar-check', 'label' => 'Booking Form'],
                ['route' => 'mentor.sessions',  'icon' => 'fa-solid fa-clock',          'label' => 'Tutorial Sessions'],
                ['route' => 'mentor.feedbacks', 'icon' => 'fa-solid fa-comment-dots',   'label' => 'Student Feedbacks'],
            ],
            $user->isStudent() => [
                ['route' => 'student.dashboard', 'icon' => 'fa-solid fa-gauge',             'label' => 'Dashboard'],
                ['route' => 'student.mentors',   'icon' => 'fa-solid fa-chalkboard-user',   'label' => 'Mentors'],
                ['route' => 'student.bookings',  'icon' => 'fa-solid fa-calendar-check',    'label' => 'Book a Session'],
                ['route' => 'student.history',   'icon' => 'fa-solid fa-clock-rotate-left', 'label' => 'History'],
            ],
            default => [],
        };
    }
};
?>

<aside class="sidebar" id="sidebar">

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
               data-tooltip="{{ $item['label'] }}">
                <i class="{{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Logout --}}
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" class="nav-item" data-tooltip="Logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>

</aside>
