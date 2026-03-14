<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
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
        
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }
        
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

        .nav-item { 
            display: flex; align-items: center; gap: 15px; padding: 15px 25px; 
            color: rgba(255,255,255,0.7); text-decoration: none; transition: background 0.3s; white-space: nowrap;
            position: relative;
        }
        .nav-item i { width: 30px; text-align: center; flex-shrink: 0; font-size: 20px; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { border-left: 4px solid white; }

        /* TOOLTIP LOGIC FOR COLLAPSED STATE */
        .sidebar.collapsed .nav-item::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            margin-left: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
            pointer-events: none;
            z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after {
            opacity: 1;
            visibility: visible;
        }

        .sidebar.collapsed .logo-content, .sidebar.collapsed .nav-item span { display: none; }
        .sidebar.collapsed .sidebar-logo-container, .sidebar.collapsed .nav-item { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .nav-item.active { border-left: none; }

        /* MAIN CONTENT AREA FIXES */
        .main-content { 
            flex: 1; 
            min-width: 0; /* Critical for chart resizing in flexbox */
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden; 
        }
        .top-header { 
            background: var(--header-maroon); height: var(--header-height); padding: 0 40px; 
            display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; 
        }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        /* CALENDAR STYLING */
        .cal-header-day { font-size: 11px; font-weight: 800; color: #94a3b8; text-align: center; padding-bottom: 10px; text-transform: uppercase; }
        .cal-day { 
            aspect-ratio: 1/1; display: flex; flex-direction: column; align-items: center; justify-content: center; 
            position: relative; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 13px; font-weight: 500;
        }
        .cal-day:hover { background: #f1f5f9; }
        .cal-today { background: #fee2e2 !important; color: var(--header-maroon) !important; font-weight: 800; }
        .cal-selected { border: 2px solid var(--header-maroon); background: #f8fafc; }
        .session-dot { width: 4px; height: 4px; background: var(--header-maroon); border-radius: 50%; margin-top: 2px; }

        /* STATISTICS CONTAINER */
        .stats-overview-container {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb;
            width: 100%;
        }
        .stats-header {
            padding: 12px 24px; background: #f8fafc; font-weight: 700; font-size: 0.9rem;
            color: #1e293b; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;
        }
        .stats-body { display: grid; grid-template-columns: repeat(2, 1fr); background: white; width: 100%; }
        .stats-column { padding: 24px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; min-width: 0; }
        .stats-column:nth-child(2n) { border-right: none; }
        .stats-column-title { font-weight: 600; margin-bottom: 15px; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }

        /* STAT CARDS */
        .stat-card { 
            background: white; padding: 25px; border-radius: 12px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--sidebar-green); }
        .stat-card i { font-size: 24px; color: var(--sidebar-green); }

        /* PAGINATION & FILTERS */
        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
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
                <a href="#" class="nav-item active" data-tooltip="Dashboard"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
                <a href="#" class="nav-item" data-tooltip="Mentor Management"><i class="fa-solid fa-chalkboard-user"></i><span>Mentor Management</span></a>
                <a href="#" class="nav-item" data-tooltip="Session Management"><i class="fa-solid fa-calendar-days"></i><span>Session Management</span></a>
                <a href="#" class="nav-item" data-tooltip="Student Feedback"><i class="fa-solid fa-comments"></i><span>Student Feedback</span></a>
            </nav>
            <div class="p-4 border-t border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item w-full" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span class="ml-4">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-red-900 font-bold">
                    {{ strtoupper(substr(auth()->user()->name,0,2)) }}
                </div>
            </header>

            <main class="scroll-container">
                <div class="grid grid-cols-5 gap-4 mb-8">
                    <div class="stat-card flex items-center gap-4"><i class="fa-solid fa-users"></i><div><h3 class="text-xs font-bold text-gray-400 uppercase">Total Mentors</h3><p class="text-2xl font-black">40</p></div></div>
                    <div class="stat-card flex items-center gap-4"><i class="fa-solid fa-calendar-day"></i><div><h3 class="text-xs font-bold text-gray-400 uppercase">Sessions Today</h3><p class="text-2xl font-black">18</p></div></div>
                    <div class="stat-card flex items-center gap-4"><i class="fa-solid fa-clock"></i><div><h3 class="text-xs font-bold text-gray-400 uppercase">Pending</h3><p class="text-2xl font-black">5</p></div></div>
                    <div class="stat-card flex items-center gap-4"><i class="fa-solid fa-star"></i><div><h3 class="text-xs font-bold text-gray-400 uppercase">Ratings</h3><p class="text-2xl font-black">4.9</p></div></div>
                    <div class="stat-card flex items-center gap-4"><i class="fa-solid fa-user-graduate"></i><div><h3 class="text-xs font-bold text-gray-400 uppercase">Total Mentees</h3><p class="text-2xl font-black">75</p></div></div>
                </div>

                <div class="grid grid-cols-3 gap-8">
                    <div class="col-span-2 space-y-8">
                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 min-h-[460px] flex flex-col">
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
                                    <p class="text-xs text-gray-400" id="tableSubtitle">March 14, 2026</p>
                                </div>
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

                            <div class="flex-grow">
                                <table class="w-full text-left text-sm">
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

                            <div class="mt-6 pt-4 border-t border-gray-50 flex items-center justify-between">
                                <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator"></div>
                                <div class="flex gap-2">
                                    <button id="prevBtn" class="pagination-btn"><i class="fa-solid fa-chevron-left"></i></button>
                                    <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="stats-overview-container">
                            <div class="stats-header">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-chart-line text-maroon-800"></i>
                                    <span>LRC Performance Analytics</span>
                                </div>
                                <select class="header-filter" id="analyticsSubjectFilter">
                                    <option value="All">All Subjects</option>
                                    <option value="CMSC 55">CMSC 55</option>
                                    <option value="CMSC 116">CMSC 116</option>
                                    <option value="Math 55">Math 55</option>
                                    <option value="Math 53">Math 53</option>
                                </select>
                            </div>
                            <div class="stats-body">
                                <div class="stats-column"><div class="stats-column-title">Monthly Session Trends</div><div class="h-44"><canvas id="lineChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Top Mentors</div><div class="h-44"><canvas id="pieChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Satisfaction Rate</div><div class="h-44 flex justify-center"><canvas id="doughnutChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Most Active College</div><div class="h-44"><canvas id="activeCollegeChart"></canvas></div></div>
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
                            <h3 class="font-bold mb-4 text-slate-800 text-sm tracking-tight">Quick Actions</h3>
                            <div class="flex flex-col gap-2">
                                <button class="w-full bg-slate-800 text-white p-3 rounded-lg text-xs font-bold hover:bg-black transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-user-plus"></i> Add Mentor
                                </button>
                                <button class="w-full border border-slate-300 p-3 rounded-lg text-xs font-bold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-calendar-plus"></i> Create Session
                                </button>
                                <button class="w-full border border-slate-300 p-3 rounded-lg text-xs font-bold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-book-open"></i> Manage Subjects
                                </button>
                                <button class="w-full border border-slate-300 p-3 rounded-lg text-xs font-bold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-file-invoice"></i> Generate Report
                                </button>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Approvals</h3>
                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">3 New</span>
                            </div>
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold">JD</div>
                                        <div>
                                            <p class="text-[11px] font-bold text-slate-700">John Doe</p>
                                            <p class="text-[9px] text-gray-400 font-medium">Mentor Applicant</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-600 transition flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 transition flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold">AS</div>
                                        <div>
                                            <p class="text-[11px] font-bold text-slate-700">Anna Smith</p>
                                            <p class="text-[9px] text-gray-400 font-medium">Session Change Request</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button class="w-6 h-6 rounded-md bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-600 transition flex items-center justify-center"><i class="fa-solid fa-xmark text-[10px]"></i></button>
                                        <button class="w-6 h-6 rounded-md bg-gray-50 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 transition flex items-center justify-center"><i class="fa-solid fa-check text-[10px]"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button class="w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">
                                View All Requests
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const charts = []; 

        document.getElementById('sidebarToggle').addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            // Manual resize call as fallback to the observer
            setTimeout(() => { charts.forEach(c => c.resize()); }, 310);
        });

        // Robust Resize Observer to ensure charts always maximize space
        const mainContent = document.querySelector('.main-content');
        const resizeObserver = new ResizeObserver(() => {
            charts.forEach(chart => chart.resize());
        });
        resizeObserver.observe(mainContent);

        // Clock logic
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000); 
        updateClock();

        // DATA
        const allSessions = [
            { date: '2026-03-14', mentor: "Daniel Dyoco", mentee: "Frian Nabo", time: "09:00 AM", status: "Completed", color: "text-blue-600" },
            { date: '2026-03-14', mentor: "Rhona Shayne Lopez", mentee: "Mark Tuan", time: "10:30 AM", status: "Active", color: "text-green-600" },
            { date: '2026-03-14', mentor: "Chezka Sinco", mentee: "Uno Dos Thirdy", time: "11:00 AM", status: "Active", color: "text-green-600" },
            { date: '2026-03-14', mentor: "Arielle Mae Solis", mentee: "Kevin Hart", time: "01:00 PM", status: "Upcoming", color: "text-orange-500" },
            { date: '2026-03-14', mentor: "Ax'l Conchada", mentee: "Alice Blue", time: "02:30 PM", status: "Upcoming", color: "text-orange-500" },
            { date: '2026-03-14', mentor: "Vonn Rosario", mentee: "Hannibal L.", time: "04:00 PM", status: "Upcoming", color: "text-orange-500" },
            { date: '2026-03-16', mentor: "Uno Dos Thirdy", mentee: "Steve Trevor", time: "10:00 AM", status: "Upcoming", color: "text-orange-500" }
        ];

        let selectedDateStr = '2026-03-14';
        let viewDate = new Date(2026, 2, 1);
        let currentPage = 1;
        const rowsPerPage = 5;

        // CALENDAR LOGIC
        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthDisp = document.getElementById('monthDisplay');
            grid.innerHTML = '';
            monthDisp.innerText = viewDate.toLocaleString('default', { month: 'long', year: 'numeric' });
            
            const lastDay = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
            const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

            for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

            for (let i = 1; i <= lastDay; i++) {
                const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                const hasSessions = allSessions.some(s => s.date === dateStr);
                const isToday = i === 14 && viewDate.getMonth() === 2;
                const isSelected = dateStr === selectedDateStr;

                const dayEl = document.createElement('div');
                dayEl.className = `cal-day ${isToday ? 'cal-today' : ''} ${isSelected ? 'cal-selected' : ''}`;
                dayEl.innerHTML = `<span>${i}</span>${hasSessions ? '<div class="session-dot"></div>' : ''}`;
                dayEl.onclick = () => {
                    selectedDateStr = dateStr;
                    document.getElementById('tableTitle').innerText = dateStr === '2026-03-14' ? "Today's Schedule" : "Daily Schedule";
                    document.getElementById('tableSubtitle').innerText = new Date(dateStr).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    currentPage = 1;
                    applyFilters();
                    renderCalendar();
                };
                grid.appendChild(dayEl);
            }
        }

        function changeMonth(dir) { viewDate.setMonth(viewDate.getMonth() + dir); renderCalendar(); }

        // TABLE LOGIC
        function applyFilters() {
            const searchTerm = document.getElementById('liveSearchInput').value.toLowerCase();
            const statusTerm = document.getElementById('statusFilter').value;

            const filtered = allSessions.filter(item => {
                const matchesDate = item.date === selectedDateStr;
                const matchesSearch = item.mentor.toLowerCase().includes(searchTerm) || item.mentee.toLowerCase().includes(searchTerm);
                const matchesStatus = statusTerm === "All" || item.status === statusTerm;
                return matchesDate && matchesSearch && matchesStatus;
            });

            const tbody = document.getElementById('tableBody');
            const start = (currentPage - 1) * rowsPerPage;
            const paginated = filtered.slice(start, start + rowsPerPage);

            tbody.innerHTML = paginated.length ? paginated.map(row => `
                <tr class="border-b last:border-0 hover:bg-gray-50 transition">
                    <td class="py-4 font-bold text-slate-700">${row.mentor}</td>
                    <td class="text-slate-600">${row.mentee}</td>
                    <td class="text-slate-500">${row.time}</td>
                    <td><span class="${row.color} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded">${row.status}</span></td>
                </tr>
            `).join('') : `<tr><td colspan="4" class="py-12 text-center text-gray-400">No sessions for this date</td></tr>`;

            document.getElementById('pageIndicator').innerText = `Showing ${filtered.length ? start + 1 : 0} to ${Math.min(start + rowsPerPage, filtered.length)} of ${filtered.length}`;
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = start + rowsPerPage >= filtered.length;
        }

        // CHARTS
        const ctxOptions = { maintainAspectRatio: false, plugins: { legend: { display: false } } };

        charts.push(new Chart(document.getElementById('lineChart'), { type: 'line', data: { labels: ['W1', 'W2', 'W3', 'W4'], datasets: [{ data: [45, 52, 38, 65], borderColor: '#7b1d1d', tension: 0.4 }] }, options: ctxOptions }));
        
        charts.push(new Chart(document.getElementById('pieChart'), { type: 'pie', data: { labels: ['Daniel D.', 'Sarah J.', 'James W.', 'Others'], datasets: [{ data: [40, 25, 20, 15], backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1'] }] }, options: { ...ctxOptions, plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } } } }));
        
        charts.push(new Chart(document.getElementById('doughnutChart'), { 
            type: 'doughnut', 
            data: { labels: ['Excl', 'Good', 'Avg'], datasets: [{ data: [70, 20, 10], backgroundColor: ['#1a3c2f', '#7b1d1d', '#cbd5e1'], borderWidth: 0 }] }, 
            options: { ...ctxOptions, cutout: '75%', scales: { x: { display: false }, y: { display: false } }, plugins: { legend: { display: true, position: 'bottom' } } } 
        }));        

        charts.push(new Chart(document.getElementById('activeCollegeChart'), { 
            type: 'bar', 
            data: { labels: ['CSS', 'CAC', 'CS'], datasets: [{ data: [85, 42, 68], backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8'], borderRadius: 4 }] }, 
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 10 } } }, y: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } } } } 
        }));

        // INITIALIZE
        document.getElementById('liveSearchInput').addEventListener('input', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('prevBtn').onclick = () => { if(currentPage > 1) { currentPage--; applyFilters(); } };
        document.getElementById('nextBtn').onclick = () => { if(currentPage * rowsPerPage < allSessions.length) { currentPage++; applyFilters(); } };

        renderCalendar();
        applyFilters();
    </script>
</body>