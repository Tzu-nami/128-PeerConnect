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
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

        <main class="scroll-container">

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">All Sessions</h2>
                        <p class="text-xs text-gray-400">All student-selected mentor sessions</p>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" id="searchInput"
                        placeholder="Search..."
                        class="px-3 py-2 text-xs border border-gray-200 rounded-lg">

                        <select id="statusFilter" class="table-filter-select">
                            <option value="All">All</option>
                            <option value="Pending">Pending</option>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <!-- TABLE -->
                <table class="w-full text-left text-sm table-fixed">
                    <thead class="text-gray-400 border-b">
                        <tr>
                            <th class="pb-3 text-[10px]">Student</th>
                            <th class="pb-3 text-[10px]">Subject</th>
                            <th class="pb-3 text-[10px]">Topic</th>
                            <th class="pb-3 text-[10px]">Hours</th>
                            <th class="pb-3 text-[10px]">Status</th>
                            <th class="pb-3 text-[10px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sessionsTable"></tbody>
                </table>

            </div>

        </main>
    </div>
</div>

<script>

/* =========================
   🔥 SHARED SESSION DATA
   ========================= */

const allSessions = [

{ id:1, date:'2026-03-16', mentee:"Frian Nabo", subject:"CMSC 143", topic:"LE1 Answer Key", start:"09:00", end:"10:30", status:"Completed"},
{ id:2, date:'2026-03-16', mentee:"Mark Tuan", subject:"Math 59", topic:"LE1 Answer Key", start:"10:45", end:"12:00", status:"Active"},
{ id:3, date:'2026-03-16', mentee:"Uno Santos", subject:"Programming 1", topic:"LE1 Answer Key",start:"13:15", end:"14:00", status:"Upcoming"},
{ id:4, date:'2026-03-17', mentee:"Kevin Hart", subject:"Database Systems", topic:"LE1 Answer Key", start:"09:30", end:"11:00", status:"Upcoming"},
{ id:5, date:'2026-03-18', mentee:"David Kim", subject:"Physics", topic:"LE1 Answer Key", start:"11:00", end:"12:30", status:"Upcoming"},
{ id:6, date:'2026-03-19', mentee:"Chris Evans", subject:"Networking", topic:"LE1 Answer Key", start:"14:00", end:"15:30", status:"Upcoming"},
{ id:7, date:'2026-03-20', mentee:"Liam Cruz", subject:"Web Development", topic:"LE1 Answer Key", start:"10:00", end:"11:30", status:"Upcoming"},
{ id:8, date:'2026-03-21', mentee:"Liam Cruz", subject:"Web Development", topic:"LE1 Answer Key", start:"11:00", end:"12:30", status:"Upcoming"}

];

const pendingRequests = [

{ id:101, date:'2026-03-16', mentee:"Lance Talavera", subject:"CMSC 143", topic:"LE1 Answer Key", start:"15:00", end:"16:00" },
{ id:102, date:'2026-03-16', mentee:"Paolo Lapid", subject:"Math 59", topic:"LE1 Answer Key", start:"10:30", end:"11:30" }, 
{ id:103, date:'2026-03-17', mentee:"Anna Cruz", subject:"Physics", topic:"LE1 Answer Key", start:"13:00", end:"14:30" },
{ id:104, date:'2026-03-17', mentee:"Anna Lyn", subject:"Physics", topic:"LE1 Answer Key", start:"13:00", end:"14:30" }

];

/* =========================
   🔎 FILTER + RENDER
   ========================= */

function renderSessions(){

const sidebar = document.getElementById('sidebar');
const profileTrigger = document.getElementById('profileTrigger');
const profileDropdown = document.getElementById('profileDropdown');
const tbody = document.getElementById("sessionsTable");
const search = document.getElementById("searchInput").value.toLowerCase();
const filter = document.getElementById("statusFilter").value;

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

let combined = [

...allSessions,
...pendingRequests.map(r => ({ ...r, status:"Pending" }))

];

const filtered = combined.filter(s => {

const hours = calculateHours(s.start, s.end);

const values = [
...Object.values(s),
hours + " hrs"
];

const matchSearch = values.some(val =>
String(val).toLowerCase().includes(search)
);

const matchStatus = filter === "All" || s.status === filter;

return matchSearch && matchStatus;

});

if(!filtered.length){
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400">No sessions found</td></tr>`;
    return;
}

tbody.innerHTML = filtered.map(s => `

<tr class="border-b hover:bg-slate-50">

<td class="py-4 font-bold text-slate-700">${s.mentee}</td>
<td>${s.subject}</td>
<td>${s.topic}</td>
<td>${calculateHours(s.start, s.end)} hrs</td>

<td>
<span class="${getStatusColor(s.status)} text-[10px] px-2 py-1 rounded border font-bold">
${s.status}
</span>
</td>

<td class="flex gap-2 py-2">

${renderActions(s)}

</td>

</tr>

`).join("");

}

/* =========================
   🎯 ACTION BUTTONS
   ========================= */

function renderActions(s){

if(s.status === "Pending"){
return `
<button onclick="acceptRequest(${s.id})"
class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded">Accept</button>

<button onclick="rejectRequest(${s.id})"
class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded">Cancel</button>
`;
}

if(s.status === "Upcoming"){
return `
<button onclick="markActive(${s.id})"
class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded">Activate</button>
`;
}

if(s.status === "Active"){
return `
<button onclick="markCompleted(${s.id})"
class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded">Done</button>
`;
}

return `-`;

}

function calculateHours(start, end){

// Convert "HH:MM" → minutes
const [sh, sm] = start.split(":").map(Number);
const [eh, em] = end.split(":").map(Number);

const startMinutes = sh * 60 + sm;
const endMinutes = eh * 60 + em;

const diff = (endMinutes - startMinutes) / 60;

// Format: 1.5 instead of 1.50
return parseFloat(diff.toFixed(2));

}

function markActive(id){

const s = allSessions.find(x => x.id === id);

if(!s) return;

if(s.status !== "Upcoming"){
    alert("Only upcoming sessions can be activated.");
    return;
}

s.status = "Active";

renderSessions();

}

/* =========================
   ⚙️ ACTION LOGIC
   ========================= */

function acceptRequest(id){

const index = pendingRequests.findIndex(r => r.id === id);
if(index === -1) return;

const req = pendingRequests[index];

allSessions.push({
    ...req,
    status:"Upcoming"
});

pendingRequests.splice(index,1);

renderSessions();

}

function rejectRequest(id){

const index = pendingRequests.findIndex(r => r.id === id);
if(index !== -1){
    pendingRequests.splice(index,1);
}

renderSessions();

}

function markCompleted(id){

const s = allSessions.find(x => x.id === id);

if(!s) return;

if(s.status !== "Active"){
    alert("Only active sessions can be marked as done.");
    return;
}

s.status = "Completed";

renderSessions();

}

/* =========================
   🎨 STATUS COLORS (SAME AS DASHBOARD)
   ========================= */

function getStatusColor(status){

switch(status){
case 'Active': return 'text-emerald-700 bg-emerald-100 border-emerald-300';
case 'Upcoming': return 'text-blue-700 bg-blue-100 border-blue-300';
case 'Completed': return 'text-gray-600 bg-gray-100 border-gray-300';
case 'Pending': return 'text-yellow-700 bg-yellow-100 border-yellow-300';
default: return '';
}

}

/* =========================
   🔁 INIT
   ========================= */

document.getElementById("searchInput").addEventListener("input", renderSessions);
document.getElementById("statusFilter").addEventListener("change", renderSessions);

document.addEventListener("DOMContentLoaded", renderSessions);

</script>

</body>
