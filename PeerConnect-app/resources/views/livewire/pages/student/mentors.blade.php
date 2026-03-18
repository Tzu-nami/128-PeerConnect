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
    <title>LRC PeerConnect | Mentors</title>
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

        .mentor-card { background: white; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; transition: all 0.25s ease; }
        .mentor-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -4px rgba(0,0,0,0.1); border-color: var(--sidebar-green); }
        .subject-pill { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .day-pill { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: #f1f5f9; color: #475569; }

        .mentor-card.hidden-card { display: none; }
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
    
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-slate-800">Our Peer Mentors</h1>
                        <p class="text-sm text-gray-400 mt-1">Browse available mentors and their expertise.</p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <div class="relative">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input id="mentorSearch" type="text" placeholder="Search mentors..."
                                   class="pl-8 pr-3 py-2 text-xs border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:ring-red-800 w-48">
                        </div>
                        <select id="subjectFilter" class="bg-white border border-gray-200 rounded-lg px-8 py-2 text-xs text-slate-600 outline-none cursor-pointer">
                            <option value="">All Subjects</option>
                            <option value="CMSC 11">CMSC 11</option>
                            <option value="CMSC 123">CMSC 123</option>
                            <option value="CMSC 128">CMSC 128</option>
                            <option value="Math 54">Math 54</option>
                            <option value="Math 53">Math 53</option>
                            <option value="Phys 101">Phys 101</option>
                            <option value="Phys 102">Phys 102</option>
                        </select>
                    </div>

                </div>

                <p class="text-xs text-gray-400 mb-4 font-medium" id="mentorCount">8 mentors found</p>

                <div id="mentorGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">

                    <div class="mentor-card" data-name="daniel dyoco" data-subjects="CMSC 11 CMSC 123 Math 53">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-emerald-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">DD</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Daniel Dyoco</p>
                                <p class="text-[11px] text-gray-400 truncate">d.dyoco@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">CMSC 11</span>
                                <span class="subject-pill">CMSC 123</span>
                                <span class="subject-pill">Math 53</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Mon</span><span class="day-pill">Wed</span><span class="day-pill">Fri</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="rhona shayne lopez" data-subjects="Math 54 Math 53 CMSC 11">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-teal-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">RL</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Rhona Shayne Lopez</p>
                                <p class="text-[11px] text-gray-400 truncate">rs.lopez@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">Math 54</span>
                                <span class="subject-pill">Math 53</span>
                                <span class="subject-pill">CMSC 11</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Tue</span><span class="day-pill">Thu</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="chezka sinco" data-subjects="CMSC 123 CMSC 128 Phys 101">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-cyan-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">CMSC</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Chezka Sinco</p>
                                <p class="text-[11px] text-gray-400 truncate">c.sinco@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">CMSC 123</span>
                                <span class="subject-pill">CMSC 128</span>
                                <span class="subject-pill">Phys 101</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Mon</span><span class="day-pill">Wed</span><span class="day-pill">Sat</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="arielle mae solis" data-subjects="Phys 101 Phys 102 Math 54">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-indigo-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">AS</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Arielle Mae Solis</p>
                                <p class="text-[11px] text-gray-400 truncate">am.solis@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">Phys 101</span>
                                <span class="subject-pill">Phys 102</span>
                                <span class="subject-pill">Math 54</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Tue</span><span class="day-pill">Thu</span><span class="day-pill">Fri</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="axl conchada" data-subjects="CMSC 128 CMSC 123">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-violet-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">AC</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Ax'l Conchada</p>
                                <p class="text-[11px] text-gray-400 truncate">a.conchada@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">CMSC 128</span>
                                <span class="subject-pill">CMSC 123</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Mon</span><span class="day-pill">Fri</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="juan dela cruz" data-subjects="Math 54 Phys 102">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-rose-800 text-white flex items-center justify-center text-sm font-black flex-shrink-0">JD</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Juan Dela Cruz</p>
                                <p class="text-[11px] text-gray-400 truncate">j.delacruz@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">Math 54</span>
                                <span class="subject-pill">Phys 102</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Wed</span><span class="day-pill">Thu</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="maria santos" data-subjects="CMSC 11 Phys 101">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-emerald-700 text-white flex items-center justify-center text-sm font-black flex-shrink-0">MS</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Maria Santos</p>
                                <p class="text-[11px] text-gray-400 truncate">m.santos@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">CMSC 11</span>
                                <span class="subject-pill">Phys 101</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Mon</span><span class="day-pill">Tue</span><span class="day-pill">Thu</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                    <div class="mentor-card" data-name="kevin reyes" data-subjects="CMSC 128 Math 53 CMSC 11">
                        <div class="p-5 flex items-center gap-4 border-b border-gray-50">
                            <div class="w-12 h-12 rounded-xl bg-teal-700 text-white flex items-center justify-center text-sm font-black flex-shrink-0">KR</div>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 text-sm truncate">Kevin Reyes</p>
                                <p class="text-[11px] text-gray-400 truncate">k.reyes@up.edu.ph</p>
                            </div>
                        </div>
                        <div class="px-5 pt-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="subject-pill">CMSC 128</span>
                                <span class="subject-pill">Math 53</span>
                                <span class="subject-pill">CMSC 11</span>
                            </div>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Available Days</p>
                            <div class="flex flex-wrap gap-1">
                                <span class="day-pill">Tue</span><span class="day-pill">Wed</span><span class="day-pill">Fri</span>
                            </div>
                        </div>
                        <div class="px-5 pb-5">
                            <a href="{{ route('student.bookings') }}" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#122b21] text-white text-xs font-bold py-2.5 rounded-lg transition">Book a Session</a>
                        </div>
                    </div>

                </div>

                <div id="mentorEmpty" class="hidden bg-white rounded-xl border border-gray-100 py-20 text-center">
                    <i class="fa-solid fa-chalkboard-user text-4xl text-gray-200 mb-4 block"></i>
                    <p class="text-gray-400 font-medium">No mentors found.</p>
                    <p class="text-gray-300 text-xs mt-1">Try adjusting your search or filter.</p>
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

        function filterMentors() {
            const search = document.getElementById('mentorSearch').value.toLowerCase();
            const subject = document.getElementById('subjectFilter').value;
            const cards = document.querySelectorAll('.mentor-card');
            let visible = 0;

            cards.forEach(card => {
                const name = card.dataset.name || '';
                const subjects = card.dataset.subjects || '';
                const matchSearch = !search || name.includes(search);
                const matchSubject = !subject || subjects.includes(subject);
                const show = matchSearch && matchSubject;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            document.getElementById('mentorCount').innerText = `${visible} mentor${visible !== 1 ? 's' : ''} found`;
            document.getElementById('mentorEmpty').classList.toggle('hidden', visible > 0);
            document.getElementById('mentorGrid').classList.toggle('hidden', visible === 0);
        }

        document.getElementById('mentorSearch').addEventListener('input', filterMentors);
        document.getElementById('subjectFilter').addEventListener('change', filterMentors);
    </script>
</body>
