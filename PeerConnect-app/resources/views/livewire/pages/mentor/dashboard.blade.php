<?php

use function Livewire\Volt\{layout, state, mount, computed};
use App\Models\Bookings;
use App\Models\MentorProfiles;
use App\Models\StudentProfiles;
use Illuminate\Support\Facades\Cache;

// ── STATE ──────────────────────────────────────────────────────────────────
state(['sessions' => []]);

// ── MOUNT ──────────────────────────────────────────────────────────────────
mount(function () {
    abort_if(!auth()->user()->isMentor(), 403);

    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

    // Inline AJAX status update (POST to same route)
    if (request()->isMethod('POST') && request()->has('id') && request()->has('status')) {
        if ($mentorProfile) {
            $booking = Bookings::where('id', request('id'))
                ->where('mentor_id', $mentorProfile->id)
                ->first();

            if ($booking) {
                $booking->booking_status = request('status');
                if (request('status') === 'completed') {
                    $booking->completed_at = now();
                }
                $booking->save();
            }
        }
        // Bust cached search index so it reflects the new status immediately
        Cache::forget("search_index_mentor_{$mentorProfile?->user_id}");

        return response()->json(['success' => true]);
    }

    if (!$mentorProfile) return;

    // Auto-complete past accepted sessions
    Bookings::where('mentor_id', $mentorProfile->id)
        ->where('booking_status', 'accepted')
        ->whereDate('date', '<', today())
        ->whereDate('updated_at', '<', today())
        ->update([
            'booking_status' => 'completed',
            'completed_at'   => now(),
        ]);

    // Load all sessions for this mentor
    $this->sessions = Bookings::with(['student.user', 'subject'])
        ->where('mentor_id', $mentorProfile->id)
        ->get()
        ->map(fn ($b) => [
            'id'          => $b->id,
            'student'     => optional(optional($b->student)->user)->firstName
                ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                : 'Unknown',
            'subject'     => optional($b->subject)->code ?? 'N/A',
            'subjectName' => optional($b->subject)->name ?? '',
            'date'        => $b->date
                ? \Carbon\Carbon::parse($b->date)->format('Y-m-d')
                : null,
            'start'  => \Carbon\Carbon::parse($b->schedule_start)->format('H:i'),
            'end'    => \Carbon\Carbon::parse($b->schedule_end)->format('H:i'),
            'topic'  => $b->topic ?? '',
            'status' => $b->booking_status,
        ])
        ->values()
        ->toArray();
});

// ── SEARCH INDEX  ───────────────────────
$searchIndex = computed(function () {
    $userId = auth()->id();

    return Cache::remember("search_index_mentor_{$userId}", now()->addMinutes(5), function () use ($userId) {
        $mentorProfileId  = MentorProfiles::where('user_id', $userId)->value('id');
        $studentProfileId = StudentProfiles::where('user_id', $userId)->value('id');

        $index = [];

        // Booking history
        $bookings = Bookings::with(['mentor.user', 'subject'])
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
                'group'        => 'Booking History',
                'label'        => $b->topic ?: 'Tutorial Session',
                'detail'       => implode(' — ', [
                    $sessionDate,
                    'Subject: ' . ($b->subject->code ?? 'N/A'),
                    'Mentor: ' . $mentorName,
                    'Status: ' . ucfirst($b->booking_status),
                ]),
                'icon'         => 'fa-calendar-days',
                'bg'           => '#dbeafe',
                'color'        => '#1e40af',
                'url'          => route('mentor.history'),
                'searchString' => strtolower(implode(' ', [
                    $b->topic, $mentorName, $b->booking_status,
                    $b->subject->code ?? '', $sessionDate,
                ])),
            ];
        }

        // Teaching sessions
        if ($mentorProfileId) {
            $teaching = Bookings::with(['student.user', 'subject'])
                ->where('mentor_id', $mentorProfileId)
                ->latest()
                ->take(50)
                ->get();

            foreach ($teaching as $b) {
                $studentName = ($b->student && $b->student->user)
                    ? "{$b->student->user->lastName}, {$b->student->user->firstName}"
                    : 'Unknown Student';
                $subjectCode = $b->subject ? strtoupper($b->subject->code) : 'N/A';
                $topic       = $b->topic ?: 'Tutorial Session';
                $sessionDate = \Carbon\Carbon::parse($b->date)->format('F j, Y');
                $status      = ucfirst($b->booking_status);

                $index[] = [
                    'group'        => 'Sessions',
                    'label'        => $studentName,
                    'detail'       => implode(' — ', [
                        $sessionDate,
                        "Subject: {$subjectCode}",
                        "Topic: {$topic}",
                        "Status: {$status}",
                    ]),
                    'icon'         => 'fa-chalkboard-user',
                    'bg'           => '#fef3c7',
                    'color'        => '#92400e',
                    'url'          => route('mentor.sessions'),
                    'searchString' => strtolower("{$topic} {$studentName} {$status} {$subjectCode} {$sessionDate}"),
                ];
            }
        }

        // Feedbacks
        if ($mentorProfileId) {
            $feedbacks = \App\Models\Feedback::with(['booking.subject'])
                ->whereHas('booking', fn ($q) => $q->where('mentor_id', $mentorProfileId))
                ->get();

            foreach ($feedbacks as $fb) {
                $subjectCode = $fb->subject ?? $fb->booking?->subject?->code ?? 'N/A';
                $comment     = $fb->feedback ?? 'No comment provided.';
                $date        = isset($fb->date_submitted)
                    ? \Carbon\Carbon::parse($fb->date_submitted)->format('F j, Y')
                    : '';
                $topic = $fb->topic ?? 'Session Feedback';

                $index[] = [
                    'group'        => 'Feedback',
                    'label'        => \Illuminate\Support\Str::limit($comment, 40),
                    'detail'       => implode(' — ', [
                        $date,
                        "Subject: {$subjectCode}",
                        "Topic: {$topic}",
                    ]),
                    'icon'         => 'fa-comment-dots',
                    'bg'           => '#d1fae5',
                    'color'        => '#065f46',
                    'url'          => route('mentor.feedbacks'),
                    'searchString' => strtolower("{$comment} {$subjectCode} {$topic} {$date}"),
                ];
            }
        }

        return $index;
    });
});

?>

<div>

    {{-- ── GLOBAL SEARCH ─────────────────────────────────────────────────── --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative z-30"
         x-data="{
             query: '',
             open: false,
             index: @js($this->searchIndex),
             get filteredResults() {
                 const term = this.query.toLowerCase();
                 const grouped = {};
                 this.index
                     .filter(item => item.searchString.includes(term))
                     .forEach(m => {
                         if (!grouped[m.group]) grouped[m.group] = [];
                         grouped[m.group].push(m);
                     });
                 return grouped;
             }
         }"
         @click.outside="open = false">

        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input type="text"
                   x-model="query"
                   @focus="open = true"
                   @keydown.escape.window="open = false; query = ''"
                   placeholder="Search students, sessions, or feedback..."
                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700
                          placeholder-gray-400 border border-gray-200 rounded-lg bg-white
                          outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon
                          h-[34px] transition-shadow">
        </div>

        {{-- Results dropdown --}}
        <div x-show="open && query.length >= 1"
             x-cloak x-transition
             class="absolute left-0 right-0 bg-white rounded-xl shadow-xl border border-gray-100 overflow-y-auto"
             style="top: calc(100% + 6px); max-height: 420px; z-index: 20;">

            <template x-if="Object.keys(filteredResults).length === 0">
                <div style="padding:20px; text-align:center; font-size:13px; color:#9ca3af; font-style:italic;">
                    No matches found for "<strong x-text="query"></strong>"
                </div>
            </template>

            <template x-for="(items, group) in filteredResults" :key="group">
                <div>
                    <div x-text="group"
                         style="padding:10px 14px; font-size:10px; font-weight:900; color:#000;
                                text-transform:uppercase; letter-spacing:.05em; background:#f0f0f0;">
                    </div>

                    <template x-for="item in items" :key="item.label + item.detail">
                        <a :href="item.url" class="block group"
                           style="display:flex; align-items:center; gap:12px; padding:10px 14px;
                                  cursor:pointer; border-bottom:1px solid #f1f5f9;
                                  transition:background .15s; text-decoration:none;"
                           onmouseover="this.style.background='#f4f5f7'"
                           onmouseout="this.style.background='transparent'">

                            <span :style="`font-size:11px; width:28px; height:28px; display:flex;
                                           align-items:center; justify-content:center; border-radius:6px;
                                           flex-shrink:0; background:${item.bg}; color:${item.color};`">
                                <i class="fa-solid" :class="item.icon"></i>
                            </span>

                            <div style="flex:1; min-width:0;">
                                <div style="font-size:13px; font-weight:700; color:#1e293b;
                                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                     x-text="item.label"></div>
                                <div style="font-size:11px; font-weight:500; color:#64748b;
                                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"
                                     x-text="item.detail"></div>
                            </div>

                            <i class="fa-solid fa-arrow-up-right-from-square opacity-0 group-hover:opacity-100 transition-opacity"
                               style="font-size:10px; color:#cbd5e1; flex-shrink:0;"></i>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ── MAIN GRID ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-3 gap-8">

        {{-- ── LEFT COLUMN ──────────────────────────────────────────── --}}
        <div class="col-span-2 space-y-8">

            {{-- Today's Schedule --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col">

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800" id="tableTitle">
                            <i class="fa-solid fa-calendar-check"></i> Today's Schedule
                        </h2>
                        <p class="text-s text-gray-500" id="tableSubtitle"></p>
                    </div>

                    <div class="flex gap-2">
                        <div class="relative w-48">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" id="liveSearchInput"
                                   placeholder="Search..."
                                   class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700
                                          placeholder-gray-400 border border-gray-200 rounded-lg bg-white
                                          outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon
                                          h-[34px] transition-shadow">
                        </div>

                        <select id="statusFilter" class="table-filter-select">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                            <option value="no_show">No Show</option>
                        </select>
                    </div>
                </div>

                <table class="w-full text-left text-sm table-fixed">
                    <thead class="text-gray-400 border-b">
                    <tr>
                        <th class="pb-3 text-[10px] tracking-wider" style="width:22%">
                            <button id="sortHead-student" onclick="toggleSort('student')"
                                    class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors"
                                    style="color:#94a3b8;">
                                Student <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                            </button>
                        </th>
                        <th class="pb-3 text-[10px] tracking-wider" style="width:30%">
                            <button id="sortHead-start" onclick="toggleSort('start')"
                                    class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors"
                                    style="color:#7b1d1d;">
                                Time <span class="sort-icon"><i class="fa-solid fa-arrow-up" style="font-size:8px;"></i></span>
                            </button>
                        </th>
                        <th class="pb-3 text-[10px] tracking-wider" style="width:28%">
                            <button id="sortHead-subject" onclick="toggleSort('subject')"
                                    class="flex items-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors"
                                    style="color:#94a3b8;">
                                Subject <span class="sort-icon"><i class="fa-solid fa-arrow-up-arrow-down" style="font-size:8px;opacity:0.4;"></i></span>
                            </button>
                        </th>
                        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
                            <button id="sortHead-status" onclick="toggleSort('status')"
                                    class="flex items-center justify-center gap-1 font-semibold uppercase hover:text-red-800 transition-colors w-full"
                                    style="color:#94a3b8;">
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

            {{-- Weekly Schedule --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">

                <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-bold text-slate-800">Weekly Schedule</h2>
                    </div>

                    <select id="weeklyStatusFilter" class="table-filter-select"
                            onchange="generateWeeklySchedule()">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="sched-legend mb-4 pb-3 border-b border-gray-50">
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#eab308;"></span>Pending
                    </span>
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#10b981;"></span>Accepted
                    </span>
                    <span class="sched-legend-item">
                        <span class="sched-legend-dot" style="background:#94a3b8;"></span>Completed
                    </span>
                </div>

                <div class="overflow-x-auto" id="weeklyGridContainer"></div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN ─────────────────────────────────────────── --}}
        <div class="flex flex-col gap-6">

            {{-- Calendar + Clock --}}
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
                                class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <span id="monthDisplay"
                              class="text-sm font-bold text-slate-800 text-center min-w-[120px]"></span>
                        <button onclick="changeMonth(1)"
                                class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 mb-1">
                        @foreach (['S','M','T','W','T','F','S'] as $d)
                            <div class="cal-header-day">{{ $d }}</div>
                        @endforeach
                    </div>
                    <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="font-bold text-slate-800 text-sm mb-4">Quick Actions</h3>
                <div id="quickActionsBannerArea" class="flex flex-col gap-2 mb-2"></div>
                <div id="quickActionsList" class="flex flex-col gap-3"></div>
            </div>

            {{-- Pending Requests --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">

                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Requests</h3>
                    <span id="pendingBadge"
                          class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                    </span>
                </div>

                <div id="pendingBannerArea" class="flex flex-col gap-2 mb-2"></div>
                <div id="pendingRequestsList" class="flex flex-col gap-4"></div>

                <button id="toggleRequestsBtn"
                        class="w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600
                               border-t border-gray-50 transition text-center">
                    View All Requests
                </button>
            </div>

        </div>
    </div>

    {{-- ── CONFIRMATION MODAL ─────────────────────────────────────────────── --}}
    <div id="confirmModal" style="display:none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div id="confirmModalBox" class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta"
                 class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1">
            </div>
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
    // DATA & CONSTANTS
    const allSessions = @json($this->sessions);
    const csrfToken   = '{{ csrf_token() }}';

    // Date helpers
    const _nowManila = () => new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
    const today      = _nowManila();
    const todayStr   = _dateStr(today);

    // Mutable UI state
    let selectedDateStr = todayStr;
    let viewDate        = new Date(today.getFullYear(), today.getMonth(), 1);

    // Pagination state
    let tablePage        = 0;  const TABLE_PER_PAGE        = 5;
    let pendingPage      = 0;  const PENDING_PER_PAGE       = 5;
    let quickActionsPage = 0;  const QUICK_ACTIONS_PER_PAGE = 5;

    // Sort state
    let sortColumn    = 'start';
    let sortDirection = 'asc';


   // HELPERS
    function _dateStr(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function formatTimeTo12Hour(timeStr) {
        const [hour, minute] = timeStr.split(':');
        let h = parseInt(hour);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${minute} ${ampm}`;
    }

    function timeToMinutes(t) {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

function getStatusColor(status) {
    const map = {
        pending:   'text-yellow-500',
        accepted:  'text-green-600',
        active:    'text-green-600',
        upcoming:  'text-orange-500',
        completed: 'text-green-600',
        rejected:  'text-red-500',
        cancelled: 'text-red-600',
        closed:    'text-purple-700',
        no_show:   'text-orange-600',
    };
    return map[status] ?? 'text-slate-400';
}

    function getStatusLabel(status) {
        const map = {
            no_show:   'No Show',
            accepted:  'Accepted',
            completed: 'Completed',
            closed:    'Closed',
            rejected:  'Rejected',
            cancelled: 'Cancelled',
            pending:   'Pending',
        };
        return map[status] ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '—');
    }

    // CLOCK & DATE BAR
    function updateClock() {
        const now = _nowManila();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
        document.getElementById('liveDate').innerText  = now.toLocaleDateString('en-US', {
            weekday: 'long', month: 'long', day: 'numeric',
        });
    }
    setInterval(updateClock, 1000);

    // SCHEDULE TABLE
    function applyFilters() {
        const tbody          = document.getElementById('tableBody');
        const searchTerm     = document.getElementById('liveSearchInput').value.toLowerCase();
        const selectedStatus = document.getElementById('statusFilter').value;

        let filtered = allSessions.filter(item => {
            const matchesDate   = item.date === selectedDateStr;
            const matchesSearch = searchTerm === '' || [
                item.student, item.subject, item.subjectName, item.start, item.end,
            ].some(v => (v ?? '').toLowerCase().includes(searchTerm));
            const matchesStatus = !selectedStatus || item.status === selectedStatus;
            return matchesDate && matchesSearch && matchesStatus;
        });

        // Sort
        filtered.sort((a, b) => {
            let aVal, bVal;
            switch (sortColumn) {
                case 'start':   aVal = a.start;                bVal = b.start;                break;
                case 'student': aVal = a.student.toLowerCase(); bVal = b.student.toLowerCase(); break;
                case 'subject': aVal = a.subject.toLowerCase(); bVal = b.subject.toLowerCase(); break;
                case 'status':  aVal = a.status;                bVal = b.status;                break;
            }
            if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDirection === 'asc' ?  1 : -1;
            return 0;
        });

        // Paginate
        const total   = filtered.length;
        const maxPage = Math.max(0, Math.ceil(total / TABLE_PER_PAGE) - 1);
        if (tablePage > maxPage) tablePage = 0;

        const start   = tablePage * TABLE_PER_PAGE;
        const visible = filtered.slice(start, start + TABLE_PER_PAGE);

        // Render rows
        if (!total) {
            tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No sessions for this date.</td></tr>`;
        } else {
  tbody.innerHTML = visible.map(row => `
    <tr class="border-b last:border-0 hover:bg-slate-50 transition">
        <td class="py-3 max-w-0" style="width:22%;">
            <div class="hover-tooltip" data-full="${row.student}" style="max-width:260px;">
                <div id="name-${row.id}"
                     class="truncate text-xs font-bold text-slate-700">
                    ${row.student}
                </div>
            </div>
        </td>
        <td class="py-3 text-xs text-slate-500" style="width:30%;white-space:nowrap;">
            ${formatTimeTo12Hour(row.start)} – ${formatTimeTo12Hour(row.end)}
        </td>
        <td class="py-3 max-w-0" style="width:28%;">
            <div class="hover-tooltip"
                 data-full="${row.subject}${row.subjectName ? ' – ' + row.subjectName : ''}"
                 style="max-width:160px;">
                <div class="truncate text-xs text-slate-600">
                    ${row.subject}
                </div>
            </div>
        </td>
        <td class="py-3 text-center" style="width:20%">
    ${row.status === 'accepted' ? `
        <div class="flex items-center justify-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-green-400 inline-block flex-shrink-0"></span>
            <span class="text-green-600 font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize">
                ${getStatusLabel(row.status)}
            </span>
        </div>
    ` : `
        <span class="${getStatusColor(row.status)} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize">
            ${getStatusLabel(row.status)}
        </span>
    `}
</td>

    </tr>
`).join('');
        }

        // Sort header indicators
        ['student', 'start', 'subject', 'status'].forEach(col => {
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

        // Pagination controls
        document.getElementById('pageIndicator').innerText = total
            ? `Showing ${start + 1}–${Math.min(start + TABLE_PER_PAGE, total)} of ${total}`
            : 'Showing 0 results';

        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        prevBtn.disabled = tablePage === 0;
        nextBtn.disabled = tablePage >= maxPage;
        prevBtn.classList.toggle('opacity-30', tablePage === 0);
        nextBtn.classList.toggle('opacity-30', tablePage >= maxPage);
    }

    function toggleSort(col) {
        if (sortColumn === col) sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        else { sortColumn = col; sortDirection = 'asc'; }
        tablePage = 0;
        applyFilters();
    }

    function updateTableDate() {
        const date = new Date(selectedDateStr);
        document.getElementById('tableSubtitle').innerText = date.toLocaleDateString('en-US', {
            month: 'long', day: 'numeric', year: 'numeric',
        });
    }

    // WEEKLY SCHEDULE
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

    function generateWeeklySchedule() {
        const weeklyFilter = document.getElementById('weeklyStatusFilter')?.value || '';
        const ALL_STATUSES = ['accepted', 'pending', 'completed'];
        const ALLOWED      = weeklyFilter ? [weeklyFilter] : ALL_STATUSES;
        const SLOT_H       = 28;
        const TIME_COL_W   = 52;
        const START_HOUR   = 8;
        const END_HOUR     = 18;
        const TOTAL_SLOTS  = (END_HOUR - START_HOUR) * 2;
        const TOTAL_HEIGHT = TOTAL_SLOTS * SLOT_H;
        const DAYS         = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        const STATUS_LABEL = { pending: 'Pending', accepted: 'Accepted', completed: 'Completed' };

        const wrapper = document.getElementById('weeklyGridContainer');

        // Remove previous grid if re-rendering
        const old = document.getElementById('weeklyGridWrap');
        if (old) old.remove();

        const week = getCurrentWeekRange();

        const weekSessions = allSessions.filter(s => {
            if (!s.date || !s.start || !s.end || !ALLOWED.includes(s.status)) return false;
            const d = new Date(s.date + 'T00:00:00').setHours(0, 0, 0, 0);
            return d >= week.monday.getTime() && d <= week.friday.getTime();
        });

        // Update range badge
        const fmtHour = h => { const ap = h >= 12 ? 'PM' : 'AM'; const d = h % 12 || 12; return `${d}:00 ${ap}`; };
        const rangeEl = document.getElementById('weeklyScheduleRange');
        if (rangeEl) rangeEl.innerText = `${fmtHour(START_HOUR)} – ${fmtHour(END_HOUR)}`;

        // Build grid wrapper
        const gridWrap = document.createElement('div');
        gridWrap.id = 'weeklyGridWrap';
        gridWrap.style.cssText = `
            position:relative; display:grid;
            grid-template-columns:${TIME_COL_W}px repeat(${DAYS.length},1fr);
            width:100%; min-width:480px; border:1px solid #c9c9c9;
            border-radius:6px; overflow:hidden; background:#fff; font-size:9px;
        `;

        // Header row — time cell
        const hdrTime = _el('div', `background:#f8fafc;border-bottom:1px solid #e5e7eb;
            border-right:1px solid #e5e7eb;padding:6px 4px;font-size:9px;font-weight:700;
            color:#94a3b8;text-transform:uppercase;text-align:center;`, 'Time');
        gridWrap.appendChild(hdrTime);

        // Header row — day cells
        DAYS.forEach((day, i) => {
            const d = new Date(week.monday);
            d.setDate(week.monday.getDate() + i);
            const label = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
            const hdrDay = _el('div', `background:#f8fafc;border-bottom:1px solid #e5e7eb;
                ${i < DAYS.length - 1 ? 'border-right:1px solid #e5e7eb;' : ''}
                padding:6px 4px;font-size:9px;font-weight:700;color:#64748b;
                text-transform:uppercase;text-align:center;`, label);
            gridWrap.appendChild(hdrDay);
        });

        // Time label column
        const timeCol = _el('div', `position:relative;height:${TOTAL_HEIGHT}px;border-right:1px solid #e5e7eb;background:#fafafa;`);
        for (let slot = 0; slot < TOTAL_SLOTS; slot++) {
            const totalMins = START_HOUR * 60 + slot * 30;
            const h = Math.floor(totalMins / 60);
            const m = totalMins % 60;
            const dh = h > 12 ? h - 12 : (h === 0 ? 12 : h);
            const label = `${dh}:${m === 0 ? '00' : '30'} ${h >= 12 ? 'PM' : 'AM'}`;
            const tick = _el('div', `position:absolute;top:${slot * SLOT_H}px;left:0;right:0;
                height:${SLOT_H}px;border-top:1px solid ${slot % 2 === 0 ? '#afafaf' : '#d8d8d8'};
                padding:2px 4px;color:#94a3b8;font-size:8px;font-weight:600;
                white-space:nowrap;display:flex;align-items:flex-start;`,
                m === 0 ? label : '');
            timeCol.appendChild(tick);
        }
        gridWrap.appendChild(timeCol);

        // Day columns
        DAYS.forEach((day, di) => {
            const dayCol = _el('div', `position:relative;height:${TOTAL_HEIGHT}px;
                ${di < DAYS.length - 1 ? 'border-right:1px solid #e5e7eb;' : ''}background:#fff;`);

            // Grid lines
            for (let slot = 0; slot < TOTAL_SLOTS; slot++) {
                dayCol.appendChild(_el('div', `position:absolute;top:${slot * SLOT_H}px;left:0;right:0;
                    height:${SLOT_H}px;border-top:1px solid ${slot % 2 === 0 ? '#afafaf' : '#d8d8d8'};
                    pointer-events:none;`));
            }

            // Sessions
            weekSessions
                .filter(s => {
                    const d = new Date(s.date + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { weekday: 'long' }) === day;
                })
                .forEach(s => {
                    const sStart   = timeToMinutes(s.start);
                    const sEnd     = timeToMinutes(s.end);
                    const topPx    = ((sStart - START_HOUR * 60) / 30) * SLOT_H;
                    const heightPx = Math.max(((sEnd - sStart) / 30) * SLOT_H - 2, 16);
                    const sk       = (s.status || 'pending').toLowerCase().replace(/[^a-z_]/g, '');
                    const lbl      = STATUS_LABEL[sk] || s.status;

                    const block = document.createElement('div');
                    block.className = `schedule-block status-${sk}`;
                    block.title = `${s.student}\n${s.subject} • ${formatTimeTo12Hour(s.start)}–${formatTimeTo12Hour(s.end)}`;
                    block.style.cssText = `position:absolute;top:${topPx + 1}px;left:2px;right:2px;
                        height:${heightPx}px;overflow:hidden;border-radius:4px;padding:2px 4px;
                        display:flex;flex-direction:column;justify-content:flex-start;z-index:2;cursor:default;`;
                    block.innerHTML = `
                        <div style="font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.3;">${s.subject}</div>
                        ${heightPx >= 28 ? `<div style="opacity:0.75;line-height:1.3;">${formatTimeTo12Hour(s.start)}–${formatTimeTo12Hour(s.end)}</div>` : ''}
                        ${heightPx >= 42 ? `<div style="font-size:8px;font-weight:700;opacity:0.65;text-transform:uppercase;letter-spacing:0.04em;">${lbl}</div>` : ''}
                    `;
                    dayCol.appendChild(block);
                });

            gridWrap.appendChild(dayCol);
        });

        wrapper.appendChild(gridWrap);
    }

    /** Quick element factory */
    function _el(tag, css, text = '') {
        const el = document.createElement(tag);
        el.style.cssText = css;
        if (text) el.innerText = text;
        return el;
    }

/**Calendar */
function hasUpcomingOnDate(dateStr) {
    return allSessions.some(s => s.date === dateStr && s.status === 'accepted' && s.date >= todayStr);
}
    function renderCalendar() {
        const grid      = document.getElementById('calendarGrid');
        const monthDisp = document.getElementById('monthDisplay');
        grid.innerHTML  = '';

        const localToday = _nowManila();
        monthDisp.innerText = viewDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });

        const lastDay  = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
        const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

        for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

        for (let i = 1; i <= lastDay; i++) {
            const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const dateObj = new Date(viewDate.getFullYear(), viewDate.getMonth(), i);
            const dayEl   = document.createElement('div');
            dayEl.className = 'cal-day';

            if (dateObj < localToday)       dayEl.style.color = '#9ca3af';
            if (dateStr === todayStr)        dayEl.classList.add('cal-today');
            if (dateStr === selectedDateStr) dayEl.classList.add('cal-selected');

dayEl.innerHTML = `
    ${hasUpcomingOnDate(dateStr) ? '<span style="position:absolute;top:2px;right:2px;width:6px;height:6px;background:#22c55e;border-radius:50%;border:1px solid white;"></span>' : ''}
    <span style="position:relative;z-index:1;">${i}</span>`;

            dayEl.onclick = () => {
                selectedDateStr = dateStr;
                tablePage = 0;
                quickActionsPage = 0;
                refreshSchedules();
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

    // QUICK ACTIONS
    function renderQuickActions() {
        const container     = document.getElementById('quickActionsList');
        const todaySessions = allSessions.filter(s => s.date === selectedDateStr && s.status === 'accepted');
        const total         = todaySessions.length;

        if (!total) {
            container.innerHTML = `<p class="text-xs text-gray-400 italic">No active sessions for this date.</p>`;
            return;
        }

        const maxPage = Math.ceil(total / QUICK_ACTIONS_PER_PAGE) - 1;
        if (quickActionsPage > maxPage) quickActionsPage = maxPage;
        if (quickActionsPage < 0) quickActionsPage = 0;

        const start   = quickActionsPage * QUICK_ACTIONS_PER_PAGE;
        const visible = todaySessions.slice(start, start + QUICK_ACTIONS_PER_PAGE);
        const hasPrev = quickActionsPage > 0;
        const hasNext = quickActionsPage < maxPage;

        const qaBtn = (icon, label, status, colorCls, textCls, id) => `
            <div class="hover-tooltip" data-full="${label}">
                <button onclick="updateStatus('${id}','${status}')"
                        class="w-7 h-7 rounded-lg ${colorCls} ${textCls} flex items-center
                               justify-center transition-all hover:scale-110 hover:shadow-sm"
                        style="flex-shrink:0;">
                    <i class="fa-solid ${icon}" style="font-size:11px;"></i>
                </button>
            </div>`;

        container.innerHTML = `
            ${visible.map(s => `
                <div class="session-row flex items-center justify-between border border-gray-100 rounded-lg p-3">
                    <div style="min-width:0;flex:1;margin-right:8px;">
                        <div style="max-width:200px;">
                            <div id="qaname-${s.id}"
                                 style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
                                        font-size:11px;font-weight:700;color:#1e293b;"
                                 title="${s.student}">${s.student}</div>
                            <button onclick="toggleQaName('${s.id}')" id="qatoggle-${s.id}"
                                    style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:1px;
                                           background:none;border:none;cursor:pointer;padding:0;display:none;">
                                Show more
                            </button>
                        </div>
                        <p class="text-[10px] text-gray-400">${s.subject} • ${formatTimeTo12Hour(s.start)} – ${formatTimeTo12Hour(s.end)}</p>
                        <p class="text-[9px] text-gray-400">${new Date(s.date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })}</p>
                    </div>
                    <div class="relative flex items-center justify-end" style="min-height:28px;">
                        <div class="action-idle absolute right-0 flex items-center gap-1 pointer-events-none">
                            <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                        </div>
                        <div class="action-buttons flex items-center gap-1 justify-end">
                            ${qaBtn('fa-flag-checkered', 'Complete', 'completed', 'bg-gray-100 hover:bg-gray-200',    'text-gray-600',   s.id)}
                            ${qaBtn('fa-user-slash',     'No-show',  'no_show',   'bg-orange-100 hover:bg-orange-200', 'text-orange-600', s.id)}
                            ${qaBtn('fa-ban',            'Cancel',   'cancelled', 'bg-red-100 hover:bg-red-200',       'text-red-600',    s.id)}
                        </div>
                    </div>
                </div>
            `).join('')}
            ${total > QUICK_ACTIONS_PER_PAGE ? `
                <div class="flex items-center justify-between pt-2 border-t border-gray-100 mt-1">
                    <span class="text-[10px] text-gray-400">${start + 1}–${Math.min(start + QUICK_ACTIONS_PER_PAGE, total)} of ${total}</span>
                    <div class="flex gap-1">
                        <button onclick="quickActionsPage--; renderQuickActions();" ${!hasPrev ? 'disabled' : ''}
                                class="pagination-btn ${!hasPrev ? 'opacity-30 cursor-not-allowed' : ''}">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <button onclick="quickActionsPage++; renderQuickActions();" ${!hasNext ? 'disabled' : ''}
                                class="pagination-btn ${!hasNext ? 'opacity-30 cursor-not-allowed' : ''}">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
            ` : ''}`;

        visible.forEach(s => {
            const nameEl   = document.getElementById('qaname-' + s.id);
            const toggleEl = document.getElementById('qatoggle-' + s.id);
            if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) toggleEl.style.display = 'block';
        });
    }

    function toggleQaName(id) {
        _toggleName('qaname-' + id, 'qatoggle-' + id);
    }

    // PENDING REQUESTS
    function renderPendingRequests() {
        const container = document.getElementById('pendingRequestsList');
        const badge     = document.getElementById('pendingBadge');
        const toggleBtn = document.getElementById('toggleRequestsBtn');

        const sorted = [...allSessions]
            .filter(s => s.status === 'pending')
            .sort((a, b) => new Date(b.date) - new Date(a.date));
        const total = sorted.length;

        badge.innerText = `${total} ${total === 1 ? 'Request' : 'Requests'}`;

        if (!total) {
            container.innerHTML = `<p class="text-xs text-gray-400 italic">No pending requests.</p>`;
            toggleBtn.style.display = 'none';
            return;
        }

        const maxPage = Math.ceil(total / PENDING_PER_PAGE) - 1;
        if (pendingPage > maxPage) pendingPage = maxPage;
        if (pendingPage < 0) pendingPage = 0;

        const start   = pendingPage * PENDING_PER_PAGE;
        const visible = sorted.slice(start, start + PENDING_PER_PAGE);
        const hasPrev = pendingPage > 0;
        const hasNext = pendingPage < maxPage;

        const pendingBtn = (icon, label, onclickFn, colorCls, textCls) => `
            <div class="hover-tooltip" data-full="${label}">
                <button onclick="${onclickFn}"
                        class="w-7 h-7 rounded-lg ${colorCls} ${textCls} flex items-center
                               justify-center transition-all hover:scale-110 hover:shadow-sm"
                        style="flex-shrink:0;">
                    <i class="fa-solid ${icon}" style="font-size:11px;"></i>
                </button>
            </div>`;

        container.innerHTML = visible.map(req => `
            <div class="session-row flex items-center justify-between group">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center
                                justify-center text-[10px] font-bold flex-shrink-0">
                        ${req.student.slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <div style="max-width:130px;">
                            <div id="pname-${req.id}"
                                 style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
                                        font-size:11px;font-weight:700;color:#1e293b;"
                                 title="${req.student}">${req.student}</div>
                            <button onclick="togglePendingName('${req.id}')" id="ptoggle-${req.id}"
                                    style="font-size:9px;color:#7b1d1d;font-weight:600;margin-top:1px;
                                           background:none;border:none;cursor:pointer;padding:0;display:none;">
                                Show more
                            </button>
                        </div>
                        <p class="text-[9px] text-gray-400 font-medium">${req.subject} • ${formatTimeTo12Hour(req.start)} – ${formatTimeTo12Hour(req.end)}</p>
                        <p class="text-[9px] text-gray-400">${new Date(req.date).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })}</p>
                    </div>
                </div>
                <div class="relative flex items-center justify-end" style="min-height:28px;">
                    <div class="action-idle absolute right-0 flex items-center gap-1 pointer-events-none">
                        <span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>
                    </div>
                    <div class="action-buttons flex items-center gap-1 justify-end">
                        ${pendingBtn('fa-xmark', 'Reject', `rejectRequest('${req.id}')`, 'bg-red-100 hover:bg-red-200', 'text-red-600')}
                        ${pendingBtn('fa-check', 'Accept', `approveRequest('${req.id}')`, 'bg-emerald-100 hover:bg-emerald-200', 'text-emerald-700')}
                    </div>
                </div>
            </div>
        `).join('');

        visible.forEach(req => {
            const nameEl   = document.getElementById('pname-' + req.id);
            const toggleEl = document.getElementById('ptoggle-' + req.id);
            if (nameEl && toggleEl && nameEl.scrollWidth > nameEl.clientWidth) toggleEl.style.display = 'block';
        });

        if (total <= PENDING_PER_PAGE) {
            toggleBtn.style.display = 'none';
        } else {
            toggleBtn.style.display = 'block';
            toggleBtn.innerHTML = `
                <div class="flex items-center justify-between w-full px-1">
                    <span class="text-[10px] text-gray-400">
                        ${start + 1}–${Math.min(start + PENDING_PER_PAGE, total)} of ${total}
                    </span>
                    <div class="flex gap-1">
                        <button onclick="pendingPage--; renderPendingRequests(); event.stopPropagation();"
                                ${!hasPrev ? 'disabled' : ''}
                                class="pagination-btn ${!hasPrev ? 'opacity-30 cursor-not-allowed' : ''}">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <button onclick="pendingPage++; renderPendingRequests(); event.stopPropagation();"
                                ${!hasNext ? 'disabled' : ''}
                                class="pagination-btn ${!hasNext ? 'opacity-30 cursor-not-allowed' : ''}">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>`;
        }
    }

    function togglePendingName(id) {
        _toggleName('pname-' + id, 'ptoggle-' + id);
    }

    /** Generic expand/collapse for truncated name elements */
    function _toggleName(nameId, btnId) {
        const nameEl = document.getElementById(nameId);
        const btn    = document.getElementById(btnId);
        if (!nameEl || !btn) return;
        const collapsed = btn.innerText === 'Show more';
        nameEl.style.whiteSpace   = collapsed ? 'normal'    : 'nowrap';
        nameEl.style.overflow     = collapsed ? 'visible'   : 'hidden';
        nameEl.style.textOverflow = collapsed ? 'unset'     : 'ellipsis';
        nameEl.style.wordBreak    = collapsed ? 'break-all' : 'normal';
        btn.innerText = collapsed ? 'Show less' : 'Show more';
    }

    // STATUS UPDATE
    function updateStatus(id, status, source = 'qa') {
        const fromPending = source === 'pending';

        const loadingMsgs = {
            accepted:  'Accepting booking',
            rejected:  'Rejecting booking',
            completed: 'Marking as completed',
            cancelled: 'Cancelling session',
            no_show:   'Marking as no-show',
        };
        const successMsgs = {
            accepted:  'Booking accepted successfully.',
            rejected:  'Booking rejected.',
            completed: 'Session marked as completed.',
            cancelled: 'Session cancelled.',
            no_show:   'Marked as no-show.',
        };

        if (fromPending) showLoadingBanner('pendingBannerArea', 'pending', loadingMsgs[status] ?? 'Updating status');
        else             showLoadingBanner('quickActionsBannerArea', 'qa', loadingMsgs[status] ?? 'Updating status');

        fetch('{{ route("mentor.dashboard.update") }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body:    JSON.stringify({ id, status }),
        })
            .then(res => res.json())
            .then(data => {
                hideLoadingBanner(fromPending ? 'pending' : 'qa');

                if (!data.success) {
                    if (fromPending) showErrorBanner('pendingBannerArea', 'pending', 'Please try again.');
                    else             showErrorBanner('quickActionsBannerArea', 'qa', 'Please try again.');
                    return;
                }

                // Optimistically update local state
                const target = allSessions.find(s => s.id === id);
                if (target) {
                    target.status = status;

                    // Auto-reject conflicting pending sessions when one is accepted
                    if (status === 'accepted') {
                        const conflictIds = getConflictingPendingIds(target);
                        conflictIds.forEach(cid => {
                            const cs = allSessions.find(s => s.id === cid);
                            if (cs) cs.status = 'rejected';
                            fetch('{{ route("mentor.dashboard.update") }}', {
                                method:  'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                body:    JSON.stringify({ id: cid, status: 'rejected' }),
                            }).catch(err => console.error('Auto-reject failed for id', cid, err));
                        });

                        if (conflictIds.length > 0) {
                            const area   = fromPending ? 'pendingBannerArea' : 'quickActionsBannerArea';
                            const prefix = fromPending ? 'pending' : 'qa';
                            showAutoRejectBannerInSection(area, prefix, conflictIds.length);
                            refreshSchedules();
                            return;
                        }
                    }
                }

                if (fromPending) showSuccessBanner('pendingBannerArea', 'pending', successMsgs[status] ?? 'Status updated.');
                else             showSuccessBanner('quickActionsBannerArea', 'qa', successMsgs[status] ?? 'Status updated.');

                refreshSchedules();
            })
            .catch(err => {
                hideLoadingBanner(fromPending ? 'pending' : 'qa');
                if (fromPending) showErrorBanner('pendingBannerArea', 'pending', 'Please check your connection and retry.');
                else             showErrorBanner('quickActionsBannerArea', 'qa', 'Please check your connection and retry.');
                console.error(err);
            });
    }

    function hasConflict(req) {
        return allSessions.some(s => {
            if (s.id === req.id || s.status !== 'accepted' || s.date !== req.date) return false;
            return timeToMinutes(req.start) < timeToMinutes(s.end)
                && timeToMinutes(req.end)   > timeToMinutes(s.start);
        });
    }

    function getConflictingPendingIds(accepted) {
        const aStart = timeToMinutes(accepted.start);
        const aEnd   = timeToMinutes(accepted.end);
        return allSessions.filter(s =>
            s.id !== accepted.id && s.status === 'pending' && s.date === accepted.date &&
            aStart < timeToMinutes(s.end) && aEnd > timeToMinutes(s.start)
        ).map(s => s.id);
    }

    // APPROVE & REQUEST BOOKINGS
    function approveRequest(id) {
        const req = allSessions.find(s => s.id == id);
        if (!req) return;

        if (hasConflict(req)) {
            const conflict = allSessions.find(s =>
                s.id !== req.id && s.status === 'accepted' && s.date === req.date);
            showConflictBanner(conflict
                ? `Conflicts with <strong>${conflict.student}</strong> (${formatTimeTo12Hour(conflict.start)} – ${formatTimeTo12Hour(conflict.end)}) on ${conflict.date}.`
                : 'This session overlaps with an already-accepted booking.');
            return;
        }

        openConfirmModal({
            title:     'Accept booking?',
            body:      'The student will be notified that their session has been approved.',
            meta:      buildMetaHtml(req),
            variant:   'accept',
            onConfirm: () => updateStatus(id, 'accepted', 'pending'),
        });
    }

    function rejectRequest(id) {
        const req = allSessions.find(s => s.id == id);
        if (!req) return;

        openConfirmModal({
            title:     'Reject booking?',
            body:      'The student will be notified that their session request was declined.',
            meta:      buildMetaHtml(req),
            variant:   'reject',
            onConfirm: () => updateStatus(id, 'rejected', 'pending'),
        });
    }

    // CONFIRMATION MODAL
    const confirmModal    = document.getElementById('confirmModal');
    const confirmModalBox = document.getElementById('confirmModalBox');
    const confirmOkBtn    = document.getElementById('confirmOkBtn');

    confirmModal.addEventListener('click', e => { if (!confirmModalBox.contains(e.target)) closeConfirmModal(); });
    document.getElementById('confirmCancelBtn').addEventListener('click', closeConfirmModal);

    function closeConfirmModal() {
        confirmModal.style.display = 'none';
        confirmOkBtn.onclick = null;
    }

    function openConfirmModal({ title, body, meta, variant, onConfirm }) {
        const variants = {
            accept:  { iconHtml: _iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
            reject:  { iconHtml: _iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
            neutral: { iconHtml: _iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800',       label: 'Confirm' },
        };
        const v = variants[variant] || variants.neutral;

        document.getElementById('confirmIconWrap').style.background = v.iconBg;
        document.getElementById('confirmIconWrap').innerHTML        = v.iconHtml;
        document.getElementById('confirmTitle').textContent         = title;
        document.getElementById('confirmBody').innerHTML            = body;

        const metaEl = document.getElementById('confirmMeta');
        metaEl.innerHTML     = meta || '';
        metaEl.style.display = meta ? 'block' : 'none';

        confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
        confirmOkBtn.textContent = v.label;
        confirmOkBtn.onclick     = () => { closeConfirmModal(); onConfirm(); };

        confirmModal.style.display = 'flex';
    }

    function buildMetaHtml(req) {
        const row = (label, value) => `
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:8px;">
                <span style="color:#9ca3af;">${label}</span>
                <span style="font-weight:600;color:#374151;text-align:right;max-width:160px;
                             white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${value}</span>
            </div>`;
        return row('Student', req.student)
            + row('Subject', req.subject)
            + row('Topic',   req.topic || 'No topic provided')
            + row('Date',    new Date(req.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }))
            + row('Time',    `${formatTimeTo12Hour(req.start)} – ${formatTimeTo12Hour(req.end)}`);
    }

    function _iconCheck(c) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function _iconX(c)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${c}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function _iconInfo(c)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${c}" stroke-width="1.5"/><path d="M10 9v5" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${c}"/></svg>`; }

    // BANNERS
    function showBanner(areaId, bannerId, html, duration = 6000) {
        const area = document.getElementById(areaId);
        if (!area) return;
        let banner = document.getElementById(bannerId);
        if (!banner) {
            banner = document.createElement('div');
            banner.id = bannerId;
            banner.style.cssText = 'border-radius:8px;overflow:hidden;font-size:11px;animation:slideDown 0.2s ease;margin-bottom:4px;';
            area.appendChild(banner);
        }
        banner.innerHTML = html;
        clearTimeout(banner._timer);
        if (duration > 0) banner._timer = setTimeout(() => banner.remove(), duration);
    }

    function showLoadingBanner(areaId, prefix, message) {
        showBanner(areaId, prefix + 'BannerLoading', `
            <div style="border:1px solid #bfdbfe;background:#eff6ff;border-radius:8px;">
                <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;flex-shrink:0;">
                        <circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/>
                        <path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <div style="flex:1;color:#1d4ed8;line-height:1.5;">
                        <span style="font-weight:600;">${message}</span> — please wait...
                    </div>
                </div>
            </div>`, 0);
    }

    function hideLoadingBanner(prefix) {
        document.getElementById(prefix + 'BannerLoading')?.remove();
    }

    function showSuccessBanner(areaId, prefix, message) {
        showBanner(areaId, prefix + 'BannerSuccess', `
            <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:8px;">
                <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;margin-top:1px;">
                        <circle cx="8" cy="8" r="7.5" stroke="#22c55e" stroke-width="1"/>
                        <path d="M5 8l2.5 2.5L11 5.5" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div style="flex:1;color:#15803d;line-height:1.5;"><span style="font-weight:600;">${message}</span></div>
                    <button onclick="document.getElementById('${prefix}BannerSuccess')?.remove()"
                            style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#15803d;font-size:14px;line-height:1;padding:0;">&times;</button>
                </div>
            </div>`);
    }

    function showErrorBanner(areaId, prefix, message) {
        showBanner(areaId, prefix + 'BannerError', `
            <div style="border:1px solid #fca5a5;background:#fef2f2;border-radius:8px;">
                <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;margin-top:1px;">
                        <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                        <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                    </svg>
                    <div style="flex:1;color:#b91c1c;line-height:1.5;"><span style="font-weight:600;">Update failed —</span> ${message}</div>
                    <button onclick="document.getElementById('${prefix}BannerError')?.remove()"
                            style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#b91c1c;font-size:14px;line-height:1;padding:0;">&times;</button>
                </div>
            </div>`);
    }

    function showAutoRejectBannerInSection(areaId, prefix, count) {
        showBanner(areaId, prefix + 'BannerAutoReject', `
            <div style="border:1px solid #fcd34d;background:#fffbeb;border-radius:8px;">
                <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;margin-top:1px;">
                        <path d="M8 1.5L14.5 13H1.5L8 1.5Z" stroke="#d97706" stroke-width="1" stroke-linejoin="round"/>
                        <path d="M8 6v3.5" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="8" cy="11.5" r="0.75" fill="#d97706"/>
                    </svg>
                    <div style="flex:1;color:#92400e;line-height:1.5;">
                        <span style="font-weight:600;">${count} conflicting ${count === 1 ? 'request' : 'requests'} auto-rejected</span>
                        — overlapping bookings were declined automatically.
                    </div>
                    <button onclick="document.getElementById('${prefix}BannerAutoReject')?.remove()"
                            style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#92400e;font-size:14px;line-height:1;padding:0;">&times;</button>
                </div>
            </div>`);
    }

    function showConflictBanner(message) {
        const list = document.getElementById('pendingRequestsList');
        if (!list) return;
        let banner = document.getElementById('conflictBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'conflictBanner';
            banner.style.cssText = 'margin-bottom:12px;border-radius:8px;overflow:hidden;border:1px solid #fca5a5;background:#fef2f2;font-size:11px;animation:slideDown 0.2s ease;';
            list.parentNode.insertBefore(banner, list);
        }
        banner.innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;">
                <div style="flex:1;color:#b91c1c;line-height:1.5;"><span style="font-weight:600;">Cannot approve —</span> ${message}</div>
                <button onclick="document.getElementById('conflictBanner')?.remove()"
                        style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#b91c1c;font-size:14px;">&times;</button>
            </div>`;
        clearTimeout(banner._timer);
        banner._timer = setTimeout(() => banner.remove(), 6000);
    }

    // ORCHESTRATION
    function refreshSchedules() {
        applyFilters();
        generateWeeklySchedule();
        renderQuickActions();
        renderPendingRequests();
    }

    function initDashboard() {
        renderCalendar();
        refreshSchedules();
        updateTableDate();
        updateClock();
    }

    // EVENT LISTENERS
    document.getElementById('prevBtn').addEventListener('click', () => { tablePage--; applyFilters(); });
    document.getElementById('nextBtn').addEventListener('click', () => { tablePage++; applyFilters(); });
    document.getElementById('liveSearchInput').addEventListener('input', () => { tablePage = 0; refreshSchedules(); });
    document.getElementById('statusFilter').addEventListener('change', () => { tablePage = 0; refreshSchedules(); });

    window.addEventListener('load', initDashboard);
    document.addEventListener('livewire:navigated', initDashboard);
</script>
