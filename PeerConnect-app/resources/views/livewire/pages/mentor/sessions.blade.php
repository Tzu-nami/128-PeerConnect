<?php

    use function Livewire\Volt\{layout, state, mount, computed, action};
    use App\Models\Bookings;
    use App\Models\MentorProfiles;
    use App\Models\MentorAvailabilities;
    use App\Models\MentorSubjects;

    layout('layouts.app');

    mount(function () {
        abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
    });

    $sessions = computed(function () {

        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

        if (!$mentorProfile) return [];

// AUTO-COMPLETE: only affect bookings NOT manually touched today
// This prevents "Uncomplete" reversions from being immediately re-completed on reload
Bookings::where('mentor_id', $mentorProfile->id)
    ->where('booking_status', 'accepted')
    ->whereDate('date', '<', today())
    ->whereDate('updated_at', '<', today())
    ->update([
        'booking_status' => 'completed',
        'completed_at'   => now(),
    ]);

        $mySubjectIds = \App\Models\MentorSubjects::where('mentor_id', $mentorProfile->id)->pluck('subject_id');
        $mySched = \App\Models\MentorAvailabilities::where('mentor_id', $mentorProfile->id)->get();

        $allBookings = Bookings::with([
            'student.user',
            'subject',
            'tutorialMode',
        ])
        ->where(function($query) use ($mentorProfile) 
        {
            $query->where('mentor_id', $mentorProfile->id);
        })->orWhere(function($query) use ($mySubjectIds) {
            // For bookings with null mentors
            $query->whereNull('mentor_id')->where('booking_status', 'pending')->whereIn('subject_id', $mySubjectIds);
        })
        ->get();

        $validBookings = $allBookings->filter(function($booking) use ($mentorProfile, $mySched) {
            // If directly assigned to mentor
            if($booking->mentor_id === $mentorProfile->id) {
                return true;
            }
            // Check day of week
            $bookingDay = strtolower(\Carbon\Carbon::parse($booking->date)->format('l'));
            
            return $mySched->contains(function($avail) use ($bookingDay, $booking) {
                if(strtolower($avail->day_of_week) !== $bookingDay) {
                    return false;
                }

                // Check if mentor available for that any slot timeframe
                $availStart = strtotime($avail->start_time);
                $availEnd = strtotime($avail->end_time);
                $bookStart = strtotime($booking->schedule_start);
                $bookEnd = strtotime($booking->schedule_end);
                return $availStart <= $bookStart && $availEnd >= $bookEnd;
            });
        });

    return $validBookings->map(function ($b) {
        $start = \Carbon\Carbon::parse($b->schedule_start);
        $end   = \Carbon\Carbon::parse($b->schedule_end);

        $durationMinutes = $start->diffInMinutes($end);
        $durationHours   = $durationMinutes / 60;

        $durationText = $durationHours == 1
            ? '1 hr'
            : rtrim(rtrim(number_format($durationHours, 2), '0'), '.') . ' hrs';

        return [
            'id'            => $b->id,
            'student'       => optional(optional($b->student)->user)->firstName
                            ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                            : 'Unknown',
            'subject'       => optional($b->subject)->code ?? 'N/A',
            'subjectName'   => optional($b->subject)->name ?? '',
            'topic'         => $b->topic ?? '—',
            'date'          => $b->date ? \Carbon\Carbon::parse($b->date)->format('F j, Y') : '—',
            'mode'          => optional($b->tutorialMode)->mode ?? '—',
            'yearLevel'     => optional(optional($b->student)->yearLevel)->name ?? 'N/A',
            'degreeProgram' => optional(optional($b->student)->degreeProgram)->name ?? 'N/A',

            'start' => $start->format('H:i'),
            'end'   => $end->format('H:i'),

            'time'     => $start->format('g:i A') . ' – ' . $end->format('g:i A'),
            'duration' => $start->format('h:i A') . ' - ' . $end->format('h:i A') . ' (' . $durationText . ')',
            'durationHours' => $durationHours,

            'status'   => $b->booking_status,
            'is_open' => is_null($b->mentor_id),
        ];
    })
        ->values()
        ->toArray();
    });

    $summaryCounts = computed(function () {
        $sessions = $this->sessions;
        $statuses = array_column($sessions, 'status');

        $total     = count($sessions);
        $accepted  = count(array_filter($statuses, fn($s) => $s === 'accepted'));
        $pending   = count(array_filter($statuses, fn($s) => $s === 'pending'));
        $completed = count(array_filter($statuses, fn($s) => $s === 'completed'));
        $completedSessions = array_filter($sessions, fn($s) => $s['status'] === 'completed');
        $totalHours = array_sum(array_column($completedSessions, 'durationHours'));

        $hoursFormatted = number_format($totalHours, 2) . ' hrs';   

        return [
            'total'          => $total,
            'accepted'       => $accepted,
            'pending'        => $pending,
            'completed'      => $completed,
            'totalHoursRaw'  => $totalHours,
            'totalHours'     => $hoursFormatted,
        ];
    });

    ?>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>LRC PeerConnect – Sessions</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.tailwindcss.com?plugins=line-clamp"></script>

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
        .scroll-container { flex-grow: 1; overflow-y: scroll; padding: 32px; width: 100%; }

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

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .table-filter-select { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }

        /* Pagination button */
        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; background: white; cursor: pointer; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: #7b1d1d; border-color: #7b1d1d; }

        .tabular-nums { font-variant-numeric: tabular-nums; }
        .topic-text { word-break: break-word; overflow-wrap: anywhere; white-space: normal; }
        .topic-text.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; white-space: normal; word-break: break-all; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .hover-tooltip { position: relative; cursor: pointer; }
.hover-tooltip::after {
    content: attr(data-full);
    position: absolute; left: 0; top: 110%;
    background: rgba(0,0,0,0.85); color: #fff;
    padding: 6px 10px; border-radius: 6px; font-size: 11px; line-height: 1.4;
    white-space: normal; word-break: break-word; overflow-wrap: break-word;
    width: max-content; max-width: 220px;
    opacity: 0; pointer-events: none;
    transform: translateY(5px); transition: 0.15s ease; z-index: 9999;
}
        .hover-tooltip:hover::after { opacity: 1; transform: translateY(0); }

        #confirmMeta { max-height: 200px; overflow-y: auto; }
.session-row .action-buttons { 
    opacity: 0; 
    transform: translateX(6px);
    transition: opacity 0.15s ease, transform 0.15s ease;
    pointer-events: none;
}
.session-row:hover .action-buttons { 
    opacity: 1; 
    transform: translateX(0);
    pointer-events: auto;
}
.session-row .action-idle {
    opacity: 1;
    transition: opacity 0.15s ease;
}
.session-row:hover .action-idle {
    opacity: 0;
}
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
                <a href="{{ route('mentor.dashboard') }}" class="nav-item {{ request()->routeIs('mentor.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('mentor.bookings') }}" class="nav-item {{ request()->routeIs('mentor.bookings') ? 'active' : '' }}" data-tooltip="Booking Form">
                    <i class="fa-solid fa-calendar-check"></i><span>Booking Form</span>
                </a>
                <a href="{{ route('mentor.history') }}" class="nav-item {{ request()->routeIs('mentor.history') ? 'active' : '' }}" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i></i><span>History</span>
                </a>
                <a href="{{ route('mentor.sessions') }}" class="nav-item {{ request()->routeIs('mentor.sessions') ? 'active' : '' }}" data-tooltip="Tutorial Sessions">
                    <i class="fa-solid fa-clock"></i><span>Tutorial Sessions</span>
                </a>
                <a href="{{ route('mentor.feedbacks') }}" class="nav-item {{ request()->routeIs('mentor.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedbacks">
                    <i class="fa-solid fa-comment-dots"></i><span>Student Feedbacks</span>
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
                <div class="flex items-center gap-2">
                <x-mentor-notifications />
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name,0,2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"></i>
                </button>
                </div>

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

                {{-- Page heading --}}
                <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                            Tutorial Sessions
                        </h1>
                        <p class="text-sm font-medium text-slate-500 mt-1">All student-selected mentor sessions</p>
                    </div>
                </div>

{{-- Summary Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-list-check text-slate-500"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Total</h3>
            <p class="text-2xl font-black text-slate-800" id="statTotal">{{ $this->summaryCounts['total'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-circle-check text-green-600"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Accepted</h3>
            <p class="text-2xl font-black text-slate-800" id="statAccepted">{{ $this->summaryCounts['accepted'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Pending</h3>
            <p class="text-2xl font-black text-slate-800" id="statPending">{{ $this->summaryCounts['pending'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-flag-checkered text-blue-600"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Completed</h3>
            <p class="text-2xl font-black text-slate-800" id="statCompleted">{{ $this->summaryCounts['completed'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-stopwatch text-purple-600"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Total Hours</h3>
            <p class="text-2xl font-black text-slate-800" id="statHours">{{ $this->summaryCounts['totalHours'] }}</p>
        </div>
    </div>
</div>

                {{-- Sessions Table Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

                    <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                        <div>
                            <h2 class="font-bold text-slate-800 text-sm">All Sessions</h2>
                            <p class="text-xs text-gray-400 font-medium" id="sessionCountLabel">— sessions found</p>
                        </div>
                        <div class="flex gap-2 items-center flex-wrap">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                <input type="text" id="searchInput" placeholder="Search..."
                                    class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                            </div>
<div class="relative" id="statusDropdownWrap">
    <button id="statusDropdownBtn"
        class="table-filter-select flex items-center gap-2 min-w-[120px] justify-between"
        onclick="toggleStatusDropdown(event)">
        <span class="flex items-center gap-1.5">
            <i class="fa-solid fa-filter text-gray-400"></i> Status
        </span>
        <span id="statusBadge" class="hidden bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold"></span>
    </button>
    <div id="statusDropdown"
        class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl z-20 overflow-hidden py-1"
        onclick="event.stopPropagation()">
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
            <input type="checkbox" id="filterAll" checked onchange="handleAllFilter(this)"
                class="rounded border-gray-300 w-4 h-4">
            <span>All</span>
        </label>
        <div class="border-t border-gray-100 my-1"></div>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="pending">
            <input type="checkbox" value="pending" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> Pending
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="accepted">
            <input type="checkbox" value="accepted" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> Accepted
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="completed">
            <input type="checkbox" value="completed" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> Completed
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="rejected">
            <input type="checkbox" value="rejected" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> Rejected
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="cancelled">
            <input type="checkbox" value="cancelled" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> Cancelled
        </label>
        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition" data-status="no_show">
            <input type="checkbox" value="no_show" onchange="handleStatusFilter()" class="status-filter-cb rounded border-gray-300 w-4 h-4"> No Show
        </label>
    </div>
</div>
                        </div>
                    </div>

                    <div id="sessionsBannerArea" class="flex flex-col gap-2 px-5 pt-3"></div>

                    <div style="overflow:visible;">
                        <table class="w-full text-left text-sm table-fixed" style="overflow:visible;">
                            <thead class="bg-slate-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[5%]">#</th>
                                    <th onclick="setSort('student')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:15%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Student<span id="sort-student" class="text-[10px]"></span></div>
                                    </th>
                                    <th onclick="setSort('subject')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:13%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Subject<span id="sort-subject" class="text-[10px]"></span></div>
                                    </th>
                                    <th onclick="setSort('topic')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:20%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Topic<span id="sort-topic" class="text-[10px]"></span></div>
                                    </th>
                                    <th onclick="setSort('date')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:15%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Date & Time<span id="sort-date" class="text-[10px]"></span></div>
                                    </th>
                                    <th onclick="setSort('mode')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:9%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Mode<span id="sort-mode" class="text-[10px]"></span></div>
                                    </th>
                                    <th onclick="setSort('status')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:10%;">
                                        <div class="flex items-center gap-1 hover:text-red-800 transition">Status<span id="sort-status" class="text-[10px]"></span></div>
                                    </th>
                                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none text-center" style="width:8%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sessionsTable">
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-gray-400 text-xs">Loading sessions…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION FOOTER (numbered style, centered) -->
                    <div id="sessionsPaginationFooter" style="display:none;"
                        class="pb-4 pt-3 flex flex-col items-center gap-2">
                        <div id="sessionsPaginationButtons" class="flex items-center gap-2"></div>
                        <span id="sessionsPageInfo" class="text-[11px] text-gray-400 font-medium"></span>
                    </div>

                </div>

            </main>
        </div>

        <!-- CONFIRMATION MODAL -->
        <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
                <div class="flex items-center gap-3 mb-3">
                    <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                    <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
                </div>
                <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
                <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
                <div class="flex justify-end gap-3">
                    <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                    <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
                </div>
            </div>
        </div>

    </div>

    <script>

let activeStatusFilters = [];

function toggleStatusDropdown(e) {
    e.stopPropagation();
    document.getElementById('statusDropdown').classList.toggle('hidden');
}

function handleAllFilter(cb) {
    if (cb.checked) {
        document.querySelectorAll('.status-filter-cb').forEach(c => c.checked = false);
        activeStatusFilters = [];
    }
    updateStatusBadge();
    sessionsPage = 0;
    renderSessions();
}

function handleStatusFilter() {
    activeStatusFilters = [...document.querySelectorAll('.status-filter-cb:checked')].map(c => c.value);
    document.getElementById('filterAll').checked = activeStatusFilters.length === 0;
    updateStatusBadge();
    sessionsPage = 0;
    renderSessions();
}

function updateStatusBadge() {
    const badge = document.getElementById('statusBadge');
    if (activeStatusFilters.length > 0) {
        badge.textContent = activeStatusFilters.length;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}       

        const allSessions = @json($this->sessions);

        /* =========================
        CONFLICT HELPERS
        ========================= */
        function toMin(t) {
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        }

        function hasConflict(newReq) {
            return allSessions.some(s => {
                if (s.id === newReq.id) return false;
                if (s.status !== 'accepted') return false;
                if (s.date !== newReq.date) return false;
                const sStart = toMin(s.start), sEnd = toMin(s.end);
                const rStart = toMin(newReq.start), rEnd = toMin(newReq.end);
                return rStart < sEnd && rEnd > sStart;
            });
        }

        function getConflictingPendingIds(acceptedSession) {
            const aStart = toMin(acceptedSession.start);
            const aEnd   = toMin(acceptedSession.end);
            return allSessions
                .filter(s => {
                    if (s.id === acceptedSession.id) return false;
                    if (s.status !== 'pending') return false;
                    if (s.date !== acceptedSession.date) return false;
                    const sStart = toMin(s.start), sEnd = toMin(s.end);
                    return aStart < sEnd && aEnd > sStart;
                })
                .map(s => s.id);
        }

        /* =========================
        BANNERS
        ========================= */
        function showBanner(id, html) {
            const area = document.getElementById('sessionsBannerArea');
            let banner = document.getElementById(id);
            if (!banner) {
                banner = document.createElement('div');
                banner.id = id;
                banner.style.cssText = 'border-radius:8px; overflow:hidden; font-size:11px; animation:slideDown 0.2s ease; margin-bottom:4px;';
                area.appendChild(banner);
            }
            banner.innerHTML = html;
            clearTimeout(banner._timer);
            banner._timer = setTimeout(() => banner.remove(), 6000);
        }

        function showConflictBanner(message) {
            showBanner('conflictBanner', `
                <div style="border:1px solid #fca5a5; background:#fef2f2; border-radius:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                        <div style="flex-shrink:0; margin-top:2px;">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                                <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                            </svg>
                        </div>
                        <div style="flex:1; color:#b91c1c; line-height:1.5;">
                            <span style="font-weight:600;">Cannot approve —</span> ${message}
                        </div>
                        <button onclick="document.getElementById('conflictBanner').remove()"
                            style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                    </div>
                </div>
            `);
        }

        function showAutoRejectBanner(count) {
            showBanner('autoRejectBanner', `
                <div style="border:1px solid #fcd34d; background:#fffbeb; border-radius:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                        <div style="flex-shrink:0; margin-top:2px;">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1.5L14.5 13H1.5L8 1.5Z" stroke="#d97706" stroke-width="1" stroke-linejoin="round"/>
                                <path d="M8 6v3.5" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="8" cy="11.5" r="0.75" fill="#d97706"/>
                            </svg>
                        </div>
                        <div style="flex:1; color:#92400e; line-height:1.5;">
                            <span style="font-weight:600;">${count} conflicting ${count === 1 ? 'request' : 'requests'} auto-rejected</span>
                            — overlapping bookings were declined automatically.
                        </div>
                        <button onclick="document.getElementById('autoRejectBanner').remove()"
                            style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#92400e; font-size:14px; line-height:1; padding:0;">&times;</button>
                    </div>
                </div>
            `);
        }

        function showLoadingBanner() {
            showBanner('loadingBanner', `
                <div style="border:1px solid #bfdbfe; background:#eff6ff; border-radius:8px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px;">
                        <div style="flex-shrink:0;">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;">
                                <circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/>
                                <path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div style="flex:1; color:#1d4ed8; line-height:1.5; font-size:11px;">
                            <span style="font-weight:600;">Updating session status</span> — please wait...
                        </div>
                    </div>
                </div>
            `);
            const banner = document.getElementById('loadingBanner');
            if (banner) clearTimeout(banner._timer);
        }

        function hideLoadingBanner() {
            const banner = document.getElementById('loadingBanner');
            if (banner) banner.remove();
        }

        const csrfToken   = '{{ csrf_token() }}';
        const sessionsUrl = '{{ route('mentor.sessions.update') }}';

        /* =========================
        UTILS
        ========================= */
function getStatusColor(status) {
    switch (status) {
    case 'pending':   return 'text-yellow-500';
    case 'accepted':  return 'text-green-600';
    case 'completed': return 'text-gray-500';
    case 'rejected':  return 'text-red-900';
    case 'cancelled': return 'text-red-600';
    case 'closed':    return 'text-purple-700';
    case 'no_show':   return 'text-orange-600';
    default:          return 'text-gray-500';
    }
}
        
        function getStatusLabel(status) {
            switch (status) {
                case 'no_show':   return 'No Show';
                case 'accepted':  return 'Accepted';
                case 'completed': return 'Completed';
                case 'closed':    return 'Closed';
                case 'rejected':  return 'Rejected';
                case 'cancelled': return 'Cancelled';
                case 'pending':   return 'Pending';
                default:          return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }

// AFTER
function renderActions(s) {
const iconBtn = (icon, label, status, color, textColor) =>
    `<div class="hover-tooltip" data-full="${label}">
        <button onclick="updateStatus('${s.id}','${status}')"
            class="w-7 h-7 rounded-lg ${color} ${textColor} flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm"
            style="flex-shrink:0;">
            <i class="fa-solid ${icon}" style="font-size:11px;"></i>
        </button>
    </div>`;

    let buttons = '';
    let idleIndicator = '';

    if (s.status === 'pending') {
        if (s.is_open) {
            buttons = iconBtn('fa-hand-pointer', 'Claim Session', 'accepted', 'bg-purple-100 hover:bg-purple-200', 'text-purple-700');
            idleIndicator = `<span class="w-2 h-2 rounded-full bg-purple-400 inline-block text-center"></span>`;
        } else {
            buttons = iconBtn('fa-check', 'Accept', 'accepted', 'bg-emerald-100 hover:bg-emerald-200', 'text-emerald-700')
                    + iconBtn('fa-xmark', 'Reject', 'rejected', 'bg-red-100 hover:bg-red-200', 'text-red-600');
            idleIndicator = `<span class="w-2 h-2 rounded-full bg-yellow-400 inline-block text-center"></span>`;
        }
    } else if (s.status === 'accepted') {
        buttons = iconBtn('fa-flag-checkered', 'Complete',  'completed', 'bg-gray-100 hover:bg-gray-200',   'text-gray-600')
                + iconBtn('fa-user-slash',     'No-show',   'no_show',   'bg-orange-100 hover:bg-orange-200','text-orange-600')
                + iconBtn('fa-ban',            'Cancel',    'cancelled', 'bg-red-100 hover:bg-red-200',      'text-red-600');
        idleIndicator = `<span class="w-2 h-2 rounded-full bg-green-400 inline-block text-center"></span>`;
    } else if (s.status === 'completed' || s.status === 'no_show' || s.status === 'rejected') {
        buttons = iconBtn('fa-rotate-left', 'Undo', 'accepted', 'bg-gray-100 hover:bg-gray-200', 'text-gray-500');
        idleIndicator = `<span class="w-2 h-2 rounded-full bg-gray-300 inline-block text-center"></span>`;
    } else {
        return `<div class="flex justify-end"><span class="text-gray-200 text-[10px]">—</span></div>`;
    }

    return `
        <div class="relative flex items-center justify-end" style="min-height:28px;">
            <div class="action-idle text-center flex items-center gap-1 pointer-events-none">
                ${idleIndicator}
            </div>
            <div class="action-buttons flex items-center gap-1 text-center">
                ${buttons}
            </div>
        </div>
    `;
}

        function formatTimeRange(s) {
            const [start, end] = s.duration.split(' (')[0].split(' - ');
            return `${start} – ${end}`;
        }

        function formatHours(s) {
            const match = s.duration.match(/\((.*?)\)/);
            return match ? `(${match[1]})` : '';
        }

        function setSort(column) {
            if (sortColumn === column) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = column;
                sortDirection = 'asc';
            }
            sessionsPage = 0;
            renderSessions();
        }

        function updateSortIcons() {
            document.querySelectorAll('[id^="sort-"]').forEach(el => el.innerHTML = '');
            if (sortColumn) {
                const icon = sortDirection === 'asc' ? '↑' : '↓';
                const el = document.getElementById(`sort-${sortColumn}`);
                if (el) el.innerHTML = icon;
            }
        }

        let sessionsPage = 0;
        const SESSIONS_PER_PAGE = 5;
        let sortColumn = 'status';
        let sortDirection = 'asc';

function updateSummaryCounts() {
    const statuses = allSessions.map(s => s.status);

    const completedHours = allSessions
        .filter(s => s.status === 'completed')
        .reduce((sum, s) => sum + s.durationHours, 0);

    document.getElementById('statTotal').textContent     = allSessions.length;
    document.getElementById('statAccepted').textContent  = statuses.filter(s => s === 'accepted').length;
    document.getElementById('statPending').textContent   = statuses.filter(s => s === 'pending').length;
    document.getElementById('statCompleted').textContent = statuses.filter(s => s === 'completed').length;
    document.getElementById('statHours').textContent     = parseFloat(completedHours.toFixed(2)) + ' hrs';
}

        function renderSessions() {
            updateSortIcons();
            const tbody  = document.getElementById('sessionsTable');
            const search = document.getElementById('searchInput').value.toLowerCase();

            let filtered = allSessions.filter(s => {
                const searchable = [s.student, s.subject, s.subjectName, s.topic, s.date, s.time, s.mode, s.status, s.degreeProgram, s.yearLevel].join(' ').toLowerCase();
                const matchSearch = searchable.includes(search);
                const matchStatus = activeStatusFilters.length === 0 || activeStatusFilters.includes(s.status);
                return matchSearch && matchStatus;
            });

            filtered = sortSessions(filtered);

            // Update count label
            const label = document.getElementById('sessionCountLabel');
            if (label) label.textContent = filtered.length + ' Session' + (filtered.length !== 1 ? 's' : '') + ' found';

            if (!filtered.length) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-16 text-gray-400 text-xs italic">No sessions found.</td></tr>`;
                updateSessionsPagination(0, 0, filtered);
                return;
            }

            const total   = filtered.length;
            const maxPage = Math.max(0, Math.ceil(total / SESSIONS_PER_PAGE) - 1);
            if (sessionsPage > maxPage) sessionsPage = 0;

            const start   = sessionsPage * SESSIONS_PER_PAGE;
            const visible = filtered.slice(start, start + SESSIONS_PER_PAGE);

            updateSessionsPagination(total, maxPage, filtered);

            tbody.innerHTML = visible.map(s => `
                <tr class="session-row border-b border-gray-50 hover:bg-slate-50 transition">

                    <td class="px-5 py-4 align-middle text-gray-400 text-xs font-medium tabular-nums" style="width:5%;">
                        ${start + visible.indexOf(s) + 1}
                    </td>
                    <td class="px-5 py-4 align-middle" style="width:18%;">
                        <div class="hover-tooltip" data-full="${s.student} \n${s.yearLevel} - ${s.degreeProgram}">
                            <p class="font-bold text-slate-700 text-xs truncate">${s.student}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5 truncate">${s.yearLevel !== '—' ? s.yearLevel : ''} - ${s.degreeProgram !== '—' ? s.degreeProgram : ''}</p>
                        </div>
                    </td>

                    <td class="px-5 py-4 align-middle" style="width:13%;">
                        <div class="hover-tooltip" data-full="${s.subject} – ${s.subjectName}">
                            <p class="font-bold text-slate-700 text-xs">${s.subject}</p>
                            <p class="text-[10px] text-gray-400 truncate">${s.subjectName}</p>
                        </div>
                    </td>

                    <td class="px-5 py-4 align-middle" style="width:17%;">
                        <div class="hover-tooltip" data-full="${s.topic}">
                            <p class="text-xs text-slate-600 truncate">${s.topic}</p>
                        </div>
                    </td>

<td class="px-5 py-4 align-middle" style="width:15%;">
    <p class="text-xs font-medium text-slate-700">${s.date} ${formatHours(s)}</p>
    <p class="text-[10px] text-gray-400">${s.time}</p>
</td>

                    <td class="px-5 py-4 align-middle text-xs text-slate-500" style="width:10%;">${s.mode}</td>

                    <td class="px-5 py-4 align-middle" style="width:10%;">
<span class="${getStatusColor(s.status)} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80">
    ${getStatusLabel(s.status)}
</span>
                    </td>

                    <td class="px-5 py-4 align-middle" style="width:17%;">
                        <div class="flex gap-1 items-center justify-end">
                            ${renderActions(s)}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function sortSessions(data) {
            if (!sortColumn) return data;

            const statusOrder = {
                accepted: 1, pending: 2, completed: 3,
                cancelled: 4, no_show: 5, rejected: 6
            };

            return [...data].sort((a, b) => {
                let valA = a[sortColumn];
                let valB = b[sortColumn];

                if (sortColumn === 'status') {
                    valA = statusOrder[valA] ?? 999;
                    valB = statusOrder[valB] ?? 999;
                }
                if (sortColumn === 'date') {
                    valA = new Date(valA);
                    valB = new Date(valB);
                }
                if (sortColumn === 'duration') {
                    valA = a.start;
                    valB = b.start;
                }
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        }

        /* =========================
        NUMBERED PAGINATION (from student history style)
        ========================= */
        function getPageNumbers(current, totalPages) {
            if (totalPages <= 8) return Array.from({ length: totalPages }, (_, i) => i + 1);
            if (current <= 4)    return [1, 2, 3, 4, 5, '...', totalPages];
            if (current >= totalPages - 3) return [1, '...', totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
            return [1, '...', current - 1, current, current + 1, '...', totalPages];
        }

        function updateSessionsPagination(total, maxPage, filtered) {
            const footer  = document.getElementById('sessionsPaginationFooter');
            const info    = document.getElementById('sessionsPageInfo');
            const btnWrap = document.getElementById('sessionsPaginationButtons');

            if (!footer) return;
            if (total === 0) { footer.style.display = 'none'; return; }

            footer.style.display    = 'flex';
            footer.style.flexDirection  = 'column';
            footer.style.alignItems = 'center';

            const currentPageNum = sessionsPage + 1; // 1-based for display
            const totalPages     = maxPage + 1;
            const start          = sessionsPage * SESSIONS_PER_PAGE;
            const end            = Math.min(start + SESSIONS_PER_PAGE, total);

            info.textContent = `${start + 1}–${end} of ${total}`;

            const pages = getPageNumbers(currentPageNum, totalPages);

            btnWrap.innerHTML = '';

            // Prev button
            const prevBtn = document.createElement('button');
            prevBtn.className = 'pagination-btn' + (sessionsPage === 0 ? ' opacity-40 cursor-not-allowed' : '');
            prevBtn.disabled  = sessionsPage === 0;
            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-[10px]"></i>';
            prevBtn.addEventListener('click', () => { if (sessionsPage > 0) { sessionsPage--; renderSessions(); } });
            btnWrap.appendChild(prevBtn);

            // Numbered page buttons
            pages.forEach(page => {
                const btn = document.createElement('button');
                btn.textContent = page;

                if (page === '...') {
                    btn.className = 'w-8 h-8 text-xs font-bold rounded-lg bg-white border border-gray-200 text-gray-400 cursor-default';
                    btn.disabled  = true;
                } else if (page === currentPageNum) {
                    btn.className = 'w-8 h-8 text-xs font-bold rounded-lg bg-[#1a3c2f] text-white shadow-sm border border-[#1a3c2f]';
                } else {
                    btn.className = 'w-8 h-8 text-xs font-bold rounded-lg bg-white border border-gray-200 text-slate-500 hover:bg-gray-100 transition';
                    btn.addEventListener('click', () => { sessionsPage = page - 1; renderSessions(); });
                }

                btnWrap.appendChild(btn);
            });

            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.className = 'pagination-btn' + (sessionsPage >= maxPage ? ' opacity-40 cursor-not-allowed' : '');
            nextBtn.disabled  = sessionsPage >= maxPage;
            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-[10px]"></i>';
            nextBtn.addEventListener('click', () => { if (sessionsPage < maxPage) { sessionsPage++; renderSessions(); } });
            btnWrap.appendChild(nextBtn);
        }

        /* =========================
        CONFIRMATION MODAL
        ========================= */
        const confirmModal    = document.getElementById('confirmModal');
        const confirmModalBox = document.getElementById('confirmModalBox');
        const confirmTitle    = document.getElementById('confirmTitle');
        const confirmBody     = document.getElementById('confirmBody');
        const confirmMeta     = document.getElementById('confirmMeta');
        const confirmOkBtn    = document.getElementById('confirmOkBtn');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmIconWrap = document.getElementById('confirmIconWrap');

        confirmModal.addEventListener('click', (e) => {
            if (!confirmModalBox.contains(e.target)) closeConfirmModal();
        });
        confirmCancelBtn.addEventListener('click', closeConfirmModal);

        function closeConfirmModal() {
            confirmModal.style.display = 'none';
            confirmOkBtn.onclick = null;
        }

        function openConfirmModal({ title, body, meta, variant, onConfirm }) {
            const variants = {
                accept:  { icon: iconCheck(),   iconBg: 'bg-emerald-100', iconColor: '#059669', btnBg: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
                reject:  { icon: iconX(),       iconBg: 'bg-red-100',     iconColor: '#dc2626', btnBg: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
                neutral: { icon: iconInfo(),    iconBg: 'bg-gray-100',    iconColor: '#64748b', btnBg: 'bg-gray-700 hover:bg-gray-800',        label: 'Confirm' },
            };

            const v = variants[variant] || variants.neutral;
            confirmIconWrap.className = `w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 ${v.iconBg}`;
            confirmIconWrap.innerHTML = v.icon(v.iconColor);
            confirmTitle.textContent  = title;
            confirmBody.innerHTML     = body;
            confirmMeta.innerHTML     = meta || '';
            confirmMeta.style.display = meta ? 'block' : 'none';
            confirmOkBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnBg}`;
            confirmOkBtn.textContent = v.label;
            confirmOkBtn.onclick = () => { closeConfirmModal(); onConfirm(); };
            confirmModal.style.display = 'flex';
        }

        function iconCheck() { return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
        function iconX()     { return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`; }
        function iconInfo()  { return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/><path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${color}"/></svg>`; }

        function toggleModalText(id) {
            const textEl = document.getElementById(`modal-text-${id}`);
            const moreBtn = document.getElementById(`modal-more-${id}`);
            const lessBtn = document.getElementById(`modal-less-${id}`);
            const isCollapsed = textEl.classList.contains('line-clamp-1');
            if (isCollapsed) {
                textEl.classList.remove('line-clamp-1');
                textEl.classList.add('line-clamp-none');
                moreBtn?.classList.add('hidden');
                lessBtn?.classList.remove('hidden');
            } else {
                textEl.classList.add('line-clamp-1');
                textEl.classList.remove('line-clamp-none');
                lessBtn?.classList.add('hidden');
                moreBtn?.classList.remove('hidden');
            }
        }

        function updateStatus(id, status) {
            const req = allSessions.find(s => s.id == id);
            if (!req) return;

            if (status === 'accepted' && req.status !== 'completed' && hasConflict(req)) {
                const conflict = allSessions.find(s =>
                    s.id !== req.id && s.status === 'accepted' && s.date === req.date
                );
                const conflictInfo = conflict
                    ? `Conflicts with <strong>${conflict.student}</strong> (${conflict.duration.split(' (')[0]}) on ${conflict.date}.`
                    : 'This session overlaps with an already-accepted booking.';
                showConflictBanner(conflictInfo);
                return;
            }

            const isUncomplete = status === 'accepted' && req.status === 'completed';
            const isClaiming   = status === 'accepted' && req.status === 'pending' && req.is_open;

            const dialogConfig = {
                accepted: isUncomplete ? {
                    title: 'Revert to accepted?',
                    body:  'This will mark the session as accepted again, reversing the completed status.',
                    variant: 'neutral',
                } : isClaiming ? {
                    title: 'Claim Open Session?',
                    body:  'You are about to claim this session. It will be permanently assigned to you.',
                    variant: 'accept',
                } : {
                    title: 'Accept booking?',
                    body:  'The student will be notified that their session has been approved.',
                    variant: 'accept',
                },
                rejected:  { title: 'Reject booking?',    body: 'The student will be notified that their session request was declined.',       variant: 'reject'  },
                completed: { title: 'Mark as completed?', body: 'This will mark the session as done.',                                         variant: 'neutral' },
                no_show:   { title: 'Mark as no-show?',   body: 'This will record that the student did not attend the session.',               variant: 'reject'  },
                cancelled: { title: 'Cancel session?',    body: 'This will cancel the accepted session.',                                      variant: 'reject'  },
            };

            const cfg = dialogConfig[status] || { title: 'Confirm action', body: 'Are you sure you want to proceed?', variant: 'neutral' };

            const metaHtml = `
                <div>
                    <div class="flex justify-between items-start gap-2">
                        <span class="text-gray-400">Student</span>
                        <div class="flex flex-col items-end max-w-[160px]">
                            <span id="modal-text-student-${req.id}" class="font-medium text-gray-700 text-right topic-text line-clamp-1 truncate" style="max-width:190px">${req.student}</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-gray-400">Subject</span>
                    <span class="font-medium text-gray-700 text-right truncate max-w-[90%]">${req.subject}${req.subjectName ? ' – ' + req.subjectName : ''}</span>
                </div>
                <div>
                    <div class="flex justify-between items-start gap-2">
                        <span class="text-gray-400">Topic</span>
                        <div class="flex flex-col items-end max-w-[180px]">
                            <span id="modal-text-topic-${req.id}" class="font-medium text-gray-700 text-right topic-text line-clamp-1 truncate" style="max-width:190px">${req.topic}</span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-gray-400">Date</span>
                    <span class="font-medium text-gray-700 text-right">${req.date}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-gray-400">Time</span>
                    <span class="font-medium text-gray-700 text-right">${req.time}</span>
                </div>
                <div class="flex justify-between gap-2">
                    <span class="text-gray-400">Mode</span>
                    <span class="font-medium text-gray-700 text-right">${req.mode}</span>
                </div>
            `;

            openConfirmModal({
                title:     cfg.title,
                body:      cfg.body,
                meta:      metaHtml,
                variant:   cfg.variant,
                onConfirm: () => commitStatus(id, status, req),
            });
        }

        function commitStatus(id, status, target) {
            showLoadingBanner();

            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('booking_id', id);
            formData.append('booking_status', status);

            fetch(sessionsUrl, { method: 'POST', body: formData })
                .then(res => {
                    if (!res.ok) throw new Error('Request failed');

                    target.status = status;

                    if (status === 'accepted') {
                        const conflictingIds = getConflictingPendingIds(target);
                        if (conflictingIds.length > 0) {
                            let completed = 0;
                            conflictingIds.forEach(conflictId => {
                                const conflictSession = allSessions.find(s => s.id == conflictId);
                                if (conflictSession) conflictSession.status = 'rejected';
                                const fd = new FormData();
                                fd.append('_token', csrfToken);
                                fd.append('booking_id', conflictId);
                                fd.append('booking_status', 'rejected');
                                fetch(sessionsUrl, { method: 'POST', body: fd })
                                    .then(() => {
                                        completed++;
                                        if (completed === conflictingIds.length) {
                                            hideLoadingBanner();
                                            renderSessions();
                                            updateSummaryCounts();
                                            showAutoRejectBanner(conflictingIds.length);
                                        }
                                    })
                                    .catch(err => {
                                        hideLoadingBanner();
                                        console.error('Auto-reject failed for id', conflictId, err);
                                    });
                            });
                            renderSessions();
                            updateSummaryCounts();
                            return;
                        }
                    }

                    hideLoadingBanner();
                    updateSummaryCounts();
                    renderSessions();
                })
                .catch(err => {
                    hideLoadingBanner();
                    showBanner('errorBanner', `
                        <div style="border:1px solid #fca5a5; background:#fef2f2; border-radius:8px;">
                            <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                                <div style="flex-shrink:0; margin-top:2px;">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                        <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                                        <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                                        <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                                    </svg>
                                </div>
                                <div style="flex:1; color:#b91c1c; line-height:1.5;">
                                    <span style="font-weight:600;">Update failed —</span> please check your connection and try again.
                                </div>
                                <button onclick="document.getElementById('errorBanner').remove()"
                                    style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                            </div>
                        </div>
                    `);
                    console.error('commitStatus failed:', err);
                });
        }

/* =========================
INIT
========================= */
document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('sidebarToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    const profileTrigger  = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    profileTrigger.addEventListener('click', e => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });

    window.addEventListener('click', () => {
        profileDropdown.classList.remove('show');
        document.getElementById('statusDropdown')?.classList.add('hidden');
    });

    document.getElementById('searchInput').addEventListener('input', () => { sessionsPage = 0; renderSessions(); });

    renderSessions();
});
    </script>
    </body>
