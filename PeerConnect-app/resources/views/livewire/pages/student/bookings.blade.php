<?php

use App\Models\Bookings;
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Models\TutorialMode;
use App\Models\StudentProfiles;
use App\Models\Colleges;
use App\Models\DegreePrograms;
use App\Models\YearLevels;
use App\Models\MentorSubjects;
use App\Models\MentorAvailabilities;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{layout, state, mount, action, computed, updated};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    // For pre-filled information
    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    if($profile) {
        $this->student_num = $profile->student_num;
        $this->college_id = $profile->college_id;
        $this->degreeProgram_id = $profile->degreeProgram_id;
        $this->yearLevel_id = $profile->yearLevel_id;
        $this->toggleProfileOpen = false;
    }
});

// For booking forms inputs
state([
    'mentor_id' => '',
    'subject_id' => '',
    'topic' => '',
    'tutorialMode_id' => '',
    'date' => '',
    'schedule_start' => '',
    'schedule_end' => '',
    'successMessage' => false,
]);

$mentors = computed(function () {
    return MentorProfiles::with('user') -> get() -> sortBy(fn($lName) => $lName->user->lastName)
        -> values()
        -> map(fn($mentorProfiles) => [
            'id' => $mentorProfiles->user->id,
            'profile_id' => $mentorProfiles->id,
            'name' => strtoupper($mentorProfiles->user->lastName). ', ' . $mentorProfiles->user->firstName,
        ]) -> toArray();
});

$mentorAvailabilities = computed(function () {
    return MentorAvailabilities::all() -> map(fn($avail)=> [
        'mentorProfile_id' => $avail->mentor_id,
        'day_of_week' => $avail->day_of_week,
        'start_time' => $avail->start_time,
        'end_time' => $avail->end_time,
    ]) -> values() -> toArray();
});

$mentorSubjects = computed(function () {
    return MentorSubjects::all() -> map(fn($subs) => [
        'mentorProfile_id' => $subs->mentor_id,
        'subject_id' => $subs->subject_id,
    ]) -> values() -> toArray();
});

$subjects = computed(function () {
    return Subjects::orderBy('code')->get();
});

$tutorialModes = computed(function () {
    return TutorialMode::orderBy('id')->get();
});

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if(!$profile) {return collect();}
    return Bookings::with(['mentor', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()
        ->take(3)
        ->get();
});

// For student profile inputs
state([
    'toggleProfileOpen' => true,
    'profileSaved' => false,
    'student_num' => '',
    'college_id' => '',
    'degreeProgram_id' => '',
    'yearLevel_id' => '',
]);

$colleges = computed(function () {
    return Colleges::orderBy('name')->get();
});

$degreePrograms = computed(function() {
    return DegreePrograms::orderBy('name')->get();
});

$yearLevels = computed(function () {
    return YearLevels::orderBy('name')->get();
});

$toggleProfile = action(function () {
    $this->toggleProfileOpen = !$this->toggleProfileOpen;
});

$saveProfile = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $validated = $this->validate([
        'student_num' => ['required', 'string', 'max:10', 'regex:/-/'],
        'college_id' => ['required', 'exists:colleges,id'],
        'degreeProgram_id' => ['required', 'exists:degree_programs,id'],
        'yearLevel_id' => ['required', 'exists:year_levels,id'],
    ],  messages: [
        'student_num.regex' => 'The student number must include a hyphen (-)',
    ],  attributes: [
        'student_num' => 'student number',
        'college_id' => 'college',
        'degreeProgram_id' => 'degree program',
        'yearLevel_id' => 'year level',
    ]);

    StudentProfiles::updateOrCreate(
        ['user_id' => auth()->id()],
        $validated
    );

    $this->profileSaved = true;
    $this->toggleProfileOpen = false;

    $this->dispatch('profile-updated');
});

// Submit form
$submitBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    abort_if(!auth()->user()->studentProfile, 422);

    $validated = $this->validate([
        'mentor_id' => ['required', 'exists:mentor_profiles,id'],
        'subject_id' => ['required', 'exists:subjects,id'],
        'topic' => ['required', 'string', 'max:255'],
        'tutorialMode_id' => ['required', 'exists:tutorial_modes,id'],
        'date' => ['required', 'date', 'after:today', function($attribute, $value, $fail) {
            $sessionDate = \Carbon\Carbon::parse($value)->format('l, F j, Y');
            if ($sessionDate === 'Sunday') {
                $fail('The session cannot be on a Sunday. Please select another date.');
            }
        }],
        'schedule_start' => ['required', 'date_format:H:i'],
        'schedule_end' => ['required', 'date_format:H:i', 'after:schedule_start'],
    ],  attributes: [
        'mentor_id' => 'mentor',
        'subject_id' => 'subject',
        'topic' => 'topic',
        'tutorialMode_id' => 'mode of tutorial',
        'date' => 'date',
        'schedule_start' => 'start time',
        'schedule_end' => 'end time',
    ]);

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    Bookings::create([
        ...$validated,
        'student_id' => $profile->id,
        'booking_status' => 'pending',
    ]);

    $this->reset([
        'mentor_id', 'subject_id', 'topic', 'tutorialMode_id', 'date', 'schedule_start', 'schedule_end',
    ]);

    $this->successMessage = true;
});

$dismissSuccessMessage = action(function () {
    $this->successMessage = false;
});

?>

<div>

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
                @if($successMessage)
                    <div class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                        <span>Your session has been booked and is now <strong>pending</strong> approval.</span>
                        <button wire:click="dismissSuccessMessage" class="text-green-600 hover:text-green-800 font-bold ml-4">X</button>
                    </div>
                @endif

                @if(!auth()->user()->studentProfile)
                    <div class="mb-6 bg-yellow-100 border border-yellow-400 text-black-800 px-4 py-3 rounded">
                        Please complete your <strong>Student Profile</strong> before booking a session.
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <div class="lg:col-span-2">
                        <div class="bg-[#fffffa] p-6 rounded-lg shadow-sm border-gray-200"
                             x-data="{
                                allMentors: @js($this->mentors),
                                allSubjects: @js($this->mentorSubjects),
                                allAvailabilities: @js($this->mentorAvailabilities),

                                getDayOfWeek(dateStr) {
                                const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                                const d = new Date(dateStr + 'T00:00:00');
                                return days[d.getDay()];
                                },

                                get filteredMentors() {
                                    let choices = this.allMentors;

                                    if($wire.subject_id) {
                                        const validIds = this.allSubjects
                                            .filter(s => s.subject_id == $wire.subject_id)
                                            .map(s => s.mentorProfile_id);
                                        choices = choices.filter(m => validIds.includes(m.profile_id));
                                    }

                                    if($wire.date) {
                                        const dayChosen = this.getDayOfWeek($wire.date);

                                        choices = choices.filter(m => {
                                            const avails = this.allAvailabilities.filter(a => a.mentorProfile_id == m.profile_id && a.day_of_week === dayChosen);

                                            if(avails.length === 0) return false;

                                            if($wire.schedule_start && $wire.schedule_end) {
                                                return avails.some(a => {
                                                    let start = a.start_time.substring(0, 5);
                                                    let end = a.end_time.substring(0, 5);
                                                    let startChosen = $wire.schedule_start.substring(0, 5);
                                                    let endChosen = $wire.schedule_end.substring(0, 5);

                                                    return start <= startChosen && end >= endChosen;
                                                    });
                                                }
                                            return true;
                                        });
                                    }
                                    return choices;
                                }
                            }">
                            <h2 class="text-lg font-semibold text-gray-900 mb-1">Request an Enrichment Session!</h2>
                            <p class="text-gray-500 text-sm mb-6">Please fill out all required fields. Your request will then be reviewed by the peer mentor.</p>

                            <form wire:submit.prevent="submitBooking" class="space-y-2">
                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-1">Subject<span class="text-red-500">*</span></label>
                                    <select wire:model="subject_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                        <option value="">--- Select a Subject ---</option>
                                        @foreach($this->subjects as $subject)
                                            <option value="{{ $subject['id'] }}">{{ $subject['code'] }} - {{$subject['name']}}</option>
                                        @endforeach
                                    </select>
                                    @error('subject_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-1">Topic<span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="topic" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1" placeholder="e.g. Integration by Parts." maxlength="255">
                                    @error('topic') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-1">Tutorial Mode<span class="text-red-500">*</span></label>
                                    <select wire:model="tutorialMode_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                        <option value="">--- Select Mode of Tutoring ---</option>
                                        @foreach($this->tutorialModes as $mode)
                                            <option value="{{ $mode['id'] }}">{{ $mode['mode'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('tutorialMode_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-1">Preferred Day<span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="date" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1" min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                                    @error('date') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">Start Time<span class="text-red-500">*</span></label>
                                        <input type="time" wire:model="schedule_start" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                        @error('schedule_start') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">End Time<span class="text-red-500">*</span></label>
                                        <input type="time" wire:model="schedule_end" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                        @error('schedule_end') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-base font-medium text-gray-700 mb-1">Preferred Mentor<span class="text-red-500">*</span></label>
                                    <select wire:model="mentor_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                        <option value="" x-text="filteredMentors.length === 0 ? '--- No mentors available. Please select a different date or timeframe. ---' : '--- Select a mentor ---'">
                                        </option>
                                        <template x-for="mentor in filteredMentors" :key="mentor.profile_id">
                                            <option :value="mentor.profile_id" x-text="mentor.name"></option>
                                        </template>
                                    </select>
                                    @error('mentor_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <div class="pt-4">
                                    <button type="submit" @if(!auth()->user()->studentProfile) disabled @endif class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors" wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="submitBooking">
                                        <span wire:loading.remove wire:target="submitBooking">Submit Booking Request</span>
                                        <span wire:loading wire:target="submitBooking">Submitting...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-[#fffffa] rounded-xl shadow-sm border-gray-200 overflow-hidden p-6"
                             x-data="{
                                open: $wire.entangle('toggleProfileOpen'),
                                college: $wire.entangle('college_id'),
                                degree: $wire.entangle('degreeProgram_id'),
                                showSuccess: false,
                                allDegrees: @js($this->degreePrograms),
                                get filteredDeProgs() {
                                    if (!this.college) return [];
                                    return this.allDegrees.filter(deprog => deprog.college_id == this.college);
                                }
                            }"
                                         @profile-updated.window="showSuccess = true; setTimeout(() => showSuccess = false, 10000)"
                                         x-init="
                                $watch('college', (val, oldVal) => {
                                    if (oldVal !== undefined && oldVal !== '') {
                                        degree = '';
                                    }
                                });
                                $nextTick(() => {
                                    let savedDegree = degree;
                                    degree = '';
                                    degree = savedDegree;
                                });
                            ">

                            <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-semibold text-gray-900">Student Profile</span>
                                    @if(auth()->user()->studentProfile)
                                        <span class="text-xs bg-green-200 px-2 py-1 rounded-full text-green-800 font-bold">Saved</span>
                                    @else
                                        <span class="text-xs bg-yellow-100 px-2 py-1 rounded-full text-yellow-800 font-bold">Required</span>
                                    @endif
                                </div>

                                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div x-show="open" style="display: none;" x-transition class="px-5 pb-5 border-t border-gray-100">

                                <div x-show="showSuccess" style="display: none;" x-transition class="mt-3 mb-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">Profile Updated!</div>

                                <form wire:submit.prevent="saveProfile" class="space-y-4 mt-4">
                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">Student Number<span class="text-red-500">*</span></label>
                                        <input type="text" wire:model="student_num" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1" placeholder="e.g 2023-00000" maxlength="10">
                                        @error('student_num') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">College<span class="text-red-500">*</span></label>
                                        <select x-model="college" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                            <option value="">--- College ---</option>
                                            @foreach($this->colleges as $c)
                                                <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('college_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">Degree Program<span class="text-red-500">*</span></label>
                                        <select x-model="degree" x-bind:disabled="!college" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1 disabled:bg-gray-100">
                                            <option value="">--- Degree Program ---</option>
                                            <template x-for="deprog in filteredDeProgs" :key="deprog.id">
                                                <option :value="deprog.id" x-text="deprog.name"></option>
                                            </template>
                                        </select>
                                        @error('degreeProgram_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-base font-medium text-gray-700 mb-1">Year Level<span class="text-red-500">*</span></label>
                                        <select wire:model="yearLevel_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-black-500 focus:ring-blue-500 text-base px-2 py-1">
                                            <option value="">--- Year Level ---</option>
                                            @foreach($this->yearLevels as $level)
                                                <option value="{{ $level['id'] }}">{{ $level['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('yearLevel_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>

                                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors" wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="saveProfile">
                                        <span wire:loading.remove wire:target="saveProfile">{{ auth()->user()->studentProfile ? 'Update Profile' : 'Save Profile' }}</span>
                                        <span wire:loading wire:target="saveProfile">Saving...</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="bg-[#fffffa] rounded-xl shadow-sm border-gray-200 overflow-hidden p-6">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Recent Bookings</h3>
                            @forelse($this->studentBookings as $booking)
                                <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ $booking->subject->code }}</p>
                                            <p class="text-xs font-medium text-gray-800">{{ $booking->topic }}</p>
                                            <p class="text-xs font-medium text-gray-800">{{ strtoupper($booking->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $booking->mentor->user->firstName ?? 'Mentor' }}</p>
                                        </div>
                                        @php
                                            $statusColors = match($booking->booking_status) {
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'no-show' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="text-xs font-medium px-2 py-1 rounded-full capitalize {{ $statusColors }}">
                                {{ str_replace('_', ' ', $booking->booking_status) }}
                            </span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }},
                                        {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }} - {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
                                    </p>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 text-center py-4">No recent bookings.</p>
                            @endforelse
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
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
    </script>
</div>
