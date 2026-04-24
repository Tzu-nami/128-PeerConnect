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

        // Load student sessions (bookings) for dashboard
        $this->sessions = Bookings::with(['mentor.user', 'subject'])
            ->where('student_id', $profile->id)
            ->get()
            ->map(function ($b) {
                $start = \Carbon\Carbon::parse($b->schedule_start);
                $end   = \Carbon\Carbon::parse($b->schedule_end);

                return [
                    'id'      => $b->id,
                    'mentor'  => optional(optional($b->mentor)->user)->firstName
                        ? $b->mentor->user->firstName . ' ' . $b->mentor->user->lastName
                        : 'Unknown',
                    'subject' => optional($b->subject)->code ?? 'N/A',
                    'topic'   => $b->topic ?? '',
                    'date'    => $b->date
                        ? \Carbon\Carbon::parse($b->date)->format('Y-m-d')
                        : null,
                    'start'  => $start->format('H:i'),
                    'end'    => $end->format('H:i'),
                    'status' => $b->booking_status,
                ];
            })
            ->values()
            ->toArray();
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
    'sessions' => [],
    'globalSearchTerm' => '',
]);

$searchIndex = computed(function () {
    $index = [];

    // Map Mentors
    $mentors = \App\Models\User::where('user_roles', 'mentor')->get();
    foreach($mentors as $m) {
        $year = $m->studentProfile->yearLevel->name;
        $deprog = $m->studentProfile->degreeProgram->name;
        $index[] = [
            'group' => 'Mentors',
            'label' => $m->lastName . ', ' . $m->firstName,
            'detail' => $m->email . ' -- ' . $year . ' ' . $deprog,
            'icon' => 'fa-chalkboard-user',
            'bg' => '#dbeafe', 'color' => '#1e40af',
            'url' => route('student.mentors'),
            'searchString' => strtolower($m->firstName . ' ' . $m->lastName . ' ' . $m->email . ' ' . $year . ' ' . $deprog)
        ];
    }

    // Map Subjects
    $subjects = \App\Models\Subjects::all();
    $allMentorProfiles = \App\Models\MentorProfiles::with(['user', 'subjects'])->get()->sortBy(fn($lName) => $lName->user->lastName);
    foreach($subjects as $s) {
        $teachingMentors = $allMentorProfiles->filter(function ($mentor) use ($s) {
            return $mentor->subjects->contains('id', $s->id);
        });
        $mentorNames = $teachingMentors->map(function ($mentor) {
            return ($mentor->user->lastName);
            })->filter()->implode(', ');

        $detailText = $mentorNames ? "Mentors: " . $mentorNames : "No mentors assigned yet";
        $index[] = [
            'group' => 'Courses',
            'label' => strtoupper($s->code),
            'detail' => $detailText,
            'icon' => 'fa-book-open',
            'bg' => '#fef3c7', 'color' => '#92400e',
            'url' => route('student.mentors'),
            'searchString' => strtolower($s->code . ' ' . $mentorNames)
        ];
    }

    // Map Recent Sessions
    $studentProfileId = \App\Models\StudentProfiles::where('user_id', auth()->id())->value('id');
    $bookings = \App\Models\Bookings::with('mentor.user',  'subject')->where('student_id', $studentProfileId)->latest()->take(50)->get();
    foreach($bookings as $b) {
        $mentorName = $b->mentor ? ($b->mentor->user->lastName . ', ' . $b->mentor->user->firstName ?? 'Unknown Mentor') : 'Unknown Mentor';
        $sessionDate = \Carbon\Carbon::parse($b->date)->format('F j, Y');
        $index[] = [
            'group' => 'Sessions',
            'label' => $b->topic ?: 'Tutorial Session', // Note: for some reason, naglalag siya kapag hindi topic yung label
            'detail' => $sessionDate . ' -- Subject: ' . $b->subject->code . ' -- Mentor: ' . $mentorName . ' -- ' . ' -- Status: ' . ucfirst($b->booking_status),
            'icon' => 'fa-calendar-days',
            'bg' => '#d1fae5', 'color' => '#065f46',
            'url' => route('student.history'),
            'searchString' => strtolower($b->topic . ' ' . $mentorName . ' ' . $b->booking_status . ' ' . $b->subject->code . ' ' . $sessionDate)
        ];
    }

    return $index;
});

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

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect – Student Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root { --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; --header-height: 80px; --sidebar-width: 260px; --sidebar-collapsed-width: 72px; }
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
            display: flex; align-items: center; justify-content: center;
            padding: 0 20px; gap: 12px; flex-shrink: 0; overflow: hidden;
            transition: padding 0.3s, justify-content 0.3s;
        }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }
        .logo-icon { flex-shrink: 0; font-size: 27px; width: auto; text-align: center; }
        .logo-text { font-size: 1.24rem; font-weight: 700; white-space: nowrap; overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }
        .sidebar.collapsed .sidebar-logo-container { justify-content: center; padding: 0; width: 100%; }
        .sidebar.collapsed .logo-content { gap: 0; justify-content: center; width: 100%; }

        /* ── Nav items ── */
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 16px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
            white-space: nowrap; position: relative; text-align: left;
            background: transparent; border: none; width: 100%;
            cursor: pointer; font-size: 0.95rem; justify-content: flex-start;
        }
        .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
        .nav-item span { overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }

        .sidebar.collapsed .nav-item { display: flex; align-items: center; justify-content: center; padding: 16px 0; width: 100%; gap: 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; text-align: center; flex-shrink: 0; }
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

        .nav-item::after {
            content: attr(data-tooltip);
            position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
            padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
            pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

        .sidebar-footer { padding: 0; border-top: 1px solid rgba(255,255,255,0.1); }

        .sidebar-toggle-btn {
            position: absolute; right: -16px; top: 50%;
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--header-maroon); border: 2px solid white;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 13px; z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25); transition: background 0.2s; flex-shrink: 0;
        }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

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

        /* ── CALENDAR (clock bar on top, compact nav — from mentor dashboard) ── */
        .cal-header-day {
            font-size: 10px;
            font-weight: 800;
            text-align: center;
            color: #94a3b8;
            text-transform: uppercase;
            padding-bottom: 4px;
        }
        .cal-day {
            aspect-ratio: 1/1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 6px;
            transition: all 0.15s;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            width: 100%;
        }
        .cal-day:hover { background: #f1f5f9; color: #1e293b; }
        .cal-today { background: #fee2e2 !important; color: #7b1d1d !important; font-weight: 800; }
        .cal-selected { border: 2px solid #7b1d1d; background: #f8fafc; }
        /* Notification dot — absolute so it never shifts the day number */
        .notif-dot {
            position: absolute;
            top: 2px;
            right: 3px;
            width: 5px;
            height: 5px;
            background: #22c55e;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }

        /* ── WEEKLY SCHEDULE TABLE ── */
        .weekly-table { table-layout: fixed; width: 100%; }
        .weekly-table th, .weekly-table td { width: 16%; }

        /* Status-differentiated schedule blocks */
        .schedule-block {
            font-size: 9px;
            line-height: 1.3;
            padding: 3px 5px;
            margin-bottom: 3px;
            border-radius: 5px;
            border-left: 3px solid transparent;
            overflow: hidden;
        }
        .schedule-block.status-pending   { background: #fef9c3; color: #854d0e;  border-left-color: #eab308; }
        .schedule-block.status-accepted  { background: #d1fae5; color: #065f46;  border-left-color: #10b981; }
        .schedule-block.status-completed { background: #e2e8f0; color: #475569;  border-left-color: #94a3b8; }

        /* Weekly schedule legend */
        .sched-legend { display: flex; flex-wrap: wrap; gap: 8px; }
        .sched-legend-item { display: flex; align-items: center; gap: 4px; font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .sched-legend-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

        .topic-text { word-break: break-word; overflow-wrap: anywhere; white-space: normal; }
        .topic-text.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .topic-text.line-clamp-none { display: block; overflow: visible; }

        .hover-tooltip { position: relative; cursor: pointer; }
        .hover-tooltip::after {
            content: attr(data-full);
            position: absolute; left: 0; top: 110%;
            background: rgba(0,0,0,0.85); color: #fff;
            padding: 8px 10px; border-radius: 6px;
            font-size: 11px; line-height: 1.4;
            white-space: normal; word-break: break-word; overflow-wrap: anywhere;
            width: 320px; max-width: 320px;
            opacity: 0; pointer-events: none;
            transform: translateY(5px); transition: 0.15s ease; z-index: 9999;
        }
        .hover-tooltip:hover::after { opacity: 1; transform: translateY(0); }
        .topic-text.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .topic-text.line-clamp-none { display: block; overflow: visible; }

        #statusToast {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            display: flex; align-items: center; gap: 10px; padding: 10px 16px;
            border-radius: 10px; font-size: 12px; font-weight: 600; color: white;
            background: #1e293b; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            opacity: 0; transform: translateY(12px); transition: opacity 0.2s, transform 0.2s;
            pointer-events: none; min-width: 200px;
        }
        #statusToast.show { opacity: 1; transform: translateY(0); }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>

<body>
<div class="app-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo-container">
            <div class="logo-content">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>
        </div>

        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">
                <i class="fa-solid fa-chevron-right"></i>
            </span>
        </button>

        <nav class="flex-grow">
            <a href="{{ route('student.dashboard') }}" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('student.mentors') }}" class="nav-item {{ request()->routeIs('student.mentors') ? 'active' : '' }}" data-tooltip="Mentors">
                <i class="fa-solid fa-chalkboard-user"></i><span>Mentors</span>
            </a>
            <a href="{{ route('student.bookings') }}" class="nav-item {{ request()->routeIs('student.bookings') ? 'active' : '' }}" data-tooltip="Bookings">
                <i class="fa-solid fa-calendar-check"></i><span>Bookings</span>
            </a>
            <a href="{{ route('student.history') }}" class="nav-item {{ request()->routeIs('student.history') ? 'active' : '' }}" data-tooltip="History">
                <i class="fa-solid fa-clock-rotate-left"></i><span>History</span>
            </a>
            <a href="{{ route('student.about') }}" class="nav-item {{ request()->routeIs('student.about') ? 'active' : '' }}" data-tooltip="About Us">
                <i class="fa-solid fa-circle-info"></i><span>About Us</span>
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

    <!-- MAIN -->
    <div class="main-content">
        <header class="top-header relative">
            <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>

            <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
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

            <!-- GLOBAL SEARCH -->
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative" x-data="{ 
                query: '',
                open: false,
                index: @js($this->searchIndex),
                
                get filteredResults() {
                    const term = this.query.toLowerCase();
                    const matches = this.index.filter(item => item.searchString.includes(term));

                    const grouped = {};
                    matches.forEach(m => {
                        if (!grouped[m.group]) grouped[m.group] = [];
                        grouped[m.group].push(m);
                    });
                    return grouped;
                }
            }" 
            @click.outside="open = false">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input
                        type="text"
                        x-model="query"
                        @focus="open = true"
                        @keydown.escape.window="open = false; query = ''"
                        placeholder="Search mentors, courses, recent sessions, or feedbacks..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon h-[34px] transition-shadow"
                    >
                </div>
                <div x-show="open && query.length >= 1" x-cloak x-transition
                    class="absolute left-0 right-0 bg-white rounded-xl shadow-xl border border-gray-100 overflow-y-auto"
                    style="top: calc(100% + 6px); max-height: 420px; z-index: 20;">
                    
                    <template x-if="Object.keys(filteredResults).length === 0">
                        <div style="padding:20px;text-align:center;font-size:13px;color:#9ca3af;font-style:italic;">
                            No matches found for "<strong x-text="query"></strong>"
                        </div>
                    </template>

                    <template x-for="(items, group) in filteredResults" :key="group">
                        <div>
                            <div x-text="group" style="padding:10px 14px;font-size:10px;font-weight:900;color:#000000;text-transform:uppercase;letter-spacing:.05em; background: #f0f0f0;"></div>
                            
                            <template x-for="item in items" :key="item.label + item.detail">
                                <a :href="item.url" class="block group" style="display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .15s; text-decoration:none;" onmouseover="this.style.background='#f4f5f7'" onmouseout="this.style.background='transparent'">
                                    
                                    {{-- Icon Badge --}}
                                    <span :style="`font-size:11px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;flex-shrink:0;background:${item.bg};color:${item.color};`">
                                        <i class="fa-solid" :class="item.icon"></i>
                                    </span>
                                    
                                    {{-- Text Content --}}
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-size:13px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                        <div style="font-size:11px;font-weight:500;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;" x-text="item.detail"></div>
                                    </div>
                                    
                                    <i class="fa-solid fa-arrow-up-right-from-square opacity-0 group-hover:opacity-100 transition-opacity" style="font-size:10px;color:#cbd5e1;flex-shrink:0; transform: translateX(-5px); group-hover:transform: translateX(0);"></i>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-8">

                <!-- LEFT COLUMN (col-span-2) -->
                <div class="col-span-2 space-y-8">

                    <!-- TODAY'S SESSIONS TABLE -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
                                <p class="text-s text-gray-500" id="tableSubtitle"></p>
                            </div>
                            <div class="flex gap-2">
                                <div class="relative w-48">
                                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                    <input type="text" id="liveSearchInput" placeholder="Search mentors..." class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                                </div>
                                <select id="statusFilter" class="table-filter-select">
                                    <option value="">All</option>
                                    <option value="pending">Pending</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <table class="w-full text-left text-sm table-fixed">
                            <thead class="text-gray-400 border-b">
                                <tr>
                                    <th class="pb-3 text-[10px] tracking-wider" style="width:35%">
                                        <button id="sortHead-mentor" onclick="toggleSort('mentor')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                                            Mentor <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                                        </button>
                                    </th>
                                    <th class="pb-3 text-[10px] tracking-wider" style="width:30%">
                                        <button id="sortHead-start" onclick="toggleSort('start')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#7b1d1d;">
                                            Time <span class="sort-icon"><i class="fa-solid fa-arrow-up" style="font-size:8px;"></i></span>
                                        </button>
                                    </th>
                                    <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
                                        <button id="sortHead-subject" onclick="toggleSort('subject')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                                            Subject <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                                        </button>
                                    </th>
                                    <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
                                        <button id="sortHead-status" onclick="toggleSort('status')" class="flex items-center justify-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors w-full" style="color:#94a3b8;">
                                            Status <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="tableBody"></tbody>
                        </table>

                        <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                            <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">Showing 0 results</div>
                            <div class="flex gap-2">
                                <button id="prevBtn" class="pagination-btn"><i class="fa-solid fa-chevron-left"></i></button>
                                <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- WEEKLY SCHEDULE (grid-based, from mentor dashboard) -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-slate-800">Weekly Schedule</h2>
                                <span class="text-[10px] text-gray-400 bg-gray-50 border border-gray-100 px-2 py-0.5 rounded-full" id="weeklyScheduleRange">8:00 AM – 6:00 PM</span>
                            </div>
                        </div>

                        <!-- Status legend -->
                        <div class="sched-legend mb-4 pb-3 border-b border-gray-50">
                            <span class="sched-legend-item"><span class="sched-legend-dot" style="background:#eab308;"></span>Pending</span>
                            <span class="sched-legend-item"><span class="sched-legend-dot" style="background:#10b981;"></span>Accepted</span>
                            <span class="sched-legend-item"><span class="sched-legend-dot" style="background:#94a3b8;"></span>Completed</span>
                        </div>

                        <div class="overflow-x-auto">
                            <!-- The old table is kept as anchor; JS will hide it and inject the grid -->
                            <table class="weekly-table text-xs text-center border" id="weeklyTableEl" style="display:none;">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase tracking-wider">Time</th>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase" id="monHead"></th>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase" id="tueHead"></th>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase" id="wedHead"></th>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase" id="thuHead"></th>
                                        <th class="p-2 border text-[10px] font-bold text-gray-500 uppercase" id="friHead"></th>
                                    </tr>
                                </thead>
                                <tbody id="weeklyScheduleBody"></tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- end col-span-2 -->

                <!-- RIGHT COLUMN -->
                <div class="flex flex-col gap-6">

                    <!-- CALENDAR with clock bar on top (from mentor dashboard) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Clock bar -->
                        <div class="bg-slate-900 px-4 py-3 flex items-center justify-between">
                            <div id="liveDate" class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Saturday, March 14</div>
                            <div id="liveClock" class="text-sm font-mono font-bold text-white tracking-widest">00:00:00</div>
                        </div>

                        <!-- Calendar -->
                        <div class="p-4">
                            <div class="flex items-center justify-center gap-3 mb-4">
                                <!-- Previous -->
                                <button onclick="changeMonth(-1)"
                                    class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                </button>

                                <!-- Month -->
                                <span id="monthDisplay"
                                    class="text-sm font-bold text-slate-800 text-center min-w-[120px]">
                                </span>

                                <!-- Next -->
                                <button onclick="changeMonth(1)"
                                    class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 gap-1 mb-1">
                                <div class="cal-header-day">S</div>
                                <div class="cal-header-day">M</div>
                                <div class="cal-header-day">T</div>
                                <div class="cal-header-day">W</div>
                                <div class="cal-header-day">T</div>
                                <div class="cal-header-day">F</div>
                                <div class="cal-header-day">S</div>
                            </div>
                            <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                        </div>
                    </div>

                    <!-- MY UPCOMING SESSIONS -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-slate-800 text-sm tracking-tight">My Upcoming Sessions</h3>
                            <span id="upcomingBadge" class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full"></span>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div id="upcomingSessionsList" class="flex flex-col gap-4"></div>
                        </div>

                        <div id="upcomingPagination" class="hidden mt-3 flex items-center justify-between px-1 border-t border-gray-50 pt-3">
                            <span id="upcomingPageInfo" class="text-[10px] text-gray-400"></span>
                            <div class="flex gap-1">
                                <button id="upcomingPrevBtn" class="pagination-btn opacity-30 cursor-not-allowed" disabled>
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                                </button>
                                <button id="upcomingNextBtn" class="pagination-btn">
                                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </button>
                            </div>
                        </div>

                        <a href="{{ route('student.bookings') }}" class="block w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">
                            Book a New Session →
                        </a>
                    </div>

                </div><!-- end right column -->
            </div><!-- end grid -->

        </main>
    </div>

    <div id="statusToast">
        <span id="statusToastMsg">Loading...</span>
    </div>

</div><!-- end app-wrapper -->

<script>
// ─── DATA FROM SERVER ────────────────────────────────────────────────────────
const allSessions = @json($this->sessions);

// ─── STATE ───────────────────────────────────────────────────────────────────
const today = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }));
const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
let selectedDateStr = todayStr;
let viewDate = new Date(today.getFullYear(), today.getMonth(), 1);

let tablePage = 0;
const TABLE_PER_PAGE = 5;
let sortColumn = 'start';
let sortDirection = 'asc';
let upcomingPage = 0;
const UPCOMING_PER_PAGE = 5;

// ─── DOM ELEMENTS ────────────────────────────────────────────────────────────
const sidebar             = document.getElementById('sidebar');
const profileTrigger      = document.getElementById('profileTrigger');
const profileDropdown     = document.getElementById('profileDropdown');
const searchInput         = document.getElementById('liveSearchInput');
const statusFilter        = document.getElementById('statusFilter');

// ─── SIDEBAR TOGGLE ──────────────────────────────────────────────────────────
document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
});

profileTrigger.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown.classList.toggle('show');
});

window.addEventListener('click', (e) => {
    if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
});

// ─── CLOCK ───────────────────────────────────────────────────────────────────
function updateClock() {
    const now = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }));
    document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
    document.getElementById('liveDate').innerText  = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
}
setInterval(updateClock, 1000);

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function formatTimeTo12Hour(timeStr) {
    const [hour, minute] = timeStr.split(':');
    let h = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minute} ${ampm}`;
}

function getStatusColor(status) {
    switch (status) {
        case 'pending':   return 'bg-yellow-100 text-yellow-800';
        case 'accepted':  return 'bg-green-100 text-green-800';
        case 'completed': return 'text-gray-900 bg-gray-100';
        case 'rejected':  return 'bg-red-100 text-red-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        case 'no_show':   return 'bg-red-100 text-red-800';
        default:          return 'bg-gray-100 text-gray-800';
    }
}

function getStatusLabel(status) {
    switch (status) {
        case 'no_show':   return 'No Show';
        case 'accepted':  return 'Accepted';
        case 'completed': return 'Completed';
        case 'rejected':  return 'Rejected';
        case 'cancelled': return 'Cancelled';
        case 'pending':   return 'Pending';
        default: return status ? status.charAt(0).toUpperCase() + status.slice(1) : '—';
    }
}

// ─── TOGGLE NAME HELPERS ─────────────────────────────────────────────────────
function toggleName(id) {
    const nameEl = document.getElementById('name-' + id);
    const btn    = document.getElementById('toggle-' + id);
    if (!nameEl || !btn) return;
    if (btn.innerText === 'Show more') {
        nameEl.style.whiteSpace = 'normal'; nameEl.style.overflow = 'visible';
        nameEl.style.textOverflow = 'unset'; nameEl.style.wordBreak = 'break-all';
        btn.innerText = 'Show less';
    } else {
        nameEl.style.whiteSpace = 'nowrap'; nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis'; nameEl.style.wordBreak = 'normal';
        btn.innerText = 'Show more';
    }
}

function toggleUpcomingName(id) {
    const nameEl = document.getElementById('uname-' + id);
    const btn    = document.getElementById('utoggle-' + id);
    if (!nameEl || !btn) return;
    if (btn.innerText === 'Show more') {
        nameEl.style.whiteSpace = 'normal'; nameEl.style.overflow = 'visible';
        nameEl.style.textOverflow = 'unset'; nameEl.style.wordBreak = 'break-all';
        btn.innerText = 'Show less';
    } else {
        nameEl.style.whiteSpace = 'nowrap'; nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis'; nameEl.style.wordBreak = 'normal';
        btn.innerText = 'Show more';
    }
}

// ─── SORT ─────────────────────────────────────────────────────────────────────
function toggleSort(col) {
    if (sortColumn === col) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = col;
        sortDirection = 'asc';
    }
    tablePage = 0;
    applyFilters();
}

// ─── TODAY'S TABLE ────────────────────────────────────────────────────────────
function applyFilters() {
    const tbody          = document.getElementById('tableBody');
    const searchTerm     = searchInput.value.toLowerCase();
    const selectedStatus = statusFilter.value;

    let filtered = allSessions.filter(item => {
        const matchesDate   = item.date === selectedDateStr;
        const matchesMentor = item.mentor.toLowerCase().includes(searchTerm);
        const matchesStatus = selectedStatus === '' || item.status === selectedStatus;
        return matchesDate && matchesMentor && matchesStatus;
    });

    filtered.sort((a, b) => {
        let aVal, bVal;
        if      (sortColumn === 'start')   { aVal = a.start;               bVal = b.start;               }
        else if (sortColumn === 'mentor')  { aVal = a.mentor.toLowerCase(); bVal = b.mentor.toLowerCase(); }
        else if (sortColumn === 'subject') { aVal = a.subject.toLowerCase();bVal = b.subject.toLowerCase();}
        else if (sortColumn === 'status')  { aVal = a.status;               bVal = b.status;               }
        if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
        if (aVal > bVal) return sortDirection === 'asc' ?  1 : -1;
        return 0;
    });

    const total   = filtered.length;
    const maxPage = Math.max(0, Math.ceil(total / TABLE_PER_PAGE) - 1);
    if (tablePage > maxPage) tablePage = 0;

    const start   = tablePage * TABLE_PER_PAGE;
    const visible = filtered.slice(start, start + TABLE_PER_PAGE);

    if (!total) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No sessions for this date.</td></tr>`;
    } else {
        tbody.innerHTML = visible.map(row => `
            <tr class="border-b last:border-0 hover:bg-slate-50 transition">
                <td class="py-4 text-slate-700" style="width:35%">
                    <div class="hover-tooltip" data-full="${row.mentor}" style="max-width:260px;">
                        <div id="name-${row.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:90%;">${row.mentor}</div>
                    </div>
                </td>
                <td class="text-slate-500" style="width:30%;white-space:nowrap;">${formatTimeTo12Hour(row.start)} - ${formatTimeTo12Hour(row.end)}</td>
                <td class="text-slate-600 truncate" style="width:20%">${row.subject}</td>
                <td style="width:20%">
                    <div class="flex items-center justify-center">
                        <span class="${getStatusColor(row.status)} font-bold text-xs px-2.5 py-1 rounded-full capitalize">
                            ${getStatusLabel(row.status)}
                        </span>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Sort header indicators
    ['mentor', 'start', 'subject', 'status'].forEach(col => {
        const el = document.getElementById('sortHead-' + col);
        if (!el) return;
        const icon = el.querySelector('.sort-icon');
        if (sortColumn === col) {
            el.style.color = '#7b1d1d';
            icon.innerHTML = sortDirection === 'asc'
                ? '<i class="fa-solid fa-arrow-up" style="font-size:8px;"></i>'
                : '<i class="fa-solid fa-arrow-down" style="font-size:8px;"></i>';
        } else {
            el.style.color = '#94a3b8';
            icon.innerHTML = '<i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i>';
        }
    });

    document.getElementById('pageIndicator').innerText =
        total ? `Showing ${start + 1}–${Math.min(start + TABLE_PER_PAGE, total)} of ${total}` : 'Showing 0 results';

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    prevBtn.disabled = tablePage === 0;
    nextBtn.disabled = tablePage >= maxPage;
    prevBtn.classList.toggle('opacity-30', tablePage === 0);
    nextBtn.classList.toggle('opacity-30', tablePage >= maxPage);
}

document.getElementById('prevBtn').addEventListener('click', () => { tablePage--; applyFilters(); });
document.getElementById('nextBtn').addEventListener('click', () => { tablePage++; applyFilters(); });
searchInput.addEventListener('input',   () => { tablePage = 0; applyFilters(); });
statusFilter.addEventListener('change', () => { tablePage = 0; applyFilters(); });

// ─── WEEKLY SCHEDULE (grid-based, from mentor dashboard) ─────────────────────
function getCurrentWeekRange() {
    const selected = new Date(selectedDateStr);
    const day  = selected.getDay();
    const diff = selected.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(selected);
    monday.setDate(diff);
    monday.setHours(0, 0, 0, 0);
    const friday = new Date(monday);
    friday.setDate(monday.getDate() + 4);
    return { monday, friday };
}

function updateWeekHeaders() {
    const { monday } = getCurrentWeekRange();
    ['monHead', 'tueHead', 'wedHead', 'thuHead', 'friHead'].forEach((id, i) => {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        document.getElementById(id).innerText = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    });
}

function generateWeeklySchedule() {
    const ALLOWED_STATUSES = ['accepted', 'pending', 'completed'];

    const container = document.getElementById("weeklyScheduleBody");
    container.innerHTML = "";

    const tableEl = container.closest('table');
    if (!tableEl) return;
    const wrapper = tableEl.parentElement;

    let gridWrap = document.getElementById('weeklyGridWrap');
    if (gridWrap) gridWrap.remove();

    const week = getCurrentWeekRange();
    const SLOT_HEIGHT = 28;
    const TIME_COL_W  = 52;

    function timeToMinutes(t) {
        const [h, m] = t.split(":").map(Number);
        return h * 60 + m;
    }

    const weekSessions = allSessions.filter(s => {
        if (!s.date || !s.start || !s.end) return false;
        if (!ALLOWED_STATUSES.includes(s.status)) return false;
        const d = new Date(s.date + "T00:00:00").setHours(0,0,0,0);
        return d >= week.monday.getTime() && d <= week.friday.getTime();
    });

    let startHour = 8, endHour = 18;

    const totalSlots  = (endHour - startHour) * 2;
    const totalHeight = totalSlots * SLOT_HEIGHT;

    const fmtHour = h => {
        const ampm = h >= 12 ? 'PM' : 'AM';
        const display = h % 12 || 12;
        return `${display}:00 ${ampm}`;
    };
    const rangeEl = document.getElementById('weeklyScheduleRange');
    if (rangeEl) rangeEl.innerText = `${fmtHour(startHour)} – ${fmtHour(endHour)}`;

    const days     = ["Monday","Tuesday","Wednesday","Thursday","Friday"];
    const dayCount = days.length;

    gridWrap = document.createElement('div');
    gridWrap.id = 'weeklyGridWrap';
    gridWrap.style.cssText = `
        position:relative;
        display:grid;
        grid-template-columns:${TIME_COL_W}px repeat(${dayCount},1fr);
        width:100%;
        min-width:480px;
        border:1px solid #c9c9c9;
        border-radius:6px;
        overflow:hidden;
        background:#fff;
        font-size:9px;
    `;

    // Header row — time cell
    const hdrTime = document.createElement('div');
    hdrTime.style.cssText = `background:#f8fafc;border-bottom:1px solid #e5e7eb;border-right:1px solid #e5e7eb;padding:6px 4px;font-size:9px;font-weight:700;color:#94a3b8;text-transform:uppercase;text-align:center;`;
    hdrTime.innerText = 'Time';
    gridWrap.appendChild(hdrTime);

    // Header row — day cells
    days.forEach((day, i) => {
        const d = new Date(week.monday);
        d.setDate(week.monday.getDate() + i);
        const label = d.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric' });
        const hdrDay = document.createElement('div');
        hdrDay.style.cssText = `background:#f8fafc;border-bottom:1px solid #e5e7eb;${i < dayCount-1?'border-right:1px solid #e5e7eb;':''}padding:6px 4px;font-size:9px;font-weight:700;color:#64748b;text-transform:uppercase;text-align:center;`;
        hdrDay.innerText = label;
        gridWrap.appendChild(hdrDay);
        // Keep th elements in sync for updateWeekHeaders compatibility
        const thId = ['monHead','tueHead','wedHead','thuHead','friHead'][i];
        const th = document.getElementById(thId);
        if (th) th.innerText = label;
    });

    // Time label column
    const timeCol = document.createElement('div');
    timeCol.style.cssText = `position:relative;height:${totalHeight}px;border-right:1px solid #e5e7eb;background:#fafafa;`;

    for (let slot = 0; slot < totalSlots; slot++) {
        const totalMins = startHour * 60 + slot * 30;
        const h = Math.floor(totalMins / 60);
        const m = totalMins % 60;
        const displayH = h > 12 ? h - 12 : (h === 0 ? 12 : h);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const label = `${displayH}:${m === 0 ? '00' : '30'} ${ampm}`;

        const tick = document.createElement('div');
        const isHour = slot % 2 === 0;
        tick.style.cssText = `
            position:absolute;
            top:${slot * SLOT_HEIGHT}px;
            left:0;right:0;
            height:${SLOT_HEIGHT}px;
            border-top:1px solid ${isHour ? '#afafaf' : '#d8d8d8'};
            padding:2px 4px;
            color:#94a3b8;
            font-size:8px;
            font-weight:600;
            white-space:nowrap;
            display:flex;
            align-items:flex-start;
        `;
        if (m === 0) tick.innerText = label;
        timeCol.appendChild(tick);
    }
    gridWrap.appendChild(timeCol);

    // Day columns
    const statusLabel = { pending: 'Pending', accepted: 'Accepted', completed: 'Completed' };

    days.forEach((day, di) => {
        const dayCol = document.createElement('div');
        dayCol.style.cssText = `
            position:relative;
            height:${totalHeight}px;
            ${di < dayCount-1 ? 'border-right:1px solid #e5e7eb;' : ''}
            background:#fff;
        `;

        // Grid lines
        for (let slot = 0; slot < totalSlots; slot++) {
            const isHour = slot % 2 === 0;
            const line = document.createElement('div');
            line.style.cssText = `
                position:absolute;
                top:${slot * SLOT_HEIGHT}px;
                left:0;right:0;
                height:${SLOT_HEIGHT}px;
                border-top:1px solid ${isHour ? '#afafaf' : '#d8d8d8'};
                pointer-events:none;
            `;
            dayCol.appendChild(line);
        }

        // Sessions for this day
        const daySessions = weekSessions.filter(s => {
            const date    = new Date(s.date + "T00:00:00");
            const dayName = date.toLocaleDateString('en-US', { weekday:'long' });
            return dayName === day;
        });

        daySessions.forEach(s => {
            const sStart   = timeToMinutes(s.start);
            const sEnd     = timeToMinutes(s.end);
            const topPx    = ((sStart - startHour * 60) / 30) * SLOT_HEIGHT;
            const heightPx = Math.max(((sEnd - sStart) / 30) * SLOT_HEIGHT - 2, 16);

            const sk  = (s.status || 'pending').toLowerCase().replace(/[^a-z_]/g, '');
            const lbl = statusLabel[sk] || s.status;

            const block = document.createElement('div');
            block.className = `schedule-block status-${sk}`;
            // Tooltip shows mentor name instead of student name
            block.title = `${s.mentor}\n${s.subject} • ${formatTimeTo12Hour(s.start)}–${formatTimeTo12Hour(s.end)}`;
            block.style.cssText = `
                position:absolute;
                top:${topPx + 1}px;
                left:2px;
                right:2px;
                height:${heightPx}px;
                overflow:hidden;
                border-radius:4px;
                padding:2px 4px;
                display:flex;
                flex-direction:column;
                justify-content:flex-start;
                z-index:2;
                cursor:default;
                margin-bottom:0;
            `;

            const showTime  = heightPx >= 28;
            const showLabel = heightPx >= 42;

            block.innerHTML = `
                <div style="font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.3;">${s.subject}</div>
                ${showTime  ? `<div style="opacity:0.75;line-height:1.3;">${formatTimeTo12Hour(s.start)}–${formatTimeTo12Hour(s.end)}</div>` : ''}
                ${showLabel ? `<div style="font-size:8px;font-weight:700;opacity:0.65;text-transform:uppercase;letter-spacing:0.04em;">${lbl}</div>` : ''}
            `;

            dayCol.appendChild(block);
        });

        gridWrap.appendChild(dayCol);
    });

    tableEl.style.display = 'none';
    wrapper.appendChild(gridWrap);
}

// ─── CALENDAR ─────────────────────────────────────────────────────────────────
function hasSessionOnDate(dateStr) {
    return allSessions.some(s =>
        s.date === dateStr &&
        s.status === 'accepted' &&
        s.date >= todayStr
    );
}

function renderCalendar() {
    const grid      = document.getElementById('calendarGrid');
    const monthDisp = document.getElementById('monthDisplay');
    grid.innerHTML  = '';

    const localToday = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));

    monthDisp.innerText = viewDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });

    const lastDay  = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
    const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

    for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

    for (let i = 1; i <= lastDay; i++) {
        const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth()+1).padStart(2,'0')}-${String(i).padStart(2,'0')}`;
        const dateObj = new Date(viewDate.getFullYear(), viewDate.getMonth(), i);
        const dayEl   = document.createElement('div');
        dayEl.className = 'cal-day';

        if (dateObj < localToday) dayEl.style.color = '#9ca3af';
        if (dateStr === todayStr)          dayEl.classList.add('cal-today');
        if (dateStr === selectedDateStr)   dayEl.classList.add('cal-selected');

        const hasSession = hasSessionOnDate(dateStr);
        dayEl.innerHTML = `<span style="position:relative;z-index:1;">${i}</span>${hasSession ? `<div class="notif-dot"></div>` : ''}`;

        dayEl.onclick = () => {
            selectedDateStr = dateStr;
            tablePage = 0;
            refreshSchedules();
            updateWeekHeaders();
            renderCalendar();
            updateTableDate();
        };

        grid.appendChild(dayEl);
    }
}

function changeMonth(dir) {
    viewDate.setMonth(viewDate.getMonth() + dir);
    renderCalendar();
}

function updateTableDate() {
    const date = new Date(selectedDateStr);
    document.getElementById('tableSubtitle').innerText = date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

// ─── MY UPCOMING SESSIONS PANEL ───────────────────────────────────────────────
function renderUpcomingSessions() {
    const container  = document.getElementById('upcomingSessionsList');
    const badge      = document.getElementById('upcomingBadge');
    const pagination = document.getElementById('upcomingPagination');
    const pageInfo   = document.getElementById('upcomingPageInfo');
    const prevBtn    = document.getElementById('upcomingPrevBtn');
    const nextBtn    = document.getElementById('upcomingNextBtn');

    const upcoming = allSessions
        .filter(s => s.status === 'accepted' && s.date >= todayStr)
        .sort((a, b) => {
            if (a.date !== b.date) return a.date > b.date ? 1 : -1;
            return a.start > b.start ? 1 : -1;
        });

    const total = upcoming.length;
    badge.innerText = `${total} ${total === 1 ? 'Session' : 'Sessions'}`;

    if (!total) {
        container.innerHTML = `<p class="text-xs text-gray-400 italic">No upcoming sessions. <a href="{{ route('student.bookings') }}" class="text-red-700 font-semibold">Book one now →</a></p>`;
        pagination.classList.add('hidden');
        return;
    }

    const maxPage = Math.ceil(total / UPCOMING_PER_PAGE) - 1;
    if (upcomingPage > maxPage) upcomingPage = maxPage;
    if (upcomingPage < 0) upcomingPage = 0;

    const start   = upcomingPage * UPCOMING_PER_PAGE;
    const visible = upcoming.slice(start, start + UPCOMING_PER_PAGE);
    const hasPrev = upcomingPage > 0;
    const hasNext = upcomingPage < maxPage;

    container.innerHTML = visible.map(s => `
        <div class="flex items-center justify-between group">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                    ${s.mentor.slice(0, 2).toUpperCase()}
                </div>
                <div>
                    <div style="max-width:180px;">
                        <div id="uname-${s.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;font-size:11px;font-weight:700;color:#1e293b;" title="${s.mentor}">${s.mentor}</div>
                        <button onclick="toggleUpcomingName('${s.id}')" id="utoggle-${s.id}" style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:1px;background:none;border:none;cursor:pointer;padding:0;display:none;">Show more</button>
                    </div>
                    <p class="text-[9px] text-gray-400 font-medium">${s.subject} • ${formatTimeTo12Hour(s.start)} – ${formatTimeTo12Hour(s.end)}</p>
                    <p class="text-[9px] text-gray-400">${new Date(s.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                </div>
            </div>
            <span class="${getStatusColor(s.status)} font-bold text-xs px-2.5 py-1 rounded-full capitalize flex-shrink-0">
                ${getStatusLabel(s.status)}
            </span>
        </div>
    `).join('');

    visible.forEach(s => {
        const nameEl   = document.getElementById('uname-' + s.id);
        const toggleEl = document.getElementById('utoggle-' + s.id);
        if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) {
            toggleEl.style.display = 'block';
        }
    });

    if (total > UPCOMING_PER_PAGE) {
        pagination.classList.remove('hidden');
        pageInfo.innerText = `${start + 1}–${Math.min(start + UPCOMING_PER_PAGE, total)} of ${total}`;
        prevBtn.disabled = !hasPrev;
        prevBtn.classList.toggle('opacity-30', !hasPrev);
        prevBtn.classList.toggle('cursor-not-allowed', !hasPrev);
        nextBtn.disabled = !hasNext;
        nextBtn.classList.toggle('opacity-30', !hasNext);
        nextBtn.classList.toggle('cursor-not-allowed', !hasNext);
    } else {
        pagination.classList.add('hidden');
    }
}

document.getElementById('upcomingPrevBtn').addEventListener('click', () => { upcomingPage--; renderUpcomingSessions(); });
document.getElementById('upcomingNextBtn').addEventListener('click', () => { upcomingPage++; renderUpcomingSessions(); });

// ─── REFRESH ALL ──────────────────────────────────────────────────────────────
function refreshSchedules() {
    applyFilters();
    generateWeeklySchedule();
    updateWeekHeaders();
    renderUpcomingSessions();
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
function initDashboard() {
    renderCalendar();
    refreshSchedules();
    updateTableDate();
    updateClock();
}

window.addEventListener('load', initDashboard);
document.addEventListener('livewire:navigated', initDashboard);
</script>
</body>
