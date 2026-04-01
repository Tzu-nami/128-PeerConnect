<?php

use function Livewire\Volt\{layout, mount, computed, uses};
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Services\Avatar;

layout('layouts.app');

// Filter and search mentors
$allMentors = computed(function () {
    $query = MentorProfiles::with([
        'user.studentProfile.college',
        'user.studentProfile.degreeProgram',
        'user.studentProfile.yearLevel',
        'subjects',
        'availabilities',
    ]);

    return $query->get()->map(function ($mp) {
        // Check days avaialble
        $dayOrder = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $activeDays = $mp->availabilities->pluck('day_of_week')->unique()->sortBy(fn($day) => $dayOrder[strtolower($day)] ?? 99)->map(fn($day) => ucfirst(substr($day, 0, 3)))->values()->toArray();
        // Group schedules by day
        $schedule = $mp->availabilities
            ->groupBy(fn($item) => strtolower($item->day_of_week))
            ->map(fn($slots, $day) => [
                'slots' => $slots->sortBy(fn($time)=> \Carbon\Carbon::parse($time->start_time)->timestamp)->map(fn($time) => [
                    'start' => \Carbon\Carbon::parse($time->start_time)->format('g:i A'),
                    'end'   => \Carbon\Carbon::parse($time->end_time)->format('g:i A'),
                ])->values()->toArray(),
            ])->toArray();

        if (empty($schedule)) {
            $schedule = new \stdClass();
        }

        return [
            'id' => $mp->id,
            'user_id' => $mp->user_id,
            'lastName' => strtoupper($mp->user->lastName),
            'firstName' => $mp->user->firstName,
            'middleInitial' => $mp->user->middleInitial ? $mp->user->middleInitial . '.' : '',
            'email' => $mp->user->email,
            'avatar' => $mp->user->avatar ?? app(Avatar::class)->placeholder($mp->user->firstName . ' ' . $mp->user->lastName),
            'subjects' => $mp->subjects->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->toArray(),
            'days' => $activeDays,
            'schedule' => $schedule,
            'yearLevel' => $mp->user->studentProfile->yearLevel->name,
            'degreeProgram' => $mp->user->studentProfile->degreeProgram->name,
            'college' => $mp->user->studentProfile->college->name,
            'bookingUrl' => route('student.bookings', ['mentor' => $mp->user_id]),
        ];
    })->sortBy('lastName')->values();
});

// Get all subjects for filter
$subjects = computed(function () {
    return Subjects::orderBy('code')->get();
});

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --sidebar-green: #1a3c2f; 
            --header-maroon: #7b1d1d; 
            --bg-light: #f4f7f6; 
            --header-height: 80px; 
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }

        [x-cloak] {
            display: none !important;
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
        .scroll-container { flex-grow: 1; overflow-y: scroll; padding: 32px; width: 100%; }

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

.mentor-card {
    background: #fffffa;
    border: 1.5px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

.mentor-card:hover {
    border-color: #1a3c2f;
    box-shadow: 0 8px 24px #1e313d;
    transform: translateY(-3px);
}

.day-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    background: #f1f5f9;
    color: #475569;
}

.modal-overlay {
    position: fixed; 
    inset: 0; 
    background: #00000080; 
    backdrop-filter: blur(4px); 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    z-index: 1000; 
    padding: 24px;
    backdrop-filter: blur(4px);
}

.avail-grid { 
    display: grid; 
    grid-template-columns: repeat(6, 1fr); 
    gap: 4px; 
}
.avail-day-header { 
    font-size: 9px; 
    font-weight: 800; 
    text-align: center; 
    color: #94a3b8; 
    text-transform: uppercase; 
    padding-bottom: 4px; 
}
.avail-day-col { 
    display: flex; 
    flex-direction: column; 
    gap: 3px; 
}
.avail-slot { 
    background: #d1fae5; 
    color: #065f46; 
    font-size: 9px; 
    font-weight: 700; 
    padding: 3px 4px; 
    border-radius: 4px; 
    text-align: center; 
    line-height: 1.3; 
}
.avail-empty { 
    background: #f8fafc; 
    border: 1px dashed #e2e8f0; 
    border-radius: 4px; 
    height: 28px; 
}
    </style>
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

            <main class="scroll-container" x-data="mentorDirectory(@js($this->allMentors))">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-black text-slate-800">Our Peer Mentors</h1>
                        <p class="text-sm text-gray-400 mt-1">Browse available mentors and their expertise.</p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <div class="relative">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" x-model="searchQuery" @input="currentPage = 1"  placeholder="Search mentors..."
                                   class="pl-8 pr-3 py-2 text-xs border border-gray-200 rounded-lg bg-[#fffffa] outline-none focus:ring-1 focus:ring-red-800 w-64">
                        </div>
                        <select x-model="selectedDay" @change="currentPage = 1" class="border border-gray-200 rounded-lg px-8 py-2 text-xs text-slate-600 outline-none cursor-pointer focus:ring-1 focus:ring-red-800 bg-[#fffffa]">
                            <option value="">Any Day</option>
                            <option value="Mon">Monday</option>
                            <option value="Tue">Tuesday</option>
                            <option value="Wed">Wednesday</option>
                            <option value="Thu">Thursday</option>
                            <option value="Fri">Friday</option>
                            <option value="Sat">Saturday</option>
                        </select>
                        <select x-model="selectedSubject" @change="currentPage = 1" class="border border-gray-200 rounded-lg px-8 py-2 text-xs text-slate-600 outline-none cursor-pointer focus:ring-1 focus:ring-red-800 bg-[#fffffa]">
                            <option value="">All Subjects</option>
                            @foreach($this->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mb-4 font-medium" x-text="filteredMentors.length + ' mentor' + (filteredMentors.length !==1 ? 's' : '') + ' found'"></p>

                <div x-show="filteredMentors.length === 0" x-cloak class="bg-[#fffffa] rounded-xl border border-gray-100 py-20 text-center shadow-sm">
                    <i class="fa-solid fa-chalkboard-user text-4xl mb-4 block"></i>
                    <p class="font-medium">No mentors found.</p>
                    <p class="text-xs mt-1">Try adjusting your search or filter.</p>
                </div>

                {{-- Display the mentors --}}
                <div x-show="filteredMentors.length > 0" x-cloak class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 max-w-7xl mx-auto">
                    <template x-for="mentor in paginatedMentors" :key="mentor.id">

                        <div class="mentor-card group flex flex-col h-full" @click="openModal(mentor)">

                            <div class="p-5 flex gap-5 border-b border-gray-100 bg-[#fffffa]">
                                <div class="w-28 h-28 aspect-square rounded-2xl overflow-hidden flex-shrink-0 bg-gray-100 border border-gray-200 shadow-inner">
                                    <img :src="mentor.avatar" :alt="mentor.lastName" class="w-full h-full object-cover bg-white" />
                                </div>
                                <div class="flex-1 flex flex-col min-w-0 pr-1">
                                    <p class="font-black text-slate-800 text-2xl sm:text-3xl leading-none uppercase tracking-tighter truncate" x-text="mentor.lastName"></p>
                                    <p class="font-bold text-slate-600 text-l leading-tight mt-0.5 truncate" x-text="mentor.firstName + ' ' + mentor.middleInitial"></p>
                                    <p class="font-bold text-slate-400 text-xs leading-tight mt-1 truncate" x-text="mentor.email"></p>
                                    <template x-if="mentor.yearLevel && mentor.degreeProgram">
                                        <p class="text-gray-400 text-[10px] mt-2 leading-tight line-clamp-3" x-html="mentor.yearLevel + '<br>' + mentor.degreeProgram"></p>
                                    </template>
                                </div>
                            </div>

                            {{-- Subjects taught --}}
                            <div class="px-5 pb-2 pt-2 flex-1">
                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Subjects</p>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="subject in mentor.subjects.slice(0, 6)" :key="subject.id">
                                        <span class="bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 rounded text-[10px] font-bold" x-text="subject.code"></span>
                                    </template>
                                    <template x-if="mentor.subjects.length > 6">
                                        <span class="bg-red-50 text-gray-500 border border-red-100 px-2 py-0.5 rounded text-[10px] font-bold" x-text="'+' + (mentor.subjects.length - 6)"></span>
                                    </template>
                                </div>
                            </div>

                            {{-- Days and view more --}}
                            <div class="px-5 pb-2 pt-2 pb-6 mt-auto flex justify-between items-end border-t border-gray-50 bg-[#fffffa] group-hover:bg-gray-50/50 transition-colors duration-300">
                                <div class="flex-1 pr-3">
                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Available Days</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="day in mentor.days">
                                            <span class="day-pill" x-text="day"></span>
                                        </template>
                                        <template x-if="mentor.days.length === 0">
                                            <span class="text-[10px] text-gray-400 italic">None</span>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex-shrink-0 mb-0.5">
                                    <span class="text-[12px] font-bold text-slate-400 group-hover:text-[#1a3c2f] transition-colors duration-300 flex items-center gap-1 tracking-widest whitespace-nowrap">View Profile <i class="fa-solid fa-chevron-right text-[10px] transition-transform duration-300 group-hover:translate-x-1"></i></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Pagination --}}
                <div class="mt-8 flex justify-center items-center gap-2" x-show="totalPages > 1" x-cloak>
                    <button @click="currentPage--" :disabled="currentPage === 1" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <template x-for="page in pages" :key="page">
                        <button @click="currentPage = page" :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'" class="w-8 h-8 text-xs font-bold rounded-lg transition" x-text="page"></button>
                    </template>
                    <button @click="currentPage++" :disabled="currentPage === totalPages" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>

                <template x-teleport="body">
                    <div class="modal-overlay" x-show="showModal" @click.self="showModal = false" x-cloak>
                        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">

                            <template x-if="selectedMentor">
                                <div class="contents">
                                    {{-- Modal Header --}}
                                    <div class="flex-shrink-0 flex items-start gap-5 p-6 bg-[#1a3c2f]">
                                        <div class="w-36 h-36 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-white/20 shadow-lg bg-gray-200">
                                            <img :src="selectedMentor.avatar" alt="selectedMentor.lastName" class="w-full h-full object-cover bg-white" />
                                        </div>

                                    <div class="flex-1 min-w-0 pt-1">
                                    <p class="text-white font-black text-2xl leading-tight tracking-tight" x-text="selectedMentor.lastName + ', ' + selectedMentor.firstName + ' ' + selectedMentor.middleInitial">
                                    </p>

                                    <template x-if="selectedMentor.yearLevel && selectedMentor.degreeProgram">
                                        <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.yearLevel + ' &mdash; ' + selectedMentor.degreeProgram"></p>
                                    </template>
                                    <template x-if="selectedMentor.college">
                                        <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.college"></p>
                                    </template>
                                        <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.email"></p>
                                </div>

                                <button @click="showModal = false" class="text-white/50 hover:text-white transition flex-shrink-0 mt-1">
                                    <i class="fa-solid fa-xmark text-xl"></i>
                                </button>
                            </div>

                            {{-- Modal Body --}}
                            <div class="overflow-y-auto flex-1 p-6 space-y-6 bg-[#fffffa]">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Teachable Subjects</p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="subject in selectedMentor.subjects" :key="subject.id">
                                            <div class="flex flex-col items-start">
                                                <span class="bg-red-50 text-red-700 border border-red-100 text-xs px-3 py-1 rounded font-bold" x-text="subject.code"></span>
                                            </div>
                                        </template>
                                        <template x-if="selectedMentor.subjects.length === 0">
                                            <p class="text-xs text-gray-400">No subjects listed.</p>
                                        </template>
                                    </div>
                                </div>

                                {{-- Availability Calendar --}}
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Weekly Availability</p>

                                    <div class="avail-grid">
                                        <template x-for="day in weekDays" :key="day">
                                            <div>
                                                <div class="avail-day-header" x-text="day.charAt(0).toUpperCase() + day.slice(1,3)"></div>
                                                <div class="avail-day-col">
                                                    <template x-if="selectedMentor.schedule[day]">
                                                        <template x-for="(slot, index) in selectedMentor.schedule[day].slots" :key="index">                                                            <div class="avail-slot" x-html="slot.start + '<br>' + slot.end"></div>
                                                        </template>
                                                    </template>
                                                    <template x-if="!selectedMentor.schedule[day]">
                                                        <div class="avail-empty"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <p class="text-[12px] mt-3 flex items-center justify-center gap-4">
                                        <span><span class="inline-block w-3 h-3 rounded bg-[#d1fae5] mr-1 align-middle"></span> Available</span>
                                        <span><span class="inline-block w-3 h-3 rounded border border-dashed border-gray-200 bg-[#f8fafc] mr-1 align-middle"></span> Unavailable</span>
                                    </p>
                                </div>

                            </div>

                            <div class="flex-shrink-0 px-6 py-4 bg-gray-50 border-t border-gray-100">
                                <a :href="selectedMentor.bookingUrl" class="block w-full text-center bg-[#1a3c2f] hover:bg-[#2d5c47] text-white text-sm font-bold py-3 rounded-xl transition shadow-sm">
                                    <i class="fa-solid fa-calendar-check mr-2"></i> Book a Session
                                </a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
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

        // Data handling script
        document.addEventListener('alpine:init', () => {
            Alpine.data('mentorDirectory', (initialMentors) => ({
                mentors: initialMentors,
                searchQuery: '',
                selectedSubject: '',
                selectedDay: '',
                currentPage: 1,
                perPage: 6,
                showModal: false,
                selectedMentor: null,
                weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

                get filteredMentors() {
                    return this.mentors.filter(mentor => {
                        const searchString = this.searchQuery.toLowerCase();
                        const fullName = (mentor.firstName + ' ' + mentor.lastName).toLowerCase();
                        const matchesSearch = searchString === '' || fullName.includes(searchString);
                        const matchesSub = this.selectedSubject === '' || mentor.subjects.some(sub=> sub.id == this.selectedSubject);
                        const matchesDay = this.selectedDay === '' || mentor.days.includes(this.selectedDay);

                        return matchesSearch && matchesSub && matchesDay;
                    });
                },

                get paginatedMentors() {
                    const start = (this.currentPage - 1) * this.perPage;
                    const end = start + this.perPage;
                    return this.filteredMentors.slice(start, end);
                },

                get totalPages() {
                    return Math.ceil(this.filteredMentors.length / this.perPage);
                },

                get pages() {
                    return Array.from({
                        length: this.totalPages
                    }, (_, i) => i + 1);
                },

                openModal(mentor) {
                    this.selectedMentor = mentor;
                    this.showModal = true;
                }
            }));
        });
    </script>
</div>
