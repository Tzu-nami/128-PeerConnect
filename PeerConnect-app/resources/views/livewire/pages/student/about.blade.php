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
        :root {
            --sidebar-green: #1a3c2f;
            --header-maroon: #7b1d1d;
            --bg-light: #f4f7f6;
            --header-height: 80px;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        .sidebar {
            width: var(--sidebar-width); background: var(--sidebar-green); flex-shrink: 0;
            display: flex; flex-direction: column; color: white; height: 100vh;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 30; position: relative;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-logo-container { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; gap: 15px; flex-shrink: 0; overflow: hidden; }
        #sidebarToggle { background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .logo-text { font-size: 1.1rem; font-weight: 700; }

        .nav-item {
            display: flex; align-items: center; gap: 15px; padding: 15px 25px;
            color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s ease;
            white-space: nowrap; position: relative; background: transparent; border: none; width: 100%; cursor: pointer;
        }
        .nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }
        .nav-item::after {
            content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 10px; background: rgba(0,0,0,0.9); color: white; padding: 5px 12px;
            border-radius: 4px; font-size: 12px; opacity: 0; visibility: hidden; transition: opacity 0.2s; pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }
        .sidebar.collapsed .logo-content, .sidebar.collapsed .nav-item span { display: none; }
        .sidebar.collapsed .sidebar-logo-container, .sidebar.collapsed .nav-item { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; }

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }
    </style>
</head>

<body>
    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
                <button id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <div class="logo-content">
                    <i class="fa-solid fa-graduation-cap text-xl"></i>
                    <span class="logo-text">LRC PeerConnect</span>
                </div>
            </div>
            <nav class="flex-grow">
                <a href="{{ route('student.dashboard') }}" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.bookings') }}" class="nav-item {{ request()->routeIs('student.bookings') ? 'active' : '' }}" data-tooltip="Bookings">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Bookings</span>
                </a>
                <a href="{{ route('student.history') }}" class="nav-item {{ request()->routeIs('student.history') ? 'active' : '' }}" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i><span>History</span>
                </a>
                <a href="{{ route('student.mentors') }}" class="nav-item {{ request()->routeIs('student.mentors') ? 'active' : '' }}" data-tooltip="Mentors">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentors</span>
                </a>
                <a href="{{ route('student.about') }}" class="nav-item {{ request()->routeIs('student.about') ? 'active' : '' }}" data-tooltip="About Us">
                    <i class="fa-solid fa-circle-info w-5"></i><span>About Us</span>
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
                    <a href="#" class="dropdown-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">
                <div class="mb-6">
                    <h1 class="text-2xl font-black text-slate-800">About Us</h1>
                    <p class="text-sm text-gray-400 mt-1">Learn more about the LRC PeerConnect platform.</p>
                </div>

                <div class="max-w-3xl space-y-5">

                     About Card
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-[#1a3c2f] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-graduation-cap text-white text-sm"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-slate-800 text-sm">LRC PeerConnect</h2>
                                <p class="text-[11px] text-gray-400">University of the Philippines Baguio — Learning Resource Center</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed">
                            LRC PeerConnect is a peer mentoring platform that connects UPB students with trained student-mentors for
                            enrichment sessions and academic support. Students can book one-on-one sessions, track their history,
                            and browse available mentors — all in one place.
                        </p>
                    </div>

                     How It Works
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <h2 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-route text-[#7b1d1d] text-xs"></i> How It Works
                        </h2>
                        <div class="space-y-3">
                            @foreach([
                                ['1', 'Complete Your Student Profile', 'Fill in your student number, college, degree program, and year level.'],
                                ['2', 'Submit a Booking Request',     'Pick a subject, topic, preferred date and time, and select a mentor.'],
                                ['3', 'Await Mentor Approval',        'Your mentor will review and confirm the session request.'],
                                ['4', 'Attend Your Session',          'Show up on time and make the most of your enrichment session!'],
                            ] as [$num, $title, $desc])
                                <div class="flex items-start gap-4">
                                    <div class="w-7 h-7 rounded-lg bg-[#1a3c2f] text-white text-xs font-black flex items-center justify-center flex-shrink-0 mt-0.5">{{ $num }}</div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700">{{ $title }}</p>
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                     Contact & Reminder row
                    <div class="grid grid-cols-2 gap-5">
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                            <h2 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-location-dot text-[#7b1d1d] text-xs"></i> Contact & Location
                            </h2>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-building-columns text-gray-400 text-xs mt-1 w-4 text-center"></i>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700">Location</p>
                                        <p class="text-xs text-gray-400">Learning Resource Center, UPB</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-clock text-gray-400 text-xs mt-1 w-4 text-center"></i>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700">Hours</p>
                                        <p class="text-xs text-gray-400">Mon – Fri, 8:00 AM – 5:00 PM</p>
                                    </div>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fa-solid fa-envelope text-gray-400 text-xs mt-1 w-4 text-center"></i>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700">Email</p>
                                        <p class="text-xs text-gray-400">lrc@up.edu.ph</p>
                                    </div>
                                </li>
                            </ul>
                        </div>

                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        document.getElementById('sidebarToggle').addEventListener('click', () => sidebar.classList.toggle('collapsed'));
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        window.addEventListener('click', () => profileDropdown.classList.remove('show'));
    </script>
</body>
