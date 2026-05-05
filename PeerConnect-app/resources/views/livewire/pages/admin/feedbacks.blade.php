<?php

use function Livewire\Volt\{layout, state, mount, computed};

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

// RETRIEVE DATA
$feedbacks = computed(function () {
    $all = \DB::table('feedback')
        ->leftJoin('bookings', \DB::raw('feedback.booking_id::text'), '=', \DB::raw('bookings.id::text'))
        ->leftJoin('mentor_profiles', \DB::raw('bookings.mentor_id::text'), '=', \DB::raw('mentor_profiles.id::text'))
        ->leftJoin('user_profiles as mentors', \DB::raw('mentor_profiles.user_id::text'), '=', \DB::raw('mentors.id::text'))
        ->select(
            'feedback.*',
            'mentor_profiles.id as mentor_id',
            'bookings.student_id',
            'bookings.date as session_date',
            'bookings.schedule_start',
            'bookings.schedule_end',
            \DB::raw("CONCAT(mentors.\"lastName\", ', ', mentors.\"firstName\") as mentor_name")
        )
        ->orderByDesc('feedback.date_submitted')
        ->get();

    return $all->map(function ($fb) {
        $scores = array_filter([
            $fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5,
            $fb->q6, $fb->q7, $fb->q8, $fb->q9,
        ], fn($v) => !is_null($v));

        $avg = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
        
        $avgClass = match(true) {
            $avg === null => '',
            $avg >= 4.5   => 'rating-excellent',
            $avg >= 3.5   => 'rating-good',
            $avg >= 2.5   => 'rating-average',
            default       => 'rating-poor',
        };

        $avgLabel = match(true) {
            $avg === null => 'N/A',
            $avg >= 4.5   => 'Excellent',
            $avg >= 3.5   => 'Good',
            $avg >= 2.5   => 'Average',
            default       => 'Poor',
        };

        return [
            'id' => $fb->id ?? uniqid(),
            'mentor_id' => $fb->mentor_id,
            'mentor_name' => $fb->mentor_name ?? '—',
            'subject' => $fb->subject ?? '—',
            'topic' => $fb->topic ?? '—',
            'date_formatted' => $fb->date_submitted ? \Carbon\Carbon::parse($fb->date_submitted)->format('F j, Y') : '—',
            'feedback' => $fb->feedback ?? '—',
            'has_feedback' => !empty($fb->feedback) && $fb->feedback !== '—',
            'avg' => $avg,
            'avgClass' => $avgClass,
            'avgLabel' => $avgLabel,
            'q1' => $fb->q1, 'q2' => $fb->q2, 'q3' => $fb->q3,
            'q4' => $fb->q4, 'q5' => $fb->q5, 'q6' => $fb->q6,
            'q7' => $fb->q7, 'q8' => $fb->q8, 'q9' => $fb->q9,
            'q10' => $fb->q10,
        ];
    })->toArray();
});

$mentorsList = computed(function () {
    return \DB::table('mentor_profiles')
        ->join('user_profiles', \DB::raw('mentor_profiles.user_id::text'), '=', \DB::raw('user_profiles.id::text'))
        ->select(
            'mentor_profiles.id', 
            \DB::raw("CONCAT(user_profiles.\"lastName\", ', ', user_profiles.\"firstName\") as mentor_name")
        )
        ->orderBy('user_profiles.lastName')
        ->get();
});

// Display statistics in stat cards
$dashboardStats = computed(function () {
    $all = \DB::table('feedback')->get();
    $totalSessions = \DB::table('bookings')->where('booking_status', 'completed')->count();
    
    if ($all->isEmpty()) {
        return ['avg' => '0.0', 'total' => 0, 'sessions' => number_format($totalSessions)];
    }

    $totalScores = [];
    foreach ($all as $fb) {
        $scores = array_filter([$fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5, $fb->q6, $fb->q7, $fb->q8, $fb->q9], fn($v) => !is_null($v));
        if (count($scores) > 0) $totalScores[] = array_sum($scores) / count($scores);
    }

    return [
        'avg'      => number_format(count($totalScores) > 0 ? array_sum($totalScores) / count($totalScores) : 0, 1),
        'total'    => number_format($all->count()),
        'sessions' => number_format($totalSessions),
    ];
});
?>

{{-- TEMPLATE --}}
<div x-data="feedbackManagement(@js($this->feedbacks))">
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Student Feedbacks
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">All student feedbacks collected</p>
        </div>
    </div>
    
    {{-- STATS CARDS --}}
    <div class="space-y-6">
        <div class="grid grid-cols-[repeat(autofit,_minmax(250px,_1fr))] sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-3 lg:gap-4">
                <div class="text-2xl flex-shrink-0">
                    <i class="fa-solid fa-chart-bar text-blue-600"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Responses</h3>
                    <p class="text-xl font-black text-slate-800 truncate">{{ $this->dashboardStats['total'] }}</p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4">
                <div class="text-2xl flex-shrink-0">
                    <i class="fa-solid fa-chalkboard-user text-yellow-500"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Sessions</h3>
                    <p class="text-xl font-black text-slate-800 truncate">{{ $this->dashboardStats['sessions'] }}</p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-3 lg:gap-4">
                <div class="text-2xl flex-shrink-0">
                    <i class="fa-solid fa-star text-green-600"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Average Rating</h3>
                    <p class="text-xl font-black text-slate-800 truncate">{{ $this->dashboardStats['avg'] }} <span class="text-lg text-gray-400">/ 5.0</span></p>
                </div>
            </div>
        </div>

        {{-- FEEDBACK TABLE --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-100 flex flex-wrap gap-4 justify-between items-center bg-white">
                <div>
                    <h2 class="font-bold text-slate-800 text-m">
                        <i class="fa-solid fa-user-secret text-gray-400"></i> 
                        Anonymous Student Feedbacks
                    </h2>
                    <p class="text-xs text-gray-500">Student identities are hidden to encourage honest reporting.</p>
                </div>
                
                <div class="flex gap-3 flex-wrap items-center">
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" x-model="searchQuery" @input="currentPage = 1"
                            placeholder="Search..."
                            class="pl-8 pr-3 py-2 h-8 text-xs font-medium text-slate-700 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-64 transition">
                    </div>
                                        
                    {{-- Filter mentors --}}
                    <div class="relative" @click.outside="showMentorDropdown = false">
                        <button @click="showMentorDropdown = !showMentorDropdown"
                            class="table-filter-select flex items-center gap-2 min-w-[110px] justify-between transition hover:border-gray-300">
                            <span class="flex items-center gap-1.5 text-slate-600 font-medium">
                                <i class="fa-solid fa-filter text-gray-400"></i> Mentor
                            </span>
                            <span x-show="mentorFilter.length > 0" x-cloak class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold" x-text="mentorFilter.length"></span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-gray-400" x-show="mentorFilter.length === 0"></i>
                        </button>

                        <div x-show="showMentorDropdown" x-transition.opacity.duration.150ms x-cloak
                            class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-xl z-20 overflow-hidden py-1 max-h-64 overflow-y-auto">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                <input type="checkbox" :checked="allMentorsSelected" @change="toggleAllMentors" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                                <span>All Mentors</span>
                            </label>

                            <div class="border-t border-gray-100 my-1"></div>
                            <template x-for="mentor in availableMentors" :key="mentor.id">
                                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                                    <input type="checkbox" :value="mentor.id" x-model="mentorFilter" @change="onMentorFilterChange" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                                    <span x-text="mentor.name" class="truncate"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Filter Ratings --}}
                    <div class="relative" @click.outside="showRatingDropdown = false">
                        <button @click="showRatingDropdown = !showRatingDropdown"
                            class="table-filter-select flex items-center gap-2 min-w-[110px] justify-between transition hover:border-gray-300">
                            <span class="flex items-center gap-1.5 text-slate-600 font-medium">
                                <i class="fa-solid fa-filter text-gray-400"></i> Ratings
                            </span>
                            <span x-show="ratingFilter.length > 0" x-cloak class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold" x-text="ratingFilter.length"></span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-gray-400" x-show="ratingFilter.length === 0"></i>
                        </button>

                        <div x-show="showRatingDropdown" x-transition.opacity.duration.150ms x-cloak
                            class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-xl z-20 overflow-hidden py-1 max-h-64 overflow-y-auto">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                <input type="checkbox" :checked="allRatingsSelected" @change="toggleAllRatings" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                                <span>All Ratings</span>
                            </label>

                            <div class="border-t border-gray-100 my-1"></div>
                            <template x-for="rating in availableRatings" :key="rating.id">
                                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium capitalize transition">
                                    <input type="checkbox" :value="rating.id" x-model="ratingFilter" @change="onRatingFilterChange" class="rounded border-gray-300 w-4 h-4 text-up-maroon focus:ring-up-maroon">
                                    <span x-text="rating.name" class="truncate"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FEEDBACK TABLE CONTENT --}}
            <div x-show="filteredFeedbacks.length > 0" x-cloak>
                <div class="w-full overflow-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-gray-100 border-b">
                            <tr>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-left w-[16%]">
                                    <button @click="toggleSort('date')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                        Date
                                        <i class="fa-solid text-[8px]"
                                            :class="sortCol === 'date'
                                                    ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                    : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-left w-[16%]">
                                    <button @click="toggleSort('mentor_name')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                        Mentor
                                        <i class="fa-solid text-[8px]"
                                        :class="sortCol === 'mentor_name'
                                                ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-left w-[10%]">
                                    <button @click="toggleSort('subject')" class="flex items-center gap-1 hover:text-slate-600 w-full transition uppercase tracking-wider">
                                        Subject
                                        <i class="fa-solid text-[8px]"
                                        :class="sortCol === 'subject'
                                                ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-left w-[16%]">
                                    <button @click="toggleSort('topic')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                        Topic
                                        <i class="fa-solid text-[8px]"
                                        :class="sortCol === 'topic'
                                                ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-left w-[26%]">
                                    <button @click="toggleSort('feedback')" class="flex items-center gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                        Feedback
                                        <i class="fa-solid text-[8px]"
                                        :class="sortCol === 'feedback'
                                                ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                                <th class="px-5 py-4 text-[10px] font-bold text-gray-400 text-right w-[16%]">
                                    <button @click="toggleSort('rating')" class="flex items-center justify-end w-full gap-1 hover:text-slate-600 transition uppercase tracking-wider">
                                        Rating
                                        <i class="fa-solid text-[8px]"
                                        :class="sortCol === 'rating'
                                                ? (sortDir === 'asc' ? 'fa-arrow-up text-slate-600' : 'fa-arrow-down text-slate-600')
                                                : 'fa-arrow-up-arrow-down opacity-30'"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="fb in paginatedFeedbacks" :key="fb.id">
                                <tr class="feedback-row cursor-pointer" @click="openDetailModal(fb)">
                                    <td class="px-5 py-5 align-middle col-date">
                                        <span class="cell-text text-slate-700 text-[13px] font-semibold" x-text="fb.date_formatted"></span>
                                    </td>
                                    <td class="px-5 py-5 align-middle col-mentor">
                                        <span class="cell-text text-slate-700 text-[12px] font-semibold" x-text="fb.mentor_name"></span>
                                    </td>
                                    <td class="px-5 py-5 align-middle col-subject">
                                        <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100 whitespace-nowrap" x-text="fb.subject"></span>
                                    </td>
                                    <td class="px-5 py-5 align-middle col-topic" style="overflow:visible; position:relative;">
                                        <div class="hover-tooltip" :data-full="fb.topic">
                                            <span class="cell-text-wrap text-xs text-slate-600" x-text="fb.topic"></span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-5 align-middle col-feedback">
                                        <button type="button" @click.stop="openFeedbackPopup(fb.has_feedback ? fb.feedback : null)" class="text-left w-full group">
                                            <span class="cell-text-wrap text-[11px] bg-slate-100 px-2 py-1 rounded text-slate-700 font-semibold block group-hover:bg-slate-200 transition-colors" x-text="fb.feedback"></span>
                                        </button>
                                    </td>
                                    <td class="px-5 py-5 align-middle col-rating text-right">
                                        <div class="flex flex-col items-end gap-1">
                                            <template x-if="fb.avg !== null">
                                                <span :class="'rating-pill ' + fb.avgClass">
                                                    <i class="fa-solid fa-star text-[10px]"></i>
                                                    <span x-text="Number(fb.avg).toFixed(1) + ' / 5 &dash; ' + fb.avgLabel"></span>
                                                </span>
                                            </template>
                                            <template x-if="fb.avg === null">
                                                <span class="text-xs text-gray-300 italic">No score</span>
                                            </template>
                                            <template x-if="fb.q10 !== null">
                                                <span :class="'ontime-badge ' + (fb.q10 ? 'ontime-yes' : 'ontime-no')">
                                                    <i :class="'fa-solid text-[9px] ' + (fb.q10 ? 'fa-clock' : 'fa-clock-rotate-left')"></i>
                                                    <span x-text="fb.q10 ? 'On time' : 'Late'"></span>
                                                </span>
                                            </template>
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
                        x-text="filteredFeedbacks.length === 0 ? 'No results' : 'Showing ' + pageStart + ' to ' + pageEnd + ' of ' + filteredFeedbacks.length">
                    </span>
                </div>
            </div>

            <div x-show="filteredFeedbacks.length === 0" x-cloak class="empty-state">
                <i class="fa-regular fa-comment-dots"></i>
                <p class="text-sm font-semibold text-gray-400 mb-1">No feedback found</p>
                <p class="text-xs text-gray-400">
                    <span x-show="searchQuery !== '' || mentorFilter !== ''">No results match your current filters. Try adjusting your search or filter.</span>
                    <span x-show="searchQuery === '' && mentorFilter === ''">Student feedback will appear here once sessions are marked complete and reviewed.</span>
                </p>
            </div>
        </div>
    </div>

    {{-- FEEDBACK VIEW MODAL --}}
    <div class="feedback-popup-overlay" :class="{ 'open': showFeedbackPopup }" x-transition.opacity @click.self="closeFeedbackPopup()" @keydown.escape.window="closeFeedbackPopup()">
        <div class="feedback-popup-box">
            <div class="feedback-popup-header">
                <span>
                    <i class="fa-regular fa-comment-dots" style="color:#94a3b8;"></i>
                    Student Remark
                </span>
                <button class="modal-close-btn" @click="closeFeedbackPopup()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="feedback-popup-body">
                <template x-if="selectedFeedbackText">
                    <p class="feedback-popup-text" x-text="selectedFeedbackText"></p>
                </template>
                <template x-if="!selectedFeedbackText">
                    <p class="feedback-popup-empty">No additional remarks provided.</p>
                </template>
            </div>
        </div>
    </div>

    {{-- SCORE VIEW MODAL --}}
    <div class="modal-overlay" :class="{ 'open': showDetailModal }" x-transition.opacity @click.self="closeDetailModal()" @keydown.escape.window="closeDetailModal()">
        <div class="modal-box" x-show="showDetailModal" x-transition>
            <div class="modal-header">
                <div style="min-width:0;flex:1;">
                    <h3 class="text-base font-bold text-slate-800">Session Feedback Details</h3>
                    <div style="margin-top:4px;" x-show="selectedFeedback">
                        <span class="modal-meta-subject" x-text="selectedFeedback?.subject + ' • Mentor: ' + selectedFeedback?.mentor_name"></span>
                        <span class="modal-meta-topic" :title="selectedFeedback?.topic" x-text="selectedFeedback?.topic"></span>
                        <span class="modal-meta-date" x-text="selectedFeedback?.date_formatted"></span>
                    </div>
                </div>
                <button class="modal-close-btn" @click="closeDetailModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body" x-show="selectedFeedback">
                <!-- Average Score Block -->
                <template x-if="selectedFeedback?.avg !== null">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Average Score &mdash; Q1 to Q9</span>
                            <span style="font-size:20px;font-weight:800;" :style="'color:' + barColor(selectedFeedback?.avg)">
                                <span x-text="selectedFeedback?.avg"></span> <span style="font-size:12px;color:#94a3b8;">/ 5</span>
                            </span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avg-bar-track">
                                <div class="avg-bar-fill" :style="'width:' + ((selectedFeedback?.avg / 5) * 100).toFixed(1) + '%; background:' + barColor(selectedFeedback?.avg)"></div>
                            </div>
                            <span style="font-size:11px;font-weight:700;white-space:nowrap;" :style="'color:' + barColor(selectedFeedback?.avg)" x-text="selectedFeedback?.avgLabel"></span>
                        </div>
                    </div>
                </template>

                <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">
                    Likert Scale (1 = Strongly Disagree &nbsp;·&nbsp; 5 = Strongly Agree)
                </p>

                <!-- Q1 to Q9 Loop -->
                <template x-for="(question, index) in questionsList" :key="index">
                    <div class="q-row">
                        <div class="q-number" x-text="index + 1"></div>
                        <div class="q-text" x-text="question"></div>
                        <div class="q-score">
                            <template x-if="getScore(index) !== null">
                                <div class="flex items-center">
                                    <template x-for="i in 5">
                                        <div class="q-dot" :class="i <= getScore(index) ? dotClass(getScore(index)) : ''"></div>
                                    </template>
                                    <span class="q-num" :class="numClass(getScore(index))" x-text="getScore(index)"></span>
                                </div>
                            </template>
                            <template x-if="getScore(index) === null">
                                <span class="q-num text-gray-400">—</span>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Q10 (On Time) -->
                <div class="q-row" style="border-bottom:none;">
                    <div class="q-number">10</div>
                    <div class="q-text">The peer mentor started the session on time.</div>
                    <div class="q-score">
                        <template x-if="selectedFeedback?.q10 === null">
                            <span style="font-size:11px;color:#94a3b8;">—</span>
                        </template>
                        <template x-if="selectedFeedback?.q10 === true || selectedFeedback?.q10 === '1'">
                            <span class="bool-answer bool-yes"><i class="fa-solid fa-check" style="font-size:9px;margin-right:3px;"></i>Yes &mdash; On time</span>
                        </template>
                        <template x-if="selectedFeedback?.q10 === false || selectedFeedback?.q10 === '0'">
                            <span class="bool-answer bool-no"><i class="fa-solid fa-xmark" style="font-size:9px;margin-right:3px;"></i>No &mdash; Late</span>
                        </template>
                    </div>
                </div>

                <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-top:20px;margin-bottom:6px;">
                    Additional Remarks
                </p>

                <template x-if="selectedFeedback?.has_feedback">
                    <div class="remarks-box" x-text="selectedFeedback.feedback"></div>
                </template>
                <template x-if="!selectedFeedback?.has_feedback">
                    <p style="font-size:12px;color:#d1d5db;font-style:italic;padding:6px 0;">No additional remarks provided.</p>
                </template>
            </div>
        </div>
    </div>
</div>