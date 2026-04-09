<?php

use function Livewire\Volt\{layout, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect | About Us</title>
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
        .schedule-block{
font-size:9px;
line-height:1.2;
padding:2px 4px;
margin-bottom:2px;
border-radius:4px;
background:#d1fae5;
color:#065f46;
}

.notif-dot {
    width: 6px;
    height: 6px;
    background: #3b82f6; /* blue */
    border-radius: 50%;
    position: top;
    bottom: 6px;
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
                <a href="{{ route('student.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.mentors') }}" class="nav-item active" data-tooltip="Mentors">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentors</span>
                </a>
                <a href="{{ route('student.bookings') }}" class="nav-item" data-tooltip="Bookings">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Bookings</span>
                </a>
                <a href="{{ route('student.history') }}" class="nav-item" data-tooltip="History">
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
                <div class="text-lg">Welcome, {{ auth()->user()->user_roles }} <span class="font-bold">{{ auth()->user()->name }}</span></div>
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

    <!-- Header -->
    <div class="mb-5">
        <h1 class="text-3xl font-black text-slate-800">About Us</h1>
        <p class="text-base text-gray-600 mt-1">Learn more about the LRC PeerConnect platform.</p>
    </div>

    <!-- Hero -->
    <div class="bg-white rounded-xl p-6 shadow-sm border flex items-center gap-6 mb-5">
        <div class="w-16 h-16 bg-[#1a3c2f] rounded-xl flex items-center justify-center">
            <i class="fa-solid fa-graduation-cap text-white text-2xl"></i>
        </div>
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">LRC PeerConnect</h2>
            <p class="text-base text-gray-500">
                Connecting UPB students with mentors for academic success.
            </p>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-3 gap-5">

        <!-- LEFT SIDE -->
        <div class="col-span-2 flex flex-col gap-5 h-full">

            <!-- WHAT IS PEERCONNECT (ENHANCED) -->
            <div class="bg-white rounded-xl border shadow-sm p-5 flex flex-col">

                <div>
                    <h2 class="text-lg font-bold text-slate-800 mb-3">What is PeerConnect?</h2>

                    <p class="text-base text-gray-600 leading-relaxed mb-4">
                        LRC PeerConnect is a peer mentoring platform that connects UPB students with trained
                        student-mentors for enrichment sessions and academic support.
                    </p>

                    <!-- Feature Highlights -->
                    <div class="grid grid-cols-3 gap-4 mt-4">

                        <div class="flex flex-col items-center text-center">
                            <div class="w-10 h-10 bg-[#1a3c2f]/10 text-[#1a3c2f] rounded-lg flex items-center justify-center mb-2">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <p class="text-sm font-semibold">Easy Booking</p>
                        </div>

                        <div class="flex flex-col items-center text-center">
                            <div class="w-10 h-10 bg-[#1a3c2f]/10 text-[#1a3c2f] rounded-lg flex items-center justify-center mb-2">
                                <i class="fa-solid fa-user-group"></i>
                            </div>
                            <p class="text-sm font-semibold">Peer Mentors</p>
                        </div>

                        <div class="flex flex-col items-center text-center">
                            <div class="w-10 h-10 bg-[#1a3c2f]/10 text-[#1a3c2f] rounded-lg flex items-center justify-center mb-2">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <p class="text-sm font-semibold">Track History</p>
                        </div>
                    </div>
                </div>

                <!-- Bottom subtle highlight -->
                <div class="mt-5 bg-gray-50 border rounded-lg p-3 text-sm text-gray-500">
                    All-in-one platform for booking, tracking, and connecting with mentors.
                </div>

            </div>

            <!-- HOW IT WORKS -->
            <div class="bg-white rounded-xl border shadow-sm p-6 flex flex-col justify-center">

                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-route text-[#7b1d1d]"></i> How It Works
                </h2>

                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['1', 'Complete Profile'],
                        ['2', 'Book Session'],
                        ['3', 'Wait Approval'],
                        ['4', 'Attend Session'],
                    ] as [$num, $title])
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#7b1d1d] text-white rounded-full flex items-center justify-center text-sm font-bold">
                                {{ $num }}
                            </div>
                            <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
                        </div>
                    @endforeach
                </div>

            </div>

        </div>

        <!-- RIGHT SIDE -->
        <div class="flex flex-col gap-5 h-full">

            <!-- CONTACT -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h2 class="text-base font-bold text-slate-800 mb-3">Contact</h2>

                <ul class="space-y-3 text-sm text-gray-600">
                    <li>📍 Learning Resource Center, UPB</li>
                    <li>🕒 Mon – Fri, 8:00 AM – 5:00 PM</li>
                    <li>✉ lrc@up.edu.ph</li>
                    <li></li>
                </ul>
            </div>

            <!-- TIPS -->
            <div class="bg-yellow-50 border border-yellow-200 p-5 rounded-xl">
                <h2 class="text-sm font-bold text-yellow-800 mb-2">Student Tips</h2>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Book early</li>
                    <li>• Be specific with topics</li>
                    <li>• Arrive on time</li>
                    <li>• Prepare questions</li>
                    <li></li>
                </ul>
            </div>

        </div>

    </div>
</main>


        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('toggleIcon');
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
});        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        window.addEventListener('click', () => profileDropdown.classList.remove('show'));
    </script>
</body>
