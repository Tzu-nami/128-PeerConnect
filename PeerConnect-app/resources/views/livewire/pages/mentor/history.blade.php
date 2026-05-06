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
        'totalHours' => number_format($totalMinutes / 60, 2) . ' hrs',
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
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-slate-500 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl"><i class="fa-solid fa-list-check text-slate-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Requests</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['total'] }}</p>
            </div>
        </div>

        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending Requests</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['pending'] }}</p>
            </div>
        </div>

        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl"><i class="fa-solid fa-flag-checkered text-blue-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Completed Sessions</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['completed'] }}</p>
            </div>
        </div>

        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl"><i class="fa-solid fa-stopwatch text-purple-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Hours</h3>
                <p class="text-2xl font-black text-slate-800 truncate">{{ $this->summaryCount['totalHours'] }}</p>
            </div>
        </div>

        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-red-500 flex items-center gap-3 lg:gap-4">
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
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left table-fixed">
                <thead class="bg-slate-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[5%]">#</th>

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
                        <td class="px-5 py-3 text-gray-400 text-xs"
                            x-text="(currentPage - 1) * perPage + index + 1"></td>

                        <td class="px-5 py-3">
                            <p class="font-bold text-slate-700 text-sm truncate"
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.subject + ' – ' + booking.subjectName })"
                               x-text="booking.subject"></p>
                            <p class="text-gray-400 text-xs truncate"
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.subjectName })"
                               x-text="booking.subjectName"></p>
                        </td>

                        <td class="px-5 py-3">
                            <p class="text-slate-600 text-sm truncate"
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.topic })"
                               x-text="booking.topic"></p>
                        </td>

                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-slate-700 truncate"
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = booking.mentor })"
                               x-text="booking.mentor"></p>
                        </td>

                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-slate-700" x-text="booking.date"></p>
                            <p class="text-xs text-gray-400" x-text="booking.time"></p>
                        </td>

                        <td class="px-5 py-3 text-sm text-slate-500" x-text="booking.mode"></td>

                        <td class="px-5 py-3">
                            <span :class="'font-bold text-xs bg-gray-50 px-2 py-1 rounded border border-current opacity-80 ' + booking.statusClass"
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
        <div class="mt-3 pb-4 flex flex-col items-center gap-2" x-show="totalPages >= 1" x-cloak>
            <div class="flex items-center gap-2">
                <button @click="currentPage--"
                        :disabled="currentPage === 1"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-50 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>
                <template x-for="(page, index) in pages" :key="index">
                    <button
                        @click="if (page !== '...') currentPage = page"
                        :disabled="page === '...'"
                        :class="currentPage === page
                        ? 'bg-sidebar-green text-white shadow-sm border-sidebar-green'
                        : page === '...'
                            ? 'bg-white border border-gray-200 text-gray-400 cursor-default'
                            : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'"
                        class="w-8 h-8 flex items-center justify-center text-xs font-bold rounded-lg transition"
                        x-text="page">
                    </button>
                </template>
                <button @click="currentPage++"
                        :disabled="currentPage === totalPages"
                        class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-50 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
            <span class="text-[11px] text-gray-400 font-medium"
                  x-text="filteredItems.length === 0 ? '' : pageStart + '–' + pageEnd + ' of ' + filteredItems.length">
        </span>
        </div>
    </div>
</div>
