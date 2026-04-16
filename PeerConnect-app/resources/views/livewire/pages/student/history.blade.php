<?php

use function Livewire\Volt\{layout, mount, computed};
use App\Models\Bookings;
use App\Models\StudentProfiles;

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

$summaryCount = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if(!$profile) return ['total' => 0, 'completed' => 0, 'totalHours' => '0.00 hrs', 'cancelled' => 0, 'pending' => 0];

    $allInfo = Bookings::where('student_id', $profile->id)->get();

    $completedSessions = $allInfo->where('booking_status', 'completed');
    $pendingSessions = $allInfo->where('booking_status', 'pending');
    $totalMinutes = $completedSessions->sum(function ($b) {
        return \Carbon\Carbon::parse($b->schedule_start)->diffInMinutes(\Carbon\Carbon::parse($b->schedule_end));
    });
    $totalHours = $totalMinutes / 60;
    $hoursFormatted = number_format($totalHours, 2) . ' hrs';

    return [
        'total'      => $allInfo->count(),
        'completed'  => $completedSessions->count(),
        'pending'    => $pendingSessions->count(),
        'totalHours' => $hoursFormatted,
        'cancelled'  => $allInfo->whereIn('booking_status', ['cancelled'])->count(),
    ];
});
// Get data from database
$studentHistory = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if(!$profile) return [];

    return Bookings::with(['subject', 'mentor.user', 'tutorialMode'])->where('student_id', $profile->id)->orderBy('date')->orderBy('schedule_start')->get()
    ->map(function ($session) {
        $start = \Carbon\Carbon::parse($session->schedule_start)->format('g:i A');
        $end = \Carbon\Carbon::parse($session->schedule_end)->format('g:i A');

        // For any choice
        $isOpen = is_null($session->mentor_id);
        $mentorName = $isOpen ? 'ANY' : strtoupper($session->mentor->user->lastName ?? 'TBD') . ', ' . ($session->mentor->user->firstName ?? '');

        $statusClass = match($session->booking_status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'accepted' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            'completed' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'closed'    => 'bg-purple-100 text-purple-800',
            'no_show'   => 'bg-red-100 text-red-800',
            default     => 'bg-gray-100 text-gray-800',
        };
        $statusLabel = ucfirst(str_replace('_', ' ', $session->booking_status));

        $startCarbon = \Carbon\Carbon::parse($session->schedule_start);
        $endCarbon   = \Carbon\Carbon::parse($session->schedule_end);
        $durationMinutes = $startCarbon->diffInMinutes($endCarbon);
        $durationHours   = $durationMinutes / 60;
        $durationText = $durationHours == 1
            ? '1 hr'
            : rtrim(rtrim(number_format($durationHours, 2), '0'), '.') . ' hrs';
        
        return [
            'id' => $session->id,
            'subject' => $session->subject->code,
            'subjectName' => $session->subject->name,
            'topic' => $session->topic,
            'mentor' => $mentorName,
            'avatar' => $isOpen ? null : ($session->mentor->avatar ?? null),
            'date' => \Carbon\Carbon::parse($session->date)->format('F j, Y'),
            'time' => $start . ' - ' . $end,
            'mode' => $session->tutorialMode->mode,
            'raw_status' => strtolower($session->booking_status),
            'statusLabel' => $statusLabel,
            'statusClass' => $statusClass,
            'durationHours' => $durationHours,
            'durationText'  => $durationText,
        ];
    })->toArray();
});

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect | History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
<style>
        :root { --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; --header-height: 80px; --sidebar-width: 260px; --sidebar-collapsed-width: 72px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

/* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-green);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            color: white;
            height: 100vh;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            position: relative;
            overflow: visible;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        /* ── Logo row ── */
        .sidebar-logo-container {
            height: var(--header-height);
            display: flex; align-items: center; justify-content: center;
            padding: 0 20px; gap: 12px; flex-shrink: 0; overflow: hidden;
            transition: padding 0.3s, justify-content 0.3s;
        }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }
        .logo-icon { flex-shrink: 0; font-size: 27px; width: auto; text-align: center; }
        .logo-text { font-size: 1.24rem; font-weight: 700; white-space: nowrap; overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }
        .sidebar.collapsed .sidebar-logo-container { justify-content: center; padding: 0; width: 100%; }
        .sidebar.collapsed .logo-content { gap: 0; justify-content: center; width: 100%; }

        /* ── Nav items ── */
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 16px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
            white-space: nowrap; position: relative; text-align: left;
            background: transparent; border: none; width: 100%;
            cursor: pointer; font-size: 0.95rem; justify-content: flex-start;
        }
        .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
        .nav-item span { overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }

        .sidebar.collapsed .nav-item { display: flex; align-items: center; justify-content: center; padding: 16px 0; width: 100%; gap: 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; text-align: center; flex-shrink: 0; }
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

        .nav-item::after {
            content: attr(data-tooltip);
            position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
            padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
            pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

        .sidebar-footer { padding: 0; border-top: 1px solid rgba(255,255,255,0.1); }

        .sidebar-toggle-btn {
            position: absolute; right: -16px; top: 50%;
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--header-maroon); border: 2px solid white;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 13px; z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25); transition: background 0.2s; flex-shrink: 0;
        }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

.main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
.scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

.profile-dropdown {
    position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
    flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
}
.profile-dropdown.show { display: flex; }

.dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
.dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

/* Hover tooltip */
.hover-tooltip { position: relative; cursor: default; }
.hover-tooltip::after {
    content: attr(data-full);
    position: absolute; left: 0; top: 110%;
    background: rgba(0,0,0,0.85); color: #fff;
    padding: 8px 10px; border-radius: 6px; font-size: 11px; line-height: 1.4;
    white-space: normal; word-break: break-word; overflow-wrap: anywhere;
    width: 240px; max-width: 240px;
    opacity: 0; pointer-events: none;
    transform: translateY(5px); transition: 0.15s ease; z-index: 9999;
}
.hover-tooltip:hover::after { opacity: 1; transform: translateY(0); }

/* Pagination button */
.pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; background: white; cursor: pointer; }
.pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: #7b1d1d; border-color: #7b1d1d; }
</style>
</head>

<body>
    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
            <div class="logo-content">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>
            </div>

            <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="toggle-icon">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            </button>

            <nav class="flex-grow">
                <a href="{{ route('student.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.mentors') }}" class="nav-item" data-tooltip="Mentors">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentors</span>
                </a>
                <a href="{{ route('student.bookings') }}" class="nav-item" data-tooltip="Bookings">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Bookings</span>
                </a>
                <a href="{{ route('student.history') }}" class="nav-item active" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i><span>History</span>
                </a>
                <a href="{{ route('student.about') }}" class="nav-item" data-tooltip="About Us">
                    <i class="fa-solid fa-circle-info w-5"></i><span>About Us</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header relative">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900"></i>
                </button>
                <div id="profileDropdown" class="profile-dropdown">
                    <div class="p-4 border-b border-gray-100 bg-slate-50">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
                        <p class="text-sm font-bold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">

            <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 animate-[slideDown_0.3s_ease]">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                        Session History
                    </h1>
                    <p class="text-sm font-medium text-slate-500 mt-1">View your past and current bookings.</p>
                </div>
            </div>

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-list-check text-slate-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Requests</p>
                            <p class="text-xl font-black text-slate-800">{{ $this->summaryCount['total'] }}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-hourglass text-yellow-800"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Pending Requests</p>
                            <p class="text-xl font-black text-slate-800">{{ $this->summaryCount['pending'] }}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fa-solid fa-circle-check text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Completed Sessions</p>
                            <p class="text-xl font-black text-slate-800">{{ $this->summaryCount['completed'] }}</p>
                        </div>
                    </div>
<div class="stat-card">
    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
        <i class="fa-solid fa-stopwatch text-purple-600"></i>
    </div>
    <div>
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Total Hours</p>
        <p class="text-xl font-black text-slate-800">{{ $this->summaryCount['totalHours'] }}</p>
    </div>
</div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                            <i class="fa-solid fa-ban text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Cancelled Requests</p>
                            <p class="text-xl font-black text-slate-800">{{ $this->summaryCount['cancelled'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{
    search: '',
    filterStatuses: [],
    currentPage: 1,
    perPage: 5,
    bookings: @js($this->studentHistory),

    statusOrder: { accepted: 1, pending: 2, completed: 3, cancelled: 4, rejected: 5, no_show: 6 },

    get filteredBookings() {
        const term = this.search.toLowerCase();
        const filtered = this.bookings.filter(session => {
            const matchSearch = session.subject.toLowerCase().includes(term) ||
                session.subjectName.toLowerCase().includes(term) ||
                session.topic.toLowerCase().includes(term) ||
                session.mentor.toLowerCase().includes(term) ||
                session.date.toLowerCase().includes(term);
            const matchStatus = this.filterStatuses.length === 0 || this.filterStatuses.includes(session.raw_status);
            return matchSearch && matchStatus;
        });
        const order = this.statusOrder;
        return filtered.sort((a, b) => (order[a.raw_status] ?? 99) - (order[b.raw_status] ?? 99));
    },

    get paginatedBookings() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredBookings.slice(start, start + this.perPage);
    },

    get totalPages() {
        return Math.ceil(this.filteredBookings.length / this.perPage) || 1;
    },

    get pageStart() {
        return this.filteredBookings.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
    },

    get pageEnd() {
        return Math.min(this.currentPage * this.perPage, this.filteredBookings.length);
    },

    get pages() {
        const total = this.totalPages;
        const current = this.currentPage;
        if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    },

    toggleAll(checked) {
        this.filterStatuses = [];
        this.currentPage = 1;
    },

    handleStatusChange() {
        this.currentPage = 1;
    }
}">
                    <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                        <div>
                            <h2 class="font-bold text-slate-800 text-sm">All Bookings</h2>
                            <p class="text-xs text-gray-400 font-medium" x-text="filteredBookings.length + ' Session' + (filteredBookings.length !==1 ? 's' : '') + ' found'"></p>
                        </div>
<div class="flex gap-3 flex-wrap">
    <div class="relative">
        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
        <input type="text" placeholder="Search subject, topic, or date..."
            class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow"
            x-model="search" @input="currentPage = 1">
    </div>

    <div class="relative" x-data="{ openFilter: false }">
        <button @click="openFilter = !openFilter"
            class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-xs font-bold text-slate-600 outline-none flex items-center gap-2 hover:bg-gray-50 transition h-[34px] w-[110px]">
            <i class="fa-solid fa-filter text-gray-400"></i>
            Status
            <span x-show="filterStatuses.length > 0"
                class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold"
                x-text="filterStatuses.length"></span>
        </button>

        <div x-show="openFilter"
            x-transition
            @click.outside="openFilter = false"
            @click.stop
            class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl z-20 py-1">

            {{-- All option --}}
            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                <input type="checkbox"
                    :checked="filterStatuses.length === 0"
                    @change="toggleAll($event.target.checked)"
                    class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                <span>All</span>
            </label>
            <div class="border-t border-gray-100 my-1"></div>

            <template x-for="status in ['pending', 'accepted', 'completed', 'cancelled', 'rejected', 'no_show']" :key="status">
                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                    <input type="checkbox"
                        :value="status"
                        x-model="filterStatuses"
                        @change="handleStatusChange()"
                        class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4 transition">
                    <span x-text="status.replace('_', ' ')"></span>
                </label>
            </template>
        </div>
    </div>
</div>
                        
                    </div>

                    <div>
                        <table class="w-full text-sm text-left table-fixed">
                            <thead class="bg-slate-50 border-b border-gray-100">
                            <tr>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[5%]">#</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[15%]">Subject</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[18%]">Topic</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[18%]">Mentor</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[17%]">Date & Time</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[15%]">Mode</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[12%]">Status</th>
                            </tr>
                            </thead>
                            <tbody>
                                <template x-for="(booking, index) in paginatedBookings" :key="booking.id">
                                    <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                                        <td class="px-5 py-4 text-gray-400 text-xs" x-text="(currentPage - 1) * perPage + index + 1"></td>
{{-- Subject with hover tooltip --}}
                                        <td class="px-5 py-4">
                                            <div class="hover-tooltip" :data-full="booking.subject + ' – ' + booking.subjectName">
                                                <p class="font-bold text-slate-700 text-xs" x-text="booking.subject"></p>
                                                <p class="text-gray-400 text-[10px] truncate" x-text="booking.subjectName"></p>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="hover-tooltip" :data-full="booking.topic">
                                                <p class="text-slate-600 text-xs truncate" x-text="booking.topic"></p>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="hover-tooltip" :data-full="booking.mentor">
                                                <span class="text-xs font-medium text-slate-700 truncate" x-text="booking.mentor"></span>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <p class="text-xs font-medium text-slate-700" x-text="booking.date"></p>
                                            <p class="text-[10px] text-gray-400" x-text="booking.time"></p>
                                        </td>
                                        <td class="px-5 py-4 text-xs text-slate-500" x-text="booking.mode"></td>
                                        <td class="px-5 py-4">
                                            <span :class="'text-xs font-bold px-2.5 py-1 rounded-full ' + booking.statusClass" x-text="booking.statusLabel"></span>
                                        </td>
                                    </tr>
                                </template>
                                
                                {{-- Empty Search --}}
                                <tr x-show="filteredBookings.length === 0" x-cloak>
                                    <td colspan="7" class="px-5 py-16 text-center text-gray-400 text-sm italic">No matching records found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-3 pb-4 flex flex-col items-center gap-2" x-show="totalPages >= 1" x-cloak>
                        {{-- Page info --}}

                        {{-- Page buttons (centered) --}}
                        <div class="flex items-center gap-2">
                            <button @click="currentPage--" :disabled="currentPage === 1" class="pagination-btn disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </button>
                            <template x-for="page in pages" :key="page">
                                <button
                                    @click="if (page !== '...') currentPage = page"
                                    :disabled="page === '...'"
                                    :class="currentPage === page
                                        ? 'bg-[#1a3c2f] text-white shadow-sm border-[#1a3c2f]'
                                        : page === '...'
                                            ? 'bg-white border border-gray-200 text-gray-400 cursor-default'
                                            : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'"
                                    class="w-8 h-8 text-xs font-bold rounded-lg transition"
                                    x-text="page">
                                </button>
                            </template>
                            <button @click="currentPage++" :disabled="currentPage === totalPages" class="pagination-btn disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        </div>

                        <span class="text-[11px] text-gray-400 font-medium"
                            x-text="filteredBookings.length === 0 ? '' : pageStart + '–' + pageEnd + ' of ' + filteredBookings.length">
                        </span>

                    </div>
                </div>
</div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar
        const sidebar = document.getElementById('sidebar');

        document.getElementById('sidebarToggle').addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
        
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        window.addEventListener('click', () => profileDropdown.classList.remove('show'));
    </script>
</body>
