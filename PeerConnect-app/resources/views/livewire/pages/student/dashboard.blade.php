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
use Illuminate\Support\Facades\Cache;
use function Livewire\Volt\{layout, state, mount, action, computed, updated};

// ─── STATE ───────────────────────────────────────────────────────────────────

state([
    // Booking form
    'mentor_id'      => '',
    'subject_id'     => '',
    'topic'          => '',
    'tutorialMode_id'=> '',
    'date'           => '',
    'schedule_start' => '',
    'schedule_end'   => '',
    'successMessage' => false,
    'sessions'       => [],
    'globalSearchTerm' => '',

    // Student profile form
    'toggleProfileOpen' => true,
    'profileSaved'      => false,
    'student_num'       => '',
    'college_id'        => '',
    'degreeProgram_id'  => '',
    'yearLevel_id'      => '',
]);

// ─── MOUNT ───────────────────────────────────────────────────────────────────

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    // Single query — reused below instead of querying StudentProfiles twice
    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    if (!$profile) return;

    // Pre-fill profile form fields
    $this->student_num      = $profile->student_num;
    $this->college_id       = $profile->college_id;
    $this->degreeProgram_id = $profile->degreeProgram_id;
    $this->yearLevel_id     = $profile->yearLevel_id;
    $this->toggleProfileOpen = false;

    // Load sessions — single query with eager-loaded relationships
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
                'start'   => $start->format('H:i'),
                'end'     => $end->format('H:i'),
                'status'  => $b->booking_status,
            ];
        })
        ->values()
        ->toArray();
});

// ─── COMPUTED: SEARCH INDEX ──────────────────────────────────────────────────
// Cached for 5 minutes per user — this was the biggest performance killer.
// Previously: N+1 on mentors (studentProfile->yearLevel, ->degreeProgram per mentor),
//             plus 3 separate uncached DB queries every page load.

$searchIndex = computed(function () {
    $userId = auth()->id();

    return Cache::remember("search_index_student_{$userId}", now()->addMinutes(5), function () use ($userId) {
        $index = [];

        // ── Mentors ──
        // Eager-load everything needed — no more N+1 per mentor
        $mentors = \App\Models\User::where('user_roles', 'mentor')
            ->with([
                'studentProfile.yearLevel',
                'studentProfile.degreeProgram',
            ])
            ->get();

        foreach ($mentors as $m) {
            $year   = optional(optional($m->studentProfile)->yearLevel)->name   ?? '';
            $deprog = optional(optional($m->studentProfile)->degreeProgram)->name ?? '';

            $index[] = [
                'group'        => 'Mentors',
                'label'        => $m->lastName . ', ' . $m->firstName,
                'detail'       => $m->email . ' — ' . $year . ' ' . $deprog,
                'icon'         => 'fa-chalkboard-user',
                'bg'           => '#dbeafe',
                'color'        => '#1e40af',
                'url'          => route('student.mentors'),
                'searchString' => strtolower(implode(' ', [$m->firstName, $m->lastName, $m->email, $year, $deprog])),
            ];
        }

        // ── Subjects ──
        // Load all mentor-subject relationships in one query instead of filtering per subject
        $allMentorProfiles = \App\Models\MentorProfiles::with(['user', 'subjects'])->get()
            ->sortBy(fn($mp) => $mp->user->lastName);

        // Build a subject → mentor names map once, not inside foreach
        $subjectMentorMap = [];
        foreach ($allMentorProfiles as $mp) {
            foreach ($mp->subjects as $s) {
                $subjectMentorMap[$s->id][] = $mp->user->lastName;
            }
        }

        $subjects = \App\Models\Subjects::all();
        foreach ($subjects as $s) {
            $mentorNames = isset($subjectMentorMap[$s->id])
                ? implode(', ', $subjectMentorMap[$s->id])
                : '';

            $index[] = [
                'group'        => 'Courses',
                'label'        => strtoupper($s->code),
                'detail'       => $mentorNames ? 'Mentors: ' . $mentorNames : 'No mentors assigned yet',
                'icon'         => 'fa-book-open',
                'bg'           => '#fef3c7',
                'color'        => '#92400e',
                'url'          => route('student.mentors'),
                'searchString' => strtolower($s->code . ' ' . $mentorNames),
            ];
        }

        // ── Recent Sessions ──
        $studentProfileId = StudentProfiles::where('user_id', $userId)->value('id');

        $bookings = \App\Models\Bookings::with(['mentor.user', 'subject'])
            ->where('student_id', $studentProfileId)
            ->latest()
            ->take(50)
            ->get();

        foreach ($bookings as $b) {
            $mentorName  = $b->mentor
                ? ($b->mentor->user->lastName . ', ' . $b->mentor->user->firstName)
                : 'Unknown Mentor';
            $sessionDate = \Carbon\Carbon::parse($b->date)->format('F j, Y');

            $index[] = [
                'group'        => 'Sessions',
                'label'        => $b->topic ?: 'Tutorial Session',
                'detail'       => implode(' — ', [
                    $sessionDate,
                    'Subject: ' . ($b->subject->code ?? 'N/A'),
                    'Mentor: ' . $mentorName,
                    'Status: ' . ucfirst($b->booking_status),
                ]),
                'icon'         => 'fa-calendar-days',
                'bg'           => '#d1fae5',
                'color'        => '#065f46',
                'url'          => route('student.history'),
                'searchString' => strtolower(implode(' ', [
                    $b->topic,
                    $mentorName,
                    $b->booking_status,
                    $b->subject->code ?? '',
                    $sessionDate,
                ])),
            ];
        }

        return $index;
    });
});

// ─── COMPUTED: BOOKING FORM DATA ─────────────────────────────────────────────
// These are short-lived lookups — cached briefly to avoid repeated hits
// if Livewire re-renders (e.g. on validation errors).

$mentors = computed(function () {
    return Cache::remember('mentor_list', now()->addMinutes(10), function () {
        return MentorProfiles::with('user')
            ->get()
            ->sortBy(fn($mp) => $mp->user->lastName)
            ->values()
            ->map(fn($mp) => [
                'id'         => $mp->user->id,
                'profile_id' => $mp->id,
                'name'       => strtoupper($mp->user->lastName) . ', ' . $mp->user->firstName,
            ])
            ->toArray();
    });
});

$mentorAvailabilities = computed(function () {
    return Cache::remember('mentor_availabilities', now()->addMinutes(10), function () {
        return MentorAvailabilities::all()
            ->map(fn($a) => [
                'mentorProfile_id' => $a->mentor_id,
                'day_of_week'      => $a->day_of_week,
                'start_time'       => $a->start_time,
                'end_time'         => $a->end_time,
            ])
            ->values()
            ->toArray();
    });
});

$mentorSubjects = computed(function () {
    return Cache::remember('mentor_subjects', now()->addMinutes(10), function () {
        return MentorSubjects::all()
            ->map(fn($s) => [
                'mentorProfile_id' => $s->mentor_id,
                'subject_id'       => $s->subject_id,
            ])
            ->values()
            ->toArray();
    });
});

$subjects = computed(function () {
    return Cache::remember('subjects_list', now()->addMinutes(10), function () {
        return Subjects::orderBy('code')->get();
    });
});

$tutorialModes = computed(function () {
    return Cache::remember('tutorial_modes', now()->addMinutes(60), function () {
        return TutorialMode::orderBy('id')->get();
    });
});

// ─── COMPUTED: STUDENT BOOKINGS (sidebar/widget) ──────────────────────────────

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return collect();

    return Bookings::with(['mentor.user', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()
        ->take(3)
        ->get();
});

// ─── COMPUTED: PROFILE DROPDOWNS ─────────────────────────────────────────────

$colleges = computed(function () {
    return Cache::remember('colleges_list', now()->addMinutes(60), function () {
        return Colleges::orderBy('name')->get();
    });
});

$degreePrograms = computed(function () {
    return Cache::remember('degree_programs_list', now()->addMinutes(60), function () {
        return DegreePrograms::orderBy('name')->get();
    });
});

$yearLevels = computed(function () {
    return Cache::remember('year_levels_list', now()->addMinutes(60), function () {
        return YearLevels::orderBy('name')->get();
    });
});

// ─── ACTIONS ─────────────────────────────────────────────────────────────────

$toggleProfile = action(function () {
    $this->toggleProfileOpen = !$this->toggleProfileOpen;
});

$saveProfile = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $validated = $this->validate([
        'student_num'      => ['required', 'string', 'max:10', 'regex:/-/'],
        'college_id'       => ['required', 'exists:colleges,id'],
        'degreeProgram_id' => ['required', 'exists:degree_programs,id'],
        'yearLevel_id'     => ['required', 'exists:year_levels,id'],
    ], messages: [
        'student_num.regex' => 'The student number must include a hyphen (-).',
    ], attributes: [
        'student_num'      => 'student number',
        'college_id'       => 'college',
        'degreeProgram_id' => 'degree program',
        'yearLevel_id'     => 'year level',
    ]);

    StudentProfiles::updateOrCreate(
        ['user_id' => auth()->id()],
        $validated
    );

    $this->profileSaved      = true;
    $this->toggleProfileOpen = false;

    // Bust the search index cache so new profile data shows immediately
    Cache::forget('search_index_student_' . auth()->id());

    $this->dispatch('profile-updated');
});

$submitBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
    abort_if(!auth()->user()->studentProfile, 422);

    $validated = $this->validate([
        'mentor_id'      => ['required', 'exists:mentor_profiles,id'],
        'subject_id'     => ['required', 'exists:subjects,id'],
        'topic'          => ['required', 'string', 'max:255'],
        'tutorialMode_id'=> ['required', 'exists:tutorial_modes,id'],
        'date'           => ['required', 'date', 'after:today', function ($attribute, $value, $fail) {
            if (\Carbon\Carbon::parse($value)->format('l') === 'Sunday') {
                $fail('The session cannot be on a Sunday. Please select another date.');
            }
        }],
        'schedule_start' => ['required', 'date_format:H:i'],
        'schedule_end'   => ['required', 'date_format:H:i', 'after:schedule_start'],
    ], attributes: [
        'mentor_id'      => 'mentor',
        'subject_id'     => 'subject',
        'topic'          => 'topic',
        'tutorialMode_id'=> 'mode of tutorial',
        'date'           => 'date',
        'schedule_start' => 'start time',
        'schedule_end'   => 'end time',
    ]);

    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    Bookings::create([
        ...$validated,
        'student_id'     => $profile->id,
        'booking_status' => 'pending',
    ]);

    $this->reset(['mentor_id', 'subject_id', 'topic', 'tutorialMode_id', 'date', 'schedule_start', 'schedule_end']);

    // Bust the per-user search index so the new booking appears
    Cache::forget('search_index_student_' . auth()->id());

    $this->successMessage = true;
});

$dismissSuccessMessage = action(function () {
    $this->successMessage = false;
});

?>

<div>
    {{-- ── GLOBAL SEARCH ── --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative animate-fade-up z-50"
         x-data="{
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
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2
                      text-gray-300 text-xs"></i>
            <input type="text"
                   x-model="query"
                   @focus="open = true"
                   @keydown.escape.window="open = false; query = ''"
                   placeholder="Search mentors, courses or recent sessions..."
                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700
                          placeholder-gray-400 border border-gray-200 rounded-lg bg-white
                          outline-none focus:ring-1 focus:border-up-maroon
                          focus:ring-up-maroon h-[34px] transition-shadow">
        </div>

        {{-- Results dropdown --}}
        <div x-show="open && query.length >= 1"
             x-cloak
             x-transition
             class="absolute left-0 right-0 bg-white rounded-xl shadow-xl border
                    border-gray-100 overflow-y-auto"
             style="top: calc(100% + 6px); max-height: 420px; z-index: 20;">

            <template x-if="Object.keys(filteredResults).length === 0">
                <div style="padding:20px; text-align:center; font-size:13px;
                            color:#9ca3af; font-style:italic;">
                    No matches found for "<strong x-text="query"></strong>"
                </div>
            </template>

            <template x-for="(items, group) in filteredResults" :key="group">
                <div>
                    <div x-text="group"
                         style="padding:10px 14px; font-size:10px; font-weight:900;
                                color:#000; text-transform:uppercase;
                                letter-spacing:.05em; background:#f0f0f0;">
                    </div>

                    <template x-for="item in items" :key="item.label + item.detail">
                        <a :href="item.url"
                           class="block group"
                           style="display:flex; align-items:center; gap:12px;
                                  padding:10px 14px; cursor:pointer;
                                  border-bottom:1px solid #f1f5f9;
                                  transition:background .15s; text-decoration:none;"
                           onmouseover="this.style.background='#f4f5f7'"
                           onmouseout="this.style.background='transparent'">

                            <span :style="`font-size:11px; width:28px; height:28px;
                                           display:flex; align-items:center;
                                           justify-content:center; border-radius:6px;
                                           flex-shrink:0; background:${item.bg};
                                           color:${item.color};`">
                                <i class="fa-solid" :class="item.icon"></i>
                            </span>

                            <div style="flex:1; min-width:0;">
                                <div style="font-size:13px; font-weight:700; color:#1e293b;
                                            white-space:nowrap; overflow:hidden;
                                            text-overflow:ellipsis;"
                                     x-text="item.label">
                                </div>
                                <div style="font-size:11px; font-weight:500; color:#64748b;
                                            white-space:nowrap; overflow:hidden;
                                            text-overflow:ellipsis; margin-top:2px;"
                                     x-text="item.detail">
                                </div>
                            </div>

                            <i class="fa-solid fa-arrow-up-right-from-square
                                      opacity-0 group-hover:opacity-100 transition-opacity"
                               style="font-size:10px; color:#cbd5e1; flex-shrink:0;">
                            </i>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ── THREE-COLUMN GRID ── --}}
    <div class="grid grid-cols-3 gap-8">

        {{-- LEFT COLUMN --}}
        <div class="col-span-2 space-y-8 animate-fade-up [animation-delay:150ms]">

            {{-- ── Today's Schedule Table ── --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col">

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800" id="tableTitle">
                            Today's Schedule
                        </h2>
                        <p class="text-s text-gray-500" id="tableSubtitle"></p>
                    </div>

                    <div class="flex gap-2">
                        <div class="relative w-48">
                            <i class="fa-solid fa-search absolute left-3 top-1/2
                                      -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text"
                                   id="liveSearchInput"
                                   placeholder="Search mentors..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium
                                          text-slate-700 placeholder-gray-400 border
                                          border-gray-200 rounded-lg bg-white outline-none
                                          focus:ring-1 focus:border-up-maroon
                                          focus:ring-up-maroon w-56 h-[34px] transition-shadow">
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
                            <button id="sortHead-mentor"
                                    onclick="toggleSort('mentor')"
                                    class="flex items-center gap-1 font-semibold uppercase
                                               hover:text-red-800 transition-colors"
                                    style="color:#94a3b8;">
                                Mentor
                                <span class="sort-icon">
                                        <i class="fa-solid fa-arrow-up-arrow-down"
                                           style="font-size:8px; opacity:0.4;"></i>
                                    </span>
                            </button>
                        </th>

                        <th class="pb-3 text-[10px] tracking-wider" style="width:30%">
                            <button id="sortHead-start"
                                    onclick="toggleSort('start')"
                                    class="flex items-center gap-1 font-semibold uppercase
                                               hover:text-red-800 transition-colors"
                                    style="color:#7b1d1d;">
                                Time
                                <span class="sort-icon">
                                        <i class="fa-solid fa-arrow-up" style="font-size:8px;"></i>
                                    </span>
                            </button>
                        </th>

                        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
                            <button id="sortHead-subject"
                                    onclick="toggleSort('subject')"
                                    class="flex items-center gap-1 font-semibold uppercase
                                               hover:text-red-800 transition-colors"
                                    style="color:#94a3b8;">
                                Subject
                                <span class="sort-icon">
                                        <i class="fa-solid fa-arrow-up-arrow-down"
                                           style="font-size:8px; opacity:0.4;"></i>
                                    </span>
                            </button>
                        </th>

                        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
                            <button id="sortHead-status"
                                    onclick="toggleSort('status')"
                                    class="flex items-center justify-center gap-1
                                               font-semibold uppercase hover:text-red-800
                                               transition-colors w-full"
                                    style="color:#94a3b8;">
                                Status
                                <span class="sort-icon">
                                        <i class="fa-solid fa-arrow-up-arrow-down"
                                           style="font-size:8px; opacity:0.4;"></i>
                                    </span>
                            </button>
                        </th>
                    </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>

                <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                    <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">
                        Showing 0 results
                    </div>
                    <div class="flex gap-2">
                        <button id="prevBtn" class="pagination-btn">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button id="nextBtn" class="pagination-btn">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>

            </div>
            {{-- end Today's Schedule Table --}}


            {{-- ── Stats Row ── --}}
            <div class="grid grid-cols-3 gap-4 animate-fade-up [animation-delay:200ms]">

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100
                            flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center
                                justify-center flex-shrink-0">
                        <i class="fa-solid fa-clock text-blue-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                            Total Session Hours
                        </p>
                        <p class="text-2xl font-bold text-slate-800 leading-tight" id="statTotalHours">
                            0h
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5" id="statTotalHoursLabel">
                            all time
                        </p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100
                            flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center
                                justify-center flex-shrink-0">
                        <i class="fa-solid fa-circle-check text-green-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                            Completed
                        </p>
                        <p class="text-2xl font-bold text-slate-800 leading-tight" id="statCompleted">
                            0
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5">sessions</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100
                            flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-yellow-50 flex items-center
                                justify-center flex-shrink-0">
                        <i class="fa-solid fa-calendar-days text-yellow-500 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                            Upcoming
                        </p>
                        <p class="text-2xl font-bold text-slate-800 leading-tight" id="statUpcoming">
                            0
                        </p>
                        <p class="text-[10px] text-gray-400 mt-0.5">accepted sessions</p>
                    </div>
                </div>

            </div>
            {{-- end Stats Row --}}


            {{-- ── Weekly Schedule ── --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 animate-fade-up [animation-delay:250ms]">

                <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-bold text-slate-800">Weekly Schedule</h2>
                        <span class="text-[10px] text-gray-400 bg-gray-50 border border-gray-100
                                     px-2 py-0.5 rounded-full"
                              id="weeklyScheduleRange">
                            8:00 AM – 6:00 PM
                        </span>
                    </div>
                </div>

                <div class="sched-legend mb-4 pb-3 border-b border-gray-50">
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#eab308;"></span>
                        Pending
                    </span>
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#10b981;"></span>
                        Accepted
                    </span>
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#94a3b8;"></span>
                        Completed
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="weekly-table text-xs text-center border"
                           id="weeklyTableEl"
                           style="display:none;">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase tracking-wider">Time</th>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase" id="monHead"></th>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase" id="tueHead"></th>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase" id="wedHead"></th>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase" id="thuHead"></th>
                            <th class="p-2 border text-[10px] font-bold text-gray-500
                                           uppercase" id="friHead"></th>
                        </tr>
                        </thead>
                        <tbody id="weeklyScheduleBody"></tbody>
                    </table>
                </div>

            </div>
            {{-- end Weekly Schedule --}}

        </div>

        {{-- RIGHT COLUMN --}}
        <div class="flex flex-col gap-6 animate-fade-up [animation-delay:150ms]">

            {{-- Calendar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <div class="bg-slate-900 px-4 py-3 flex items-center justify-between">
                    <div id="liveDate"
                         class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">
                    </div>
                    <div id="liveClock"
                         class="text-sm font-mono font-bold text-white tracking-widest">
                        00:00:00
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <button onclick="changeMonth(-1)"
                                class="w-6 h-6 flex items-center justify-center rounded-md
                                       hover:bg-gray-100 text-gray-400 hover:text-slate-700
                                       transition">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>

                        <span id="monthDisplay"
                              class="text-sm font-bold text-slate-800 text-center min-w-[120px]">
                        </span>

                        <button onclick="changeMonth(1)"
                                class="w-6 h-6 flex items-center justify-center rounded-md
                                       hover:bg-gray-100 text-gray-400 hover:text-slate-700
                                       transition">
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

            {{-- Upcoming Sessions --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 animate-fade-up [animation-delay:200ms]">

                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800 text-sm tracking-tight">
                        My Upcoming Sessions
                    </h3>
                    <span id="upcomingBadge"
                          class="bg-blue-100 text-blue-700 text-[10px] font-bold
                                 px-2 py-0.5 rounded-full">
                    </span>
                </div>

                <div id="upcomingSessionsList" class="flex flex-col gap-4"></div>

                <div id="upcomingPagination"
                     class="hidden mt-3 flex items-center justify-between px-1
                            border-t border-gray-50 pt-3">
                    <span id="upcomingPageInfo" class="text-[10px] text-gray-400"></span>
                    <div class="flex gap-1">
                        <button id="upcomingPrevBtn"
                                class="pagination-btn opacity-30 cursor-not-allowed"
                                disabled>
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <button id="upcomingNextBtn" class="pagination-btn">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Status toast --}}
    <div id="statusToast">
        <span id="statusToastMsg">Loading...</span>
    </div>

</div>

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
const searchInput         = document.getElementById('liveSearchInput');
const statusFilter        = document.getElementById('statusFilter');


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
    renderStatCards();
}

window.addEventListener('load', initDashboard);
document.addEventListener('livewire:navigated', initDashboard);
</script>
