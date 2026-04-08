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

    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    if ($profile) {
        $this->student_num       = $profile->student_num;
        $this->college_id        = $profile->college_id;
        $this->degreeProgram_id  = $profile->degreeProgram_id;
        $this->yearLevel_id      = $profile->yearLevel_id;
        $this->toggleProfileOpen = false;

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
                    'date'    => $b->date ? \Carbon\Carbon::parse($b->date)->format('Y-m-d') : null,
                    'start'   => $start->format('H:i'),
                    'end'     => $end->format('H:i'),
                    'status'  => $b->booking_status,
                ];
            })
            ->values()
            ->toArray();
    }
});

state([
    'mentor_id'      => '',
    'subject_id'     => '',
    'topic'          => '',
    'tutorialMode_id' => '',
    'date'           => '',
    'schedule_start' => '',
    'schedule_end'   => '',
    'successMessage' => false,
    'sessions'       => [],
]);

$mentors = computed(function () {
    return MentorProfiles::with('user')->get()
        ->sortBy(fn($mp) => $mp->user->lastName)
        ->values()
        ->map(fn($mp) => [
            'id'         => $mp->user->id,
            'profile_id' => $mp->id,
            'name'       => strtoupper($mp->user->lastName) . ', ' . $mp->user->firstName,
        ])->toArray();
});

$mentorAvailabilities = computed(function () {
    return MentorAvailabilities::all()->map(fn($a) => [
        'mentorProfile_id' => $a->mentor_id,
        'day_of_week'      => $a->day_of_week,
        'start_time'       => $a->start_time,
        'end_time'         => $a->end_time,
    ])->values()->toArray();
});

$mentorSubjects = computed(function () {
    return MentorSubjects::all()->map(fn($s) => [
        'mentorProfile_id' => $s->mentor_id,
        'subject_id'       => $s->subject_id,
    ])->values()->toArray();
});

$subjects      = computed(fn() => Subjects::orderBy('code')->get());
$tutorialModes = computed(fn() => TutorialMode::orderBy('id')->get());

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return collect();
    return Bookings::with(['mentor', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()->take(3)->get();
});

state([
    'toggleProfileOpen' => true,
    'profileSaved'      => false,
    'student_num'       => '',
    'college_id'        => '',
    'degreeProgram_id'  => '',
    'yearLevel_id'      => '',
]);

$colleges       = computed(fn() => Colleges::orderBy('name')->get());
$degreePrograms = computed(fn() => DegreePrograms::orderBy('name')->get());
$yearLevels     = computed(fn() => YearLevels::orderBy('name')->get());

$toggleProfile = action(fn() => $this->toggleProfileOpen = !$this->toggleProfileOpen);

$saveProfile = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $this->validate([
        'student_num'      => ['required', 'string', 'max:10', 'regex:/-/'],
        'college_id'       => ['required', 'exists:colleges,id'],
        'degreeProgram_id' => ['required', 'exists:degree_programs,id'],
        'yearLevel_id'     => ['required', 'exists:year_levels,id'],
    ], messages: [
        'student_num.regex' => 'The student number must include a hyphen (-)',
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
    $this->dispatch('profile-updated');
});

$submitBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
    abort_if(!auth()->user()->studentProfile, 422);

    $validated = $this->validate([
        'mentor_id'       => ['required', 'exists:mentor_profiles,id'],
        'subject_id'      => ['required', 'exists:subjects,id'],
        'topic'           => ['required', 'string', 'max:255'],
        'tutorialMode_id' => ['required', 'exists:tutorial_modes,id'],
        'date'            => ['required', 'date', 'after:today', function ($attr, $val, $fail) {
            if (\Carbon\Carbon::parse($val)->isSunday()) {
                $fail('The session cannot be on a Sunday. Please select another date.');
            }
        }],
        'schedule_start'  => ['required', 'date_format:H:i'],
        'schedule_end'    => ['required', 'date_format:H:i', 'after:schedule_start'],
    ], attributes: [
        'mentor_id'       => 'mentor',
        'subject_id'      => 'subject',
        'topic'           => 'topic',
        'tutorialMode_id' => 'mode of tutorial',
        'date'            => 'date',
        'schedule_start'  => 'start time',
        'schedule_end'    => 'end time',
    ]);

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    Bookings::create([
        ...$validated,
        'student_id'     => $profile->id,
        'booking_status' => 'pending',
    ]);

    $this->reset(['mentor_id', 'subject_id', 'topic', 'tutorialMode_id', 'date', 'schedule_start', 'schedule_end']);
    $this->successMessage = true;
});

$dismissSuccessMessage = action(fn() => $this->successMessage = false);

?>

<style>
    .cal-header-day { font-size: 11px; font-weight: 800; color: #94a3b8; text-align: center; padding-bottom: 10px; text-transform: uppercase; }
    .cal-day { aspect-ratio: 1/1; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 13px; font-weight: 500; }
    .cal-today { background: #fee2e2 !important; color: #7b1d1d !important; font-weight: 800; }
    .cal-selected { border: 2px solid #7b1d1d; background: #f8fafc; }
    .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
    .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: #7b1d1d; border-color: #7b1d1d; }
    .table-filter-select { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
    .weekly-table { table-layout: fixed; width: 100%; }
    .weekly-table th, .weekly-table td { width: 16%; }
    .schedule-block { font-size: 9px; line-height: 1.2; padding: 2px 4px; margin-bottom: 2px; border-radius: 4px; background: #d1fae5; color: #065f46; }
    .notif-dot { width: 6px; height: 6px; background: #3b82f6; border-radius: 50%; }
    #statusToast {
        position: fixed; bottom: 24px; right: 24px; z-index: 9999;
        display: flex; align-items: center; gap: 10px; padding: 10px 16px;
        border-radius: 10px; font-size: 12px; font-weight: 600; color: white;
        background: #1e293b; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        opacity: 0; transform: translateY(12px); transition: opacity 0.2s, transform 0.2s;
        pointer-events: none; min-width: 200px;
    }
    #statusToast.show { opacity: 1; transform: translateY(0); }
</style>

<div>

    {{-- GLOBAL SEARCH --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input type="text" id="globalSearchInput"
                   placeholder="Search ALL sessions (mentor, subject, date, status)..."
                   class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
        </div>
        <div id="globalSearchResults"
             class="absolute left-0 right-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-72 overflow-y-auto z-50 hidden">
        </div>
    </div>

    <div class="grid grid-cols-3 gap-8">

        {{-- LEFT COLUMN --}}
        <div class="col-span-2 space-y-8">

            {{-- TODAY'S SESSIONS TABLE --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 min-h-[460px] flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
                        <p class="text-s text-gray-500" id="tableSubtitle"></p>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative w-48">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" id="liveSearchInput" placeholder="Search mentors..."
                                   class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                        </div>
                        <select id="statusFilter" class="table-filter-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="flex-grow">
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="text-gray-400 border-b">
                        <tr>
                            <th class="pb-3 text-[10px] tracking-wider" style="width:35%">
                                <button id="sortHead-mentor" onclick="toggleSort('mentor')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                                    Mentor <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                                </button>
                            </th>
                            <th class="pb-3 text-[10px] tracking-wider" style="width:25%">
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
                                <button id="sortHead-status" onclick="toggleSort('status')" class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors" style="color:#94a3b8;">
                                    Status <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                                </button>
                            </th>
                        </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-50 flex items-center justify-between">
                    <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">Showing 0 results</div>
                    <div class="flex gap-2">
                        <button id="prevBtn" class="pagination-btn"><i class="fa-solid fa-chevron-left"></i></button>
                        <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            {{-- WEEKLY SCHEDULE --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800">Weekly Schedule</h2>
                    <span class="text-xs text-gray-400" id="weeklyScheduleRange">8:00 AM – 6:00 PM</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="weekly-table text-xs text-center border">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="p-2 border">Time</th>
                            <th class="p-2 border" id="monHead"></th>
                            <th class="p-2 border" id="tueHead"></th>
                            <th class="p-2 border" id="wedHead"></th>
                            <th class="p-2 border" id="thuHead"></th>
                            <th class="p-2 border" id="friHead"></th>
                        </tr>
                        </thead>
                        <tbody id="weeklyScheduleBody"></tbody>
                    </table>
                </div>
            </div>

        </div>{{-- end col-span-2 --}}

        {{-- RIGHT COLUMN --}}
        <div class="flex flex-col gap-6">

            {{-- CALENDAR + CLOCK --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex gap-4">
                        <button onclick="changeMonth(-1)" class="text-gray-400 hover:text-red-800"><i class="fa-solid fa-chevron-left text-xs"></i></button>
                        <span id="monthDisplay" class="text-sm font-bold w-24 text-center"></span>
                        <button onclick="changeMonth(1)" class="text-gray-400 hover:text-red-800"><i class="fa-solid fa-chevron-right text-xs"></i></button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <div class="cal-header-day">S</div><div class="cal-header-day">M</div><div class="cal-header-day">T</div>
                    <div class="cal-header-day">W</div><div class="cal-header-day">T</div><div class="cal-header-day">F</div><div class="cal-header-day">S</div>
                </div>
                <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>

                <div class="mt-6 pt-6 border-t border-gray-50">
                    <div class="bg-slate-900 rounded-xl p-4 shadow-inner">
                        <div id="liveClock" class="text-3xl font-mono font-black text-white tracking-widest text-center">00:00:00</div>
                        <div id="liveDate" class="text-[10px] font-medium text-slate-400 text-center mt-1 uppercase"></div>
                    </div>
                </div>
            </div>

            {{-- MY UPCOMING SESSIONS --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800 text-sm tracking-tight">My Upcoming Sessions</h3>
                    <span id="upcomingBadge" class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded-full"></span>
                </div>
                <div id="upcomingSessionsList" class="flex flex-col gap-4"></div>

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

        </div>{{-- end right column --}}
    </div>

    <div id="statusToast"><span id="statusToastMsg">Loading...</span></div>

</div>

@script
<script>
    const allSessions = @json($this->sessions);

    const today = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }));
    let selectedDateStr = today.toISOString().split("T")[0];
    let viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let tablePage = 0;
    const TABLE_PER_PAGE = 5;
    let sortColumn = 'start';
    let sortDirection = 'asc';
    let upcomingPage = 0;
    const UPCOMING_PER_PAGE = 5;

    const searchInput         = document.getElementById('liveSearchInput');
    const statusFilter        = document.getElementById('statusFilter');
    const globalSearchInput   = document.getElementById('globalSearchInput');
    const globalSearchResults = document.getElementById('globalSearchResults');

    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
        document.getElementById('liveDate').innerText  = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
    }
    setInterval(updateClock, 1000);

    function formatTimeTo12Hour(timeStr) {
        const [hour, minute] = timeStr.split(':');
        let h = parseInt(hour);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${minute} ${ampm}`;
    }

    function getStatusColor(status) {
        switch (status) {
            case 'accepted':  return 'text-blue-700 bg-blue-100 border-blue-300';
            case 'completed': return 'text-gray-600 bg-gray-100 border-gray-300';
            case 'pending':   return 'text-yellow-700 bg-yellow-100 border-yellow-300';
            case 'rejected':  return 'text-red-700 bg-red-100 border-red-300';
            case 'cancelled': return 'text-red-700 bg-red-100 border-red-300';
            case 'no_show':   return 'text-orange-700 bg-orange-100 border-orange-300';
            default:          return 'text-gray-500 bg-gray-50 border-gray-200';
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

    function timeToMinutes(t) {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

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

    globalSearchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (!q) { globalSearchResults.classList.add('hidden'); return; }
        const results = allSessions.filter(s =>
            (s.mentor || '').toLowerCase().includes(q) ||
            (s.subject || '').toLowerCase().includes(q) ||
            (s.status  || '').toLowerCase().includes(q) ||
            (s.date    || '').includes(q)
        );
        globalSearchResults.innerHTML = results.length
            ? results.map(r => `
            <div class="p-3 border-b last:border-0 hover:bg-gray-50 cursor-pointer">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-bold text-slate-700">${r.mentor}</p>
                        <p class="text-xs text-gray-400">${r.subject} • ${formatTimeTo12Hour(r.start)} – ${formatTimeTo12Hour(r.end)}</p>
                        <p class="text-[10px] text-gray-400">${r.date}</p>
                    </div>
                    <span class="${getStatusColor(r.status)} text-[10px] px-2 py-1 rounded font-bold border">${getStatusLabel(r.status)}</span>
                </div>
            </div>`).join('')
            : `<div class="p-4 text-xs text-gray-400 text-center">No matching sessions found</div>`;
        globalSearchResults.classList.remove('hidden');
    });

    window.addEventListener('click', (e) => {
        if (!globalSearchInput.contains(e.target)) {
            globalSearchResults.classList.add('hidden');
        }
    });

    function toggleSort(col) {
        if (sortColumn === col) { sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; }
        else { sortColumn = col; sortDirection = 'asc'; }
        tablePage = 0;
        applyFilters();
    }

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
            let aVal = sortColumn === 'mentor'  ? a.mentor.toLowerCase()
                : sortColumn === 'subject' ? a.subject.toLowerCase()
                    : sortColumn === 'status'  ? a.status
                        : a.start;
            let bVal = sortColumn === 'mentor'  ? b.mentor.toLowerCase()
                : sortColumn === 'subject' ? b.subject.toLowerCase()
                    : sortColumn === 'status'  ? b.status
                        : b.start;
            if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDirection === 'asc' ?  1 : -1;
            return 0;
        });

        const total   = filtered.length;
        const maxPage = Math.max(0, Math.ceil(total / TABLE_PER_PAGE) - 1);
        if (tablePage > maxPage) tablePage = 0;
        const start   = tablePage * TABLE_PER_PAGE;
        const visible = filtered.slice(start, start + TABLE_PER_PAGE);

        tbody.innerHTML = !total
            ? `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic text-sm">No sessions found for this date.</td></tr>`
            : visible.map(row => `
            <tr class="border-b last:border-0 hover:bg-slate-50 transition">
                <td class="py-4 font-bold text-slate-700" style="width:35%">
                    <div style="max-width:260px;">
                        <div id="name-${row.id}" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;" title="${row.mentor}">${row.mentor}</div>
                        <button onclick="toggleName('${row.id}')" id="toggle-${row.id}" style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:2px;background:none;border:none;cursor:pointer;padding:0;display:none;">Show more</button>
                    </div>
                </td>
                <td class="text-slate-500" style="width:25%;white-space:nowrap;">${formatTimeTo12Hour(row.start)} – ${formatTimeTo12Hour(row.end)}</td>
                <td class="text-slate-600 truncate" style="width:20%">${row.subject}</td>
                <td style="width:20%"><span class="${getStatusColor(row.status)} font-bold text-[10px] px-2 py-1 rounded border">${getStatusLabel(row.status)}</span></td>
            </tr>`).join('');

        visible.forEach(row => {
            const nameEl   = document.getElementById('name-' + row.id);
            const toggleEl = document.getElementById('toggle-' + row.id);
            if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) toggleEl.style.display = 'block';
        });

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
        const tbody = document.getElementById("weeklyScheduleBody");
        tbody.innerHTML = "";
        const startHour = 8;
        const week = getCurrentWeekRange();

        const weekSessions = allSessions.filter(s => {
            if (!s.date || !s.end) return false;
            if (!['accepted', 'pending', 'completed'].includes(s.status)) return false;
            const d = new Date(s.date + "T00:00:00").setHours(0, 0, 0, 0);
            return d >= week.monday.getTime() && d <= week.friday.getTime();
        });

        let endHour = 15;
        if (weekSessions.length) {
            const latestEnd = Math.max(...weekSessions.map(s => timeToMinutes(s.end)));
            endHour = Math.ceil(latestEnd / 60);
        }
        endHour = Math.max(endHour, startHour + 2);
        endHour = Math.min(endHour, 22);

        const fmtH = h => { const ampm = h >= 12 ? 'PM' : 'AM'; return `${h % 12 || 12}:00 ${ampm}`; };
        const rangeEl = document.getElementById('weeklyScheduleRange');
        if (rangeEl) rangeEl.innerText = `${fmtH(startHour)} – ${fmtH(endHour)}`;

        const days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"];
        for (let hour = startHour; hour < endHour; hour++) {
            for (let min of [0, 30]) {
                const row = document.createElement("tr");
                const timeCell = document.createElement("td");
                timeCell.className = "p-3 text-gray-500 font-semibold";
                timeCell.innerText = `${hour > 12 ? hour - 12 : hour}:${min === 0 ? '00' : '30'} ${hour >= 12 ? 'PM' : 'AM'}`;
                row.appendChild(timeCell);

                days.forEach(day => {
                    const cell = document.createElement("td");
                    cell.className = "p-2";
                    const slotStart = hour * 60 + min;
                    const slotEnd   = slotStart + 30;

                    const sessions = allSessions.filter(s => {
                        if (!s.date || !s.start || !s.end) return false;
                        const date    = new Date(s.date + "T00:00:00");
                        const dayName = date.toLocaleDateString('en-US', { weekday: 'long' });
                        const d = new Date(date).setHours(0, 0, 0, 0);
                        if (!(d >= week.monday.getTime() && d <= week.friday.getTime())) return false;
                        if (dayName !== day) return false;
                        if (!['accepted', 'pending', 'completed'].includes(s.status)) return false;
                        return timeToMinutes(s.start) < slotEnd && timeToMinutes(s.end) > slotStart;
                    });

                    if (sessions.length) {
                        cell.innerHTML = sessions.map(s => {
                            const colorClass = s.status === 'pending'
                                ? 'bg-yellow-100 text-yellow-700 border border-yellow-300'
                                : s.status === 'accepted'
                                    ? 'bg-green-100 text-green-700 border border-green-300'
                                    : 'bg-gray-100 text-gray-600 border border-gray-300';
                            return `<div class="schedule-block ${colorClass}">${s.subject}<br>${formatTimeTo12Hour(s.start)} - ${formatTimeTo12Hour(s.end)}</div>`;
                        }).join('');
                    }
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            }
        }
    }

    function hasSessionOnDate(dateStr) {
        const todayStr = new Date().toISOString().split('T')[0];
        return allSessions.some(s => s.date === dateStr && s.status === 'accepted' && s.date >= todayStr);
    }

    function renderCalendar() {
        const grid       = document.getElementById('calendarGrid');
        const monthDisp  = document.getElementById('monthDisplay');
        grid.innerHTML   = '';
        const localToday = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
        monthDisp.innerText = viewDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });

        const lastDay  = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
        const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

        for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';
        for (let i = 1; i <= lastDay; i++) {
            const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const dateObj = new Date(viewDate.getFullYear(), viewDate.getMonth(), i);
            const dayEl   = document.createElement('div');
            dayEl.className = 'cal-day';
            if (dateObj < localToday) dayEl.style.color = '#9ca3af';
            if (dateObj.toDateString() === localToday.toDateString()) dayEl.classList.add('cal-today');
            if (dateStr === selectedDateStr) dayEl.classList.add('cal-selected');
            const hasSession = hasSessionOnDate(dateStr);
            dayEl.innerHTML = `<span>${i}</span>${hasSession ? `<div class="notif-dot"></div>` : ''}`;
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

    function renderUpcomingSessions() {
        const container  = document.getElementById('upcomingSessionsList');
        const badge      = document.getElementById('upcomingBadge');
        const pagination = document.getElementById('upcomingPagination');
        const pageInfo   = document.getElementById('upcomingPageInfo');
        const prevBtn    = document.getElementById('upcomingPrevBtn');
        const nextBtn    = document.getElementById('upcomingNextBtn');
        const todayStr   = new Date().toISOString().split('T')[0];

        const upcoming = allSessions
            .filter(s => s.status === 'accepted' && s.date >= todayStr)
            .sort((a, b) => a.date !== b.date ? (a.date > b.date ? 1 : -1) : (a.start > b.start ? 1 : -1));

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
            <span class="${getStatusColor(s.status)} font-bold text-[10px] px-2 py-1 rounded border flex-shrink-0">${getStatusLabel(s.status)}</span>
        </div>`).join('');

        visible.forEach(s => {
            const nameEl   = document.getElementById('uname-' + s.id);
            const toggleEl = document.getElementById('utoggle-' + s.id);
            if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) toggleEl.style.display = 'block';
        });

        if (total > UPCOMING_PER_PAGE) {
            pagination.classList.remove('hidden');
            pageInfo.innerText = `${start + 1}–${Math.min(start + UPCOMING_PER_PAGE, total)} of ${total}`;
            prevBtn.disabled = upcomingPage === 0;
            prevBtn.classList.toggle('opacity-30', upcomingPage === 0);
            nextBtn.disabled = upcomingPage >= maxPage;
            nextBtn.classList.toggle('opacity-30', upcomingPage >= maxPage);
        } else {
            pagination.classList.add('hidden');
        }
    }

    document.getElementById('upcomingPrevBtn').addEventListener('click', () => { upcomingPage--; renderUpcomingSessions(); });
    document.getElementById('upcomingNextBtn').addEventListener('click', () => { upcomingPage++; renderUpcomingSessions(); });

    function refreshSchedules() {
        applyFilters();
        generateWeeklySchedule();
        updateWeekHeaders();
        renderUpcomingSessions();
    }

    function initDashboard() {
        renderCalendar();
        refreshSchedules();
        updateTableDate();
        updateClock();
    }

    window.addEventListener('load', initDashboard);
    document.addEventListener('livewire:navigated', initDashboard);
</script>
@endscript
