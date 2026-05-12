<?php

use function Livewire\Volt\{layout, mount, computed};
use App\Models\Bookings;
use App\Models\MentorProfiles;
use App\Models\MentorAvailabilities;
use App\Models\MentorSubjects;

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

$sessions = computed(function () {
    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
    if (!$mentorProfile) return [];

    // AUTO-COMPLETE: only affect bookings NOT manually touched today
    Bookings::where('mentor_id', $mentorProfile->id)
        ->where('booking_status', 'accepted')
        ->whereDate('date', '<', today())
        ->whereDate('updated_at', '<', today())
        ->update([
            'booking_status' => 'completed',
            'completed_at'   => now(),
        ]);

    $mySubjectIds = MentorSubjects::where('mentor_id', $mentorProfile->id)->pluck('subject_id');
    $mySched      = MentorAvailabilities::where('mentor_id', $mentorProfile->id)->get();

    $allBookings = Bookings::with(['student.user', 'subject', 'tutorialMode'])
        ->where(function ($query) use ($mentorProfile) {
            $query->where('mentor_id', $mentorProfile->id);
        })->orWhere(function ($query) use ($mySubjectIds) {
            $query->whereNull('mentor_id')
                ->where('booking_status', 'pending')
                ->whereIn('subject_id', $mySubjectIds);
        })
        ->get();

    $validBookings = $allBookings->filter(function ($booking) use ($mentorProfile, $mySched) {
        if ($booking->mentor_id === $mentorProfile->id) return true;

        $bookingDay = strtolower(\Carbon\Carbon::parse($booking->date)->format('l'));

        return $mySched->contains(function ($avail) use ($bookingDay, $booking) {
            if (strtolower($avail->day_of_week) !== $bookingDay) return false;

            $availStart = strtotime($avail->start_time);
            $availEnd   = strtotime($avail->end_time);
            $bookStart  = strtotime($booking->schedule_start);
            $bookEnd    = strtotime($booking->schedule_end);

            return $availStart <= $bookStart && $availEnd >= $bookEnd;
        });
    });

    return $validBookings->map(function ($b) {
        $start = \Carbon\Carbon::parse($b->schedule_start);
        $end   = \Carbon\Carbon::parse($b->schedule_end);

        $durationMinutes = $start->diffInMinutes($end);
        $durationHours   = $durationMinutes / 60;
        $durationText    = $durationHours == 1
            ? '1 hr'
            : rtrim(rtrim(number_format($durationHours, 2), '0'), '.') . ' hrs';

        return [
            'id'            => $b->id,
            'student'       => optional(optional($b->student)->user)->firstName
                ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                : 'Unknown',
            'subject'       => optional($b->subject)->code ?? 'N/A',
            'subjectName'   => optional($b->subject)->name ?? '',
            'topic'         => $b->topic ?? '—',
            'date'          => $b->date ? \Carbon\Carbon::parse($b->date)->format('F j, Y') : '—',
            'rawDate'       => $b->date ? \Carbon\Carbon::parse($b->date)->format('Y-m-d') : '',
            'mode' => optional($b->tutorialMode)->mode? trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', preg_replace('/\s*(Tutorial|Session|Sessions)\s*/i', ' ', optional($b->tutorialMode)->mode))): '—',            'yearLevel'     => optional(optional($b->student)->yearLevel)->name ?? 'N/A',
            'degreeProgram' => optional(optional($b->student)->degreeProgram)->name ?? 'N/A',
            'start'         => $start->format('H:i'),
            'end'           => $end->format('H:i'),
            'time'          => $start->format('g:i A') . ' – ' . $end->format('g:i A'),
            'duration'      => $start->format('h:i A') . ' - ' . $end->format('h:i A') . ' (' . $durationText . ')',
            'durationHours' => $durationHours,
            'status'        => $b->booking_status,
            'is_open'       => is_null($b->mentor_id),
        ];
    })->values()->toArray();
});

$summaryCounts = computed(function () {
    $sessions  = $this->sessions;
    $statuses  = array_column($sessions, 'status');
    $completed = array_filter($sessions, fn($s) => $s['status'] === 'completed');
    $totalHours = array_sum(array_column($completed, 'durationHours'));

    return [
        'total'     => count($sessions),
        'accepted'  => count(array_filter($statuses, fn($s) => $s === 'accepted')),
        'pending'   => count(array_filter($statuses, fn($s) => $s === 'pending')),
        'completed' => count(array_filter($completed)),
        'totalHours'=> number_format($totalHours, 2),
    ];
});

?>

<div>
    {{-- Page Heading --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 animate-fade-up">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Tutorial Sessions
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">All student-selected mentor sessions.</p>
        </div>
    </div>

    {{-- Summary Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6 animate-fade-up [animation-delay:150ms]">
    <div onclick="openTotalModal()" class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-list-check text-slate-500"></i></div>
        <div class="min-w-0 flex-1">
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">All Sessions</h3>
            <p class="text-2xl font-black text-slate-800 truncate" id="statTotal">{{ $this->summaryCounts['total'] }}</p>
        </div>
    </div>
    <div onclick="openAcceptedModal()" class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-circle-check text-green-600"></i></div>
        <div class="min-w-0 flex-1">
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Accepted</h3>
            <p class="text-2xl font-black text-slate-800 truncate" id="statAccepted">{{ $this->summaryCounts['accepted'] }}</p>
        </div>
    </div>
    <div onclick="openPendingModal()" class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
        <div class="min-w-0 flex-1">
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending</h3>
            <p class="text-2xl font-black text-slate-800 truncate" id="statPending">{{ $this->summaryCounts['pending'] }}</p>
        </div>
    </div>
    <div onclick="openCompletedModal()" class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-circle-check text-blue-600"></i></div>
        <div class="min-w-0 flex-1">
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Completed</h3>
            <p class="text-2xl font-black text-slate-800 truncate" id="statCompleted">{{ $this->summaryCounts['completed'] }}</p>
        </div>
    </div>
    <div onclick="openHoursModal()" class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
        <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-stopwatch text-purple-600"></i></div>
        <div class="min-w-0 flex-1">
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Hours</h3>
            <p class="text-2xl font-black text-slate-800 truncate" id="statHours">{{ $this->summaryCounts['totalHours'] }}</p>
        </div>
    </div>
</div>

    {{-- Sessions Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 animate-fade-up [animation-delay:250ms]"
         x-data="tutorialSessions(@js($this->sessions))">

        {{-- Header & Controls --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-sm">All Sessions</h2>
                <p class="text-xs text-gray-400 font-medium"
                   x-text="filteredItems.length + ' Session' + (filteredItems.length !== 1 ? 's' : '') + ' found'"></p>
            </div>

            <div class="flex gap-3 flex-wrap">
                {{-- Search --}}
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm"></i>
                    <input type="text"
                           placeholder="Search..."
                           class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow"
                           x-model="search"
                           @input="currentPage = 1">
                </div>

                {{-- Status Filter --}}
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
                                   @change="filterStatuses = []; currentPage = 1;"
                                   class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                            <span>All</span>
                        </label>
                        <div class="border-t border-gray-100 my-1"></div>

                        <template x-for="status in ['pending', 'accepted', 'completed', 'cancelled', 'rejected', 'no_show']" :key="status">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                                <input type="checkbox"
                                       :value="status"
                                       x-model="filterStatuses"
                                       @change="currentPage = 1"
                                       class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                                <span x-text="status.replace('_', ' ')"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Banner Area --}}
        <div id="sessionsBannerArea" class="flex flex-col gap-2 px-5 pt-3"></div>

        {{-- Data Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left table-fixed">
                <thead class="bg-slate-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider w-[15%]">
                        <button @click="toggleSort('student')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'student' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Student
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'student' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider w-[12%]">
                        <button @click="toggleSort('subject')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'subject' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Subject
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'subject' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

<th class="px-5 py-3 pl-14 text-xs font-bold text-gray-400 tracking-wider w-[20%]">
        <button @click="toggleSort('topic')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'topic' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Topic
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'topic' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider w-[15%]">
                        <button @click="toggleSort('rawDate')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'rawDate' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Date &amp; Time
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'rawDate' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider w-[12%]">
                        <button @click="toggleSort('mode')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'mode' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Mode
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'mode' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider w-[7%]">
                        <button @click="toggleSort('status')" class="flex items-center gap-1 transition"
                                :class="sortCol === 'status' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'">
                            Status
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'status' ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    <th class="px-5 py-3 text-xs font-bold text-gray-400 tracking-wider text-center w-[8%]">
                        Actions
                    </th>
                </tr>
                </thead>

                <tbody>
                <template x-for="(s, index) in paginatedItems" :key="s.id">
                    <tr class="border-b border-gray-50 hover:bg-slate-50 transition group" style="height:56px;">

 {{-- Student --}}
<td class="px-5 py-3 max-w-0 align-middle" style="width:15%;">
    <p class="font-bold text-slate-700 text-xs truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = s.student })"
       x-text="s.student"></p>
    <p class="text-xs text-gray-400 truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = s.yearLevel + ' – ' + s.degreeProgram })"
       x-text="s.yearLevel + ' – ' + s.degreeProgram"></p>
</td>

{{-- Subject --}}
<td class="px-5 py-3 max-w-0 align-middle" style="width:12%;">
    <p class="font-bold text-slate-700 text-xs truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = s.subject })"
       x-text="s.subject"></p>
    <p class="text-xs text-gray-400 truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = s.subjectName })"
       x-text="s.subjectName"></p>
</td>

{{-- Topic --}}
<td class="px-5 py-3 pl-14 max-w-0" style="width:20%;max-width:0;"
    x-init="$nextTick(() => { const p = $el.querySelector('.topic-text'); if (p && p.scrollWidth > p.clientWidth) $el.title = s.topic })">
    <p class="topic-text text-xs text-slate-600 truncate w-full" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="s.topic"></p>
</td>

{{-- Date and Time --}}
<td class="px-5 py-3">
    <p class="text-xs font-medium text-slate-700" x-text="s.date"></p>
    <p class="text-xs text-gray-400" x-text="s.time"></p>
</td>

{{-- Mode --}}
<td class="px-5 py-3">
    <p class="text-xs text-slate-500 truncate"
       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = s.mode })"
       x-text="s.mode"></p>
</td>

{{-- Status --}}
<td class="px-5 py-3">
    <span class="font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize"
          style="white-space:nowrap;"
          :class="getStatusColor(s.status)"
          x-text="getStatusLabel(s.status)"></span>
</td>


                        {{-- Actions --}}
                        <td class="px-5 py-3 overflow-hidden">
                            <div class="relative flex items-center justify-center min-h-[28px]">

                                {{-- Dash for cancelled — always visible --}}
                                <template x-if="s.status === 'cancelled'">
                                    <span class="text-gray-300 text-xs">—</span>
                                </template>

                                {{-- Dot indicator — centered, fades out on row hover --}}
                                <div class="w-2 h-2 rounded-full group-hover:opacity-0 transition-opacity duration-150 flex-shrink-0"
                                     x-show="s.status !== 'cancelled'"
                                     :class="{
                                     'bg-yellow-400':  s.status === 'pending',
                                     'bg-emerald-500': s.status === 'accepted',
                                     'bg-gray-300':    ['completed', 'no_show', 'rejected'].includes(s.status),
                                 }">
                                </div>

                                {{-- Actions (revealed on row hover) --}}
                                <template x-if="s.status !== 'cancelled'">
                                    <div class="absolute inset-0 flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">

                                        {{-- pending + assigned --}}
                                        <template x-if="s.status === 'pending' && !s.is_open">
                                            <div class="flex gap-1">
                                                <button @click="updateStatus(s.id, 'accepted')"
                                                        title="Accept"
                                                        class="w-7 h-7 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 flex items-center justify-center transition hover:scale-110">
                                                    <i class="fa-solid fa-check" style="font-size:11px;"></i>
                                                </button>
                                                <button @click="updateStatus(s.id, 'rejected')"
                                                        title="Reject"
                                                        class="w-7 h-7 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition hover:scale-110">
                                                    <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                                                </button>
                                            </div>
                                        </template>

                                        {{-- pending + open (claim) --}}
                                        <template x-if="s.status === 'pending' && s.is_open">
                                            <button @click="updateStatus(s.id, 'accepted')"
                                                    title="Claim Session"
                                                    class="w-7 h-7 rounded-lg bg-purple-100 hover:bg-purple-200 text-purple-700 flex items-center justify-center transition hover:scale-110">
                                                <i class="fa-solid fa-hand-pointer" style="font-size:11px;"></i>
                                            </button>
                                        </template>

                                        {{-- accepted --}}
                                        <template x-if="s.status === 'accepted'">
                                            <div class="flex gap-1">
                                                <button @click="updateStatus(s.id, 'completed')"
                                                        title="Complete"
                                                        class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center transition hover:scale-110">
                                                    <i class="fa-solid fa-flag-checkered" style="font-size:11px;"></i>
                                                </button>
                                                <button @click="updateStatus(s.id, 'no_show')"
                                                        title="No-show"
                                                        class="w-7 h-7 rounded-lg bg-orange-100 hover:bg-orange-200 text-orange-600 flex items-center justify-center transition hover:scale-110">
                                                    <i class="fa-solid fa-user-slash" style="font-size:11px;"></i>
                                                </button>
                                                <button @click="updateStatus(s.id, 'cancelled')"
                                                        title="Cancel"
                                                        class="w-7 h-7 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition hover:scale-110">
                                                    <i class="fa-solid fa-ban" style="font-size:11px;"></i>
                                                </button>
                                            </div>
                                        </template>

                                        {{-- completed / no_show / rejected — undo --}}
                                        <template x-if="['completed','no_show','rejected'].includes(s.status)">
                                            <button @click="updateStatus(s.id, s.status === 'completed' ? 'accepted' : 'pending')"
                                                    title="Undo"
                                                    class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center transition hover:scale-110">
                                                <i class="fa-solid fa-rotate-left" style="font-size:11px;"></i>
                                            </button>
                                        </template>

                                    </div>
                                </template>

                            </div>
                        </td>

                    </tr>
                </template>

                {{-- Empty State --}}
                <tr x-show="filteredItems.length === 0" x-cloak>
                    <td colspan="8" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-2xl mb-3 opacity-20"></i>
                            <p class="text-sm font-medium">No matching sessions found.</p>
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
                <button @click="if(currentPage > 1) currentPage--" :disabled="currentPage === 1" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>
                <template x-for="(page, index) in pages" :key="index">
                    <div>
                        <button @click="currentPage = page" :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'" class="w-8 h-8 text-xs font-bold rounded-lg transition" x-text="page" x-show="page !== '...'"></button>
                        <span x-show="page === '...'" class="w-7 h-7 flex items-center justify-center text-[11px] font-bold text-gray-400 tracking-widest shrink-0">...</span>
                    </div>
                </template>
                <button @click="if(currentPage < totalPages) currentPage++" :disabled="currentPage === totalPages" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
            <span class="text-[11px] text-gray-400 font-medium"
                    x-text="filteredItems.length === 0 ? 'No results' : 'Showing ' + pageStart + ' to ' + pageEnd + ' of ' + filteredItems.length">
            </span>
        </div>
    </div>

    {{-- Confirmation Modal --}}
    <div id="confirmModal" style="display:none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
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
    {{-- Total Modal --}}
<div id="totalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-list-check"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">All Sessions</h2><p class="text-xs text-slate-400" id="totalModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="totalModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('totalModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Accepted Modal --}}
<div id="acceptedModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-circle-check"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Accepted Sessions</h2><p class="text-xs text-slate-400" id="acceptedModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="acceptedModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('acceptedModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Pending Modal --}}
<div id="pendingSessModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Pending Sessions</h2><p class="text-xs text-slate-400" id="pendingSessModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="pendingSessModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('pendingSessModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Completed Modal --}}
<div id="completedSessModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-circle-check"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Completed Sessions</h2><p class="text-xs text-slate-400" id="completedSessModalCount"></p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="completedSessModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('completedSessModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>

{{-- Hours Modal --}}
<div id="hoursSessModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-11 h-11 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xl flex-shrink-0"><i class="fa-solid fa-stopwatch"></i></div>
            <div class="flex-1"><h2 class="text-lg font-extrabold text-slate-800">Total Session Hours</h2><p class="text-xs text-slate-400">Completed sessions only</p></div>
        </div>
        <div class="px-6 py-4 max-h-80 overflow-y-auto" id="hoursSessModalBody"></div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
            <button onclick="document.getElementById('hoursSessModal').style.display='none'" class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">Close</button>
        </div>
    </div>
</div>
</div>

<script>
    const csrfToken   = '{{ csrf_token() }}';
    const sessionsUrl = '{{ route('mentor.sessions.update') }}';

    /* ── Conflict helpers ── */
    function toMin(t) {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

    function hasConflict(req, items) {
        return items.some(s => {
            if (s.id === req.id) return false;
            if (!['accepted', 'completed'].includes(s.status)) return false;
            if (s.date !== req.date) return false;
            const sStart = toMin(s.start), sEnd = toMin(s.end);
            const rStart = toMin(req.start), rEnd = toMin(req.end);
            return rStart < sEnd && rEnd > sStart;
        });
    }

    function getConflictingPendingIds(acceptedSession, items) {
        const aStart = toMin(acceptedSession.start);
        const aEnd   = toMin(acceptedSession.end);
        return items
            .filter(s => {
                if (s.id === acceptedSession.id) return false;
                if (s.status !== 'pending') return false;
                if (s.date !== acceptedSession.date) return false;
                const sStart = toMin(s.start), sEnd = toMin(s.end);
                return aStart < sEnd && aEnd > sStart;
            })
            .map(s => s.id);
    }

    /* ── Banners ── */
    function showBanner(id, html) {
        const area = document.getElementById('sessionsBannerArea');
        let banner = document.getElementById(id);
        if (!banner) {
            banner = document.createElement('div');
            banner.id = id;
            banner.style.cssText = 'border-radius:8px; overflow:hidden; font-size:11px; animation:slideDown 0.2s ease; margin-bottom:4px;';
            area.appendChild(banner);
        }
        banner.innerHTML = html;
        clearTimeout(banner._timer);
        banner._timer = setTimeout(() => banner.remove(), 6000);
    }

    function showConflictBanner(message) {
        showBanner('conflictBanner', `
            <div style="border:1px solid #fca5a5; background:#fef2f2; border-radius:8px;">
                <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                    <div style="flex-shrink:0; margin-top:2px;">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                            <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                        </svg>
                    </div>
                    <div style="flex:1; color:#b91c1c; line-height:1.5;">
                        <span style="font-weight:600;">Cannot approve —</span> ${message}
                    </div>
                    <button onclick="document.getElementById('conflictBanner').remove()"
                        style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                </div>
            </div>
        `);
    }

    function showAutoRejectBanner(count) {
        showBanner('autoRejectBanner', `
            <div style="border:1px solid #fcd34d; background:#fffbeb; border-radius:8px;">
                <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                    <div style="flex-shrink:0; margin-top:2px;">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                            <path d="M8 1.5L14.5 13H1.5L8 1.5Z" stroke="#d97706" stroke-width="1" stroke-linejoin="round"/>
                            <path d="M8 6v3.5" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/>
                            <circle cx="8" cy="11.5" r="0.75" fill="#d97706"/>
                        </svg>
                    </div>
                    <div style="flex:1; color:#92400e; line-height:1.5;">
                        <span style="font-weight:600;">${count} conflicting ${count === 1 ? 'request' : 'requests'} auto-rejected</span>
                        — overlapping bookings were declined automatically.
                    </div>
                    <button onclick="document.getElementById('autoRejectBanner').remove()"
                        style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#92400e; font-size:14px; line-height:1; padding:0;">&times;</button>
                </div>
            </div>
        `);
    }

    function showLoadingBanner() {
        showBanner('loadingBanner', `
            <div style="border:1px solid #bfdbfe; background:#eff6ff; border-radius:8px;">
                <div style="display:flex; align-items:center; gap:8px; padding:10px 12px;">
                    <div style="flex-shrink:0;">
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;">
                            <circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/>
                            <path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div style="flex:1; color:#1d4ed8; line-height:1.5; font-size:11px;">
                        <span style="font-weight:600;">Updating session status</span> — please wait...
                    </div>
                </div>
            </div>
        `);
        const banner = document.getElementById('loadingBanner');
        if (banner) clearTimeout(banner._timer);
    }

    function hideLoadingBanner() {
        document.getElementById('loadingBanner')?.remove();
    }

    /* ── Summary card updater ── */
    function updateSummaryCounts(items) {
        const statuses      = items.map(s => s.status);
        const completedHours = items
            .filter(s => s.status === 'completed')
            .reduce((sum, s) => sum + s.durationHours, 0);

        document.getElementById('statTotal').textContent     = items.length;
        document.getElementById('statAccepted').textContent  = statuses.filter(s => s === 'accepted').length;
        document.getElementById('statPending').textContent   = statuses.filter(s => s === 'pending').length;
        document.getElementById('statCompleted').textContent = statuses.filter(s => s === 'completed').length;
        document.getElementById('statHours').textContent     = parseFloat(completedHours.toFixed(2));
    }

    /* ── Confirmation modal ── */
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
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirmModal(); });

    function closeConfirmModal() {
        confirmModal.style.display = 'none';
        confirmOkBtn.onclick = null;
    }

    function iconCheck() { return (c) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function iconX()     { return (c) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${c}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function iconInfo()  { return (c) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${c}" stroke-width="1.5"/><path d="M10 9v5" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${c}"/></svg>`; }

    function openConfirmModal({ title, body, meta, variant, onConfirm }) {
        const variants = {
            accept:  { icon: iconCheck(), iconBg: 'bg-emerald-100', iconColor: '#059669', btnBg: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
            reject:  { icon: iconX(),     iconBg: 'bg-red-100',     iconColor: '#dc2626', btnBg: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
            neutral: { icon: iconInfo(),  iconBg: 'bg-gray-100',    iconColor: '#64748b', btnBg: 'bg-gray-700 hover:bg-gray-800',        label: 'Confirm' },
        };

        const v = variants[variant] || variants.neutral;
        confirmIconWrap.className = `w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 ${v.iconBg}`;
        confirmIconWrap.innerHTML = v.icon(v.iconColor);
        confirmTitle.textContent  = title;
        confirmBody.innerHTML     = body;
        confirmMeta.innerHTML     = meta || '';
        confirmMeta.style.display = meta ? 'block' : 'none';
        confirmOkBtn.className    = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnBg}`;
        confirmOkBtn.textContent  = v.label;
        confirmOkBtn.onclick      = () => { closeConfirmModal(); onConfirm(); };
        confirmModal.style.display = 'flex';
    }

    /* ── updateStatus — called from Alpine @click ── */
    function updateStatus(id, status, items) {
        const req = items.find(s => s.id == id);
        if (!req) return;

        if (status === 'accepted' && req.status !== 'completed' && hasConflict(req, items)) {
            const conflict = items.find(s =>
                s.id !== req.id && s.status === 'accepted' && s.date === req.date
            );
            const conflictInfo = conflict
                ? `Conflicts with <strong>${conflict.student}</strong> (${conflict.time}) on ${conflict.date}.`
                : 'This session overlaps with an existing booking.';
            showConflictBanner(conflictInfo);
            return;
        }

        const isClaiming   = status === 'accepted' && req.status === 'pending' && req.is_open;
        const isUncomplete = status === 'accepted' && req.status === 'completed';

        const dialogConfig = {
            accepted: isUncomplete ? {
                title: 'Revert to accepted?',
                body:  'This will mark the session as accepted again, reversing the completed status.',
                variant: 'neutral',
            } : isClaiming ? {
                title: 'Claim Open Session?',
                body:  'You are about to claim this session. It will be permanently assigned to you.',
                variant: 'accept',
            } : {
                title: 'Accept booking?',
                body:  'The student will be notified that their session has been approved.',
                variant: 'accept',
            },
            pending:   { title: 'Revert to pending?',  body: 'This will move the session back to pending, allowing it to be reviewed again.', variant: 'neutral' },
            rejected:  { title: 'Reject booking?',     body: 'The student will be notified that their request was declined.',                  variant: 'reject'  },
            completed: { title: 'Mark as completed?',  body: 'This will mark the session as done.',                                           variant: 'neutral' },
            no_show:   { title: 'Mark as no-show?',    body: 'This will record that the student did not attend the session.',                  variant: 'reject'  },
            cancelled: { title: 'Cancel session?',     body: 'This will cancel the accepted session.',                                        variant: 'reject'  },
        };

        const cfg = dialogConfig[status] || { title: 'Confirm action', body: 'Are you sure?', variant: 'neutral' };

        const metaHtml = `
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Student</span>
                <span class="font-medium text-gray-700 text-right truncate max-w-[60%]">${req.student}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Subject</span>
                <span class="font-medium text-gray-700 text-right">${req.subject}${req.subjectName ? ' – ' + req.subjectName : ''}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Topic</span>
                <span class="font-medium text-gray-700 text-right truncate max-w-[60%]">${req.topic}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Date</span>
                <span class="font-medium text-gray-700 text-right">${req.date}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Time</span>
                <span class="font-medium text-gray-700 text-right">${req.time}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Mode</span>
                <span class="font-medium text-gray-700 text-right">${req.mode}</span>
            </div>
        `;

        openConfirmModal({
            title:     cfg.title,
            body:      cfg.body,
            meta:      metaHtml,
            variant:   cfg.variant,
            onConfirm: () => commitStatus(id, status, req, items),
        });
    }

    /* ── commitStatus ── */
    function commitStatus(id, status, target, items) {
        showLoadingBanner();

        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('booking_id', id);
        formData.append('booking_status', status);

        fetch(sessionsUrl, { method: 'POST', body: formData })
            .then(res => {
                if (!res.ok) throw new Error('Request failed');

                target.status = status;

                if (status === 'accepted') {
                    const conflictingIds = getConflictingPendingIds(target, items);
                    if (conflictingIds.length > 0) {
                        let completed = 0;
                        conflictingIds.forEach(conflictId => {
                            const conflictSession = items.find(s => s.id == conflictId);
                            if (conflictSession) conflictSession.status = 'rejected';

                            const fd = new FormData();
                            fd.append('_token', csrfToken);
                            fd.append('booking_id', conflictId);
                            fd.append('booking_status', 'rejected');

                            fetch(sessionsUrl, { method: 'POST', body: fd })
                                .then(() => {
                                    completed++;
                                    if (completed === conflictingIds.length) {
                                        hideLoadingBanner();
                                        updateSummaryCounts(items);
                                        showAutoRejectBanner(conflictingIds.length);
                                    }
                                })
                                .catch(err => {
                                    hideLoadingBanner();
                                    console.error('Auto-reject failed for id', conflictId, err);
                                });
                        });
                        updateSummaryCounts(items);
                        return;
                    }
                }

                hideLoadingBanner();
                updateSummaryCounts(items);
            })
            .catch(() => {
                hideLoadingBanner();
                showBanner('errorBanner', `
                    <div style="border:1px solid #fca5a5; background:#fef2f2; border-radius:8px;">
                        <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                            <div style="flex-shrink:0; margin-top:2px;">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                    <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                                    <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                                </svg>
                            </div>
                            <div style="flex:1; color:#b91c1c; line-height:1.5;">
                                <span style="font-weight:600;">Update failed —</span> please check your connection and try again.
                            </div>
                            <button onclick="document.getElementById('errorBanner').remove()"
                                style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                        </div>
                    </div>
                `);
            });
    }
const _sessData = @json($this->sessions);

function _getStatusColor(status) {
    const map = { pending:'text-yellow-500', accepted:'text-green-600', completed:'text-gray-500', rejected:'text-red-500', cancelled:'text-red-600', no_show:'text-orange-600' };
    return map[status] ?? 'text-slate-400';
}
function _getStatusLabel(status) {
    const map = { pending:'Pending', accepted:'Accepted', completed:'Completed', rejected:'Rejected', cancelled:'Cancelled', no_show:'No Show' };
    return map[status] ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '—');
}

function _sessRow(item, pillColor, pillLabel) {
    const line1 = `${item.subject ?? '—'} — ${item.topic || '—'}`;
    const line2 = `${item.student ?? '—'} — ${item.date ?? '—'}`;
    return `<div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
            ${(item.student ?? '?').slice(0,2).toUpperCase()}
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-slate-700 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" onmouseenter="this.title=this.scrollWidth>this.clientWidth?'${line1.replace(/'/g,'&#39;').replace(/"/g,'&quot;')}':'';">${line1}</p>
            <p class="text-[10px] text-gray-400 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" onmouseenter="this.title=this.scrollWidth>this.clientWidth?'${line2.replace(/'/g,'&#39;').replace(/"/g,'&quot;')}':'';">${line2}</p>
            <p class="text-[10px] text-gray-400 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${item.time ?? '—'}</p>
        </div>
        <div class="flex-shrink-0">
            <span class="${pillColor} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize" style="font-size:10px;line-height:1;">${pillLabel}</span>
        </div>
    </div>`;
}

function openTotalModal() {
    const items = [..._sessData].sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('totalModalCount').innerText = `${items.length} session${items.length !== 1 ? 's' : ''}`;
    document.getElementById('totalModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No sessions yet.</p>`
        : items.map(i => _sessRow(i, _getStatusColor(i.status), _getStatusLabel(i.status))).join('');
    document.getElementById('totalModal').style.display = 'flex';
}

function openAcceptedModal() {
    const items = _sessData.filter(i => i.status === 'accepted').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('acceptedModalCount').innerText = `${items.length} session${items.length !== 1 ? 's' : ''}`;
    document.getElementById('acceptedModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No accepted sessions.</p>`
        : items.map(i => _sessRow(i, 'text-green-600', 'Accepted')).join('');
    document.getElementById('acceptedModal').style.display = 'flex';
}

function openPendingModal() {
    const items = _sessData.filter(i => i.status === 'pending').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('pendingSessModalCount').innerText = `${items.length} session${items.length !== 1 ? 's' : ''}`;
    document.getElementById('pendingSessModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No pending sessions.</p>`
        : items.map(i => _sessRow(i, 'text-yellow-500', 'Pending')).join('');
    document.getElementById('pendingSessModal').style.display = 'flex';
}

function openCompletedModal() {
    const items = _sessData.filter(i => i.status === 'completed').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('completedSessModalCount').innerText = `${items.length} session${items.length !== 1 ? 's' : ''}`;
    document.getElementById('completedSessModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No completed sessions yet.</p>`
        : items.map(i => _sessRow(i, 'text-gray-500', 'Completed')).join('');
    document.getElementById('completedSessModal').style.display = 'flex';
}

function openHoursModal() {
    const items = _sessData.filter(i => i.status === 'completed').sort((a,b) => b.rawDate.localeCompare(a.rawDate));
    document.getElementById('hoursSessModalBody').innerHTML = !items.length
        ? `<p class="text-xs text-gray-400 italic py-4 text-center">No completed sessions yet.</p>`
        : items.map(i => {
            const hrs = typeof i.durationHours === 'number'
                ? (i.durationHours === 1 ? '1 hr' : i.durationHours.toFixed(2).replace(/\.?0+$/, '') + ' hrs')
                : '—';
            const line1 = `${i.subject ?? '—'} — ${i.topic || '—'}`;
            const line2 = `${i.student ?? '—'} — ${i.date ?? '—'}`;
            return `<div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                    ${(i.student ?? '?').slice(0,2).toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-700 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" onmouseenter="this.title=this.scrollWidth>this.clientWidth?'${line1.replace(/'/g,'&#39;').replace(/"/g,'&quot;')}':'';">${line1}</p>
                    <p class="text-[10px] text-gray-400 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" onmouseenter="this.title=this.scrollWidth>this.clientWidth?'${line2.replace(/'/g,'&#39;').replace(/"/g,'&quot;')}':'';">${line2}</p>
                    <p class="text-[10px] text-gray-400 truncate" style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${i.time ?? '—'}</p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="text-xs font-black text-purple-600">${hrs}</span>
                    <span class="text-gray-500 font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize" style="font-size:10px;line-height:1;">Completed</span>
                </div>
            </div>`;
        }).join('');
    document.getElementById('hoursSessModal').style.display = 'flex';
}
</script>
