<?php

use function Livewire\Volt\{layout, state, mount, computed};

layout('layouts.app');

state([
    'showModal' => false,
    'search' => '',
    // Form States
    'surname' => '',
    'first_name' => '',
    'middle_name' => '',
    'up_mail' => '',
    'student_number' => '',
    'subjects' => '',
    // Initial Dummy Data
    'mentors' => [
        ['id' => 1, 'name' => 'Sarmiento, Clara M.', 'student_no' => '2021-04512', 'email' => 'cmsarmiento@up.edu.ph', 'subjects' => 'CMSC 11, MATH 53', 'init' => 'SC'],
        ['id' => 2, 'name' => 'Rizal, Jose P.', 'student_no' => '2020-12345', 'email' => 'jprizal@up.edu.ph', 'subjects' => 'HIST 1, PI 10', 'init' => 'JR'],
        ['id' => 3, 'name' => 'Luna, Manuel G.', 'student_no' => '2022-98765', 'email' => 'mgluna@up.edu.ph', 'subjects' => 'PHYS 21', 'init' => 'ML'],
    ]
]);

// Live Search logic - Optimized with Filtered computed property
$filteredMentors = computed(function () {
    if (empty($this->search)) {
        return $this->mentors;
    }

    $searchLower = strtolower($this->search);
    
    return array_filter($this->mentors, function ($mentor) use ($searchLower) {
        return str_contains(strtolower($mentor['name']), $searchLower) ||
               str_contains(strtolower($mentor['student_no']), $searchLower) ||
               str_contains(strtolower($mentor['subjects']), $searchLower);
    });
});

$saveMentor = function () {
    $this->validate([
        'surname' => 'required',
        'first_name' => 'required',
        'up_mail' => 'required|email|ends_with:up.edu.ph',
        'student_number' => 'required|regex:/^\d{4}-\d{5}$/',
        'subjects' => 'required',
    ]);

    $fullName = $this->surname . ', ' . $this->first_name . ' ' . ($this->middle_name ? $this->middle_name[0] . '.' : '');
    
    // Push new mentor to list
    $this->mentors[] = [
        'id' => count($this->mentors) + 1,
        'name' => $fullName,
        'student_no' => $this->student_number,
        'email' => $this->up_mail,
        'subjects' => $this->subjects,
        'init' => strtoupper(substr($this->first_name, 0, 1) . substr($this->surname, 0, 1))
    ];

    $this->reset(['surname', 'first_name', 'middle_name', 'up_mail', 'student_number', 'subjects', 'showModal']);
};

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

?>

<div class="livewire-root-scope">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root { 
            --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; 
            --header-height: 80px; --sidebar-width: 280px; --sidebar-collapsed-width: 80px;
        }
        
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }
        
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
            background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
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
        .sidebar.collapsed .logo-content, .sidebar.collapsed .nav-item span { display: none; }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; position: relative; }
        
        .profile-dropdown {
            position: absolute; top: 75px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; text-decoration: none; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
        .form-input:focus { border-color: var(--header-maroon); }
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
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>                
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
            <header class="top-header">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group text-slate-800">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500" id="dropdownArrow"></i>
                </button>

                <div id="profileDropdown" class="profile-dropdown">
                    <div class="p-4 border-b border-gray-100 bg-slate-50 text-slate-800">
                        <p class="text-[11px] font-bold text-gray-400 uppercase mb-1">Signed in as</p>
                        <p class="text-sm font-bold truncate">{{ auth()->user()->name }}</p>
                    </div>
                    <a href="#" class="dropdown-item"><i class="fa-solid fa-user-gear"></i> Account Settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="m-0 border-t border-gray-50">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-red-600 font-semibold bg-transparent border-none cursor-pointer">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">
                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h1 class="text-2xl font-black text-slate-800">Mentor Management</h1>
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">LRC Registry of Peer Mentors</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search mentors..." 
                                   class="pl-11 pr-6 py-3 bg-white border border-gray-200 rounded-xl text-xs font-medium w-64 focus:outline-none focus:border-red-800 transition shadow-sm">
                        </div>
                        <button wire:click="$set('showModal', true)" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-black transition shadow-lg">
                            <i class="fa-solid fa-user-plus"></i> Add New Mentor
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-white border-b uppercase text-[10px] font-bold text-gray-400 tracking-widest">
                            <tr>
                                <th class="px-6 py-5">Mentor Name</th>
                                <th class="px-6 py-5">Student Number</th>
                                <th class="px-6 py-5">UP Mail</th>
                                <th class="px-6 py-5">Subject Specializations</th>
                                <th class="px-6 py-5 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($this->filteredMentors as $mentor)
                            <tr class="hover:bg-gray-50 transition" wire:key="{{ $mentor['id'] }}">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-[10px]">{{ $mentor['init'] }}</div>
                                        <span class="font-bold text-slate-700 text-sm">{{ $mentor['name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-slate-600 text-sm">{{ $mentor['student_no'] }}</td>
                                <td class="px-6 py-5 text-slate-500 text-sm">{{ $mentor['email'] }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(explode(',', $mentor['subjects']) as $sub)
                                        <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100">{{ trim($sub) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-800 hover:text-white transition"><i class="fa-solid fa-eye text-[10px]"></i></button>
                                        <button class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition"><i class="fa-solid fa-pen text-[10px]"></i></button>
                                        <button class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition"><i class="fa-solid fa-trash text-[10px]"></i></button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    @if($showModal)
    <div class="modal-overlay">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-8 py-6 bg-gray-50 border-b flex justify-between items-center text-slate-800">
                <h2 class="text-xl font-black">Register Mentor</h2>
                <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-red-600"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form wire:submit="saveMentor" class="p-8 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Surname</label><input type="text" wire:model="surname" class="form-input"></div>
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">First Name</label><input type="text" wire:model="first_name" class="form-input"></div>
                </div>
                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">UP Mail</label><input type="email" wire:model="up_mail" class="form-input" placeholder="name@up.edu.ph"></div>
                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Student No.</label><input type="text" wire:model="student_number" class="form-input" placeholder="20XX-XXXXX"></div>
                <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Subjects (Comma separated)</label><input type="text" wire:model="subjects" class="form-input"></div>
                <div class="flex gap-2 pt-4">
                    <button type="button" wire:click="$set('showModal', false)" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
                    <button type="submit" class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg">Save Profile</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @script
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        const dropdownArrow = document.getElementById('dropdownArrow');

        sidebarToggle.onclick = () => sidebar.classList.toggle('collapsed');

        profileTrigger.onclick = (e) => {
            e.stopPropagation();
            const isShown = profileDropdown.classList.toggle('show');
            dropdownArrow.style.transform = isShown ? 'rotate(180deg)' : 'rotate(0deg)';
        };

        window.onclick = () => {
            profileDropdown.classList.remove('show');
            dropdownArrow.style.transform = 'rotate(0deg)';
        };
    </script>
    @endscript
</div>