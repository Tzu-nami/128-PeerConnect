<?php

use function Livewire\Volt\{layout, state, computed, mount};

layout('layouts.app');

state([
    'filterStatus' => 'All',
    'sortField' => 'schedule',
    'sortDirection' => 'asc',
]);

$sessions = computed(function () {
    $data = collect([
        ['id' => 1, 'mentee' => 'Nabo, Frian Karl', 'email' => 'fknabo@up.edu.ph', 'subject' => 'CMSC 12', 'course' => 'Computer Science', 'mentor' => 'Reyes, Daniel', 'mentor_init' => 'RD', 'schedule' => '2026-03-18 14:00', 'status' => 'Pending'],
        ['id' => 2, 'mentee' => 'Dela Cruz, Juan', 'email' => 'jdelacruz@up.edu.ph', 'subject' => 'MATH 27', 'course' => 'Calculus I', 'mentor' => 'Solis, Mae', 'mentor_init' => 'MS', 'schedule' => '2026-03-19 10:30', 'status' => 'Confirmed'],
        ['id' => 3, 'mentee' => 'Santos, Maria', 'email' => 'msantos@up.edu.ph', 'subject' => 'PHYS 13', 'course' => 'Physics I', 'mentor' => 'Luna, Antonio', 'mentor_init' => 'AL', 'schedule' => '2026-03-20 09:00', 'status' => 'Completed'],
        ['id' => 10, 'mentee' => 'Del Pilar, Marcelo', 'email' => 'mdelpilar@up.edu.ph', 'subject' => 'CMSC 100', 'course' => 'Web Dev', 'mentor' => 'Solis, Mae', 'mentor_init' => 'MS', 'schedule' => '2026-03-26 10:00', 'status' => 'Pending'],
    ]);

    return $data
        ->filter(fn($s) => $this->filterStatus === 'All' || $s['status'] === $this->filterStatus)
        ->sortBy($this->sortField, SORT_REGULAR, $this->sortDirection === 'desc');
});

$setFilter = function ($status) { $this->filterStatus = $status; };
$sortBy = function ($field) {
    if ($this->sortField === $field) { $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc'; }
    else { $this->sortField = $field; $this->sortDirection = 'asc'; }
};

mount(function () { abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access'); });
?>

<div x-data="{ search: '' }">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root { 
            --sidebar-green: #1a3c2f; 
            --header-maroon: #7b1d1d; 
            --bg-light: #f4f7f6; 
            --header-height: 80px; 
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }

        /* GLOBAL FONT CORRECTION */
        body { 
            margin: 0; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: var(--bg-light); 
            overflow: hidden; 
            -webkit-font-smoothing: antialiased; /* Makes the font crisp like your screenshot */
            -moz-osx-font-smoothing: grayscale;
        }

        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* SIDEBAR - MATCHING SCREENSHOT */
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
            display: flex; align-items: center; padding: 0 24px; gap: 15px; flex-shrink: 0; overflow: hidden;
        }
        #sidebarToggle {
            background: transparent; border: none; color: white; font-size: 1.4rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .logo-text { font-size: 1.15rem; font-weight: 700; letter-spacing: -0.025em; }

        /* NAVIGATION ITEMS */
        .nav-item { 
            display: flex; align-items: center; gap: 15px; padding: 15px 25px; 
            color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.2s; 
            white-space: nowrap; cursor: pointer; font-weight: 500; font-size: 0.95rem;
            letter-spacing: -0.01em; border: none; background: transparent; width: 100%;
        }
        .nav-item i { width: 28px; text-align: center; font-size: 1.2rem; flex-shrink: 0; }
        
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { border-left: 4px solid white; font-weight: 600; }

        /* COLLAPSED STATE & TOOLTIPS */
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
        .sidebar.collapsed .nav-item.active { border-left: none; }

        /* MAIN UI */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        /* TABLE UI */
        .session-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .tab-btn { padding: 15px 25px; font-size: 13px; font-weight: 700; color: #64748b; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid transparent; font-family: 'Inter', sans-serif; }
        .tab-btn.active { color: var(--header-maroon); border-color: var(--header-maroon); }
        .status-pill { padding: 4px 12px; border-radius: 9999px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: #fff7ed; color: #9a3412; }
    </style>

    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
                <button id="sidebarToggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="logo-content">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span class="logo-text">LRC PeerConnect</span>
                </div>
            </div>
            
            <nav class="flex-grow">
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('admin.mentors') }}" class="nav-item {{ request()->routeIs('admin.mentors') ? 'active' : '' }}" data-tooltip="Mentor Management">
                    <i class="fa-solid fa-chalkboard-user"></i><span>Mentor Management</span>
                </a>                
                <a href="{{ route('admin.sessions') }}" class="nav-item active" data-tooltip="Session Management">
                    <i class="fa-solid fa-calendar-days"></i><span>Session Management</span>
                </a>                
                <a href="{{ route('admin.feedbacks') }}" class="nav-item {{ request()->routeIs('admin.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedback">
                    <i class="fa-solid fa-comments"></i><span>Student Feedback</span>
                </a>            
            </nav>

            <div class="mt-auto p-4 border-t border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item w-full" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
            </header>

            <main class="scroll-container">
                <div class="flex flex-col gap-6">
                    <div class="flex justify-between items-center">
                        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Tutoring Sessions</h2>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input x-model="search" type="text" placeholder="Search mentee..." 
                                class="pl-11 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm w-72 focus:outline-none focus:ring-2 focus:ring-red-900/10">
                        </div>
                    </div>

                    <div class="session-card">
                        <div class="flex bg-slate-50/50 border-b">
                            @foreach(['All', 'Pending', 'Confirmed', 'Completed'] as $status)
                                <button wire:click="setFilter('{{ $status }}')" class="tab-btn {{ $this->filterStatus === $status ? 'active' : '' }}">
                                    {{ $status }}
                                </button>
                            @endforeach
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-slate-400 text-[11px] font-black uppercase tracking-widest border-b">
                                        <th class="px-6 py-4">Student</th>
                                        <th class="px-6 py-4">Subject</th>
                                        <th class="px-6 py-4">Mentor</th>
                                        <th class="px-6 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->sessions as $session)
                                        <tr x-show="search === '' || '{{ strtolower($session['mentee']) }}'.includes(search.toLowerCase())" class="hover:bg-slate-50 transition">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-bold text-slate-800">{{ $session['mentee'] }}</div>
                                                <div class="text-xs text-slate-400">{{ $session['email'] }}</div>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-bold text-slate-600">{{ $session['subject'] }}</td>
                                            <td class="px-6 py-4 text-sm text-slate-600">{{ $session['mentor'] }}</td>
                                            <td class="px-6 py-4">
                                                <span class="status-pill {{ 'status-'.strtolower($session['status']) }}">{{ $session['status'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
    </script>
</div>