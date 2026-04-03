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
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

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
    return MentorProfiles::with('user')
        ->get()
        ->filter(fn($mentorProfiles) => $mentorProfiles->user->id !== auth()->id()) // 🚫 exclude self
        ->sortBy(fn($lName) => $lName->user->lastName)
        ->values()
        ->map(fn($mentorProfiles) => [
            'id' => $mentorProfiles->user->id,
            'profile_id' => $mentorProfiles->id,
            'name' => strtoupper($mentorProfiles->user->lastName). ', ' . $mentorProfiles->user->firstName,
        ])
        ->toArray();
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
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

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
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

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

// Get the selected mentor profile
$selectedMentor = MentorProfiles::find($validated['mentor_id']);

// 🚫 Prevent mentor from booking themselves
if ($selectedMentor && $selectedMentor->user_id === auth()->id()) {
    $this->addError('mentor_id', 'You cannot book yourself as a mentor.');
    return;
}
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
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
        }

        * { box-sizing: border-box; }

        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* ── SIDEBAR ── */
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

        /* ── Logo row ── */
        .sidebar-logo-container {
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            gap: 12px;
            flex-shrink: 0;
            overflow: hidden;
            transition: padding 0.3s, justify-content 0.3s;
        }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }

        .logo-icon { flex-shrink: 0; font-size: 1.25rem; width: 32px; text-align: center; }

        .logo-text {
            font-size: 1rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            opacity: 1;
            max-width: 200px;
            transition: opacity 0.2s, max-width 0.3s;
        }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }

        /* ── Nav items ── */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
            white-space: nowrap;
            position: relative;
            text-align: left;
            background: transparent;
            border: none;
            width: 100%;
            cursor: pointer;
            font-size: 0.875rem;
            justify-content: flex-start;
        }
        .sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }

        .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 18px; transition: width 0.3s; }
        .sidebar.collapsed .nav-item i { width: 32px; margin: 0; }

        .nav-item span {
            overflow: hidden; opacity: 1; max-width: 200px;
            transition: opacity 0.2s, max-width 0.3s;
        }
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active {
            background: var(--bg-light);
            color: var(--header-maroon);
            font-weight: 700;
            border-radius: 0;
            width: calc(100% + 1px);
            z-index: 10;
        }

        /* Tooltips */
        .nav-item::after {
            content: attr(data-tooltip);
            position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
            padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
            pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

        /* Logout section */
        .sidebar-footer { padding: 12px 0; border-top: 1px solid rgba(255,255,255,0.1); }

        /* ── TOGGLE BUTTON ── */
            .sidebar-toggle-btn {
                position: absolute;
                right: -16px;
                top: 3%;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #7b1d1d;
                font-size: 13px;
                z-index: 50;
                box-shadow: 0 2px 8px rgba(0,0,0,0.25);
                transition: background 0.2s;
                flex-shrink: 0;
            }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; justify-content: center;
        }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

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

        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
        .weekly-table{table-layout:fixed;width:100%;}
        .weekly-table th, .weekly-table td{width:16%;}
        .schedule-block{font-size:9px;line-height:1.2;padding:2px 4px;margin-bottom:2px;border-radius:4px;background:#d1fae5;color:#065f46;}
    </style>

    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>

            <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="toggle-icon">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            </button>

            <nav class="flex-grow">
                <a href="{{ route('mentor.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('mentor.bookings') }}" class="nav-item active" data-tooltip="Booking Form">
                    <i class="fa-solid fa-calendar-check"></i><span>Booking Form</span>
                </a>
                <a href="{{ route('mentor.sessions') }}" class="nav-item" data-tooltip="Tutorial Sessions">
                    <i class="fa-solid fa-clock"></i><span>Tutorial Sessions</span>
                </a>
                <a href="{{ route('mentor.feedbacks') }}" class="nav-item" data-tooltip="Student Feedbacks">
                    <i class="fa-solid fa-comment-dots"></i><span>Student Feedbacks</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" data-tooltip="Logout">
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

<!-- dito kayo mageedit -->
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

                <form id="bookingForm" wire:submit.prevent="submitBooking" class="space-y-2">
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
                        <button
                            type="button"
                            id="bookingSubmitBtn"
                            @if(!auth()->user()->studentProfile) disabled @endif
                            class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-60 cursor-not-allowed"
                            wire:target="submitBooking">
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

<!-- CONFIRMATION MODAL — inside app-wrapper for Livewire single-root compliance -->
        <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">

                <div class="flex items-center gap-3 mb-3">
                    <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                    <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
                </div>

                <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>

                <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>

                <div class="flex justify-end gap-3">
                    <button id="confirmCancelBtn"
                        class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button id="confirmOkBtn"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                        Confirm
                    </button>
                </div>

            </div>
        </div>

    </div>

<script>
        const sidebar        = document.getElementById('sidebar');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');

        document.getElementById('sidebarToggle').addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        window.addEventListener('click', () => {
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        });

        /* =========================
           CONFIRMATION MODAL
           ========================= */
        const confirmModal     = document.getElementById('confirmModal');
        const confirmModalBox  = document.getElementById('confirmModalBox');
        const confirmTitle     = document.getElementById('confirmTitle');
        const confirmBody      = document.getElementById('confirmBody');
        const confirmMeta      = document.getElementById('confirmMeta');
        const confirmOkBtn     = document.getElementById('confirmOkBtn');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmIconWrap  = document.getElementById('confirmIconWrap');

        confirmModal.addEventListener('click', (e) => {
            if (!confirmModalBox.contains(e.target)) closeConfirmModal();
        });
        confirmCancelBtn.addEventListener('click', closeConfirmModal);

        function closeConfirmModal() {
            confirmModal.style.display = 'none';
            confirmOkBtn.onclick = null;
        }

        function openConfirmModal({ title, body, meta, variant, onConfirm }) {
            const variants = {
                accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
                reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
                neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800',       label: 'Confirm' },
            };
            const v = variants[variant] || variants.neutral;

            confirmIconWrap.style.background = v.iconBg;
            confirmIconWrap.innerHTML        = v.iconHtml;
            confirmTitle.textContent         = title;
            confirmBody.innerHTML            = body;
            confirmMeta.innerHTML            = meta || '';
            confirmMeta.style.display        = meta ? 'block' : 'none';

            confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
            confirmOkBtn.textContent = v.label;
            confirmOkBtn.onclick     = () => { closeConfirmModal(); onConfirm(); };

            confirmModal.style.display = 'flex';
        }

        function iconCheck(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
        }
        function iconX(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/>
            </svg>`;
        }
        function iconInfo(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/>
                <path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="10" cy="6.5" r="0.8" fill="${color}"/>
            </svg>`;
        }

        /* =========================
           BOOKING SUBMIT INTERCEPT
           ========================= */
        document.getElementById('bookingSubmitBtn').addEventListener('click', function () {

            // Read current Livewire state from the DOM for the summary card
            const subjectEl  = document.querySelector('[wire\\:model="subject_id"]');
            const topicEl    = document.querySelector('[wire\\:model="topic"]');
            const dateEl     = document.querySelector('[wire\\:model="date"]');
            const startEl    = document.querySelector('[wire\\:model="schedule_start"]');
            const endEl      = document.querySelector('[wire\\:model="schedule_end"]');
            const mentorEl   = document.querySelector('[wire\\:model="mentor_id"]');

            const subjectText = subjectEl?.options[subjectEl.selectedIndex]?.text  || '—';
            const topicText   = topicEl?.value  || '—';
            const dateText    = dateEl?.value
                ? new Date(dateEl.value + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
                : '—';
            const startText   = formatTime(startEl?.value) || '—';
            const endText     = formatTime(endEl?.value)   || '—';
            const mentorText  = mentorEl?.options[mentorEl.selectedIndex]?.text || '—';

            function formatTime(t) {
                if (!t) return '';
                const [h, m] = t.split(':').map(Number);
                const ampm = h >= 12 ? 'PM' : 'AM';
                const hr   = h % 12 || 12;
                return `${hr}:${String(m).padStart(2,'0')} ${ampm}`;
            }

            const metaHtml = `
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#9ca3af;">Subject</span>
                    <span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${subjectText}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#9ca3af;">Topic</span>
                    <span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${topicText}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#9ca3af;">Mentor</span>
                    <span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${mentorText}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#9ca3af;">Date</span>
                    <span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${dateText}</span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#9ca3af;">Time</span>
                    <span style="font-weight:600;color:#374151;">${startText} – ${endText}</span>
                </div>
            `;

            openConfirmModal({
                title:     'Confirm booking request?',
                body:      'Please review your session details before submitting. Your request will be reviewed by the peer mentor.',
                meta:      metaHtml,
                variant:   'accept',
                onConfirm: () => {
                    // Trigger actual Livewire form submission
                    document.getElementById('bookingForm').dispatchEvent(
                        new Event('submit', { bubbles: true, cancelable: true })
                    );
                },
            });
        });
</script>

    </div>
