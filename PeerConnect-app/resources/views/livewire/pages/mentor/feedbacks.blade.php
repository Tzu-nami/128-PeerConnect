<?php

use function Livewire\Volt\{layout, state, mount, computed};
use App\Models\MentorProfiles;

state([]);

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

$feedbacks = computed(function () {
    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
    if (!$mentorProfile) return collect();

    $rows = \DB::table('feedback')
        ->join('bookings', 'feedback.booking_id', '=', 'bookings.id')
        ->where('bookings.mentor_id', $mentorProfile->id)
        ->select(
            'feedback.id',
            'feedback.feedback',
            'feedback.subject',
            'feedback.topic',
            'feedback.date_submitted',
            'feedback.q1', 'feedback.q2', 'feedback.q3', 'feedback.q4', 'feedback.q5',
            'feedback.q6', 'feedback.q7', 'feedback.q8', 'feedback.q9', 'feedback.q10',
        )
        ->orderByDesc('feedback.date_submitted')
        ->get();

    return $rows->map(function ($fb) {
        $scores = array_filter([
            $fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5,
            $fb->q6, $fb->q7, $fb->q8, $fb->q9,
        ], fn($v) => !is_null($v));

        $avg = count($scores) > 0
            ? round(array_sum($scores) / count($scores), 1)
            : null;

        $avgLabel = match(true) {
            $avg === null => 'N/A',
            $avg >= 4.5   => 'Excellent',
            $avg >= 3.5   => 'Good',
            $avg >= 2.5   => 'Average',
            default       => 'Poor',
        };

        $avgClass = match(true) {
            $avg === null => '',
            $avg >= 4.5   => 'text-green-600',
            $avg >= 3.5   => 'text-blue-600',
            $avg >= 2.5   => 'text-yellow-500',
            default       => 'text-red-500',
        };

        return [
            'id'             => $fb->id,
            'subject'        => $fb->subject ?? '—',
            'topic'          => $fb->topic   ?? '—',
            'feedback'       => $fb->feedback ?? null,
            'date'           => $fb->date_submitted
                ? \Carbon\Carbon::parse($fb->date_submitted)->format('M j, Y')
                : '—',
            'rawDate'        => $fb->date_submitted ?? '',
            'avg'            => $avg,
            'avgLabel'       => $avgLabel,
            'avgClass'       => $avgClass,
            'q1'  => $fb->q1,  'q2'  => $fb->q2,
            'q3'  => $fb->q3,  'q4'  => $fb->q4,
            'q5'  => $fb->q5,  'q6'  => $fb->q6,
            'q7'  => $fb->q7,  'q8'  => $fb->q8,
            'q9'  => $fb->q9,  'q10' => $fb->q10,
        ];
    });
});

$subjects = computed(function () {
    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
    if (!$mentorProfile) return collect();

    return \DB::table('feedback')
        ->join('bookings', 'feedback.booking_id', '=', 'bookings.id')
        ->where('bookings.mentor_id', $mentorProfile->id)
        ->whereNotNull('feedback.subject')
        ->distinct()
        ->orderBy('feedback.subject')
        ->pluck('feedback.subject');
});

?>

<div>
    {{-- Page Heading --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 animate-fade-up">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Student Feedbacks
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">All student feedbacks collected from your sessions.</p>
        </div>
    </div>

    {{-- Feedbacks Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 animate-fade-up [animation-delay:150ms]"
         x-data="mentorFeedbacks(@js($this->feedbacks), @js($this->subjects))">

        {{-- Header & Controls --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-sm">All Feedbacks</h2>
                <p class="text-xs text-gray-400 font-medium"
                   x-text="filteredItems.length + ' Feedback' + (filteredItems.length !== 1 ? 's' : '') + ' found'"></p>
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

                {{-- Subject Filter Dropdown --}}
                <div class="relative" x-data="{ openFilter: false }">
                    <button @click="openFilter = !openFilter"
                            class="bg-white border border-gray-200 rounded-lg px-4 py-2 text-xs font-bold text-slate-600 outline-none flex items-center gap-2 hover:bg-gray-50 transition h-[34px] w-[120px]">
                        <i class="fa-solid fa-filter text-gray-400"></i>
                        Subject
                        <span x-show="filterSubjects.length > 0"
                              class="bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold"
                              x-text="filterSubjects.length"></span>
                    </button>
                    <div x-show="openFilter"
                         x-transition
                         @click.outside="openFilter = false"
                         x-cloak
                         class="absolute right-0 mt-2 w-52 bg-white border border-gray-200 rounded-xl shadow-xl z-20 py-1 max-h-64 overflow-y-auto">

                        <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                            <input type="checkbox"
                                   :checked="filterSubjects.length === 0"
                                   @change="filterSubjects = []; currentPage = 1;"
                                   class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                            <span>All</span>
                        </label>
                        <div class="border-t border-gray-100 my-1"></div>

                        <template x-for="subject in allSubjects" :key="subject">
                            <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                <input type="checkbox"
                                       :value="subject"
                                       x-model="filterSubjects"
                                       @change="currentPage = 1"
                                       class="rounded border-gray-300 text-red-900 focus:ring-red-900 w-4 h-4">
                                <span x-text="subject"></span>
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
                    {{-- Date --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[14%]">
                        <button @click="toggleSort('rawDate')"
                                :class="sortCol === 'rawDate' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition uppercase">
                            Date
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'rawDate'
                               ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                               : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Subject --}}
                    <th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[12%]">
                        <button @click="toggleSort('subject')"
                                :class="sortCol === 'subject' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition uppercase">
                            Subject
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'subject'
                               ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                               : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Topic --}}
<th class="px-5 py-3 pl-8 text-xs font-bold text-gray-400 uppercase tracking-wider w-[22%]">
    <button @click="toggleSort('topic')"
                                :class="sortCol === 'topic' ? 'text-up-maroon' : 'text-gray-400 hover:text-up-maroon'"
                                class="flex items-center gap-1 transition uppercase">
                            Topic
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'topic'
                               ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                               : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>

                    {{-- Feedback --}}
<th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[25%]">
    Feedback
</th>

{{-- Rating --}}
<th class="px-5 py-3 text-xs font-bold text-gray-400 uppercase tracking-wider w-[23%]">
    <button @click="toggleSort('avg')"
            :class="sortCol === 'avg' ? 'text-up-maroon' : 'text-gray-400 hover:text-slate-600'"
            class="flex items-center gap-1 transition uppercase ml-auto">
                            Rating
                            <i class="fa-solid text-[8px]"
                               :class="sortCol === 'avg'
                           ? (sortDir === 'asc' ? 'fa-arrow-up text-up-maroon' : 'fa-arrow-down text-up-maroon')
                           : 'fa-arrow-up-arrow-down opacity-30'"></i>
                        </button>
                    </th>
                </tr>
                </thead>

                <tbody>
                <template x-for="(fb, index) in paginatedItems" :key="fb.id">
                    <tr class="border-b border-gray-50 hover:bg-slate-50 transition cursor-pointer"
                        @click="openDetailModal(fb)">

{{-- Date --}}
<td class="px-5 py-3">
    <p class="text-xs font-medium text-slate-700" x-text="fb.date"></p>
</td>

{{-- Subject --}}
<td class="px-5 py-3">
    <span class="font-bold text-[10px] bg-red-50 text-red-700 px-2 py-1 rounded border border-red-100 whitespace-nowrap"
          x-text="fb.subject"></span>
</td>

{{-- Topic --}}
<td class="px-5 pl-8 py-3 max-w-0" style="width:22%;max-width:0;"
    x-init="$nextTick(() => { const p = $el.querySelector('.topic-text'); if (p && p.scrollWidth > p.clientWidth) $el.title = fb.topic })">
    <p class="topic-text text-xs text-slate-600 truncate w-full" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="fb.topic"></p>
</td>

{{-- Feedback --}}
<td class="px-5 py-3 max-w-0">
    <button type="button" @click.stop="openFeedbackPopup(fb)" class="text-left w-full group">
        <span class="text-xs bg-slate-100 px-2 py-1 rounded text-slate-600 font-medium block group-hover:bg-slate-200 transition-colors truncate"
              style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
              x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = fb.feedback ?? '—' })"
              x-text="fb.feedback ?? '—'"></span>
    </button>
</td>

{{-- Rating --}}
<td class="px-5 py-3">
    <template x-if="fb.avg !== null">
        <div class="flex flex-col gap-1 items-end">
<div class="flex items-center gap-1.5 justify-end flex-nowrap">
            <span class="rating-pill"
          style="font-size:10px;padding:4px 8px;line-height:1;white-space:nowrap;"
          :class="{
      'rating-excellent': fb.avgLabel === 'Excellent',
      'rating-good':      fb.avgLabel === 'Good',
      'rating-average':   fb.avgLabel === 'Average',
      'rating-poor':      fb.avgLabel === 'Poor',
  }">
        <i class="fa-solid fa-star" style="font-size:9px;"></i>
        <span x-text="fb.avg + ' / 5 - ' + fb.avgLabel"></span>
    </span>
    <template x-if="fb.q10 !== null && fb.q10 !== undefined">
        <span class="ontime-badge"
              style="font-size:10px;padding:4px 8px;line-height:1;white-space:nowrap;"
              :class="fb.q10 ? 'ontime-yes' : 'ontime-no'">
            <i class="fa-solid"
               :class="fb.q10 ? 'fa-circle-check' : 'fa-rotate-left'"
               style="font-size:9px;"></i>
            <span x-text="fb.q10 ? 'On time' : 'Late'"></span>
        </span>
    </template>
</div>
        </div>
    </template>

    <template x-if="fb.avg === null">
<span class="text-xs text-gray-300 font-semibold block w-full text-right">No score</span>
    </template>
</td>

                    </tr>
                </template>

                {{-- Empty State --}}
                <tr x-show="filteredItems.length === 0" x-cloak>
                    <td colspan="6" class="px-5 py-16 text-center">
                        <div class="flex flex-col items-center justify-center text-gray-400">
                            <i class="fa-regular fa-comment-dots text-2xl mb-3 opacity-20"></i>
                            <p class="text-sm font-medium">No matching feedbacks found.</p>
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
<button @click="if(currentPage > 1) currentPage--" :disabled="currentPage === 1"
 class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
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

    {{-- FEEDBACK POPUP --}}
    <div class="feedback-popup-overlay" id="feedbackPopup"
         :class="{ 'open': showFeedbackPopup }"
         @click.self="closeFeedbackPopup()" x-cloak>

        <div class="feedback-popup-box">
            <div class="feedback-popup-header">
                <span>
                    <i class="fa-regular fa-comment-dots" style="color:#94a3b8;"></i>
                    Student Remark
                </span>
                <button class="modal-close-btn" onclick="closeFeedbackPopup()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="feedback-popup-body" id="feedbackPopupBody">
            </div>
        </div>
    </div>

    {{-- DETAIL MODAL --}}
    <div class="modal-overlay" id="feedbackModal"
         x-show="showDetailModal"
         @click.self="closeDetailModal()" x-cloak>

        <div class="modal-box">
            <div class="modal-header">
                <div style="min-width:0;flex:1;">
                    <h3 class="text-base font-bold text-slate-800">Session Feedback Details</h3>
                    <div id="modalMeta" style="margin-top:4px;"></div>
                </div>
                <button class="modal-close-btn" @click="closeDetailModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>
</div>
<script>
    function openFeedbackPopup(data) {
        const body = document.getElementById('feedbackPopupBody');
        body.innerHTML = data.feedback
            ? `<p class="feedback-popup-text">${escapeHtml(data.feedback)}</p>`
            : `<p class="feedback-popup-empty">No additional remarks provided.</p>`;
        document.getElementById('feedbackPopup').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFeedbackPopup() {
        document.getElementById('feedbackPopup').classList.remove('open');
        if (!document.getElementById('feedbackModal').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }

    const QUESTIONS = [
        'The topics have been discussed very well.',
        'I have learned a lot from the Tutorial Session.',
        'The mentor is good enough in doing his/her tasks.',
        'The mentor was able to clearly explain the topics I do not understand.',
        'There were adequate exercises given.',
        'The mentor has mastery of the subject matter.',
        'The mentor introduces new techniques or simpler approach to the subject.',
        'I will recommend the Tutorial Sessions to my classmates.',
        'I am coming back to attend more Tutorial Sessions.',
    ];

    function dotClass(score) { return ['','c1','c2','c3','c4','c5'][score] ?? ''; }
    function numClass(score) { return ['','s1','s2','s3','s4','s5'][score] ?? ''; }
    function barColor(avg) {
        if (avg >= 4.5) return '#16a34a';
        if (avg >= 3.5) return '#3b82f6';
        if (avg >= 2.5) return '#eab308';
        return '#ef4444';
    }
    function buildDots(score) {
        return [1,2,3,4,5].map(i =>
            `<div class="q-dot ${i <= score ? dotClass(score) : ''}"></div>`
        ).join('');
    }
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openDetailModal(data) {
        const metaEl = document.getElementById('modalMeta');
        metaEl.innerHTML = `
            <span class="modal-meta-subject">${escapeHtml(data.subject)}</span>
            <span class="modal-meta-topic" title="${escapeHtml(data.topic)}">${escapeHtml(data.topic)}</span>
            <span class="modal-meta-date">${escapeHtml(data.date)}</span>
        `;

        const avg    = data.avg;
        const avgPct = avg ? ((avg / 5) * 100).toFixed(1) : 0;
        const bc     = avg ? barColor(avg) : '#e2e8f0';

        let html = '';

        if (avg !== null && avg !== undefined) {
            html += `
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">
                        Average Score &mdash; Q1 to Q9
                    </span>
                    <span style="font-size:20px;font-weight:800;color:${bc};">
                        ${avg} <span style="font-size:12px;color:#94a3b8;">/ 5</span>
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="avg-bar-track">
                        <div class="avg-bar-fill" style="width:${avgPct}%;background:${bc};"></div>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:${bc};white-space:nowrap;">${data.avgLabel}</span>
                </div>
            </div>`;
        }

        html += `<p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">
                    Likert Scale (1 = Strongly Disagree &nbsp;·&nbsp; 5 = Strongly Agree)
                 </p>`;

        ['q1','q2','q3','q4','q5','q6','q7','q8','q9'].forEach((key, idx) => {
            const s = data[key];
            const valid = s !== null && s !== undefined;
            html += `
            <div class="q-row">
                <div class="q-number">${idx + 1}</div>
                <div class="q-text">${QUESTIONS[idx]}</div>
                <div class="q-score">
                    ${valid ? buildDots(s) : ''}
                    <span class="q-num ${valid ? numClass(s) : ''}">${valid ? s : '—'}</span>
                </div>
            </div>`;
        });

        const q10 = data.q10;
        const q10Html = (q10 === null || q10 === undefined)
            ? `<span style="font-size:11px;color:#94a3b8;">—</span>`
            : q10
                ? `<span class="bool-answer bool-yes"><i class="fa-solid fa-check" style="font-size:9px;margin-right:3px;"></i>Yes &mdash; On time</span>`
                : `<span class="bool-answer bool-no"><i class="fa-solid fa-xmark" style="font-size:9px;margin-right:3px;"></i>No &mdash; Late</span>`;

        html += `
        <div class="q-row" style="border-bottom:none;">
            <div class="q-number">10</div>
            <div class="q-text">The peer mentor started the session on time.</div>
            <div class="q-score">${q10Html}</div>
        </div>`;

        html += `
        <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-top:20px;margin-bottom:6px;">
            Additional Remarks
        </p>`;

        html += data.feedback
            ? `<div class="remarks-box">${escapeHtml(data.feedback)}</div>`
            : `<p style="font-size:12px;color:#d1d5db;font-style:italic;padding:6px 0;">No additional remarks provided.</p>`;

        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('feedbackModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeDetailModal() {
        document.getElementById('feedbackModal').style.display = 'none';
        if (!document.getElementById('feedbackPopup').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }
</script>
