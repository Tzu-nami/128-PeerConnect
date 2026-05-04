<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use App\Models\Bookings;
use App\Models\MentorProfiles;
use App\Models\MentorAvailabilities;
use App\Models\MentorSubjects;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\SessionAccepted;
use App\Mail\SessionCompleted;
use App\Mail\SessionRejected;
use App\Mail\AdminCancelledMentor;
use App\Mail\AdminCancelledStudent;

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

$sessions = computed(function () {
    $allBookings = Bookings::with([
        'student.user',
        'mentor.user',
        'subject',
        'tutorialMode',
    ])->get();

    return $allBookings->map(function ($b) {
        $start = \Carbon\Carbon::parse($b->schedule_start);
        $end   = \Carbon\Carbon::parse($b->schedule_end);

        $durationMinutes = $start->diffInMinutes($end);
        $durationHours   = $durationMinutes / 60;

        $durationText = $durationHours == 1
            ? '1 hr'
            : rtrim(rtrim(number_format($durationHours, 2), '0'), '.');

        return [
            'id'            => $b->id,
            'student'       => optional(optional($b->student)->user)->firstName
                             ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                             : 'Unknown',
            'mentor'        => optional(optional($b->mentor)->user)->firstName
                             ? $b->mentor->user->firstName . ' ' . $b->mentor->user->lastName
                             : '—',
            'subject'       => optional($b->subject)->code ?? 'N/A',
            'subjectName'   => optional($b->subject)->name ?? '',
            'topic'         => $b->topic ?? '—',
            'date'          => $b->date ? \Carbon\Carbon::parse($b->date)->format('F j, Y') : '—',
            'mode'          => optional($b->tutorialMode)->mode ?? '—',
            'yearLevel'     => optional(optional($b->student)->yearLevel)->name ?? 'N/A',
            'degreeProgram' => optional(optional($b->student)->degreeProgram)->name ?? 'N/A',

            'start' => $start->format('H:i'),
            'end'   => $end->format('H:i'),

            'time'     => $start->format('g:i A') . ' – ' . $end->format('g:i A'),
            'duration' => $start->format('h:i A') . ' - ' . $end->format('h:i A') . ' (' . $durationText . ')',
            'durationHours' => $durationHours,

            'status'   => $b->booking_status,
            'is_open' => is_null($b->mentor_id),
        ];
    })
    ->values()
    ->toArray();
});

$summaryCounts = computed(function () {
    $sessions = $this->sessions;
    $statuses = array_column($sessions, 'status');

    $total     = count($sessions);
    $accepted  = count(array_filter($statuses, fn($s) => $s === 'accepted'));
    $pending   = count(array_filter($statuses, fn($s) => $s === 'pending'));
    $completed = count(array_filter($statuses, fn($s) => $s === 'completed'));
    $completedSessions = array_filter($sessions, fn($s) => $s['status'] === 'completed');
    $totalHours = array_sum(array_column($completedSessions, 'durationHours'));

    $hoursFormatted = number_format($totalHours, 2);

    return [
        'total'          => $total,
        'accepted'       => $accepted,
        'pending'        => $pending,
        'completed'      => $completed,
        'totalHoursRaw'  => $totalHours,
        'totalHours'     => $hoursFormatted,
    ];
});

?>

{{-- TEMPLATE --}}
<div x-data="sessionManagement(@js($this->sessions), @js($this->summaryCounts))"
    x-init="
            @if(session('success'))
                setTimeout(() => triggerBanner('{{ session('success') }}', 'success'), 100);
            @endif
            @if(session('error'))
                setTimeout(() => triggerBanner('{{ session('error') }}', 'error'), 100);
            @endif
        ">

    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Session Management
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">All tutorial sessions across all mentors</p>
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-[repeat(autofit,_minmax(250px,_1fr))] sm:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-list-check text-slate-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total</h3>
                <p class="text-2xl font-black text-slate-800 truncate" x-text="counts.total"></p>
            </div>
        </div>
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-circle-check text-green-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Accepted</h3>
                <p class="text-2xl font-black text-slate-800 truncate" x-text="counts.accepted"></p>
            </div>
        </div>
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending</h3>
                <p class="text-2xl font-black text-slate-800 truncate" x-text="counts.pending"></p>
            </div>
        </div>
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-flag-checkered text-blue-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Completed</h3>
                <p class="text-2xl font-black text-slate-800 truncate" x-text="counts.completed"></p>
            </div>
        </div>
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-stopwatch text-purple-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Hours</h3>
                <p class="text-2xl font-black text-slate-800 truncate" x-text="counts.totalHours"></p>
            </div>
        </div>
    </div>

    {{-- SESSIONS TABLE --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-m">All Sessions</h2>
                <p class="text-xs text-gray-400 font-medium" x-text="filteredSessions.length + ' sessions found'"></p>
            </div>
            <div class="flex gap-2 items-center flex-wrap">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search..."
                        class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                </div>

                {{-- Status Filters --}}
                <div class="relative" @click.outside="showStatusDropdown = false">
                    <button @click="showStatusDropdown = !showStatusDropdown" class="table-filter-select flex items-center gap-2 min-w-[120px] justify-between transition hover:border-gray-300">
                        <span class="flex items-center gap-1.5 text-slate-600 font-medium"><i class="fa-solid fa-filter text-gray-400"></i> Status</span>
                        <span x-show="statusFilter.length > 0" x-cloak class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold" x-text="statusFilter.length"></span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-gray-400" x-show="statusFilter.length === 0"></i>
                    </button>
                    
                    <div x-show="showStatusDropdown" x-transition.opacity.duration.150ms x-cloak
                        class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl z-20 py-1">
                        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                            <input type="checkbox" :checked="isAllStatusesSelected" @change="toggleAllStatuses" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                            <span>All</span>
                        </label>
                        <div class="border-t border-gray-100 my-1"></div>
                        <template x-for="status in ['pending', 'accepted', 'completed', 'rejected', 'cancelled', 'no_show']" :key="status">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                                <input type="checkbox" :value="status" x-model="statusFilter" @change="currentPage = 1" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                                <span x-text="status.replace('_', ' ')"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Banner Notifications --}}
        <div x-show="banner.show" 
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="mx-5 mt-4 mb-2">
            
            <div class="flex items-center justify-between px-4 py-3 rounded-lg border"
                 :class="banner.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'">
                <div class="flex items-center gap-3">
                    <i class="fa-solid" :class="banner.type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600'"></i>
                    <span class="text-sm font-semibold" x-text="banner.message"></span>
                </div>
            </div>
        </div>

        {{-- SESSIONS TABLE CONTENT --}}
        <div x-show="filteredSessions.length > 0" x-cloak>
            <div class="w-full overflow-x-auto pb-2">
                <table class="w-full text-left text-sm table-fixed min-w-[900px]">
                    <thead class="bg-slate-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[4%]">#</th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[13%]">
                                <button @click="toggleSort('student')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Student <i class="fa-solid text-[8px]" :class="sortCol === 'student' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[15%]">
                                <button @click="toggleSort('mentor')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Mentor <i class="fa-solid text-[8px]" :class="sortCol === 'mentor' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[10%]">
                                <button @click="toggleSort('subject')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Subject <i class="fa-solid text-[8px]" :class="sortCol === 'subject' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[12%]">
                                <button @click="toggleSort('topic')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Topic <i class="fa-solid text-[8px]" :class="sortCol === 'topic' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[17%]">
                                <button @click="toggleSort('date')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Date & Time <i class="fa-solid text-[8px]" :class="sortCol === 'date' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[9%]">
                                <button @click="toggleSort('mode')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Mode <i class="fa-solid text-[8px]" :class="sortCol === 'mode' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 w-[10%]">
                                <button @click="toggleSort('status')" class="flex items-center justify-center w-full gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                    Status <i class="fa-solid text-[8px]" :class="sortCol === 'status' ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600') : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider text-center w-[10%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(s, index) in paginatedSessions" :key="s.id">
                            <tr class="session-row group hover:bg-slate-50 transition">
                                <td class="px-5 py-4 align-middle text-gray-400 text-xs font-medium tabular-nums" x-text="pageStart + index"></td>
                                
                                <td class="px-5 py-4 align-middle">
                                    <div class="hover-tooltip" :data-full="s.student + '\n' + s.yearLevel + ' - ' + s.degreeProgram">
                                        <p class="font-bold text-slate-700 text-xs truncate" x-text="s.student"></p>
                                        <p class="text-[10px] text-gray-400 mt-0.5 truncate" x-text="(s.yearLevel !== '—' ? s.yearLevel : '') + ' - ' + (s.degreeProgram !== '—' ? s.degreeProgram : '')"></p>
                                    </div>
                                </td>
                                
                                <td class="px-5 py-4 align-middle">
                                    <div class="hover-tooltip" :data-full="s.mentor">
                                        <p class="font-bold text-slate-700 text-xs truncate" x-text="s.mentor"></p>
                                    </div>
                                </td>
                                
                                <td class="px-5 py-4 align-middle">
                                    <div class="hover-tooltip" :data-full="s.subject + ' – ' + s.subjectName">
                                        <p class="font-bold text-slate-700 text-xs" x-text="s.subject"></p>
                                        <p class="text-[10px] text-gray-400 truncate" x-text="s.subjectName"></p>
                                    </div>
                                </td>
                                
                                <td class="px-5 py-4 align-middle">
                                    <div class="hover-tooltip" :data-full="s.topic">
                                        <p class="text-xs text-slate-600 truncate" x-text="s.topic"></p>
                                    </div>
                                </td>
                                
                                <td class="px-5 py-4 align-middle">
                                    <p class="text-xs font-medium text-slate-700" x-text="s.date + ' ' + formatHours(s)"></p>
                                    <p class="text-[10px] text-gray-400" x-text="s.time"></p>
                                </td>
                                
                                <td class="px-5 py-4 align-middle text-xs text-slate-500" x-text="s.mode"></td>
                                
                                <td class="px-5 py-4 align-middle text-center">
                                    <span class="font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80"
                                          :class="getStatusColor(s.status)" x-text="getStatusLabel(s.status)">
                                    </span>
                                </td>
                                
                                <td class="px-5 py-4 align-middle text-center">
                                    <div class="relative flex items-center justify-center min-h-[28px]">
                                        <div class="action-idle absolute flex items-center justify-center gap-1 pointer-events-none">
                                            <span class="w-2 h-2 rounded-full inline-block" :class="getIdleIndicatorColor(s)"></span>
                                        </div>
                                        <div class="action-buttons flex items-center gap-1 justify-center">
                                            {{-- Actions --}}
                                            <template x-if="s.status === 'pending'">
                                                <div class="contents">
                                                    <template x-if="s.is_open">
                                                        <div class="hover-tooltip" data-full="Claim Session">
                                                            <button @click.stop="promptUpdateStatus(s, 'accepted')" class="w-7 h-7 rounded-lg bg-purple-100 hover:bg-purple-200 text-purple-700 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                                <i class="fa-solid fa-hand-pointer text-[11px]"></i>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="!s.is_open">
                                                        <div class="flex gap-1">
                                                            <div class="hover-tooltip" data-full="Accept">
                                                                <button @click.stop="promptUpdateStatus(s, 'accepted')" class="w-7 h-7 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                                    <i class="fa-solid fa-check text-[11px]"></i>
                                                                </button>
                                                            </div>
                                                            <div class="hover-tooltip" data-full="Reject">
                                                                <button @click.stop="promptUpdateStatus(s, 'rejected')" class="w-7 h-7 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                                    <i class="fa-solid fa-xmark text-[11px]"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            
                                            <template x-if="s.status === 'accepted'">
                                                <div class="flex gap-1">
                                                    <div class="hover-tooltip" data-full="Complete">
                                                        <button @click.stop="promptUpdateStatus(s, 'completed')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                            <i class="fa-solid fa-flag-checkered text-[11px]"></i>
                                                        </button>
                                                    </div>
                                                    <div class="hover-tooltip" data-full="No-show">
                                                        <button @click.stop="promptUpdateStatus(s, 'no_show')" class="w-7 h-7 rounded-lg bg-orange-100 hover:bg-orange-200 text-orange-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                            <i class="fa-solid fa-user-slash text-[11px]"></i>
                                                        </button>
                                                    </div>
                                                    <div class="hover-tooltip" data-full="Cancel">
                                                        <button @click.stop="promptUpdateStatus(s, 'cancelled')" class="w-7 h-7 rounded-lg bg-red-100 hover:bg-red-200 text-red-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                            <i class="fa-solid fa-ban text-[11px]"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                            
                                            <template x-if="['completed', 'no_show', 'rejected'].includes(s.status)">
                                                <div class="hover-tooltip" data-full="Undo">
                                                    <button @click.stop="promptUpdateStatus(s, 'accepted')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm shrink-0">
                                                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                                                    </button>
                                                </div>
                                            </template>
                                            
                                            <template x-if="s.status === 'cancelled'">
                                                <div class="flex justify-end"><span class="text-gray-200 text-[10px]">—</span></div>
                                            </template>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            <div class="p-6 border-t border-gray-100 flex flex-col items-center justify-center gap-3 bg-white" x-cloak>
                <div class="flex items-center gap-2" x-show="totalPages > 1">
                    <button @click="currentPage--" :disabled="currentPage === 1" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <template x-for="(page, index) in pages" :key="index">
                        <div class="contents">
                            <button @click="currentPage = page" :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'" class="w-8 h-8 text-xs font-bold rounded-lg transition" x-text="page" x-show="page !== '...'"></button>
                            <span x-show="page === '...'" class="w-7 h-7 flex items-center justify-center text-[11px] font-bold text-gray-400 tracking-widest shrink-0">...</span>
                        </div>
                    </template>
                    <button @click="currentPage++" :disabled="currentPage === totalPages" class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
                <span class="text-[11px] text-gray-400 font-medium"
                      x-text="filteredSessions.length === 0 ? 'No results' : 'Showing ' + pageStart + ' to ' + pageEnd + ' of ' + filteredSessions.length + ' entries'">
                </span>
            </div>
        </div>
        <div x-show="filteredSessions.length === 0" x-cloak class="empty-state">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p class="text-sm font-semibold text-gray-400 mb-1">No sessions found</p>
            <p class="text-xs text-gray-400">Try adjusting your search or status filters.</p>
        </div>
    </div>

    {{-- CONFIRMATION MODAL --}}
    <div x-show="showConfirmModal" style="display:none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 backdrop-blur-sm" x-transition.opacity @click.self="closeConfirmModal()" @keydown.escape.window="closeConfirmModal()">
        <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" x-show="showConfirmModal" x-transition>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0" :class="confirmConfig.iconBgClass" x-html="confirmConfig.iconHtml"></div>
                <h3 class="text-base font-bold text-gray-900" x-text="confirmConfig.title"></h3>
            </div>
            <p class="text-sm text-gray-600 mb-1 leading-relaxed" x-text="confirmConfig.body"></p>
            
            <div class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1" x-html="confirmConfig.metaHtml"></div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button @click="closeConfirmModal()" :disabled="isConfirming" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-300 hover:text-gray-800 rounded-lg transition-colors disabled:opacity-50">Cancel</button>
                <button @click="executeConfirm()" :disabled="isConfirming" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors flex items-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed" :class="confirmConfig.btnClass">
                    <i x-show="isConfirming" class="fa-solid fa-spinner fa-spin"></i>
                    <span x-text="isConfirming ? confirmConfig.loadingText : confirmConfig.confirmText"></span>
                </button>
            </div>
        </div>
    </div>
</div>