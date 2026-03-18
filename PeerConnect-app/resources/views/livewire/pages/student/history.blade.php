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
    <title>LRC PeerConnect | History</title>
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

        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
        .status-pending   { background: #fef9c3; color: #854d0e; }
        .status-approved  { background: #dcfce7; color: #166534; }
        .status-rejected  { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-no-show   { background: #fce7f3; color: #9d174d; }

        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 14px; }
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

                <div class="mb-6">
                    <h1 class="text-2xl font-black text-slate-800">Session History</h1>
                    <p class="text-sm text-gray-400 mt-1">View all your past and current enrichment session bookings.</p>
                </div>

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                            <i class="fa-solid fa-list-check text-slate-600"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-400 uppercase">Total</p>
                            <p class="text-xl font-black text-slate-800">8</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <i class="fa-solid fa-circle-check text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-400 uppercase">Completed</p>
                            <p class="text-xl font-black text-slate-800">5</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-yellow-50 flex items-center justify-center">
                            <i class="fa-solid fa-clock text-yellow-500"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-400 uppercase">Ongoing</p>
                            <p class="text-xl font-black text-slate-800">2</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                            <i class="fa-solid fa-ban text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-gray-400 uppercase">Cancelled</p>
                            <p class="text-xl font-black text-slate-800">1</p>
                        </div>
                    </div>
                </div>

                {{-- Table --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                        <h2 class="font-bold text-slate-800 text-sm">All Bookings</h2>
                        <div class="flex gap-2 flex-wrap">
                            <div class="relative">
                                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                <input id="historySearch" type="text" placeholder="Search subject or topic..."
                                       class="pl-8 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800 w-52">
                            </div>
                            <select id="historyStatusFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-slate-600 outline-none cursor-pointer">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="completed">Completed</option>
                                <option value="rejected">Rejected</option>
                                <option value="no-show">No Show</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 border-b border-gray-100">
                            <tr>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">#</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Subject</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Topic</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mentor</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Date & Time</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mode</th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                            </thead>
                            <tbody id="historyTableBody">
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-[11px] text-gray-400 font-medium" id="historyPageIndicator">Showing 0 results</p>
                        <div class="flex gap-2">
                            <button id="historyPrevBtn" class="px-3 py-1.5 text-[11px] font-semibold border border-gray-200 rounded-lg text-gray-500 hover:border-red-800 hover:text-red-800 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <button id="historyNextBtn" class="px-3 py-1.5 text-[11px] font-semibold border border-gray-200 rounded-lg text-gray-500 hover:border-red-800 hover:text-red-800 disabled:opacity-40 disabled:cursor-not-allowed transition">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar
        const sidebar = document.getElementById('sidebar');
        document.getElementById('sidebarToggle').addEventListener('click', () => sidebar.classList.toggle('collapsed'));
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        window.addEventListener('click', () => profileDropdown.classList.remove('show'));

        // Hardcoded data
        const bookings = [
            { subject: 'CMSC 11', subjectName: 'Introduction to Computing', topic: 'Binary & Hexadecimal', mentor: 'Dyoco, Daniel', date: 'Mar 1, 2026', time: '9:00 AM – 10:30 AM', mode: 'Face-to-face', status: 'completed' },
            { subject: 'Math 53', subjectName: 'Elementary Analysis I', topic: 'Derivatives', mentor: 'Lopez, Rhona Shayne', date: 'Mar 3, 2026', time: '1:00 PM – 2:30 PM', mode: 'Online', status: 'completed' },
            { subject: 'CMSC 123', subjectName: 'Data Structures and Algorithms', topic: 'Linked Lists', mentor: 'Sinco, Chezka', date: 'Mar 5, 2026', time: '10:00 AM – 11:30 AM', mode: 'Face-to-face', status: 'completed' },
            { subject: 'Phys 101', subjectName: 'Physics', topic: 'Newton\'s Laws', mentor: 'Solis, Arielle Mae', date: 'Mar 7, 2026', time: '3:00 PM – 4:30 PM', mode: 'Face-to-face', status: 'completed' },
            { subject: 'CMSC 128', subjectName: 'Introduction to Software Engineering', topic: 'Develop ka website mo', mentor: 'Conchada, Ax\'l', date: 'Mar 10, 2026', time: '9:00 AM – 10:30 AM', mode: 'Online', status: 'completed' },
            { subject: 'Math 54', subjectName: 'Calculus I', topic: 'Integration by Parts', mentor: 'Dyoco, Daniel', date: 'Mar 14, 2026', time: '11:00 AM – 12:30 PM', mode: 'Face-to-face', status: 'approved' },
            { subject: 'CMSC 123', subjectName: 'Data Structures and Algorithms', topic: 'Binary Trees', mentor: 'Lopez, Rhona Shayne', date: 'Mar 18, 2026', time: '2:00 PM – 3:30 PM', mode: 'Online', status: 'pending' },
            { subject: 'CMSC 11', subjectName: 'Introduction to Computing', topic: 'Flowcharts', mentor: 'Sinco, Chezka', date: 'Feb 20, 2026', time: '10:00 AM – 11:00 AM', mode: 'Face-to-face', status: 'rejected' },
        ];

        const statusBadge = {
            pending:   '<span class="status-badge status-pending">Pending</span>',
            approved:  '<span class="status-badge status-approved">Approved</span>',
            completed: '<span class="status-badge status-completed">Completed</span>',
            rejected:  '<span class="status-badge status-rejected">Rejected</span>',
            'no-show': '<span class="status-badge status-no-show">No Show</span>',
        };

        const mentorColors = ['bg-emerald-800','bg-teal-800','bg-cyan-800','bg-indigo-800','bg-violet-800'];
        function mentorInitials(name) {
            const parts = name.replace(',','').split(' ');
            return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
        }
        function mentorColor(name) { return mentorColors[name.charCodeAt(0) % mentorColors.length]; }

        let currentPage = 1;
        const perPage = 5;

        function renderTable() {
            const search = document.getElementById('historySearch').value.toLowerCase();
            const status = document.getElementById('historyStatusFilter').value;

            const filtered = bookings.filter(b => {
                const matchSearch = b.subject.toLowerCase().includes(search) || b.topic.toLowerCase().includes(search) || b.mentor.toLowerCase().includes(search);
                const matchStatus = status === 'all' || b.status === status;
                return matchSearch && matchStatus;
            });

            const totalPages = Math.ceil(filtered.length / perPage);
            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
            const paginated = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);

            const tbody = document.getElementById('historyTableBody');
            if (paginated.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-16 text-center text-gray-400 text-sm italic">No matching records found.</td></tr>`;
            } else {
                tbody.innerHTML = paginated.map((b, i) => `
                <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                    <td class="px-5 py-4 text-gray-400 text-xs">${(currentPage - 1) * perPage + i + 1}</td>
                    <td class="px-5 py-4">
                        <p class="font-bold text-slate-700 text-xs">${b.subject}</p>
                        <p class="text-gray-400 text-[10px]">${b.subjectName}</p>
                    </td>
                    <td class="px-5 py-4 text-slate-600 text-xs max-w-[160px] truncate">${b.topic}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full ${mentorColor(b.mentor)} text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">${mentorInitials(b.mentor)}</div>
                            <span class="text-xs font-medium text-slate-700">${b.mentor}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-xs font-medium text-slate-700">${b.date}</p>
                        <p class="text-[10px] text-gray-400">${b.time}</p>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500">${b.mode}</td>
                    <td class="px-5 py-4">${statusBadge[b.status] || ''}</td>
                </tr>
            `).join('');
            }

            document.getElementById('historyPageIndicator').innerText = filtered.length === 0
                ? 'No results'
                : `Showing ${Math.min((currentPage - 1) * perPage + 1, filtered.length)}–${Math.min(currentPage * perPage, filtered.length)} of ${filtered.length}`;

            document.getElementById('historyPrevBtn').disabled = currentPage <= 1;
            document.getElementById('historyNextBtn').disabled = currentPage >= totalPages;
        }

        document.getElementById('historySearch').addEventListener('input', () => { currentPage = 1; renderTable(); });
        document.getElementById('historyStatusFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });
        document.getElementById('historyPrevBtn').addEventListener('click', () => { currentPage--; renderTable(); });
        document.getElementById('historyNextBtn').addEventListener('click', () => { currentPage++; renderTable(); });

        renderTable();
    </script>
</body>
