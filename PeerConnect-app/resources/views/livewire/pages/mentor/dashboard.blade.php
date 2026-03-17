<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
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
            position: relative; text-align: left; background: transparent; border: none; width: 100%;
        }
        .nav-item i { width: 30px; text-align: center; flex-shrink: 0; font-size: 20px; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
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
            content: attr(data-tooltip);
            position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 10px; background: rgba(0, 0, 0, 0.9); color: white;
            padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
            pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }
        .sidebar.collapsed .logo-content, .sidebar.collapsed .nav-item span { display: none; }
        .sidebar.collapsed .sidebar-logo-container, .sidebar.collapsed .nav-item { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; }
        .sidebar.collapsed .nav-item.active { border-left: none; }

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
                <a href="{{ route('mentor.dashboard') }}" class="nav-item {{ request()->routeIs('mentor.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                </a> 
                <a href="{{ route('mentor.bookings') }}" class="nav-item {{ request()->routeIs('mentor.bookings') ? 'active' : '' }}" data-tooltip="Booking Form">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Booking Form</span>
                </a>                
                <a href="{{ route('mentor.sessions') }}" class="nav-item {{ request()->routeIs('mentor.sessions') ? 'active' : '' }}" data-tooltip="Tutorial Sessions">
                    <i class="fa-solid fa-clock w-5"></i><span>Tutorial Sessions</span>
                </a>                
                <a href="{{ route('mentor.feedbacks') }}" class="nav-item {{ request()->routeIs('mentor.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedbacks">
                    <i class="fa-solid fa-comment-dots w-5"></i><span>Student Feedbacks</span>
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
<div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative">
    
    <div class="relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>

        <input 
            type="text" 
            id="globalSearchInput"
            placeholder="Search ALL sessions (mentee, subject, date, status)..."
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
                                    <p class="text-xs text-gray-400" id="tableSubtitle"></p>
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
                                <table class="w-full text-left text-sm table-fixed">
<thead class="text-gray-400 border-b">
<tr>
<th class="pb-3 w-1/4 font-semibold uppercase text-[10px] tracking-wider">Student</th>
<th class="pb-3 w-1/4 font-semibold uppercase text-[10px] tracking-wider">Subject</th>
<th class="pb-3 w-1/4 font-semibold uppercase text-[10px] tracking-wider">Time</th>
<th class="pb-3 w-1/4 font-semibold uppercase text-[10px] tracking-wider">Status</th>
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
                            <span class="text-xs text-gray-400">8:00 AM – 6:00 PM</span>
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
    </div>

<script>
const globalSearchInput = document.getElementById("globalSearchInput");
const globalSearchResults = document.getElementById("globalSearchResults");

function initDashboard(){

renderCalendar();
refreshSchedules();
generateWeeklySchedule();
updateTableDate();
updateWeekHeaders();
renderQuickActions();
renderPendingRequests();
updateClock();

// Charts (important to initialize AFTER DOM is ready)
initCharts();

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
            item.mentee.toLowerCase().includes(q) ||
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
                        ${r.mentee}
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
renderQuickActions();

}
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        const searchInput = document.getElementById('liveSearchInput');
        const statusFilter = document.getElementById('statusFilter');
        const charts = []; 
        

        // Dashboard Interactivity
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            setTimeout(() => { charts.forEach(c => c.resize()); }, 310);
        });

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        window.addEventListener('click', () => {
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        });

        document.getElementById("toggleRequestsBtn").addEventListener("click", function(){

            showAllRequests = !showAllRequests;
            renderPendingRequests();

        });

        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000); 

        // Local State
const allSessions = [

{ id:1, date:'2026-03-16', mentor:"Daniel Dyoco", mentee:"Frian Nabo", subject:"CMSC 143", start:"09:00", end:"10:30", status:"Completed"},
{ id:2, date:'2026-03-16', mentor:"Rhona Lopez", mentee:"Mark Tuan", subject:"Math 59", start:"10:45", end:"12:00", status:"Active"},
{ id:3, date:'2026-03-16', mentor:"Chezka Sinco", mentee:"Uno Santos", subject:"Programming 1", start:"13:15", end:"14:00", status:"Upcoming"},
{ id:4, date:'2026-03-17', mentor:"Arielle Solis", mentee:"Kevin Hart", subject:"Database Systems", start:"09:30", end:"11:00", status:"Upcoming"},
{ id:5, date:'2026-03-18', mentor:"Sarah Johnson", mentee:"David Kim", subject:"Physics", start:"11:00", end:"12:30", status:"Upcoming"},
{ id:6, date:'2026-03-19', mentor:"James Walker", mentee:"Chris Evans", subject:"Networking", start:"14:00", end:"15:30", status:"Upcoming"},
{ id:7, date:'2026-03-20', mentor:"Maria Santos", mentee:"Liam Cruz", subject:"Web Development", start:"10:00", end:"11:30", status:"Upcoming"},
{ id:8, date:'2026-03-21', mentor:"Maria Santos", mentee:"Liam Cruz", subject:"Web Development", start:"11:00", end:"12:30", status:"Upcoming"}

];

const pendingRequests = [

{ id:101, date:'2026-03-16', mentor:"You", mentee:"Lance Talavera", subject:"CMSC 143", start:"15:00", end:"16:00" },
{ id:102, date:'2026-03-16', mentor:"You", mentee:"Paolo Lapid", subject:"Math 59", start:"10:30", end:"11:30" }, 
{ id:103, date:'2026-03-17', mentor:"You", mentee:"Anna Cruz", subject:"Physics", start:"13:00", end:"14:30" },
{ id:104, date:'2026-03-17', mentor:"You", mentee:"Anna Lyn", subject:"Physics", start:"13:00", end:"14:30" }

];

        let showAllRequests = false;

        const today = new Date(new Date().toLocaleString("en-US",{timeZone:"Asia/Manila"}));

        let selectedDateStr = today.toISOString().split("T")[0];
        let viewDate = new Date(today.getFullYear(), today.getMonth(), 1);

        // Table Logic
        function applyFilters() {
            const tbody = document.getElementById('tableBody');
            const searchTerm = searchInput.value.toLowerCase();
            const selectedStatus = statusFilter.value;

            const filtered = allSessions.filter(item => {
                const matchesDate = item.date === selectedDateStr;
                const matchesSearch = item.mentor.toLowerCase().includes(searchTerm) || 
                                      item.mentee.toLowerCase().includes(searchTerm);
                const matchesStatus = selectedStatus === 'All' || item.status === selectedStatus;
                return matchesDate && matchesSearch && matchesStatus;
            });

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No matching sessions found.</td></tr>`;
            } else {
tbody.innerHTML = filtered.map(row => {

return `
<tr class="border-b last:border-0 hover:bg-slate-50 transition">

<td class="py-4 font-bold text-slate-700 truncate">${row.mentee}</td>

<td class="text-slate-600 truncate">${row.subject}</td>

<td class="text-slate-500">
${formatTimeTo12Hour(row.start)} - ${formatTimeTo12Hour(row.end)}
</td>

<td>
<span class="${getStatusColor(row.status)} font-bold text-[10px] px-2 py-1 rounded border">
${row.status}
</span>
</td>

</tr>
`;
}).join('');
            }
            document.getElementById('pageIndicator').innerText = `Showing ${filtered.length} results`;
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
const endHour=18;

const days=["Monday","Tuesday","Wednesday","Thursday","Friday"];

const week=getCurrentWeekRange();

function timeToMinutes(t){
const [h,m]=t.split(":").map(Number);
return h*60+m;
}

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

const sessions = allSessions.filter(s=>{

const date = new Date(s.date + "T00:00:00"); // lock date
const dayName=date.toLocaleDateString('en-US',{weekday:'long'});

const d = date.setHours(0,0,0,0);
const m = week.monday.getTime();
const f = week.friday.getTime();

if(!(d >= m && d <= f)) return false;
if(dayName!==day) return false;

const sessionStart=timeToMinutes(s.start);
const sessionEnd=timeToMinutes(s.end);

return sessionStart < slotEnd && sessionEnd > slotStart;

});

if(sessions.length){

cell.innerHTML = sessions.map(s=>`

<div class="schedule-block">

${s.subject}
<br>
${formatTimeTo12Hour(s.start)} - ${formatTimeTo12Hour(s.end)}

</div>

`).join("");

}

row.appendChild(cell);

});

tbody.appendChild(row);

}

}

}
function getStatusColor(status){

switch(status){
case 'Active':
return 'text-emerald-700 bg-emerald-100 border-emerald-300';

case 'Upcoming':
return 'text-blue-700 bg-blue-100 border-blue-300';

case 'Completed':
return 'text-gray-600 bg-gray-100 border-gray-300';

default:
return 'text-gray-500 bg-gray-100 border-gray-200';
}

}
        // Calendar Logic
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
        if(dateObj.toDateString() === today.toDateString()){
            dayEl.classList.add("cal-today");
        }

        /* Highlight selected day */
        if(dateStr === selectedDateStr){
            dayEl.classList.add("cal-selected");
        }

        /* DAY NUMBER */
        dayEl.innerHTML = `<span>${i}</span>`;

        /* RESTORE CLICK FUNCTIONALITY */
        dayEl.onclick = () => {

        selectedDateStr = dateStr;

        /* Update table */
        refreshSchedules();

        /* Re-render calendar to show selected day */
        renderCalendar();
        updateTableDate();

        };

    grid.appendChild(dayEl);
    }

}

function updateStatus(id, newStatus){

const session = allSessions.find(s => s.id === id);

if(!session) return;

if(session.status === "Completed"){
    alert("Completed sessions cannot be changed.");
    return;
}

session.status = newStatus;

refreshSchedules();
renderQuickActions();

}
function renderPendingRequests(){

    const container = document.getElementById("pendingRequestsList");
    const badge = document.getElementById("pendingBadge");
    const toggleBtn = document.getElementById("toggleRequestsBtn");

    if(pendingRequests.length == 1) {
        badge.innerText = `${pendingRequests.length} Request`;
    } else {
        badge.innerText = `${pendingRequests.length} Requests`;
    }

    if(!pendingRequests.length){
        container.innerHTML = `<p class="text-xs text-gray-400 italic">No pending requests.</p>`;
        toggleBtn.style.display = "none";
        return;
    }

    toggleBtn.style.display = "block";

    const sorted = [...pendingRequests].sort((a,b) => b.id - a.id);

    const visible = showAllRequests ? sorted : sorted.slice(0,3);

    container.innerHTML = visible.map(req => `

    <div class="flex items-center justify-between group">

        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold">
                ${req.mentee.slice(0,2).toUpperCase()}
            </div>
            <div> 
                <p class="text-[11px] font-bold text-slate-700">${req.mentee}</p>
                <p class="text-[9px] text-gray-400 font-medium">
                    ${req.subject} • ${formatTimeTo12Hour(req.start)} - ${formatTimeTo12Hour(req.end)}
                </p>
            </div>
        </div>

        <div class="flex gap-1">

            <button onclick="rejectRequest(${req.id})"
            class="w-6 h-6 rounded-md bg-gray-50 hover:bg-red-50 hover:text-red-600 flex items-center justify-center">
                <i class="fa-solid fa-xmark text-[10px]"></i>
            </button>

            <button onclick="approveRequest(${req.id})"
            class="w-6 h-6 rounded-md bg-gray-50 hover:bg-emerald-50 hover:text-emerald-600 flex items-center justify-center">
                <i class="fa-solid fa-check text-[10px]"></i>
            </button>

        </div>

    </div>

    `).join("");

    toggleBtn.innerText = showAllRequests ? "Show Less" : "View All Requests";
}

function hasConflict(newReq){

function toMin(t){
const [h,m]=t.split(":").map(Number);
return h*60+m;
}

return allSessions.some(s => {

if(s.date !== newReq.date) return false;

const sStart = toMin(s.start);
const sEnd = toMin(s.end);
const rStart = toMin(newReq.start);
const rEnd = toMin(newReq.end);

return rStart < sEnd && rEnd > sStart;

});

}

function approveRequest(id){

const reqIndex = pendingRequests.findIndex(r => r.id === id);
if(reqIndex === -1) return;

const req = pendingRequests[reqIndex];

// ❌ AUTO REJECT IF CONFLICT
if(hasConflict(req)){
    alert("Schedule conflict! Request automatically rejected.");
    pendingRequests.splice(reqIndex,1);
    renderPendingRequests();
    return;
}

// ✅ ADD TO SESSIONS
allSessions.push({
    id: Date.now(),
    date: req.date,
    mentor: req.mentor,
    mentee: req.mentee,
    subject: req.subject,
    start: req.start,
    end: req.end,
    status: "Upcoming"
});

// REMOVE FROM PENDING
pendingRequests.splice(reqIndex,1);

refreshSchedules();
renderPendingRequests();

}

function rejectRequest(id){
    const index = pendingRequests.findIndex(r => r.id === id);
    if(index !== -1){
        pendingRequests.splice(index,1);
        renderPendingRequests();
    }
}

function renderQuickActions(){

const container = document.getElementById("quickActionsList");

const todaySessions = allSessions.filter(s => s.date === selectedDateStr);

if(!todaySessions.length){
    container.innerHTML = `<p class="text-xs text-gray-400 italic">No sessions today.</p>`;
    return;
}

container.innerHTML = todaySessions.map((s, index) => `
    
<div class="flex items-center justify-between border border-gray-100 rounded-lg p-3">

    <div>
        <p class="text-xs font-bold text-slate-700">${s.mentee}</p>
        <p class="text-[10px] text-gray-400">
            ${s.subject} • ${formatTimeTo12Hour(s.start)} - ${formatTimeTo12Hour(s.end)}
        </p>
    </div>

    <div class="flex gap-1">

        <button onclick="updateStatus(${s.id}, 'Active')"
        class="text-[10px] px-2 py-1 rounded bg-emerald-100 text-emerald-700 font-bold">
        Active
        </button>

        <button onclick="updateStatus(${s.id}, 'Completed')"
        class="text-[10px] px-2 py-1 rounded bg-gray-100 text-gray-600 font-bold">
        Done
        </button>

    </div>

</div>

`).join("");

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
    searchInput.addEventListener('input', refreshSchedules);
    statusFilter.addEventListener('change', refreshSchedules);

    </script>
</body>
