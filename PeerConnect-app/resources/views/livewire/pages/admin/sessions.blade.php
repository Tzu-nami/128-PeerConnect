<?php

use function Livewire\Volt\{layout, state, computed, mount};
use Carbon\Carbon;

layout('layouts.app');

state([
    'search' => '',
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
        ->filter(function($s) {
            $matchesStatus = ($this->filterStatus === 'All' || $s['status'] === $this->filterStatus);
            $matchesSearch = empty($this->search) || 
                             str_contains(strtolower($s['mentee']), strtolower($this->search)) ||
                             str_contains(strtolower($s['subject']), strtolower($this->search));
            return $matchesStatus && $matchesSearch;
        })
        ->sortBy($this->sortField, SORT_REGULAR, $this->sortDirection === 'desc');
});

$setFilter = fn($status) => $this->filterStatus = $status;

$sortBy = function ($field) {
    $this->sortDirection = ($this->sortField === $field && $this->sortDirection === 'asc') ? 'desc' : 'asc';
    $this->sortField = $field;
};

mount(function () { 
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access'); 
});
?>

<div class="app-wrapper" x-data="{ sidebarCollapsed: false, profileOpen: false }">
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
        .nav-item.active { border-left: 4px solid white; }

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
                <a href="#" class="nav-item" data-tooltip="Dashboard"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
                <a href="{{ route('admin.mentors') }}" class="nav-item {{ request()->routeIs('admin.mentors') ? 'active' : '' }}" data-tooltip="Mentor Management">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
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
            <div class="max-w-7xl mx-auto space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @php
                        $stats = [
                            ['label' => 'Total Sessions', 'val' => count($this->sessions), 'icon' => 'fa-layer-group', 'color' => 'blue'],
                            ['label' => 'Pending Approval', 'val' => $this->sessions->where('status', 'Pending')->count(), 'icon' => 'fa-clock', 'color' => 'amber'],
                            ['label' => 'Confirmed Today', 'val' => $this->sessions->where('status', 'Confirmed')->count(), 'icon' => 'fa-check-circle', 'color' => 'emerald'],
                            ['label' => 'Completion Rate', 'val' => '85%', 'icon' => 'fa-chart-line', 'color' => 'maroon'],
                        ];
                    @endphp
                    @foreach($stats as $stat)
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-lg text-slate-600">
                                <i class="fa-solid {{ $stat['icon'] }}"></i>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ $stat['label'] }}</p>
                                <p class="text-xl font-black text-slate-800">{{ $stat['val'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="flex bg-slate-100 p-1 rounded-xl">
                            @foreach(['All', 'Pending', 'Confirmed', 'Completed'] as $status)
                                <button wire:click="setFilter('{{ $status }}')" 
                                    class="px-4 py-2 text-xs font-bold rounded-lg transition-all {{ $this->filterStatus === $status ? 'bg-white shadow-sm text-red-900' : 'text-slate-500 hover:text-slate-700' }}">
                                    {{ $status }}
                                </button>
                            @endforeach
                        </div>
                        <div class="relative w-full md:w-80">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search mentee or subject..." 
                                class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-red-900/10">
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-slate-400 text-[11px] font-black uppercase tracking-widest border-b bg-slate-50/30">
                                    <th class="px-6 py-4 cursor-pointer hover:text-red-900" wire:click="sortBy('mentee')">Student <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                                    <th class="px-6 py-4">Course Info</th>
                                    <th class="px-6 py-4">Assigned Mentor</th>
                                    <th class="px-6 py-4 cursor-pointer hover:text-red-900" wire:click="sortBy('schedule')">Schedule <i class="fa-solid fa-sort ml-1 opacity-30"></i></th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($this->sessions as $session)
                                    <tr class="hover:bg-slate-50/80 transition group">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-bold text-slate-800">{{ $session['mentee'] }}</div>
                                            <div class="text-[11px] text-slate-400 font-medium">{{ $session['email'] }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-xs font-bold text-red-900 bg-red-50 px-2 py-1 rounded inline-block">{{ $session['subject'] }}</div>
                                            <div class="text-[11px] text-slate-500 mt-1">{{ $session['course'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded bg-emerald-100 text-emerald-700 text-[10px] flex items-center justify-center font-bold">{{ $session['mentor_init'] }}</span>
                                                {{ $session['mentor'] }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-slate-700">{{ Carbon::parse($session['schedule'])->format('M d, Y') }}</div>
                                            <div class="text-[11px] text-slate-400">{{ Carbon::parse($session['schedule'])->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="status-pill status-{{ strtolower($session['status']) }}">{{ $session['status'] }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button class="p-2 text-slate-400 hover:text-emerald-600 transition" title="Approve"><i class="fa-solid fa-circle-check"></i></button>
                                                <button class="p-2 text-slate-400 hover:text-red-900 transition" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-20 text-center text-slate-400">
                                            <i class="fa-solid fa-inbox text-4xl mb-4 opacity-20"></i>
                                            <p class="font-medium">No sessions match your search criteria.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>