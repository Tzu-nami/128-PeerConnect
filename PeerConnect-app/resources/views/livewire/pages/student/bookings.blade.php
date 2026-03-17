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
:root { --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; }
body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); }

.app-wrapper { display: flex; height: 100vh; overflow: hidden; }
.sidebar { width: 280px; background: var(--sidebar-green); flex-shrink: 0; display: flex; flex-direction: column; color: white; }
.main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }

.nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; }
.nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
.nav-item.active { border-left: 4px solid white; }

.top-header { background: var(--header-maroon); height: 80px; padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }

.stat-card { background: white; padding: 25px; border-radius: 12px; transition: all 0.3s ease; border: 1px solid transparent; }
.stat-card:hover { transform: translateY(-5px); border-color: var(--header-maroon); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

.stat-card i { font-size: 24px; color: var(--sidebar-green); transition: 0.3s; }
.stat-card:hover i { transform: scale(1.2); color: var(--header-maroon); }

.cal-day { padding: 8px; border-radius: 8px; cursor: pointer; font-size: 13px; }
.cal-day:hover { background: #f0f0f0; }
.cal-today { background: var(--header-maroon) !important; color: white !important; font-weight: bold; }

.btn-maroon { background: var(--header-maroon); color: white; font-weight: 700; width: 100%; padding: 12px; border-radius: 8px; margin-bottom: 10px; transition: 0.2s; }
.btn-maroon:hover { filter: brightness(1.2); transform: scale(1.02); }

</style>

<div class="app-wrapper">

<aside class="sidebar">

    <div class="p-8 text-xl font-bold flex items-center gap-3">
        <i class="fa-solid fa-graduation-cap"></i>
        <span>LRC PeerConnect</span>
    </div>

    <nav class="flex-grow">

        <a href="{{ route('student.dashboard') }}"
        class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-gauge w-5"></i>
            Dashboard
        </a>

        <a href="{{ route('student.bookings') }}"
        class="nav-item {{ request()->routeIs('student.bookings') ? 'active' : '' }}">
            <i class="fa-solid fa-calendar-check w-5"></i>
            Bookings
        </a>

        <a href="{{ route('student.history') }}"
        class="nav-item {{ request()->routeIs('student.history') ? 'active' : '' }}">
            <i class="fa-solid fa-clock-rotate-left w-5"></i>
            History
        </a>

        <a href="{{ route('student.mentors') }}"
        class="nav-item {{ request()->routeIs('student.mentors') ? 'active' : '' }}">
            <i class="fa-solid fa-chalkboard-user w-5"></i>
            Mentors
        </a>

        <a href="{{ route('student.about') }}"
        class="nav-item {{ request()->routeIs('student.about') ? 'active' : '' }}">
            <i class="fa-solid fa-circle-info w-5"></i>
            About Us
        </a>

    </nav>

    <div class="p-4 border-t border-white/10">

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-item w-full text-left">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </button>
        </form>

    </div>

</aside>

<div class="main-content">

    <header class="top-header">

        <div class="text-lg">
            Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
        </div>

        <div class="relative">

            <button onclick="toggleProfileMenu()" 
            class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-red-900 font-bold">
                {{ strtoupper(substr(auth()->user()->name,0,2)) }}
            </button>

            <div id="profileMenu"
            class="hidden absolute right-0 mt-3 w-56 bg-white rounded-lg shadow-lg border overflow-hidden">

                <div class="px-4 py-3 border-b text-sm text-gray-600">
                    <div class="font-semibold">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-gray-400">{{ auth()->user()->email }}</div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                        <i class="fa-solid fa-right-from-bracket w-4"></i>
                        Logout
                    </button>
                </form>

            </div>
        </div>

    </header>

    <main class="p-8">

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

        <div class="lg:col-span-2" x-data="{ showConfirmation: false }">
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

                <div x-show="showConfirmation" style="display: none;" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                    <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" @click.away="showConfirmation = false">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Confirm booking</h3>
                        <p class="text-sm text-gray-600 mb-6">Are you sure all the inputted information is correct?</p>
                        <div class="flex justify-end gap-3">
                            <button @click="showConfirmation = false" type="button" class="px-4 py-2 text-base font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                            <button @click="$wire.submitBooking(); showConfirmation = false" type="button" class="px-4 py-2 text-base font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">Submit</button>
                        </div>
                    </div>
                </div>
                <form x-on:submit.prevent="showConfirmation = true" class="space-y-2">
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
                    showConfirmation: false,
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

                    <div x-show="showConfirmation" style="display: none;" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                        <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" @click.away="showConfirmation = false">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Confirm Profile</h3>
                            <p class="text-sm text-gray-600 mb-6">Are you sure all the inputted information is correct?</p>
                            <div class="flex justify-end gap-3">
                                <button @click="showConfirmation = false" type="button" class="px-4 py-2 text-base font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                                <button @click="$wire.saveProfile(); showConfirmation = false" type="button" class="px-4 py-2 text-base font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors">Submit</button>
                            </div>
                        </div>
                    </div>

                    <form x-on:submit.prevent="showConfirmation = true" class="space-y-4 mt-4">
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
function toggleProfileMenu(){
    document.getElementById("profileMenu").classList.toggle("hidden");
}

window.addEventListener('click',function(e){
    const menu=document.getElementById("profileMenu");
    const button=e.target.closest("button");
    if(!button && !e.target.closest("#profileMenu")){
        menu.classList.add("hidden");
    }
});
</script>

</div>