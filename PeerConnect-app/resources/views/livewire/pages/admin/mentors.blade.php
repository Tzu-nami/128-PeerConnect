<?php

use function Livewire\Volt\{layout, state, mount, computed, action};
use App\Models\MentorProfiles;
use App\Models\MentorAvailabilities;
use App\Models\MentorSubjects;
use App\Models\Subjects;
use App\Models\User;

layout('layouts.app');

state([
    'search' => '',
    // Form states
    'showModal' => false,
    'showSubjectModal' => false,

    'showConfirm' => false,
    'showSubjectConfirm' => false,

    'up_mail' => '',
    'newMentor' => null,
    'emailError' => '',
    'selectedSubjects' => [],

    'availabilities' => [
        ['day_of_week' => '', 'start_time' => '', 'end_time' => '']
    ],

    'newSubjectCode' => '',
    'newSubjectName' => '',
]);

// Live Search logic - Optimized with Filtered computed property
$filteredMentors = computed(function () {
    $query = MentorProfiles::with(['user.studentProfile', 'subjects']);

    if (!empty($this->search)) {
        $searchWord = '%' . $this->search . '%';
        $query->whereHas('user', function ($word) use ($searchWord) {
            //ilike for case insensitive searches
            $word->where('lastName', 'ilike', $searchWord)
            ->orWhere('firstName', 'ilike', $searchWord)
            ->orWhere('email', 'ilike', $searchWord)
            ->orWhereHas('studentProfile', function ($sWord) use ($searchWord) {
                $sWord->where('student_num', 'ilike', $searchWord);
            });
        })->orWhereHas('subjects', function ($word) use ($searchWord) {
            $word->where('code', 'ilike', $searchWord);
        });
    }
    return $query->get()->map(function ($mentor) {
        return [
            'id' => $mentor->id,
            'name' => $mentor->user->lastName . ', ' . $mentor->user->firstName,
            'student_num' => $mentor->user->studentProfile->student_num,
            'email' => $mentor->user->email,
            'subjects' => $mentor->subjects->pluck('code')->join(', '),
        ];
    })->toArray();
});

// Display all subjects available
$allSubjects = computed(function () {
    return Subjects::orderBy('code')->get()
    ->map(fn($subs) => ['id' => $subs->id, 'code' => $subs->code, 'name' => $subs->name])
    ->toArray();
});

// For add new mentors
$openModal = action(function () {
    $this->reset([
        'up_mail',
        'newMentor',
        'emailError',
        'selectedSubjects',
        'availabilities',
        'showConfirm'
    ]);
    $this->availabilities = [['day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    $this->showModal = true;
});

$closeModal = action(function () {
    $this->showModal = false;
    $this->showConfirm = false;
});

// For add new subjects
$openSubjectModal = action(function () {
    $this->reset([
        'newSubjectCode',
        'newSubjectName',
        'showSubjectConfirm'
    ]);
    $this->showSubjectModal = true;
});

$closeSubjectModal = action(function () {
    $this->showSubjectModal = false;
    $this->showSubjectConfirm = false;
});

// Find student email to give mentor access
$checkEmail = action(function () {
    $this->emailError = '';
    $this->newMentor = null;

    $this->validate(['up_mail' => ['required', 'email']]);
    $userEmail = User::where('email', $this->up_mail) -> first();

    if (!$userEmail) {
        $this->emailError = 'The student with this email does not exist.';
        return;
    }

    if ($userEmail->isMentor()) {
        $this->emailError = 'This student is already a peer mentor';
        return;
    }

    $this->newMentor = [
        'id' => $userEmail->id,
        'name' => $userEmail->name,
        'email' => $userEmail->email,
    ];
});

// Expand new mentor form for multiple availabilities
$toggleAvailabilityOn = action(function () {
    $this->availabilities[] = ['day_of_week' => '', 'start_time' => '', 'end_time' => ''];
});

$toggleAvailabilityOff = action(function (int $index) {
    array_splice($this->availabilities, $index, 1);
    $this->availabilities = array_values($this->availabilities);
});

// Confirmation dialogs
$confirmMentor = action(function () {
    if (!$this->newMentor) {
        $this->emailError = 'Please input a valid student email.';
        return;
    }

    // Check if inputs are valid
    $this->validate([
        'selectedSubjects' => ['required', 'array', 'min:1'],
        'selectedSubjects.*' => ['exists:subjects,id'],
        'availabilities' => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time' => ['required', 'date_format:H:i'],
        'availabilities.*.end_time' => ['required', 'date_format:H:i'],
    ], [], [
        'selectedSubjects' => 'subjects',
        'availabilities' => 'availabilities',
    ]);

    foreach ($this->availabilities as $i => $row) {
        // Start should be earlier than end
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }
    }
    $this->showConfirm = true;
});

// Save new mentor information
$saveMentor = action(function () {
    if (!$this->newMentor) return;

    // Update student role to mentor role
    $userMentor = User::findOrFail($this->newMentor['id']);
    $userMentor->update(['user_roles' => 'mentor']);

    $mentorProf = MentorProfiles::create(['user_id' => $userMentor->id]);

    foreach ($this->selectedSubjects as $subjectId) {
        MentorSubjects::create([
            'mentor_id'  => $mentorProf->id,
            'subject_id' => $subjectId,
        ]);
    }

    foreach ($this->availabilities as $sched) {
        MentorAvailabilities::create([
            'mentor_id' => $mentorProf->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time' => $sched['start_time'],
            'end_time' => $sched['end_time'],
        ]);
    }

    $this->showModal = false;
    $this->showConfirm = false;
    session()->flash('successMessage', "{$userMentor->name} has been registered as a mentor.");
});

$confirmSubject = action(function () {
    $this->validate([
        'newSubjectCode' => ['required', 'string', 'max:20', 'unique:subjects,code'],
        'newSubjectName' => ['required', 'string', 'max:255'],
    ], [], [
        'newSubjectCode' => 'subject_code',
        'newSubjectName' => 'subject_name',
    ]);

    $this->showSubjectConfirm = true;
});

$saveSubject = action(function () {
    Subjects::create([
        'code' => trim($this->newSubjectCode),
        'name' => trim($this->newSubjectName),
    ]);

    $this->showSubjectModal = false;
    $this->showSubjectConfirm = false;
    session()->flash('successMessage', "{$this->newSubjectCode} has been added.");
});

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
                    <form method="POST" action="{{ route('logout') }}" class="m-0 border-t border-gray-50">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-red-600 font-semibold bg-transparent border-none cursor-pointer">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">
                {{-- Success Message --}}
                @if(session('successMessage'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl">
                        {{ session('successMessage') }}
                    </div>
                @endif

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
                        <button wire:click="openSubjectModal" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-black transition shadow-lg">
                            <i class="fa-solid fa-book"></i> Add New Subject
                        </button>
                        <button wire:click="openModal" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-black transition shadow-lg">
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
    <div class="flex items-center">
        <span class="font-bold text-slate-700 text-sm">{{ $mentor['name'] }}</span>
    </div>
</td>
                                <td class="px-6 py-5 text-slate-600 text-sm">{{ $mentor['student_num'] }}</td>
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

    {{-- Add new mentor modal form --}}
    @if($showModal)
    <div class="modal-overlay" wire:click.self="closeModal">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">

            {{-- Header --}}
            <div class="px-8 py-6 bg-gray-50 border-b flex justify-between items-center flex-shrink-0">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Register Mentor</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Add their email, assign their subjects, then set their availabilities.</p>
                </div>
                <button wire:click="closeModal" class="text-gray-400 hover:text-red-600 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-5 overflow-y-auto">
                
                {{-- Student Email --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">1</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Student Email</h3>
                    </div>
                    
                    <div>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <input type="email" wire:model="up_mail" placeholder="student@up.edu.ph" class="form-input" wire:keydown.enter.prevent="checkEmail" />
                                @error('up_mail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @if($emailError) <p class="mt-1 text-xs text-red-600">{{ $emailError }}</p> @endif
                            </div>
                            <button wire:click="checkEmail" type="button" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-black transition flex-shrink-0" wire:loading.attr="disabled" wire:target="checkEmail">
                                <span wire:loading.remove wire:target="checkEmail">Find Email</span>
                                <span wire:loading wire:target="checkEmail">...</span>
                            </button>
                        </div>

                        @if($newMentor)
                            <div class="mt-3 flex items-center gap-3 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">{{ substr($newMentor['name'], 0, 2) }}</div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ $newMentor['name'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $newMentor['email'] }}</p>
                                </div>
                                <i class="fa-solid fa-circle-check text-green-500 ml-auto text-lg"></i>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Subjects --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">2</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Teachable Subjects</h3>
                    </div>

                    <div>
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="max-h-44 overflow-y-auto divide-y divide-gray-50 bg-white">
                                @forelse($this->allSubjects as $subject)
                                    <div class="flex items-center px-4 hover:bg-gray-50" wire:key="sub-{{ $subject['id'] }}">
                                        
                                        <input 
                                            type="checkbox" 
                                            id="subject-{{ $subject['id'] }}"
                                            wire:model="selectedSubjects" 
                                            value="{{ $subject['id'] }}" 
                                            class="rounded border-gray-300 text-slate-800 focus:ring-slate-800 w-4 h-4 cursor-pointer flex-shrink-0" 
                                        />
                                        
                                        <label for="subject-{{ $subject['id'] }}" class="flex items-center gap-3 ml-3 py-2.5 cursor-pointer flex-1">
                                            <span class="text-xs font-bold text-slate-700 w-16">{{ $subject['code'] }}</span>
                                            <span class="text-xs text-gray-400">{{ $subject['name'] }}</span>
                                        </label>
                                        
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 text-center py-4">No subjects yet. Please add the subject first.</p>
                                @endforelse
                            </div>
                        </div>
                        @error('selectedSubjects') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Availabilities --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">3</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Availability Schedule</h3>
                    </div>
                    
                    <div>
                        <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 px-1 mb-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Day</label>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Start Time</label>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">End Time</label>
                            <div class="w-8"></div> </div>

                        <div class="space-y-2">
                            @foreach($availabilities as $i => $row)
                                <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center" wire:key="avail-{{ $i }}">
                                    
                                    <div>
                                        <select wire:model="availabilities.{{ $i }}.day_of_week" class="form-input text-xs h-10 w-full">
                                            <option value="">- Day -</option>
                                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                                                <option value="{{ $day }}">{{ ucfirst($day) }}</option>
                                            @endforeach
                                        </select>
                                        @error("availabilities.{$i}.day_of_week") <p class="mt-0.5 text-[10px] text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <input type="time" wire:model="availabilities.{{ $i }}.start_time" class="form-input text-xs h-10 w-full" />
                                        @error("availabilities.{$i}.start_time") <p class="mt-0.5 text-[10px] text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <input type="time" wire:model="availabilities.{{ $i }}.end_time" class="form-input text-xs h-10 w-full" />
                                        @error("availabilities.{$i}.end_time") <p class="mt-0.5 text-[10px] text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="flex items-center justify-center">
                                        @if(count($availabilities) > 1)
                                            <button type="button" wire:click="toggleAvailabilityOff({{ $i }})" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        @else
                                            <div class="w-8"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button wire:click="toggleAvailabilityOn" type="button" class="mt-3 flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                            <i class="fa-solid fa-plus text-[10px]"></i> Add more days or time slots
                        </button>
                        @error('availabilities') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

            </div> {{-- Save Button --}}
            <div class="px-8 py-5 bg-gray-50 border-t flex-shrink-0">
                @if(!$showConfirm)
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeModal" class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition">
                            Cancel
                        </button>
                        <button type="button" wire:click="confirmMentor" class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition">
                            Register Mentor
                        </button>
                    </div>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start gap-3 mb-4">
                            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg flex-shrink-0 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Confirm Mentor Registration</p>
                                <p class="text-xs text-gray-500 mt-1">This will change their account role.</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showConfirm', false)" class="flex-1 py-2.5 text-xs font-bold text-gray-500 hover:bg-amber-100 rounded-lg transition">
                                Cancel
                            </button>
                            <button type="button" wire:click="saveMentor" class="flex-1 bg-red-900 text-white py-2.5 rounded-lg text-xs font-bold hover:bg-red-800 transition" wire:loading.attr="disabled" wire:loading.class="opacity-60" wire:target="saveMentor">
                                <span wire:loading.remove wire:target="saveMentor">Save</span>
                                <span wire:loading wire:target="saveMentor">Saving...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>
    @endif

    {{-- Subject Modal --}}
    @if($showSubjectModal)
    <div class="modal-overlay" wire:click.self="closeSubjectModal">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="px-8 py-5 bg-gray-50 border-b flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Add New Subject</h2>
                    <p class="text-xs text-gray-400 mt-0.5">This subject will become available for mentor assignments.</p>
                </div>
                <button wire:click="closeSubjectModal" class="text-gray-400 hover:text-red-600 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-8 py-6 space-y-4">
                <div>
                    <label class="form-label">Subject Code</label>
                    <input type="text" wire:model="newSubjectCode"
                        placeholder="e.g. Math 54"
                        class="form-input" />
                    @error('newSubjectCode')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label">Subject Name</label>
                    <input type="text" wire:model="newSubjectName"
                        placeholder="e.g. Elementary Analysis II"
                        class="form-input" />
                    @error('newSubjectName')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Save button --}}
            <div class="px-8 py-5 bg-gray-50 border-t">

                @if(!$showSubjectConfirm)
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeSubjectModal"
                                class="flex-1 py-3 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition">
                            Cancel
                        </button>
                        <button type="button" wire:click="confirmSubject"
                                class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60"
                                wire:target="confirmSubject">
                            <span wire:loading.remove wire:target="confirmSubject">Add Subject</span>
                            <span wire:loading wire:target="confirmSubject">Saving...</span>
                        </button>
                    </div>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <div class="flex items-start gap-3 mb-4">
                            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-lg flex-shrink-0 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Confirm New Subject</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    This will be added to the list of available subjects.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button"
                                    wire:click="$set('showSubjectConfirm', false)"
                                    class="flex-1 py-2.5 text-xs font-bold text-gray-500 hover:bg-amber-100 rounded-lg transition">
                                Cancel
                            </button>
                            <button type="button" wire:click="saveSubject"
                                    class="flex-1 bg-red-900 text-white py-2.5 rounded-lg text-xs font-bold hover:bg-red-800 transition"
                                    wire:loading.attr="disabled" wire:loading.class="opacity-60"
                                    wire:target="saveSubject">
                                <span wire:loading.remove wire:target="saveSubject">Save</span>
                                <span wire:loading wire:target="saveSubject">Saving...</span>
                            </button>
                        </div>
                    </div>
                @endif

            </div>
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
