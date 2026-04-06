<?php

    use function Livewire\Volt\{layout, state, mount};
    use App\Models\Bookings;
    use App\Models\MentorProfiles;

    layout('layouts.app');

    state(['sessions' => []]);

    mount(function () {

        abort_if(!auth()->user()->isMentor(), 403);

        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

        if (request()->isMethod('POST') && request()->has('id') && request()->has('status')) {
        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
        
        if ($mentorProfile) {
            $booking = Bookings::where('id', request('id'))
                ->where('mentor_id', $mentorProfile->id)
                ->first();
            if ($booking) {
                $booking->booking_status = request('status');
                if (request('status') === 'completed') {
                    $booking->completed_at = now();
                }
                $booking->save();
            }
        }
        return response()->json(['success' => true]);
    }
        if (!$mentorProfile) return;

        // same logic as sessions.blade.php
        Bookings::where('mentor_id', $mentorProfile->id)
            ->where('booking_status', 'accepted')
            ->whereDate('date', '<', today())
            ->update([
                'booking_status' => 'completed',
                'completed_at' => now(),
            ]);

    $this->sessions = Bookings::with(['student.user','subject'])
        ->where('mentor_id', $mentorProfile->id)
        ->get()
        ->map(function ($b) {

            $start = \Carbon\Carbon::parse($b->schedule_start);
            $end   = \Carbon\Carbon::parse($b->schedule_end);

            return [
                'id' => $b->id,

                // ✅ FIX: use "student" (NOT mentee)
                'student' => optional(optional($b->student)->user)->firstName
                    ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                    : 'Unknown',

                'subject' => optional($b->subject)->code ?? 'N/A',

                // keep same format used in sessions.blade
                'date' => $b->date
                    ? \Carbon\Carbon::parse($b->date)->format('Y-m-d')
                    : null,

                'start' => $start->format('H:i'),
                'end'   => $end->format('H:i'),

                'status' => $b->booking_status // ✅ NO MODIFICATION
            ];
        })
        ->values()
        ->toArray();

    });

    ?>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>LRC PeerConnect Dashboard</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <style>
            :root {
                --sidebar-green: #1a3c2f;
                --header-maroon: #7b1d1d;
                --bg-light: #f4f7f6;
                --header-height: 80px;
                --sidebar-width: 260px;
                --sidebar-collapsed-width: 72px;
            }

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
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 20px;
                gap: 12px;
                flex-shrink: 0;
                overflow: hidden;
                transition: padding 0.3s, justify-content 0.3s;
            }
            .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }

            .logo-icon { flex-shrink: 0; font-size: 1.3rem; width: 32px; text-align: center; }

            .logo-text {
                font-size: 1.2rem;
                font-weight: 700;
                white-space: nowrap;
                overflow: hidden;
                opacity: 1;
                max-width: 200px;
                transition: opacity 0.2s, max-width 0.3s;
            }
            .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }

            /* ── Nav items ── */
            .nav-item {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 18px 20px;
                color: rgba(255,255,255,0.7);
                text-decoration: none;
                transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
                white-space: nowrap;
                position: relative;
                text-align: left;
                background: transparent;
                border: none;
                width: 100%;
                cursor: pointer;
                font-size: 1.04rem;
                justify-content: flex-start;
            }
            .sidebar.collapsed .nav-item { justify-content: center; padding: 18px 0; }

            .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
            .sidebar.collapsed .nav-item i { width: 32px; margin: 0; }

            .nav-item span {
                overflow: hidden; opacity: 1; max-width: 200px;
                transition: opacity 0.2s, max-width 0.3s;
            }
            .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

            .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
            .nav-item.active {
                background: var(--bg-light);
                color: var(--header-maroon);
                font-weight: 700;
                border-radius: 0;
                width: calc(100% + 1px);
                z-index: 10;
            }

            /* Tooltips */
            .nav-item::after {
                content: attr(data-tooltip);
                position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
                margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
                padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
                white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
                pointer-events: none; z-index: 100;
            }
            .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

            /* Logout section */
            .sidebar-footer { padding: 6px 0; border-top: 1px solid rgba(255,255,255,0.1); }

            /* ── TOGGLE BUTTON ── */
            .sidebar-toggle-btn {
                position: absolute;
                right: -16px;
                top: 3%;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #7b1d1d;
                font-size: 13px;
                z-index: 50;
                box-shadow: 0 2px 8px rgba(0,0,0,0.25);
                transition: background 0.2s;
                flex-shrink: 0;
            }
            .sidebar-toggle-btn:hover { background: #dfcece; }
            .sidebar-toggle-btn .toggle-icon {
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex; align-items: center; justify-content: center;
            }
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

            .cal-header-day { font-size: 11px; font-weight: 800; color: #94a3b8; text-align: center; padding-bottom: 10px; text-transform: uppercase; }
            .cal-day { aspect-ratio: 1/1; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 13px; font-weight: 500; }
            .cal-today { background: #fee2e2 !important; color: var(--header-maroon) !important; font-weight: 800; }
            .cal-selected { border: 2px solid var(--header-maroon); background: #f8fafc; }

            .stats-overview-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb; width: 100%; }
            .stats-header { padding: 12px 24px; background: #f8fafc; font-weight: 700; font-size: 0.9rem; color: #1e293b; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
            .stats-body { display: grid; grid-template-columns: repeat(2, 1fr); background: white; width: 100%; }
            .stats-column { padding: 24px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; min-width: 0; }
            .stats-column:nth-child(2n) { border-right: none; }
            .stats-column-title { font-weight: 600; margin-bottom: 15px; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }

            .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer; }
            .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--sidebar-green); }
            .stat-card i { font-size: 24px; color: var(--sidebar-green); }

            .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
            .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
            .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
            .weekly-table{table-layout:fixed;width:100%;}
            .weekly-table th, .weekly-table td{width:16%;}
            .schedule-block{font-size:9px;line-height:1.2;padding:2px 4px;margin-bottom:2px;border-radius:4px;background:#d1fae5;color:#065f46;}

            .notif-dot {width: 6px;height: 6px;background: #3b82f6;border-radius: 50%;position: top;bottom: 6px;}
            @keyframes slideDown {from { opacity: 0; transform: translateY(-6px); }to   { opacity: 1; transform: translateY(0); }}
            #confirmMeta {max-height: 200px;overflow-y: auto;}
            .topic-text {
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: normal;
}

.topic-text.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.topic-text.line-clamp-none {
    display: block;
    overflow: visible;
}

#statusToast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    background: #1e293b;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.2s, transform 0.2s;
    pointer-events: none;
    min-width: 200px;
}
#statusToast.show {
    opacity: 1;
    transform: translateY(0);
}
#statusToast .toast-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
        </style>
    </head>

    <body>
        <div class="app-wrapper">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo-container">
                    <i class="fa-solid fa-graduation-cap logo-icon"></i>
                    <span class="logo-text">LRC PeerConnect</span>
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
                    <div class="text-lg">Welcome, {{ auth()->user()->user_roles}} <span class="font-bold">{{ auth()->user()->name }}</span></div>
                    
                    <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                        <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr(auth()->user()->name,0,2)) }}
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200" id="dropdownArrow"></i>
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
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative">
        
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>

            <input 
                type="text" 
                id="globalSearchInput"
                placeholder="Search ALL sessions (student, subject, date, status)..."
                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800"
            >
        </div>

        <!-- RESULTS DROPDOWN -->
        <div id="globalSearchResults"
            class="absolute left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-72 overflow-y-auto z-50 hidden">
        </div>

    </div>
                    <div class="grid grid-cols-3 gap-8">
                        <div class="col-span-2 space-y-8">
                            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 min-h-[460px] flex flex-col">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
                                        <p class="text-s text-gray-500" id="tableSubtitle"></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <div class="relative w-48">
                                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                            <input type="text" id="liveSearchInput" placeholder="Search names..." class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                                        </div>
                                        <select id="statusFilter" class="table-filter-select">
                                            <option value="">All</option>
                                            <option value="pending">Pending</option>
                                            <option value="accepted">Accepted</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex-grow">
                                    <table class="w-full text-left text-sm table-fixed">
<thead class="text-gray-400 border-b">
    <tr>
        <th class="pb-3 text-[10px] tracking-wider" style="width:35%">
            <button id="sortHead-student" onclick="toggleSort('student')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                Student <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
            </button>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:25%">
            <button id="sortHead-start" onclick="toggleSort('start')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#7b1d1d;">
                Time <span class="sort-icon"><i class="fa-solid fa-arrow-up" style="font-size:8px;"></i></span>
            </button>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
            <button id="sortHead-subject" onclick="toggleSort('subject')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                Subject <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
            </button>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
            <button id="sortHead-status" onclick="toggleSort('status')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                Status <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
            </button>
        </th>
    </tr>
</thead>
                                        <tbody id="tableBody"></tbody>
                                    </table>
                                </div>

                                <div class="mt-6 pt-4 border-t border-gray-50 flex items-center justify-between">
                                    <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">Showing 0 results</div>
                                    <div class="flex gap-2">
                                        <button id="prevBtn" class="pagination-btn"><i class="fa-solid fa-chevron-left"></i></button>
                                        <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                                    </div>
                            </div>
                        </div>

                                        <!-- WEEKLY SCHEDULE -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold text-slate-800">Weekly Schedule</h2>
                                <span class="text-xs text-gray-400" id="weeklyScheduleRange">8:00 AM – 6:00 PM</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="weekly-table text-xs text-center border">
                                <thead class="bg-gray-50">
    <tr>
    <th class="p-2 border">Time</th>
    <th class="p-2 border" id="monHead"></th>
    <th class="p-2 border" id="tueHead"></th>
    <th class="p-2 border" id="wedHead"></th>
    <th class="p-2 border" id="thuHead"></th>
    <th class="p-2 border" id="friHead"></th>
    </tr>
                                </thead>

                                    <tbody id="weeklyScheduleBody"></tbody>

                            </table>
                        </div>
                    </div>
                    </div>

                        <div class="flex flex-col gap-6">
                            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-6">
                                    <div class="flex gap-4">
                                        <button onclick="changeMonth(-1)" class="text-gray-400 hover:text-maroon-800"><i class="fa-solid fa-chevron-left text-xs"></i></button>
                                        <span id="monthDisplay" class="text-sm font-bold w-24 text-center"></span>
                                        <button onclick="changeMonth(1)" class="text-gray-400 hover:text-maroon-800"><i class="fa-solid fa-chevron-right text-xs"></i></button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-7 gap-1 mb-2">
                                    <div class="cal-header-day">S</div><div class="cal-header-day">M</div><div class="cal-header-day">T</div>
                                    <div class="cal-header-day">W</div><div class="cal-header-day">T</div><div class="cal-header-day">F</div><div class="cal-header-day">S</div>
                                </div>
                                <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>

                                <div class="mt-6 pt-6 border-t border-gray-50">
                                    <div class="bg-slate-900 rounded-xl p-4 shadow-inner">
                                        <div id="liveClock" class="text-3xl font-mono font-black text-white tracking-widest text-center">00:00:00</div>
                                        <div id="liveDate" class="text-[10px] font-medium text-slate-400 text-center mt-1 uppercase">Saturday, March 14</div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Requests</h3>
                                        <span id="pendingBadge" class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full"></span>
                                </div>
                                <div class="flex flex-col gap-4">
                                    <div id="pendingRequestsList" class="flex flex-col gap-4"></div>
                                    <div class="flex items-center justify-between group">
                                    </div>
                                </div>
                                <button id="toggleRequestsBtn" class="w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">
                                    View All Requests
                                </button>
                                <div id="pendingPagination" class="hidden mt-2 flex items-center justify-between px-1">
                                        <span id="pendingPageInfo" class="text-[10px] text-gray-400"></span>
                                        <div class="flex gap-1">
                                            <button id="pendingPrevBtn" class="pagination-btn opacity-30 cursor-not-allowed" disabled>
                                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                            </button>
                                            <button id="pendingNextBtn" class="pagination-btn">
                                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                            </button>
                                        </div>
                                </div>
                            </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <h3 class="font-bold text-slate-800 text-sm mb-4">Quick Actions</h3>

        <div id="quickActionsList" class="flex flex-col gap-3">
            <!-- Dynamic content -->
        </div>
    </div>
                        </div>
                    </div>
</main>
            </div>

            <!-- CONFIRMATION MODAL — must be inside .app-wrapper (Livewire single root) -->
            <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">

                    <div class="flex items-center gap-3 mb-3">
                        <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                        <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
                    </div>

                    <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>

                    <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>

                    <div class="flex justify-end gap-3">
                        <button id="confirmCancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button id="confirmOkBtn"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                            Confirm
                        </button>
                    </div>

</div>
            </div>

            <div id="statusToast">
                <div class="toast-spinner"></div>
                <span id="statusToastMsg">Updating status...</span>
            </div>

        </div>

    <script>
    const globalSearchInput = document.getElementById("globalSearchInput");
    const globalSearchResults = document.getElementById("globalSearchResults");

    function toggleName(id, fullName) {
    const nameEl = document.getElementById('name-' + id);
    const btn = document.getElementById('toggle-' + id);
    if (!nameEl || !btn) return;
    if (btn.innerText === 'Show more') {
        nameEl.style.whiteSpace = 'normal';
        nameEl.style.overflow = 'visible';
        nameEl.style.textOverflow = 'unset';
        nameEl.style.wordBreak = 'break-all';
        btn.innerText = 'Show less';
    } else {
        nameEl.style.whiteSpace = 'nowrap';
        nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis';
        nameEl.style.wordBreak = 'normal';
        btn.innerText = 'Show more';
    }
}
    function initDashboard(){

        renderCalendar();

        refreshSchedules(); // ✅ handles weekly + headers

        updateTableDate();
        renderPendingRequests();
        updateClock();
    }

    function universalSearch(query) {

        const q = query.toLowerCase();

        // 🔹 Merge sessions + pending
        const merged = [
            ...allSessions.map(s => ({ ...s, type: "Session" })),
            ...pendingRequests.map(p => ({ ...p, status: "Pending", type: "Pending" }))
        ];

        return merged.filter(item => {

            return (
                item.student.toLowerCase().includes(q) ||
                item.subject.toLowerCase().includes(q) ||
                item.status.toLowerCase().includes(q) ||
                item.date.includes(q)
            );

        });
    }

    function renderGlobalResults(results) {

        if (!results.length) {
            globalSearchResults.innerHTML = `
                <div class="p-4 text-xs text-gray-400 text-center">
                    No matching sessions found
                </div>`;
            return;
        }

        globalSearchResults.innerHTML = results.map(r => {

            return `
            <div class="p-3 border-b last:border-0 hover:bg-gray-50 cursor-pointer">

                <div class="flex justify-between items-center">
                    
                    <div>
                        <p class="text-sm font-bold text-slate-700">
                            ${r.student}
                        </p>

                        <p class="text-xs text-gray-400">
                            ${r.subject} • ${formatTimeTo12Hour(r.start)} - ${formatTimeTo12Hour(r.end)}
                        </p>

                        <p class="text-[10px] text-gray-400">
                            ${r.date}
                        </p>
                    </div>

                    <span class="${getStatusColor(r.status)} text-[10px] px-2 py-1 rounded font-bold border">
                        ${r.status}
                    </span>

                </div>

            </div>
            `;
        }).join('');
    }

    globalSearchInput.addEventListener("input", function () {

        const value = this.value.trim();

        if (value === "") {
            globalSearchResults.classList.add("hidden");
            return;
        }

        const results = universalSearch(value);

        renderGlobalResults(results);
        globalSearchResults.classList.remove("hidden");

    });

    window.addEventListener("click", function(e) {

        if (!e.target.closest("#globalSearchInput")) {
            globalSearchResults.classList.add("hidden");
        }

    });

    function updateWeekHeaders(){

    const {monday} = getCurrentWeekRange();

    const days=["monHead","tueHead","wedHead","thuHead","friHead"];

    days.forEach((id,i)=>{

    const d = new Date(monday);
    d.setDate(monday.getDate()+i);

    document.getElementById(id).innerText =
    d.toLocaleDateString('en-US',{
    weekday:'short',
    month:'short',
    day:'numeric'
    });

    });

    }
    function refreshSchedules(){
        applyFilters();
        generateWeeklySchedule();
        updateWeekHeaders();
        renderQuickActions();
        renderPendingRequests(); // ✅ ADD THIS
    }
            // DOM Elements
            const sidebar = document.getElementById('sidebar');
            const profileTrigger = document.getElementById('profileTrigger');
            const profileDropdown = document.getElementById('profileDropdown');
            const searchInput = document.getElementById('liveSearchInput');
            const statusFilter = document.getElementById('statusFilter');
            const charts = []; 
            

            // Sidebar toggle
            document.getElementById('sidebarToggle').addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
            });

            profileTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            window.addEventListener('click', () => {
                if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            });

function updateClock() {
    const now = new Date(new Date().toLocaleString("en-US", {timeZone:"Asia/Manila"}));
    document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
    document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
}
setInterval(updateClock, 1000);

            // Local State
    const allSessions = @json($this->sessions);

    let pendingRequests = [];
    refreshLocalState();

            let showAllRequests = false;

const today = new Date(new Date().toLocaleString("en-US",{timeZone:"Asia/Manila"}));
const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

let selectedDateStr = todayStr;
let viewDate = new Date(today.getFullYear(), today.getMonth(), 1);

            // Table Logic

// Pagination state for daily table
let tablePage = 0;
const TABLE_PER_PAGE = 5;

document.getElementById('prevBtn').addEventListener('click', () => { tablePage--; applyFilters(); });
document.getElementById('nextBtn').addEventListener('click', () => { tablePage++; applyFilters(); });

let sortColumn = 'start'; // default sort by time
let sortDirection = 'asc'; // default ascending

function applyFilters() {
    const tbody          = document.getElementById('tableBody');
    const searchTerm     = searchInput.value.toLowerCase();
    const selectedStatus = statusFilter.value;

    let filtered = allSessions.filter(item => {
        const matchesDate   = item.date === selectedDateStr;
        const matchesSearch = item.student.toLowerCase().includes(searchTerm);
        const matchesStatus = selectedStatus === '' || item.status === selectedStatus;
        return matchesDate && matchesSearch && matchesStatus;
    });

    // ── SORT ──
    filtered.sort((a, b) => {
        let aVal, bVal;
        if (sortColumn === 'start') {
            aVal = a.start; bVal = b.start;
        } else if (sortColumn === 'student') {
            aVal = a.student.toLowerCase(); bVal = b.student.toLowerCase();
        } else if (sortColumn === 'subject') {
            aVal = a.subject.toLowerCase(); bVal = b.subject.toLowerCase();
        } else if (sortColumn === 'status') {
            aVal = a.status; bVal = b.status;
        }
        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });

    const total   = filtered.length;
    const maxPage = Math.max(0, Math.ceil(total / TABLE_PER_PAGE) - 1);
    if (tablePage > maxPage) tablePage = 0;

    const start   = tablePage * TABLE_PER_PAGE;
    const visible = filtered.slice(start, start + TABLE_PER_PAGE);

    if (!total) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No matching sessions found.</td></tr>`;
    } else {
        tbody.innerHTML = visible.map(row => `
            <tr class="border-b last:border-0 hover:bg-slate-50 transition">
                <td class="py-4 font-bold text-slate-700" style="width:35%">
                    <div style="max-width:260px;">
                        <div id="name-${row.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;" title="${row.student}">${row.student}</div>
                        <button onclick="toggleName('${row.id}','${row.student.replace(/'/g,"\\'")}')" id="toggle-${row.id}" style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:2px;background:none;border:none;cursor:pointer;padding:0;display:none;">Show more</button>
                    </div>
                </td>
                <td class="text-slate-500" style="width:25%;white-space:nowrap;">${formatTimeTo12Hour(row.start)} - ${formatTimeTo12Hour(row.end)}</td>
                <td class="text-slate-600 truncate" style="width:20%">${row.subject}</td>
                <td style="width:20%">
                    <span class="${getStatusColor(row.status)} font-bold text-[10px] px-2 py-1 rounded border">
                        ${getStatusLabel(row.status)}
                    </span>
                </td>
            </tr>
        `).join('');

        visible.forEach(row => {
            const nameEl   = document.getElementById('name-' + row.id);
            const toggleEl = document.getElementById('toggle-' + row.id);
            if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) {
                toggleEl.style.display = 'block';
            }
        });
    }

    // Update sort header indicators
    ['student','start','subject','status'].forEach(col => {
        const el = document.getElementById('sortHead-' + col);
        if (!el) return;
        const icon = el.querySelector('.sort-icon');
        if (sortColumn === col) {
            el.style.color = '#7b1d1d';
            icon.innerHTML = sortDirection === 'asc'
                ? '<i class="fa-solid fa-arrow-up" style="font-size:8px;"></i>'
                : '<i class="fa-solid fa-arrow-down" style="font-size:8px;"></i>';
        } else {
            el.style.color = '#94a3b8';
            icon.innerHTML = '<i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i>';
        }
    });

    document.getElementById('pageIndicator').innerText =
        total ? `Showing ${start + 1}–${Math.min(start + TABLE_PER_PAGE, total)} of ${total}` : 'Showing 0 results';

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    prevBtn.disabled = tablePage === 0;
    nextBtn.disabled = tablePage >= maxPage;
    prevBtn.classList.toggle('opacity-30', tablePage === 0);
    nextBtn.classList.toggle('opacity-30', tablePage >= maxPage);
}

function toggleSort(col) {
    if (sortColumn === col) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = col;
        sortDirection = 'asc';
    }
    tablePage = 0;
    applyFilters();
}

    function formatTimeTo12Hour(timeStr) {
        const [hour, minute] = timeStr.split(':');
        let h = parseInt(hour);
        const ampm = h >= 12 ? 'PM' : 'AM';

        h = h % 12;
        h = h ? h : 12; // 0 → 12

        return `${h}:${minute} ${ampm}`;
    }

    function getCurrentWeekRange(){

    const selected = new Date(selectedDateStr); // ✅ USE SELECTED DATE

    const day = selected.getDay();
    const diff = selected.getDate() - day + (day === 0 ? -6 : 1);

    // ❗ DO NOT mutate original date
    const monday = new Date(selected);
    monday.setDate(diff);
    monday.setHours(0,0,0,0);

    const friday = new Date(monday);
    friday.setDate(monday.getDate() + 4);

    return { monday, friday };

    }

function generateWeeklySchedule(){

    const tbody=document.getElementById("weeklyScheduleBody");
    tbody.innerHTML="";

    const startHour=8;
    const week=getCurrentWeekRange();

    function timeToMinutes(t){
    const [h,m]=t.split(":").map(Number);
    return h*60+m;
    }

    // Compute dynamic end hour from the latest session end in this week
    const weekSessions = allSessions.filter(s => {
        if(!s.date || !s.end) return false;
        if(!['accepted','pending','completed'].includes(s.status)) return false;
        const d = new Date(s.date + "T00:00:00").setHours(0,0,0,0);
        return d >= week.monday.getTime() && d <= week.friday.getTime();
    });

    let endHour = 15;
    if(weekSessions.length) {
        const latestEndMinutes = Math.max(...weekSessions.map(s => timeToMinutes(s.end)));
        endHour = Math.ceil(latestEndMinutes / 60);
    }
    endHour = Math.max(endHour, startHour + 2);
    endHour = Math.min(endHour, 22);

    // Update the header range label dynamically
    const fmtHour = h => {
        const ampm = h >= 12 ? 'PM' : 'AM';
        const display = h % 12 || 12;
        return `${display}:00 ${ampm}`;
    };
    const rangeEl = document.getElementById('weeklyScheduleRange');
    if(rangeEl) rangeEl.innerText = `${fmtHour(startHour)} – ${fmtHour(endHour)}`;

    const days=["Monday","Tuesday","Wednesday","Thursday","Friday"];
    for(let hour=startHour;hour<endHour;hour++){

    for(let min of [0,30]){

    const row=document.createElement("tr");

    const timeCell=document.createElement("td");
    timeCell.className="p-3 text-gray-500 font-semibold";

    const displayHour = hour>12 ? hour-12 : hour;
    const ampm = hour>=12 ? "PM":"AM";

    timeCell.innerText=`${displayHour}:${min===0?'00':'30'} ${ampm}`;

    row.appendChild(timeCell);

    days.forEach(day=>{

    const cell=document.createElement("td");
    cell.className="p-2";

    const slotStart=hour*60+min;
    const slotEnd=slotStart+30;

const sessions = allSessions.filter(s => {

    // ❌ ignore invalid or old injected data
    if(!s.date || !s.start || !s.end) return false;

    const date = new Date(s.date + "T00:00:00");
    const dayName = date.toLocaleDateString('en-US',{weekday:'long'});

    const d = new Date(date).setHours(0,0,0,0);
    const m = week.monday.getTime();
    const f = week.friday.getTime();

    if(!(d >= m && d <= f)) return false;
    if(dayName !== day) return false;

    // ✅ ONLY show real statuses
    if(!['accepted','pending','completed'].includes(s.status)) return false;

    const sessionStart = timeToMinutes(s.start);
    const sessionEnd = timeToMinutes(s.end);

    return sessionStart < slotEnd && sessionEnd > slotStart;

});

    if(sessions.length){

cell.innerHTML = sessions.map(s => {

    let colorClass = '';

    if(s.status === 'pending'){
        colorClass = 'bg-yellow-100 text-yellow-700 border border-yellow-300';
    }else if(s.status === 'accepted'){
        colorClass = 'bg-green-100 text-green-700 border border-green-300';
    }else if(s.status === 'completed'){
        colorClass = 'bg-gray-100 text-gray-600 border border-gray-300';
    }

    return `
    <div class="schedule-block ${colorClass}">
        ${s.subject}<br>
        ${formatTimeTo12Hour(s.start)} - ${formatTimeTo12Hour(s.end)}
    </div>
    `;
}).join("");

    }

    row.appendChild(cell);

    });

    tbody.appendChild(row);

    }
    }
    }

        function getStatusColor(status) {
            switch (status) {
                case 'accepted':  return 'text-blue-700 bg-blue-100 border-blue-300';
                case 'completed': return 'text-gray-900 bg-gray-100 border-gray-400';
                case 'closed':    return 'text-gray-500 bg-gray-100 border-gray-300';
                case 'pending':   return 'text-yellow-700 bg-yellow-100 border-yellow-300';
                case 'rejected':  return 'text-red-700 bg-red-100 border-red-300';
                case 'cancelled': return 'text-red-700 bg-red-100 border-red-300';
                case 'no_show':   return 'text-orange-700 bg-orange-100 border-orange-300';
                default:          return 'text-gray-500 bg-gray-50 border-gray-200';
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

            // Calendar Logic
    function hasUpcomingOnDate(dateStr) {
        const todayStr = new Date().toISOString().split("T")[0];

        return allSessions.some(s => 
            s.date === dateStr &&
            s.status === "accepted" &&
            s.date >= todayStr
        );
    }

    function renderCalendar() {

        const grid = document.getElementById('calendarGrid');
        const monthDisp = document.getElementById('monthDisplay');

        grid.innerHTML = '';

        const today = new Date(new Date().toLocaleString("en-US",{timeZone:"Asia/Manila"}));

            /* Display Month and Year */
            monthDisp.innerText = viewDate.toLocaleString('en-US', {
            month: 'long',
            year: 'numeric'
        });

        const lastDay = new Date(viewDate.getFullYear(), viewDate.getMonth()+1, 0).getDate();
        const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

        /* Empty cells before first day */
        for (let i = 0; i < startDay; i++) {
            grid.innerHTML += '<div></div>';
        }

        for (let i = 1; i <= lastDay; i++) {

            const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth()+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
            const dateObj = new Date(viewDate.getFullYear(), viewDate.getMonth(), i);

            const dayEl = document.createElement('div');
            dayEl.className = "cal-day";

            /* Gray text for completed days */
            if(dateObj < today){
                dayEl.style.color = "#9ca3af";
            }

/* Highlight current day */
if(dateStr === todayStr){
    dayEl.classList.add("cal-today");
}

            /* Highlight selected day */
            if(dateStr === selectedDateStr){
                dayEl.classList.add("cal-selected");
            }

            /* DAY NUMBER */
    const hasUpcoming = hasUpcomingOnDate(dateStr);

    dayEl.innerHTML = `
        <span>${i}</span>
        ${hasUpcoming ? `<div class="notif-dot"></div>` : ``}
    `;

            /* RESTORE CLICK FUNCTIONALITY */
            dayEl.onclick = () => {

            selectedDateStr = dateStr;
            tablePage = 0;           // reset table page
            quickActionsPage = 0; 

            /* Update table */
            refreshSchedules();
            updateWeekHeaders();

            /* Re-render calendar to show selected day */
            renderCalendar();
            updateTableDate();

            };

        grid.appendChild(dayEl);
        }
    }

const csrfToken = '{{ csrf_token() }}';

function truncateText(text, maxLength = 25) {
    if (!text) return '—';
    return text.length > maxLength
        ? text.substring(0, maxLength) + '...'
        : text;
}

function toggleModalText(id) {
    const textEl = document.getElementById(`modal-text-${id}`);
    const moreBtn = document.getElementById(`modal-more-${id}`);
    const lessBtn = document.getElementById(`modal-less-${id}`);

    const isCollapsed = textEl.classList.contains('line-clamp-1');

    if (isCollapsed) {
        textEl.classList.remove('line-clamp-1');
        textEl.classList.add('line-clamp-none');

        if (moreBtn) moreBtn.style.display = 'none';
        if (lessBtn) lessBtn.style.display = 'inline';
    } else {
        textEl.classList.add('line-clamp-1');
        textEl.classList.remove('line-clamp-none');

        if (lessBtn) lessBtn.style.display = 'none';
        if (moreBtn) moreBtn.style.display = 'inline';
    }
}

function showStatusToast(message, duration = 0) {
    const toast = document.getElementById('statusToast');
    const msg   = document.getElementById('statusToastMsg');
    msg.textContent = message;

    const hasSpinner = toast.querySelector('.toast-spinner');

    if (duration === 0) {
        // Persistent with spinner (while loading)
        if (!hasSpinner) {
            const spinner = document.createElement('div');
            spinner.className = 'toast-spinner';
            toast.insertBefore(spinner, msg);
        }
    } else {
        // Auto-dismiss (success/info)
        if (hasSpinner) hasSpinner.remove();
    }

    toast.classList.add('show');
    if (duration > 0) {
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('show'), duration);
    }
}

function hideStatusToast() {
    document.getElementById('statusToast').classList.remove('show');
}

function updateStatus(id, status) {
    const statusLabels = {
        accepted: 'Accepting booking...',
        rejected: 'Rejecting booking...',
        completed: 'Marking as completed...',
        cancelled: 'Cancelling session...',
        no_show: 'Marking as no-show...',
    };
    showStatusToast(statusLabels[status] || 'Updating status...');

    fetch('{{ route("mentor.dashboard.update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ id: id, status: status })
    })
    .then(res => res.json())
    .then(data => {
        hideStatusToast();

        if (data.success) {
            const target = allSessions.find(s => s.id === id);
            if (target) {
                target.status = status;

                if (status === 'accepted') {
                    const conflictingIds = getConflictingPendingIds(target);

                    conflictingIds.forEach(conflictId => {
                        const conflictSession = allSessions.find(s => s.id === conflictId);
                        if (conflictSession) conflictSession.status = 'rejected';

                        fetch('{{ route("mentor.dashboard.update") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ id: conflictId, status: 'rejected' })
                        }).catch(err => console.error('Auto-reject failed for id', conflictId, err));
                    });

                    if (conflictingIds.length > 0) {
                        showAutoRejectBanner(conflictingIds.length);
                        showAutoRejectBannerQA(conflictingIds.length); // ✅ also in Quick Actions
                    }
                }
            }

            refreshLocalState();
            refreshSchedules();
        } else {
            showStatusToast('Update failed. Please try again.', 4000);
        }
    })
    .catch(err => {
        hideStatusToast();
        showStatusToast('Network error. Please retry.', 4000);
        console.error(err);
    });
}

function showAutoRejectBannerQA(count) {
    const container = document.getElementById('quickActionsList');
    if (!container) return;

    let banner = document.getElementById('autoRejectBannerQA');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'autoRejectBannerQA';
        banner.style.cssText = `
            margin-bottom: 10px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #fcd34d;
            background: #fffbeb;
            font-size: 11px;
            animation: slideDown 0.2s ease;
        `;
        container.insertBefore(banner, container.firstChild);
    }

    banner.innerHTML = `
        <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
            <div style="flex-shrink:0; margin-top:1px;">
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
            <button onclick="document.getElementById('autoRejectBannerQA').remove()"
                style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#92400e; font-size:14px; line-height:1; padding:0; margin-top:-1px;">
                &times;
            </button>
        </div>
    `;

    clearTimeout(banner._timer);
    banner._timer = setTimeout(() => banner.remove(), 6000);
}

function refreshLocalState(){
    // recompute pending dynamically
    pendingRequests.length = 0;

    allSessions.forEach(s => {
        if(s.status === "pending"){
            pendingRequests.push(s);
        }
    });
}

// Pagination state for pending requests
let pendingPage = 0;
const PENDING_PER_PAGE = 5; // show 3 initially, 3 per page

function renderPendingRequests() {
    const container = document.getElementById('pendingRequestsList');
    const badge     = document.getElementById('pendingBadge');
    const toggleBtn = document.getElementById('toggleRequestsBtn');

    const sorted = [...allSessions].filter(s => s.status === 'pending').sort((a, b) => b.id - a.id);
    const total  = sorted.length;

    badge.innerText = `${total} ${total === 1 ? 'Request' : 'Requests'}`;

    if (!total) {
        container.innerHTML = `<p class="text-xs text-gray-400 italic">No pending requests.</p>`;
        toggleBtn.style.display = 'none';
        return;
    }

    // Clamp page
    const maxPage = Math.ceil(total / PENDING_PER_PAGE) - 1;
    if (pendingPage > maxPage) pendingPage = maxPage;
    if (pendingPage < 0) pendingPage = 0;

    const start   = pendingPage * PENDING_PER_PAGE;
    const visible = sorted.slice(start, start + PENDING_PER_PAGE);
    const hasPrev = pendingPage > 0;
    const hasNext = pendingPage < maxPage;

    container.innerHTML = visible.map(req => `
        <div class="flex items-center justify-between group">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold">
                    ${req.student.slice(0, 2).toUpperCase()}
                </div>
                <div>
                <div style="max-width:180px;">
                    <div id="pname-${req.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:11px;font-weight:700;color:#1e293b;" title="${req.student}">${req.student}</div>
                    <button onclick="togglePendingName('${req.id}','${req.student.replace(/'/g,"\\'")}')" id="ptoggle-${req.id}" style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:1px;background:none;border:none;cursor:pointer;padding:0;display:none;">Show more</button>
                </div>
                    <p class="text-[9px] text-gray-400 font-medium">
                        ${req.subject} • ${formatTimeTo12Hour(req.start)} - ${formatTimeTo12Hour(req.end)}
                    </p>
                    <p class="text-[9px] text-gray-400">
                        ${new Date(req.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </p>
                </div>
            </div>
            <div class="flex gap-1">
                <button onclick="rejectRequest('${req.id}')"
                    class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark text-[10px]"></i>
                </button>
                <button onclick="approveRequest('${req.id}')"
                    class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center">
                    <i class="fa-solid fa-check text-[10px]"></i>
                </button>
            </div>
        </div>
`).join('');

    // Check which pending names overflow and show toggle button
    visible.forEach(req => {
        const nameEl = document.getElementById('pname-' + req.id);
        const toggleEl = document.getElementById('ptoggle-' + req.id);
        if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) {
            toggleEl.style.display = 'block';
        }
    });

    // Pagination footer
    if (total <= PENDING_PER_PAGE) {
        toggleBtn.style.display = 'none';
    } else {
        toggleBtn.style.display = 'block';
        toggleBtn.innerHTML = `
            <div class="flex items-center justify-between w-full px-1">
                <span class="text-[10px] text-gray-400">${start + 1}–${Math.min(start + PENDING_PER_PAGE, total)} of ${total}</span>
                <div class="flex gap-1">
                    <button onclick="pendingPage--; renderPendingRequests(); event.stopPropagation();"
                        ${!hasPrev ? 'disabled' : ''}
                        class="pagination-btn ${!hasPrev ? 'opacity-30 cursor-not-allowed' : ''}">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <button onclick="pendingPage++; renderPendingRequests(); event.stopPropagation();"
                        ${!hasNext ? 'disabled' : ''}
                        class="pagination-btn ${!hasNext ? 'opacity-30 cursor-not-allowed' : ''}">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        `;
    }
}

function hasConflict(newReq) {
        function toMin(t) {
            const [h, m] = t.split(":").map(Number);
            return h * 60 + m;
        }

        return allSessions.some(s => {
            if (s.id === newReq.id) return false;
            if (s.status !== 'accepted') return false;
            if (s.date !== newReq.date) return false;

            const sStart = toMin(s.start);
            const sEnd   = toMin(s.end);
            const rStart = toMin(newReq.start);
            const rEnd   = toMin(newReq.end);

            return rStart < sEnd && rEnd > sStart;
        });
    }

    function getConflictingPendingIds(acceptedSession) {
        function toMin(t) {
            const [h, m] = t.split(":").map(Number);
            return h * 60 + m;
        }

        const aStart = toMin(acceptedSession.start);
        const aEnd   = toMin(acceptedSession.end);

        return allSessions
            .filter(s => {
                if (s.id === acceptedSession.id) return false;
                if (s.status !== 'pending') return false;
                if (s.date !== acceptedSession.date) return false;

                const sStart = toMin(s.start);
                const sEnd   = toMin(s.end);

                return aStart < sEnd && aEnd > sStart;
            })
            .map(s => s.id);
    }

/* =========================
   CONFIRMATION MODAL
   ========================= */
const confirmModal     = document.getElementById('confirmModal');
const confirmModalBox  = document.getElementById('confirmModalBox');
const confirmTitle     = document.getElementById('confirmTitle');
const confirmBody      = document.getElementById('confirmBody');
const confirmMeta      = document.getElementById('confirmMeta');
const confirmOkBtn     = document.getElementById('confirmOkBtn');
const confirmCancelBtn = document.getElementById('confirmCancelBtn');
const confirmIconWrap  = document.getElementById('confirmIconWrap');

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
        accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm'  },
        reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700',         label: 'Reject'   },
        neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800',       label: 'Confirm'  },
    };
    const v = variants[variant] || variants.neutral;

    confirmIconWrap.style.background = v.iconBg;
    confirmIconWrap.innerHTML        = v.iconHtml;
    confirmTitle.textContent         = title;
    confirmBody.innerHTML            = body;
    confirmMeta.innerHTML            = meta || '';
    confirmMeta.style.display        = meta ? 'block' : 'none';

    confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
    confirmOkBtn.textContent = v.label;
    confirmOkBtn.onclick     = () => { closeConfirmModal(); onConfirm(); };

    confirmModal.style.display = 'flex';
}

function iconCheck(color) {
    return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
        <path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
}
function iconX(color) {
    return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
        <path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/>
    </svg>`;
}
function iconInfo(color) {
    return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
        <circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/>
        <path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="10" cy="6.5" r="0.8" fill="${color}"/>
    </svg>`;
}

function buildMetaHtml(req) {
    return `
        <!-- STUDENT -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;gap:8px;">
            <span style="color:#9ca3af;">Student</span>

            <div style="display:flex;flex-direction:column;align-items:flex-end;max-width:160px;">
                <span id="modal-text-student-${req.id}"
                    class="topic-text line-clamp-1"
                    style="font-weight:600;color:#374151;text-align:right;">
                    ${req.student}
                </span>

                ${req.student.length > 25 ? `
                    <button onclick="toggleModalText('student-${req.id}')"
                        id="modal-more-student-${req.id}"
                        style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;">
                        see more
                    </button>

                    <button onclick="toggleModalText('student-${req.id}')"
                        id="modal-less-student-${req.id}"
                        style="display:none;font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;">
                        view less
                    </button>
                ` : ''}
            </div>
        </div>

        <!-- SUBJECT -->
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:8px;">
            <span style="color:#9ca3af;">Subject</span>
            <span style="font-weight:600;color:#374151;text-align:right;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                ${req.subject}
            </span>
        </div>

        <!-- TOPIC -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;gap:8px;">
            <span style="color:#9ca3af;">Topic</span>

            <div style="display:flex;flex-direction:column;align-items:flex-end;max-width:180px;">
                <span id="modal-text-topic-${req.id}"
                    class="topic-text line-clamp-1"
                    style="font-weight:600;color:#374151;text-align:right;">
                    ${req.topic || 'No topic provided'}
                </span>

                ${(req.topic && req.topic.length > 40) ? `
                    <button onclick="toggleModalText('topic-${req.id}')"
                        id="modal-more-topic-${req.id}"
                        style="font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;">
                        see more
                    </button>

                    <button onclick="toggleModalText('topic-${req.id}')"
                        id="modal-less-topic-${req.id}"
                        style="display:none;font-size:10px;color:#9ca3af;background:none;border:none;cursor:pointer;">
                        view less
                    </button>
                ` : ''}
            </div>
        </div>

        <!-- DATE -->
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <span style="color:#9ca3af;">Date</span>
            <span style="font-weight:600;color:#374151;">
                ${new Date(req.date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}
            </span>
        </div>

        <!-- TIME -->
        <div style="display:flex;justify-content:space-between;">
            <span style="color:#9ca3af;">Time</span>
            <span style="font-weight:600;color:#374151;">
                ${formatTimeTo12Hour(req.start)} – ${formatTimeTo12Hour(req.end)}
            </span>
        </div>
    `;
}


function approveRequest(id) {
    const req = allSessions.find(s => s.id == id);
    if (!req) return;

    if (hasConflict(req)) {
        const conflict = allSessions.find(s =>
            s.id !== req.id &&
            s.status === 'accepted' &&
            s.date === req.date
        );
        const conflictInfo = conflict
            ? `Conflicts with <strong>${conflict.student}</strong> (${formatTimeTo12Hour(conflict.start)} – ${formatTimeTo12Hour(conflict.end)}) on ${conflict.date}.`
            : 'This session overlaps with an already-accepted booking.';
        showConflictBanner(conflictInfo);
        return;
    }

    openConfirmModal({
        title:     'Accept booking?',
        body:      'The student will be notified that their session has been approved.',
        meta:      buildMetaHtml(req),
        variant:   'accept',
        onConfirm: () => updateStatus(id, 'accepted'),
    });
}

function showConflictBanner(message) {
    let banner = document.getElementById('conflictBanner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'conflictBanner';
        banner.style.cssText = `
            margin-bottom: 12px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #fca5a5;
            background: #fef2f2;
            font-size: 11px;
            animation: slideDown 0.2s ease;
        `;
        const pendingList = document.getElementById('pendingRequestsList');
        pendingList.parentNode.insertBefore(banner, pendingList);
    }

    banner.innerHTML = `
        <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
            <div style="flex-shrink:0; margin-top:1px;">
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
                style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0; margin-top:-1px;">
                &times;
            </button>
        </div>
    `;

    clearTimeout(banner._timer);
    banner._timer = setTimeout(() => banner.remove(), 6000);
}

function showAutoRejectBanner(count) {
    let banner = document.getElementById('autoRejectBanner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'autoRejectBanner';
        banner.style.cssText = `
            margin-bottom: 12px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #fcd34d;
            background: #fffbeb;
            font-size: 11px;
            animation: slideDown 0.2s ease;
        `;
        const pendingList = document.getElementById('pendingRequestsList');
        pendingList.parentNode.insertBefore(banner, pendingList);
    }

    banner.innerHTML = `
        <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
            <div style="flex-shrink:0; margin-top:1px;">
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
                style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#92400e; font-size:14px; line-height:1; padding:0; margin-top:-1px;">
                &times;
            </button>
        </div>
    `;

    clearTimeout(banner._timer);
    banner._timer = setTimeout(() => banner.remove(), 6000);
}

function rejectRequest(id) {
    const req = allSessions.find(s => s.id == id);
    if (!req) return;

    openConfirmModal({
        title:     'Reject booking?',
        body:      'The student will be notified that their session request was declined.',
        meta:      buildMetaHtml(req),
        variant:   'reject',
        onConfirm: () => updateStatus(id, 'rejected'),
    });
}

function togglePendingName(id, fullName) {
    const nameEl = document.getElementById('pname-' + id);
    const btn = document.getElementById('ptoggle-' + id);
    if (!nameEl || !btn) return;
    if (btn.innerText === 'Show more') {
        nameEl.style.whiteSpace = 'normal';
        nameEl.style.overflow = 'visible';
        nameEl.style.textOverflow = 'unset';
        nameEl.style.wordBreak = 'break-all';
        btn.innerText = 'Show less';
    } else {
        nameEl.style.whiteSpace = 'nowrap';
        nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis';
        nameEl.style.wordBreak = 'normal';
        btn.innerText = 'Show more';
    }
}

function toggleQaName(id) {
    const nameEl = document.getElementById('qaname-' + id);
    const btn = document.getElementById('qatoggle-' + id);
    if (!nameEl || !btn) return;
    if (btn.innerText === 'Show more') {
        nameEl.style.whiteSpace = 'normal';
        nameEl.style.overflow = 'visible';
        nameEl.style.textOverflow = 'unset';
        nameEl.style.wordBreak = 'break-all';
        btn.innerText = 'Show less';
    } else {
        nameEl.style.whiteSpace = 'nowrap';
        nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis';
        nameEl.style.wordBreak = 'normal';
        btn.innerText = 'Show more';
    }
}

// Pagination state for quick actions
let quickActionsPage = 0;
const QUICK_ACTIONS_PER_PAGE = 5;

function renderQuickActions() {
    const container     = document.getElementById('quickActionsList');
    const todaySessions = allSessions.filter(s => s.date === selectedDateStr && s.status === 'accepted');
    const total         = todaySessions.length;

    if (!total) {
        container.innerHTML = `<p class="text-xs text-gray-400 italic">No active sessions.</p>`;
        return;
    }

    // Clamp page
    const maxPage = Math.ceil(total / QUICK_ACTIONS_PER_PAGE) - 1;
    if (quickActionsPage > maxPage) quickActionsPage = maxPage;
    if (quickActionsPage < 0) quickActionsPage = 0;

    const start   = quickActionsPage * QUICK_ACTIONS_PER_PAGE;
    const visible = todaySessions.slice(start, start + QUICK_ACTIONS_PER_PAGE);
    const hasPrev = quickActionsPage > 0;
    const hasNext = quickActionsPage < maxPage;

container.innerHTML = `
        ${visible.map(s => `
            <div class="flex items-center justify-between border border-gray-100 rounded-lg p-3">
                <div style="min-width:0;flex:1;margin-right:8px;">
                    <div style="max-width:200px;">
                        <div id="qaname-${s.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:11px;font-weight:700;color:#1e293b;" title="${s.student}">${s.student}</div>
                        <button onclick="toggleQaName('${s.id}')" id="qatoggle-${s.id}" style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:1px;background:none;border:none;cursor:pointer;padding:0;display:none;">Show more</button>
                    </div>
                    <p class="text-[10px] text-gray-400">${s.subject} • ${formatTimeTo12Hour(s.start)} - ${formatTimeTo12Hour(s.end)}</p>
                    <p class="text-[9px] text-gray-400">
                        ${new Date(s.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </p>
                </div>
                <div class="flex gap-1 flex-wrap justify-end">
                    <button onclick="updateStatus('${s.id}', 'completed')" class="text-[10px] px-2 py-1 rounded bg-gray-100 text-gray-700 font-bold">Complete</button>
                    <button onclick="updateStatus('${s.id}', 'no_show')"   class="text-[10px] px-2 py-1 rounded bg-orange-100 text-orange-700 font-bold">No-show</button>
                    <button onclick="updateStatus('${s.id}', 'cancelled')" class="text-[10px] px-2 py-1 rounded bg-red-100 text-red-700 font-bold">Cancel</button>
                </div>
            </div>
        `).join('')}

        ${total > QUICK_ACTIONS_PER_PAGE ? `
            <div class="flex items-center justify-between pt-2 border-t border-gray-100 mt-1">
                <span class="text-[10px] text-gray-400">
                    ${start + 1}–${Math.min(start + QUICK_ACTIONS_PER_PAGE, total)} of ${total}
                </span>
                <div class="flex gap-1">
                    <button onclick="quickActionsPage--; renderQuickActions();"
                        ${!hasPrev ? 'disabled' : ''}
                        class="pagination-btn ${!hasPrev ? 'opacity-30 cursor-not-allowed' : ''}">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <button onclick="quickActionsPage++; renderQuickActions();"
                        ${!hasNext ? 'disabled' : ''}
                        class="pagination-btn ${!hasNext ? 'opacity-30 cursor-not-allowed' : ''}">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        ` : ''}
    `;

    visible.forEach(s => {
        const nameEl = document.getElementById('qaname-' + s.id);
        const toggleEl = document.getElementById('qatoggle-' + s.id);
        if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) {
            toggleEl.style.display = 'block';
        }
    });
}

    window.addEventListener("load", function () {
    initDashboard();
    });

    document.addEventListener("livewire:navigated", function () {
        initDashboard();
    });

        function changeMonth(dir) {
            viewDate.setMonth(viewDate.getMonth() + dir);
            renderCalendar();
        }

    function updateTableDate(){
        const date = new Date(selectedDateStr);
        document.getElementById("tableSubtitle").innerText =
            date.toLocaleDateString('en-US',{
                month:'long',
                day:'numeric',
                year:'numeric'
            });
    }

            // Listeners
        searchInput.addEventListener('input',  () => { tablePage = 0; refreshSchedules(); });
        statusFilter.addEventListener('change', () => { tablePage = 0; refreshSchedules(); });  

</script>

    </body>
