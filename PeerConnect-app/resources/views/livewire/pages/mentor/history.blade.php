<?php
use function Livewire\Volt\{layout, mount, computed};
use App\Models\Bookings;
use App\Models\StudentProfiles;

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

$studentProfile = computed(function () {
    return StudentProfiles::where('user_id', auth()->id())->first();
});

$summaryCount = computed(function () {
    $profile = $this->studentProfile;
    if (!$profile) return ['total' => 0, 'completed' => 0, 'totalHours' => '0.00 hrs', 'cancelled' => 0, 'pending' => 0];

    $bookings = Bookings::where('student_id', $profile->id)
        ->select('booking_status', 'schedule_start', 'schedule_end')
        ->get();

    $completedSessions = $bookings->where('booking_status', 'completed');
    $totalMinutes = $completedSessions->sum(function ($b) {
        return \Carbon\Carbon::parse($b->schedule_start)->diffInMinutes(\Carbon\Carbon::parse($b->schedule_end));
    });

    return [
        'total'      => $bookings->count(),
        'completed'  => $completedSessions->count(),
        'pending'    => $bookings->where('booking_status', 'pending')->count(),
        'totalHours' => number_format($totalMinutes / 60, 2),
        'cancelled'  => $bookings->where('booking_status', 'cancelled')->count(),
    ];
});

$studentHistory = computed(function () {
    $profile = $this->studentProfile;
    if (!$profile) return [];

    return Bookings::with(['subject', 'mentor.user', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->get() // no orderBy — Alpine handles sorting
        ->map(function ($session) {
            $startCarbon = \Carbon\Carbon::parse($session->schedule_start);
            $endCarbon   = \Carbon\Carbon::parse($session->schedule_end);
            $start = $startCarbon->format('g:i A');
            $end   = $endCarbon->format('g:i A');

            $isOpen     = is_null($session->mentor_id);
            $mentorName = $isOpen
                ? 'ANY'
                : strtoupper($session->mentor->user->lastName ?? 'TBD') . ', ' . ($session->mentor->user->firstName ?? '');

            $statusClass = match ($session->booking_status) {
                'pending'   => 'text-yellow-500',
                'accepted'  => 'text-green-600',
                'rejected'  => 'text-red-900',
                'completed' => 'text-gray-500',
                'cancelled' => 'text-red-600',
                'closed'    => 'text-purple-700',
                'no_show'   => 'text-orange-600',
                default     => 'text-gray-500',
            };

            $statusLabel     = ucfirst(str_replace('_', ' ', $session->booking_status));
            $durationMinutes = $startCarbon->diffInMinutes($endCarbon);
            $durationHours   = $durationMinutes / 60;
            $durationText    = $durationHours === 1
                ? '1 hr'
                : rtrim(rtrim(number_format($durationHours, 2), '0'), '.') . ' hrs';

            return [
                'id'           => $session->id,
                'subject'      => $session->subject->code ?? null,
                'subjectName'  => $session->subject->name ?? null,
                'topic'        => $session->topic,
                'mentor'       => $mentorName,
                'avatar'       => $isOpen ? null : ($session->mentor->avatar ?? null),
                'date'         => \Carbon\Carbon::parse($session->date)->format('F j, Y'),
                'rawDate'      => \Carbon\Carbon::parse($session->date)->format('Y-m-d'),   // for sort
                'rawTime'      => $startCarbon->format('H:i'),                               // for sort
                'time'         => $start . ' - ' . $end,
                'mode'         => $session->tutorialMode->mode ?? null,
                'raw_status'   => strtolower($session->booking_status),
                'statusLabel'  => $statusLabel,
                'statusClass'  => $statusClass,
                'durationHours'=> $durationHours,
                'durationText' => $durationText,
            ];
        })->toArray();
});
?>

<div>
    {{-- Page Heading --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 animate-fade-up">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Booking History
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">View your past and current bookings.</p>
        </div>
    </div>

    {{-- Summary Stat Cards --}}
    <div class="grid grid-cols-[repeat(autofit,_minmax(250px,_1fr))] sm:grid-cols-5 gap-4 mb-6 animate-fade-up [animation-delay:150ms]">
<div onclick="openTotalModal()" class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-slate-500 flex items-center gap-3 lg:gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl"><i class="fa-solid fa-list-check text-slate-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Requests</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['total'] }}</p>
            </div>
        </div>

<div onclick="openPendingModal()" class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending Requests</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['pending'] }}</p>
            </div>
        </div>

<div onclick="openCompletedModal()" class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-3 lg:gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl"><i class="fa-solid fa-circle-check text-blue-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Completed Sessions</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['completed'] }}</p>
            </div>
        </div>

<div onclick="openHoursModal()" class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-3 lg:gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl"><i class="fa-solid fa-stopwatch text-purple-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Hours</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['totalHours'] }}</p>
            </div>
        </div>

<div onclick="openCancelledModal()" class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-red-500 flex items-center gap-3 lg:gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl"><i class="fa-solid fa-ban text-red-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Cancelled Requests</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['cancelled'] }}</p>
            </div>
        </div>
    </div>

    {{-- Sessions Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 animate-fade-up [animation-delay:250ms]"
         x-data="sessionHistory(@js($this->studentHistory))">

        {{-- Header & Controls --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-sm">All Bookings</h2>
                <p class="text-xs text-gray-400 font-medium"
                   x-text="filteredItems.length + ' Session' + (filteredItems.length !== 1 ? 's' : '') + ' found'"></p>
            </div>

            <div class="flex gap-3 flex-wrap">
                {{-- Search Bar --}}
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                    <input type="text"
                           placeholder="Search..."
                           class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow"
                           x-model="search"
                           @input="currentPage = 1">
                </div>

                {{-- Status Filter Dropdown --}}
                <div class="relative" x-data="{ openFilter: false }">
                    <button @click="openFilter = !openFilter"
                            class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-xs font-bold text-slate-600 outline-none flex items-center gap-2 hover:bg-gray-50 transition h-[34px] w-[110px]">
                        <i class="fa-solid fa-filter text-gray-400"></i>
                        Status
                        <span x-show="filterStatuses.length > 0"
                              class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold"
                              x-text="filterStatuses.length"></span>
                    </button>
                    <div x-show="openFilter"
                         x-transition
                         @click.outside="openFilter = false"
                         x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl z-20 py-1">

                        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                            <input type="checkbox"
                                   :checked="filterStatuses.length === 0"
                                   @change="toggleAll()"
                                   class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                            <span>All</span>
                        </label>
                        <div class="border-t border-gray-100 my-1"></div>

                        <template x-for="status in ['pending', 'accepted', 'completed', 'cancelled', 'rejected', 'no_show']" :key="status">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                                <input type="checkbox"
                                       :value="status"
                                       x-model="filterStatuses"
                                       @change="handleStatusChange()"
                                       class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4 transition">
                                <span x-text="status.replace('_', ' ')"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Data Table --}}
<div class="overflow-x-auto overflow-y-visible">
    <table class="w-full text-sm text-left table-fixed overflow-visible">
                <thead class="bg-slate-50 border-b border-gray-100">
                <tr>
                    {{-- Subject --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[15%]">
                        <button @click="toggleSort('subject')"
                                :class="sortCol === 'subject' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 w-full transition">
                            Subject
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'subject'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Topic --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[18%]">
                        <button @click="toggleSort('topic')"
                                :class="sortCol === 'topic' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition">
                            Topic
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'topic'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Mentor --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[18%]">
                        <button @click="toggleSort('mentor')"
                                :class="sortCol === 'mentor' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition">
                            Mentor
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'mentor'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Date & Time --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[17%]">
                        <button @click="toggleSort('date')"
                                :class="sortCol === 'date' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition">
                            Date &amp; Time
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'date'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Mode --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[15%]">
                        <button @click="toggleSort('mode')"
                                :class="sortCol === 'mode' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition">
                            Mode
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'mode'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Status --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[12%]">
                        <button @click="toggleSort('raw_status')"
                                :class="sortCol === 'raw_status' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition">
                            Status
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'raw_status'
                                   ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                                   : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>
                </tr>
                </thead>

                <tbody>
                <template x-for="(booking, index) in paginatedItems" :key="booking.id">
                    <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
<td class="px-5 py-3">
    <p class="font-bold text-slate-700 text-xs truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.subject + ' – ' + booking.subjectName })"
       x-text="booking.subject"></p>
    <p class="text-gray-400 text-xs truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.subjectName })"
       x-text="booking.subjectName"></p>
</td>

<td class="px-5 py-3 max-w-0" style="width:18%;max-width:0;">
    <div x-data="{
            truncated: false,
            show: false,
            x: 0,
            y: 0,
            onEnter(e) {
                if (!this.truncated) return;
                const r = e.currentTarget.getBoundingClientRect();
                this.x = r.left;
                this.y = r.bottom + 6;
                this.show = true;
            },
            onLeave() { this.show = false; }
         }"
         x-init="$nextTick(() => { truncated = $el.querySelector('.topic-text').scrollWidth > $el.querySelector('.topic-text').clientWidth })"
         @mouseenter="onEnter($event)"
         @mouseleave="onLeave()">
        <p class="topic-text text-slate-600 text-xs truncate w-full" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="booking.topic"></p>

        <template x-teleport="body">
            <div x-show="show"
                 :style="`position:fixed;left:${x}px;top:${y}px;z-index:9999;max-width:220px;`"
                 class="bg-slate-800 text-white text-xs rounded px-2 py-1 shadow-lg pointer-events-none">
                <div style="-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;display:-webkit-box;white-space:normal;word-break:break-word;line-height:1.5;"
                     x-text="booking.topic"></div>
            </div>
        </template>
    </div>
</td>

<td class="px-5 py-3">
    <p class="text-xs font-medium text-slate-700 truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.mentor })"
       x-text="booking.mentor"></p>
</td>

<td class="px-5 py-3">
    <p class="text-xs font-medium text-slate-700" x-text="booking.date"></p>
    <p class="text-xs text-gray-400" x-text="booking.time"></p>
</td>

<td class="px-5 py-3 text-xs text-slate-500" x-text="booking.mode"></td>

<td class="px-5 py-3">
    <span :class="'font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize ' + booking.statusClass"
          x-text="booking.statusLabel"></span>
</td>
                    </tr>
                </template>

                {{-- Empty State --}}
                <tr x-show="filteredItems.length === 0" x-cloak>
                    <td colspan="7" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-2xl mb-3 opacity-20"></i>
                            <p class="text-sm font-medium">No matching records found.</p>
                            <p class="text-xs mt-1">Try adjusting your search or filters.</p>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="p-6 flex flex-col items-center justify-center gap-3" x-cloak>
            <div class="flex items-center gap-2" x-show="totalPages > 1">
                <button @click="currentPage--" :disabled="currentPage === 1" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>
                <template x-for="(page, index) in pages" :key="index">
                    <div>
                        <button @click="currentPage = page" :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'" class="w-8 h-8 text-xs font-bold rounded-lg transition" x-text="page" x-show="page !== '...'"></button>
                        <span x-show="page === '...'" class="w-7 h-7 flex items-center justify-center text-[11px] font-bold text-gray-400 tracking-widest shrink-0">...</span>
                    </div>
                </template>
                <button @click="currentPage++" :disabled="currentPage === totalPages" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
            <span class="text-[11px] text-gray-400 font-medium"
                    x-text="filteredItems.length === 0 ? 'No results' : 'Showing ' + pageStart + ' to ' + pageEnd + ' of ' + filteredItems.length">
            </span>
        </div>
    </div>
    {{-- Total Requests Modal --}}
<div id="totalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-list-check"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">All Booking Requests</h2><p class="text-xs text-slate-400" id="totalModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="totalModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('totalModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Pending Modal --}}
<div id="pendingHistModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Pending Requests</h2><p class="text-xs text-slate-400" id="pendingHistModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="pendingHistModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('pendingHistModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Completed Modal --}}
<div id="completedHistModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-circle-check"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Completed Sessions</h2><p class="text-xs text-slate-400" id="completedHistModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="completedHistModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('completedHistModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Hours Modal --}}
<div id="hoursHistModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-stopwatch"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Total Session Hours</h2><p class="text-xs text-slate-400">Completed sessions only</p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="hoursHistModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('hoursHistModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Cancelled Modal --}}
<div id="cancelledHistModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-red-100 text-red-500 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-ban"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Cancelled Requests</h2><p class="text-xs text-slate-400" id="cancelledHistModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="cancelledHistModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('cancelledHistModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>
</div>
<script>
const _histData = @json($this->studentHistory);

function _histRow(item, pillColor, pillLabel) {
    return `<div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
            ${(item.mentor ?? '?').slice(0,2).toUpperCase()}
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-slate-700 truncate">${item.subject ?? '—'} — ${item.topic || '—'}</p>
            <p class="text-[10px] text-gray-400">${item.mentor ?? '—'} &mdash; ${item.date ?? '—'}</p>
            <p class="text-[10px] text-gray-400">${item.time ?? '—'}</p>
        </div>
        <div class="flex-shrink-0">
            <span class="${pillColor} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize" style="font-size:10px;line-height:1;">${pillLabel}</span>
        </div>
    </div>`;
}

function openTotalModal() {
    const items = [..._histData].sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('totalModalCount').innerText = `${items.length} request${items.length !== 1 ? 's' : ''}`;
    document.getElementById('totalModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No bookings yet.</p>`
        : items.map(i => _histRow(i, i.statusClass, i.statusLabel)).join('');
    document.getElementById('totalModal').style.display = 'flex';
}

function openPendingModal() {
    const items = _histData.filter(i => i.raw_status === 'pending').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('pendingHistModalCount').innerText = `${items.length} request${items.length !== 1 ? 's' : ''}`;
    document.getElementById('pendingHistModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No pending requests.</p>`
        : items.map(i => _histRow(i, 'text-yellow-500', 'Pending')).join('');
    document.getElementById('pendingHistModal').style.display = 'flex';
}

function openCompletedModal() {
    const items = _histData.filter(i => i.raw_status === 'completed').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('completedHistModalCount').innerText = `${items.length} session${items.length !== 1 ? 's' : ''}`;
    document.getElementById('completedHistModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No completed sessions yet.</p>`
        : items.map(i => _histRow(i, 'text-gray-500', 'Completed')).join('');
    document.getElementById('completedHistModal').style.display = 'flex';
}

function openHoursModal() {
    const items = _histData.filter(i => i.raw_status === 'completed').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('hoursHistModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No completed sessions yet.</p>`
        : items.map(i => {
            return `<div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                    ${(i.mentor ?? '?').slice(0,2).toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-700 truncate">${i.subject ?? '—'} — ${i.topic || '—'}</p>
                    <p class="text-[10px] text-gray-400">${i.mentor ?? '—'} &mdash; ${i.date ?? '—'}</p>
                    <p class="text-[10px] text-gray-400">${i.time ?? '—'}</p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="text-xs font-black text-purple-600">${i.durationText ?? '—'}</span>
                    <span class="text-gray-500 font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize" style="font-size:10px;line-height:1;">Completed</span>
                </div>
            </div>`;
        }).join('');
    document.getElementById('hoursHistModal').style.display = 'flex';
}

function openCancelledModal() {
    const items = _histData.filter(i => i.raw_status === 'cancelled').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('cancelledHistModalCount').innerText = `${items.length} request${items.length !== 1 ? 's' : ''}`;
    document.getElementById('cancelledHistModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No cancelled requests.</p>`
        : items.map(i => _histRow(i, 'text-red-600', 'Cancelled')).join('');
    document.getElementById('cancelledHistModal').style.display = 'flex';
}
</script>
