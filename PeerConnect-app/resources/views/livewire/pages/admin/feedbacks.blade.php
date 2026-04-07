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

/* THE BLEND: Rectangular connection to the main page */
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
    <div class="space-y-6">
    <div class="grid grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600">
            <p class="text-xs font-bold text-gray-400 uppercase">Average Rating</p>
            <p class="text-2xl font-black text-slate-800">4.8 / 5.0</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600">
            <p class="text-xs font-bold text-gray-400 uppercase">Total Responses</p>
            <p class="text-2xl font-black text-slate-800">1,240</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500">
            <p class="text-xs font-bold text-gray-400 uppercase">Positive Sentiment</p>
            <p class="text-2xl font-black text-slate-800">94%</p>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-red-600">
            <p class="text-xs font-bold text-gray-400 uppercase">Flagged Comments</p>
            <p class="text-2xl font-black text-slate-800">3</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-user-secret text-gray-400"></i> 
                    Anonymous Student Feedbacks
                </h2>
                <p class="text-xs text-gray-500">Student identities are hidden to encourage honest reporting.</p>
            </div>
            <div class="flex gap-3">
                <select class="table-filter-select">
                    <option>All Ratings</option>
                    <option>5 Stars</option>
                    <option>4 Stars</option>
                    <option>Below 3</option>
                </select>
                <button class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-200">
                    <i class="fa-solid fa-download mr-2"></i>Export
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="px-6 py-4">Participant Ref</th>
                        <th class="px-6 py-4">Course/Year</th>
                        <th class="px-6 py-4">Mentor Assigned</th>
                        <th class="px-6 py-4">Rating</th>
                        <th class="px-6 py-4">Comment</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-8821</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Computer Science - 2nd Year</td>
                        <td class="px-6 py-4 text-slate-600 font-medium">Dyoco, Daniel Joco</td>
                        <td class="px-6 py-4">
                            <div class="flex text-yellow-400 text-xs">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="feedback-text text-sm text-slate-600 italic">"The peer mentor was very patient with the coding exercises."</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button class="text-slate-400 hover:text-red-800" title="View Details"><i class="fa-solid fa-circle-info"></i></button>
                        </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-4509</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Information Tech - 1st Year</td>
                        <td class="px-6 py-4 text-slate-600 font-medium">Santos, Ricardo</td>
                        <td class="px-6 py-4">
                            <div class="flex text-yellow-400 text-xs">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star text-gray-300"></i><i class="fa-regular fa-star text-gray-300"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="feedback-text text-sm text-slate-600 italic">"Hard to hear the audio during the session."</p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-[10px] font-bold uppercase mr-2">Flagged</span>
                            <button class="text-slate-400 hover:text-red-800"><i class="fa-solid fa-circle-info"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="p-4 border-t border-gray-100 flex justify-between items-center bg-slate-50 text-xs text-gray-500">
            <span>Identity Protection: Enabled</span>
            <div class="flex gap-2">
                <button class="pagination-btn">Previous</button>
                <button class="pagination-btn bg-red-800 text-white border-red-800">1</button>
                <button class="pagination-btn">Next</button>
            </div>
        </div>
        
    </div>
    </div>
</main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        window.addEventListener('click', () => {
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        });

        document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('toggleIcon');
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
    setTimeout(() => { charts.forEach(c => c.resize()); }, 310);
});
    </script>
</body>
