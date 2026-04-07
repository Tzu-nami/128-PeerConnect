<?php

use function Livewire\Volt\{layout, state, mount};
use App\Models\MentorProfiles;
use App\Models\StudentProfiles;
use App\Models\Bookings;
use Carbon\Carbon;

layout('layouts.app');

state([
    'totalMentors' => 0,
    'sessionsToday' => 0,
    'pendingBookings' => 0,
    'totalStudents' => 0,
]);

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
    $this->totalMentors = MentorProfiles::count();
    $this->sessionsToday = Bookings::whereDate('date', Carbon::today()) -> count();
    $this->pendingBookings = Bookings::where('booking_status', 'pending') -> count();
    $this->totalStudents = StudentProfiles::count();
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
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }

        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; }

        /* SIDEBAR */
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

        .sidebar-logo-container {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 15px;
            flex-shrink: 0;
            overflow: hidden;
        }
        #sidebarToggle {
            background: transparent; border: none; color: white; font-size: 1.4rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .logo-text { font-size: 1.1rem; font-weight: 700; }

        
       /* FLAT BLENDING STYLE */
.nav-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 25px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    position: relative;
    background: transparent;
    border: none;
    width: 100%;
    cursor: pointer;
}

.nav-item:hover {
    color: white;
    background: rgba(255,255,255,0.05);
}

.nav-item.active {
    background: var(--bg-light);
    color: var(--header-maroon);
    font-weight: 700;
    /* This ensures it aligns perfectly with the content area */
    border-radius: 0;
    width: calc(100% + 1px); /* Overlaps the sidebar border/edge slightly */
    z-index: 10;
}
.nav-item::after {
            content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 10px; background: rgba(0, 0, 0, 0.9); color: white; padding: 5px 12px; border-radius: 4px; font-size: 12px; opacity: 0; visibility: hidden; transition: opacity 0.2s; pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }
        .sidebar.collapsed .logo-content span, .sidebar.collapsed .nav-item span { display: none; }
        .sidebar.collapsed .sidebar-logo-container, .sidebar.collapsed .nav-item { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; }
        .sidebar.collapsed .nav-item.active { border-left: none; }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
.scroll-container { flex-grow: 1; overflow-y: auto; padding: 16px 32px; width: 100%; }

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .cal-header-day {
    font-size: 10px;
    font-weight: 800;
    text-align: center;
    color: #94a3b8;
    text-transform: uppercase;
    padding-bottom: 4px;
}
.cal-day {
    aspect-ratio: 1/1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.15s;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    width: 100%;
}
.cal-day:hover { background: #f1f5f9; color: #1e293b; }
.cal-today { background: #fee2e2 !important; color: #7b1d1d !important; font-weight: 800; }
.cal-selected { border: 2px solid #7b1d1d; background: #f8fafc; }

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
        .pagination-btn:disabled {
    background-color: #f3f4f6; /* Light gray */
    color: #9ca3af;            /* Faded icon color */
    cursor: not-allowed;       /* Shows a "prohibited" cursor */
    border-color: #e5e7eb;
}
    
    /* MAIN CONTENT SEARCH BAR */
.main-search-container {
    background: white;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    border: 1px solid #eef2f3;
}

.main-search-input {
    width: 100%;
    padding: 9px 16px 9px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.82rem;
    outline: none;
    transition: all 0.2s;
}

.main-search-wrapper {
    position: relative;
    flex: 1;
}

.main-search-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.9rem;
}

.main-search-input:focus {
    border-color: var(--header-maroon);
    box-shadow: 0 0 0 3px rgba(123, 29, 29, 0.05);
}

.tooltip-wrap { position: relative; }
.tooltip-wrap .tooltip-text {
    visibility: hidden;
    opacity: 0;
    background: #1e293b;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 6px;
    position: absolute;
    bottom: calc(100% + 4px);
    left: 0;
    white-space: nowrap;
    z-index: 50;
    transition: opacity 0.1s ease;
    pointer-events: none;
}
.tooltip-wrap:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}
    </style>

</head>

<body>
    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
<div class="sidebar-logo-container">
    <div class="logo-content">
        <i class="fa-solid fa-graduation-cap text-xl"></i>
        <span class="logo-text">LRC PeerConnect</span>
    </div>
</div>

<!-- Floating toggle button on the sidebar edge -->
<button id="sidebarToggle" style="
    position: absolute;
    top: 50%;
    right: -16px;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--header-maroon);
    border: 2px solid white;
    color: white;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 40;
    box-shadow: 2px 0 8px rgba(0,0,0,0.15);
    transition: background 0.2s;
">
    <i class="fa-solid fa-chevron-left" id="toggleIcon"></i>
</button>
            <nav class="flex-grow">
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>                
                <a href="{{ route('admin.mentors') }}" class="nav-item {{ request()->routeIs('admin.mentors') ? 'active' : '' }}" data-tooltip="Mentor Management">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
                </a>
                <a href="{{ route('admin.mentors') }}" class="nav-item {{ request()->routeIs('admin.mentors') ? 'active' : '' }}" data-tooltip="Course Management">
                    <i class="fa-solid fa-book-open w-5"></i><span>Course Management</span>
                </a>
                <a href="{{ route('admin.sessions') }}" class="nav-item {{ request()->routeIs('admin.sessions') ? 'active' : '' }}" data-tooltip="Session Management">
                    <i class="fa-solid fa-calendar-days w-5"></i><span>Session Management</span>
                </a>
                <a href="{{ route('admin.feedbacks') }}" class="nav-item {{ request()->routeIs('admin.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedback">
                    <i class="fa-solid fa-comments w-5"></i><span>Student Feedback</span>
                </a>
            </nav>
            <div class="p-4 border-t border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item w-full bg-transparent border-none text-left" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header relative flex items-center justify-between px-6 py-4">
    <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>

    <div class="flex items-center gap-3">
        
        <div class="relative">
            <button id="notificationTrigger" class="relative p-2 rounded-full text-gray-400 hover:bg-red-900 hover:text-white transition-all duration-200 focus:outline-none group">
    <i class="fa-solid fa-bell text-xl"></i>
    
    <span class="absolute top-2 right-2 flex h-2.5 w-2.5">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75 group-hover:hidden"></span>
        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600 border border-white"></span>
    </span>
</button>

            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                <div class="p-3 border-b border-gray-100 bg-slate-50">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Notifications</p>
                </div>
                <div class="max-h-64 overflow-y-auto">
                    <div class="p-4 border-b border-gray-50 hover:bg-gray-50 transition cursor-pointer">
                        <p class="text-xs text-gray-800">Your report has been approved.</p>
                        <p class="text-[10px] text-gray-400 mt-1">2 minutes ago</p>
                    </div>
                </div>
                <a href="#" class="block p-3 text-center text-xs font-bold text-red-900 hover:bg-gray-50">View All</a>
            </div>
        </div>

        <div class="relative">
            <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200" id="dropdownArrow"></i>
            </button>

            <div id="profileDropdown" class="profile-dropdown absolute right-0 mt-2 hidden">
                <div class="p-4 border-b border-gray-100 bg-slate-50">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
                    <p class="text-sm font-bold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold p-3 hover:bg-gray-50 flex items-center gap-2">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </form>
            </div>
        </div>

    </div>
</header>

            <main class="scroll-container">
<div class="main-search-container mb-8" style="position: relative; z-index: 10;">
    <div class="main-search-wrapper flex-1" style="position: relative;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="mainDashboardSearch"
            placeholder="Search dashboard..."
            class="main-search-input" autocomplete="off">
    </div>
    <div class="ml-4 flex gap-2">
    </div>
    <div id="globalSearchResults"
        class="hidden absolute left-0 right-0 bg-white rounded-xl shadow-xl border border-gray-100 overflow-y-auto"
        style="top: calc(100% + 6px); max-height: 420px; z-index: 20;">
    </div>
</div>

 <div class="grid grid-cols-5 gap-4 mb-8">
  <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4">
    <div class="text-2xl">
      <i class="fa-solid fa-users text-green-600"></i>
    </div>
    <div>
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Total Mentors</h3>
      <p class="text-2xl font-black">{{ $totalMentors }}</p>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-4">
    <div class="text-2xl">
      <i class="fa-solid fa-calendar-day text-blue-600"></i>
    </div>
    <div>
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Sessions Today</h3>
      <p class="text-2xl font-black">{{ $sessionsToday }}</p>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4">
    <div class="text-2xl">
      <i class="fa-solid fa-clock text-yellow-500"></i>
    </div>
    <div>
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Pending</h3>
      <p class="text-2xl font-black">{{ $pendingBookings }}</p>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-red-600 flex items-center gap-4">
    <div class="text-2xl">
      <i class="fa-solid fa-star text-red-600"></i>
    </div>
    <div>
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Ratings</h3>
      <p class="text-2xl font-black">4.9</p>
    </div>
  </div>

  <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-pink-600 flex items-center gap-4">
    <div class="text-2xl">
      <i class="fa-solid fa-user-graduate text-pink-600"></i>
    </div>
    <div>
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Total Mentees</h3>
      <p class="text-2xl font-black">{{ $totalStudents }}</p>
    </div>
  </div>
</div>

                <div class="grid grid-cols-3 gap-8">
                    <div class="col-span-2 space-y-8">
<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col" id="section-schedule">                            <div class="flex justify-between items-center mb-6">
                                <div>
<div class="flex items-center gap-2">
    <i class="fa-solid fa-calendar-check"></i>
    <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
</div>    <p class="text-xs text-gray-400" id="tableSubtitle"></p>
</div>


<script>
function updateDate() {
    const today = new Date();

    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };

    const formattedDate = today.toLocaleDateString('en-US', options);

    document.getElementById("tableSubtitle").textContent = formattedDate;
}

// run when page loads
updateDate();
</script>
                                <div class="flex gap-2">
                                    <div class="relative w-48">
                                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                        <input type="text" id="liveSearchInput" placeholder="Search names..." class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                                    </div>
                                    <select id="statusFilter" class="table-filter-select">
                                        <option value="All">All Status</option>
                                        <option value="Active">Active</option>
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>

<div>
    <table class="w-full text-left text-sm table-fixed">
                                    <thead class="text-gray-400 border-b">
                                        <tr>
                                            <th class="pb-3 font-semibold uppercase text-[10px] tracking-wider">Mentor</th>
                                            <th class="pb-3 font-semibold uppercase text-[10px] tracking-wider">Mentee</th>
                                            <th class="pb-3 font-semibold uppercase text-[10px] tracking-wider">Time</th>
                                            <th class="pb-3 font-semibold uppercase text-[10px] tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tableBody"></tbody>
                                </table>
                            </div>

<div class="mt-2 pt-2 border-t border-gray-50 flex items-center justify-between">
                                    <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">Showing 0 results</div>
                                <div class="flex gap-2">
<button id="prevBtn" class="pagination-btn" disabled>
    <i class="fa-solid fa-chevron-left"></i>
</button>                                    <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>

<div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-chart-line"></i>
            <span class="text-lg font-bold text-slate-800">LRC Performance Analytics</span>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-6">
                                <div class="stats-column"><div class="stats-column-title">Monthly Session Trends</div><div class="h-44"><canvas id="lineChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Top Mentors</div><div class="h-44"><canvas id="pieChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Satisfaction Rate</div><div class="h-44 flex justify-center"><canvas id="doughnutChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Most Active Colleges (CS, CSS, CAC)</div><div class="h-44"><canvas id="activeCollegeChart"></canvas></div></div>
                            </div>
                        </div>
                    </div>

 <div class="flex flex-col gap-6">

<div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100" id="section-quickactions">
    <h3 class="font-bold mb-3 text-slate-800 text-sm tracking-tight">Quick Actions</h3>
    <div class="grid grid-cols-2 gap-2">
        <button class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-user-plus text-[10px]"></i> Add Mentor
        </button>
        <button class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-calendar-plus text-[10px]"></i> Create Session
        </button>
        <button class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-book-open text-[10px]"></i> Manage Subjects
        </button>
        <button class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-file-invoice text-[10px]"></i> Generate Report
        </button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    
    {{-- Clock --}}
    <div class="bg-slate-900 rounded-t-xl px-4 py-3 flex items-center justify-between">
        <div id="liveDate" class="text-[10px] font-medium text-slate-400 uppercase tracking-widest"></div>
        <div id="liveClock" class="text-sm font-mono font-bold text-white tracking-widest"></div>
    </div>

    {{-- Calendar --}}
    <div class="p-4">
        <div class="flex justify-between items-center mb-4">
            <span id="monthDisplay" class="text-sm font-bold text-slate-800"></span>
            <div class="flex gap-2">
                <button onclick="changeMonth(-1)" class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>
                <button onclick="changeMonth(1)" class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1 mb-1">
            <div class="cal-header-day">S</div>
            <div class="cal-header-day">M</div>
            <div class="cal-header-day">T</div>
            <div class="cal-header-day">W</div>
            <div class="cal-header-day">T</div>
            <div class="cal-header-day">F</div>
            <div class="cal-header-day">S</div>
        </div>
        <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
    </div>
</div>
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Approvals</h3>
                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">4 New</span>
                            </div>
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold">JD</div>
                                        <div><p class="text-[11px] font-bold text-slate-700">John Doe</p><p class="text-[9px] text-gray-400 font-medium">Mentor Applicant</p></div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold">SM</div>
                                        <div><p class="text-[11px] font-bold text-slate-700">Sarah Miller</p><p class="text-[9px] text-gray-400 font-medium">Session Change</p></div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center text-[10px] font-bold">AL</div>
                                        <div><p class="text-[11px] font-bold text-slate-700">Amy Lee</p><p class="text-[9px] text-gray-400 font-medium">Subject Add</p></div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-[10px] font-bold">TC</div>
                                        <div><p class="text-[11px] font-bold text-slate-700">Tom Chen</p><p class="text-[9px] text-gray-400 font-medium">Profile Edit</p></div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button class="w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">View All Requests</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        const searchInput = document.getElementById('liveSearchInput');
        const statusFilter = document.getElementById('statusFilter');
        const charts = [];
        let currentPage = 1;

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        window.addEventListener('click', () => {
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        });

        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);

        // Local State
        const allSessions = [
            { date: '2026-03-14', mentor: "Daniel Dyoco", mentee: "Frian Nabo", time: "09:00 AM", status: "Completed", color: "text-blue-600" },
            { date: '2026-03-14', mentor: "Rhona Shayne Lopez", mentee: "Mark Tuan", time: "10:30 AM", status: "Active", color: "text-green-600" },
            { date: '2026-03-14', mentor: "Chezka SincoChezka Sincezka SincoChezka SincoChezka SincoChezka SincoChezka SincoChezka Sinco", mentee: "Uno Dos Thirdy", time: "11:00 AM", status: "Active", color: "text-green-600" },
            { date: '2026-03-14', mentor: "Arielle Mae Solis", mentee: "Kevin Hart", time: "01:00 PM", status: "Pending", color: "text-yellow-500" },
            { date: '2026-03-14', mentor: "Ax'l Conchada", mentee: "Alice Blue", time: "02:30 PM", status: "Upcoming", color: "text-orange-500" },
        ];

const _now = new Date();
let selectedDateStr = `${_now.getFullYear()}-${String(_now.getMonth() + 1).padStart(2, '0')}-${String(_now.getDate()).padStart(2, '0')}`;
let viewDate = new Date(_now.getFullYear(), _now.getMonth(), 1);

        // Chart Configs
        const linearOptions = { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 9 } } }, y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } } } };

        function initCharts() {
            // Line Chart
            charts.push(new Chart(document.getElementById('lineChart'), { type: 'line', data: { labels: ['W1', 'W2', 'W3', 'W4'], datasets: [{ data: [45, 52, 38, 65], borderColor: '#7b1d1d', tension: 0.4 }] }, options: linearOptions }));

            // Pie Chart (Top Mentors)
            charts.push(new Chart(document.getElementById('pieChart'), { type: 'pie', data: { labels: ['Daniel D.', 'Sarah J.', 'James W.', 'Others'], datasets: [{ data: [40, 25, 20, 15], backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1'] }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } } } }));

            // Doughnut Chart (Satisfaction Rate)
            charts.push(new Chart(document.getElementById('doughnutChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Excl', 'Good', 'Avg'],
                    datasets: [{ data: [70, 20, 10], backgroundColor: ['#1a3c2f', '#7b1d1d', '#cbd5e1'], borderWidth: 0 }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } }
                    },
                    cutout: '70%'
                }
            }));

            // Bar Chart (Most Active Colleges - CS, CSS, CAC)
            charts.push(new Chart(document.getElementById('activeCollegeChart'), {
                type: 'bar',
                data: {
                    labels: ['CS', 'CSS', 'CAC'],
                    datasets: [{
                        label: 'Active Students',
                        data: [68, 85, 42],
                        backgroundColor: ['#94a3b8', '#1a3c2f', '#7b1d1d'],
                        borderRadius: 4,
                        barThickness: 20
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            }));
        }

        // Table Logic
function applyFilters() {
    const tbody = document.getElementById('tableBody');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const selectedStatus = statusFilter.value;

    const filtered = allSessions.filter(item => {
        const matchesSearch = searchTerm
            ? item.mentor.toLowerCase().includes(searchTerm) ||
              item.mentee.toLowerCase().includes(searchTerm)
            : true;
        const matchesStatus = selectedStatus === 'All' || item.status === selectedStatus;
        return matchesSearch && matchesStatus;
    });

    const perPage = 4;
    const totalPages = Math.ceil(filtered.length / perPage);
    if (currentPage > totalPages) currentPage = 1;

    const start = (currentPage - 1) * perPage;
    const paginated = filtered.slice(start, start + perPage);

    if (paginated.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No matching sessions found.</td></tr>`;
    } else {
        tbody.innerHTML = paginated.map(row => `
            <tr class="border-b last:border-0 hover:bg-slate-50 transition">
                <td class="py-4 font-bold text-slate-700 max-w-0 w-1/4">
                    <div class="tooltip-wrap">
                        <div class="truncate">${row.mentor}</div>
                        <span class="tooltip-text">${row.mentor}</span>
                    </div>
                </td>
                <td class="text-slate-600 max-w-0 w-1/4">
                    <div class="tooltip-wrap">
                        <div class="truncate">${row.mentee}</div>
                        <span class="tooltip-text">${row.mentee}</span>
                    </div>
                </td>
                <td class="text-slate-500 w-1/4">${row.time}</td>
                <td class="w-1/4"><span class="${row.color} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80">${row.status}</span></td>
            </tr>
        `).join('');
    }

    document.getElementById('pageIndicator').innerText = `Showing ${start + 1}–${Math.min(start + perPage, filtered.length)} of ${filtered.length} result${filtered.length !== 1 ? 's' : ''}`;

    document.getElementById('prevBtn').disabled = currentPage === 1;
document.getElementById('nextBtn').disabled = currentPage >= totalPages;
}
        // Calendar Logic
function renderCalendar() {
    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    const grid = document.getElementById('calendarGrid');
    const monthDisp = document.getElementById('monthDisplay');
    grid.innerHTML = '';
    monthDisp.innerText = viewDate.toLocaleString('default', { month: 'long', year: 'numeric' });

    const lastDay = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
    const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

    for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

    for (let i = 1; i <= lastDay; i++) {
        const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const isToday = dateStr === todayStr;
        const isSelected = dateStr === selectedDateStr;
        const dayEl = document.createElement('div');
        dayEl.className = `cal-day ${isToday ? 'cal-today' : ''} ${isSelected ? 'cal-selected' : ''}`;
        dayEl.innerHTML = `<span>${i}</span>`;
        dayEl.onclick = () => { selectedDateStr = dateStr; applyFilters(); renderCalendar(); };
        grid.appendChild(dayEl);
    }
}

        function changeMonth(dir) {
            viewDate.setMonth(viewDate.getMonth() + dir);
            renderCalendar();
        }

// Listeners
searchInput.addEventListener('input', applyFilters);
statusFilter.addEventListener('change', applyFilters);
document.getElementById('prevBtn').addEventListener('click', () => { currentPage--; applyFilters(); });
document.getElementById('nextBtn').addEventListener('click', () => { currentPage++; applyFilters(); });

        // Bootstrap
        initCharts();
        renderCalendar();
        applyFilters();
        updateClock();

        function updatePaginationUI() {
    const prevBtn = document.getElementById('prevBtn');
    
    if (currentPage === 1) {
        prevBtn.disabled = true;
    } else {
        prevBtn.disabled = false;
    }
    nextBtn.disabled = (currentPage === totalPages);
}
    </script>

<script>
    const mainSearch = document.getElementById('mainDashboardSearch');

    mainSearch.addEventListener('input', function () {
    });
</script>

<script>
(function () {

    // ── INDEX ──────────────────────────────────────────────────────────────
    // Every piece of content on the dashboard lives here.
    // `target` = the element id to scroll to on click.
    // `action` = optional extra behaviour on click.
    const INDEX = [

        // ── STAT CARDS ──
        { group: 'Stats',    label: 'Total Mentors',           detail: '{{ $totalMentors }} mentors registered',         target: 'section-stats',     keywords: ['total','mentors','mentor','count','registered'] },
        { group: 'Stats',    label: 'Sessions Today',          detail: '{{ $sessionsToday }} sessions scheduled today',  target: 'section-stats',     keywords: ['sessions','today','scheduled','daily'] },
        { group: 'Stats',    label: 'Pending Bookings',        detail: '{{ $pendingBookings }} awaiting approval',        target: 'section-approvals', keywords: ['pending','bookings','booking','approval','awaiting'] },
        { group: 'Stats',    label: 'Average Rating',          detail: '4.9 overall satisfaction',                        target: 'section-analytics', keywords: ['rating','ratings','satisfaction','stars','average','4.9'] },
        { group: 'Stats',    label: 'Total Mentees',           detail: '{{ $totalStudents }} students enrolled',          target: 'section-stats',     keywords: ['mentees','students','student','enrolled','total'] },

        // ── TODAY'S SCHEDULE / SESSIONS ──
        { group: 'Sessions', label: 'Daniel Dyoco',            detail: 'Mentoring Frian Nabo · 09:00 AM · Completed',    target: 'section-schedule',  keywords: ['daniel','dyoco','frian','nabo','completed','09:00','09','am'] },
        { group: 'Sessions', label: 'Rhona Shayne Lopez',      detail: 'Mentoring Mark Tuan · 10:30 AM · Active',        target: 'section-schedule',  keywords: ['rhona','shayne','lopez','mark','tuan','active','10:30','10'] },
        { group: 'Sessions', label: 'Chezka Sinco',            detail: 'Mentoring Uno Dos Thirdy · 11:00 AM · Active',   target: 'section-schedule',  keywords: ['chezka','sinco','uno','dos','thirdy','active','11:00','11'] },
        { group: 'Sessions', label: 'Arielle Mae Solis',       detail: 'Mentoring Kevin Hart · 01:00 PM · Pending',      target: 'section-schedule',  keywords: ['arielle','mae','solis','kevin','hart','pending','1:00','01:00','pm'] },
        { group: 'Sessions', label: "Ax'l Conchada",           detail: 'Mentoring Alice Blue · 02:30 PM · Upcoming',     target: 'section-schedule',  keywords: ["ax'l",'axl','conchada','alice','blue','upcoming','2:30','02:30'] },

        // ── SESSION STATUSES (searchable words) ──
        { group: 'Sessions', label: 'Active Sessions',         detail: 'View all currently active sessions',              target: 'section-schedule',  keywords: ['active','ongoing','live'] },
        { group: 'Sessions', label: 'Completed Sessions',      detail: 'View all completed sessions',                    target: 'section-schedule',  keywords: ['completed','done','finished'] },
        { group: 'Sessions', label: 'Upcoming Sessions',       detail: 'View all upcoming sessions',                     target: 'section-schedule',  keywords: ['upcoming','future','scheduled'] },
        { group: 'Sessions', label: 'Pending Sessions',        detail: 'View sessions awaiting confirmation',             target: 'section-schedule',  keywords: ['pending','waiting','unconfirmed'] },

        // ── PENDING APPROVALS ──
        { group: 'Approvals', label: 'John Doe',               detail: 'Mentor Applicant · Pending approval',            target: 'section-approvals', keywords: ['john','doe','mentor applicant','applicant','new mentor'] },
        { group: 'Approvals', label: 'Sarah Miller',           detail: 'Session Change Request · Pending',               target: 'section-approvals', keywords: ['sarah','miller','session change','change request'] },
        { group: 'Approvals', label: 'Amy Lee',                detail: 'Subject Add Request · Pending',                  target: 'section-approvals', keywords: ['amy','lee','subject add','add subject','new subject'] },
        { group: 'Approvals', label: 'Tom Chen',               detail: 'Profile Edit Request · Pending',                 target: 'section-approvals', keywords: ['tom','chen','profile edit','edit profile'] },

        // ── ANALYTICS ──
        { group: 'Analytics', label: 'Monthly Session Trends', detail: 'Weekly session volume — W1 to W4',               target: 'section-analytics', keywords: ['monthly','trends','sessions','volume','weekly','line chart','chart'] },
        { group: 'Analytics', label: 'Top Mentors',            detail: 'Daniel D., Sarah J., James W. ranked by sessions', target: 'section-analytics', keywords: ['top','mentors','ranked','pie chart','leaderboard','best'] },
        { group: 'Analytics', label: 'Satisfaction Rate',      detail: 'Excellent 70%, Good 20%, Average 10%',           target: 'section-analytics', keywords: ['satisfaction','rate','excellent','good','average','doughnut','donut','feedback'] },
        { group: 'Analytics', label: 'Most Active Colleges',   detail: 'CS · CSS · CAC — session counts',                target: 'section-analytics', keywords: ['college','colleges','cs','css','cac','active','bar chart','campus'] },

        // ── QUICK ACTIONS ──
        { group: 'Quick Actions', label: 'Add Mentor',         detail: 'Register a new peer mentor',                     target: 'section-quickactions', keywords: ['add','mentor','register','new mentor','create mentor'] },
        { group: 'Quick Actions', label: 'Create Session',     detail: 'Schedule a new mentoring session',               target: 'section-quickactions', keywords: ['create','session','schedule','new session','book'] },
        { group: 'Quick Actions', label: 'Manage Subjects',    detail: 'View and edit available subjects',               target: 'section-quickactions', keywords: ['manage','subjects','subject','edit subjects','topics'] },
        { group: 'Quick Actions', label: 'Generate Report',    detail: 'Export a summary report',                        target: 'section-quickactions', keywords: ['generate','report','export','download','summary'] },

        // ── NAVIGATION ──
        { group: 'Navigation', label: 'Dashboard',             detail: 'Go to the main dashboard',                       target: 'section-stats',     keywords: ['dashboard','home','overview','main'] },
        { group: 'Navigation', label: 'Mentor Management',     detail: 'Manage and register mentors',                    target: 'section-stats',     keywords: ['mentor management','manage mentors','mentors page'] },
        { group: 'Navigation', label: 'Session Management',    detail: 'View and manage all sessions',                   target: 'section-schedule',  keywords: ['session management','sessions page','all sessions'] },
        { group: 'Navigation', label: 'Student Feedback',      detail: 'View student ratings and feedback',              target: 'section-analytics', keywords: ['feedback','ratings','student feedback','reviews'] },
    ];

    // ── BADGE STYLES ────────────────────────────────────────────────────────
    const BADGE = {
        'Stats':         { bg: '#f1f5f9', color: '#475569', text: 'Stat' },
        'Sessions':      { bg: '#dbeafe', color: '#1e40af', text: 'Session' },
        'Approvals':     { bg: '#fef3c7', color: '#92400e', text: 'Approval' },
        'Analytics':     { bg: '#d1fae5', color: '#065f46', text: 'Analytics' },
        'Quick Actions': { bg: '#ede9fe', color: '#5b21b6', text: 'Action' },
        'Navigation':    { bg: '#fee2e2', color: '#991b1b', text: 'Nav' },
    };

    // ── HIGHLIGHT ───────────────────────────────────────────────────────────
    function hl(text, q) {
        if (!q) return text;
        const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return text.replace(re, '<mark style="background:#fef08a;border-radius:2px;padding:0 1px;color:inherit;">$1</mark>');
    }

    // ── SCROLL + HIGHLIGHT TARGET ────────────────────────────────────────────
    function scrollTo(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'box-shadow 0.3s';
        el.style.boxShadow = '0 0 0 3px rgba(123,29,29,0.4)';
        setTimeout(() => { el.style.boxShadow = ''; }, 1800);
    }

    // ── RENDER ───────────────────────────────────────────────────────────────
    function render(q, matches) {
        const resultsEl = document.getElementById('globalSearchResults');

        if (!matches.length) {
            resultsEl.innerHTML = `<div style="padding:20px;text-align:center;font-size:13px;color:#9ca3af;font-style:italic;">No results for "<strong>${q}</strong>"</div>`;
            resultsEl.classList.remove('hidden');
            return;
        }

        // Group
        const groups = {};
        matches.forEach(m => {
            if (!groups[m.group]) groups[m.group] = [];
            groups[m.group].push(m);
        });

        let html = '';
        for (const [grp, items] of Object.entries(groups)) {
            const b = BADGE[grp] || BADGE['Stats'];
            html += `<div style="padding:8px 14px 4px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;">${grp}</div>`;
            items.forEach(item => {
                html += `
                <div class="global-result-item"
                    data-target="${item.target}"
                    style="display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .15s;">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap;flex-shrink:0;background:${b.bg};color:${b.color};">${b.text}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${hl(item.label, q)}</div>
                        <div style="font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${hl(item.detail, q)}</div>
                    </div>
                    <i class="fa-solid fa-arrow-right" style="font-size:10px;color:#cbd5e1;flex-shrink:0;"></i>
                </div>`;
            });
        }

        resultsEl.innerHTML = html;
        resultsEl.classList.remove('hidden');

        // Click handlers
        resultsEl.querySelectorAll('.global-result-item').forEach(el => {
            el.addEventListener('mouseenter', () => el.style.background = '#f8fafc');
            el.addEventListener('mouseleave', () => el.style.background = '');
            el.addEventListener('click', () => {
                scrollTo(el.dataset.target);
                resultsEl.classList.add('hidden');
                document.getElementById('mainDashboardSearch').value = '';
            });
        });
    }

    // ── SEARCH ───────────────────────────────────────────────────────────────
    document.getElementById('mainDashboardSearch').addEventListener('input', function () {
        const q = this.value.trim();
        const resultsEl = document.getElementById('globalSearchResults');

        if (!q) { resultsEl.classList.add('hidden'); return; }

        const lower = q.toLowerCase();
        const matches = INDEX.filter(item =>
            item.label.toLowerCase().includes(lower) ||
            item.detail.toLowerCase().includes(lower) ||
            item.keywords.some(k => k.includes(lower))
        );

        render(q, matches);
    });

    document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('toggleIcon');
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
    setTimeout(() => { charts.forEach(c => c.resize()); }, 310);
});

    // ── CLOSE ON OUTSIDE CLICK ───────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.main-search-container')) {
            document.getElementById('globalSearchResults').classList.add('hidden');
        }
    });

    // ── KEYBOARD: ESCAPE TO CLOSE ────────────────────────────────────────────
    document.getElementById('mainDashboardSearch').addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('globalSearchResults').classList.add('hidden');
            this.value = '';
        }
    });

})();

</script>


</body>
