<?php

use function Livewire\Volt\{layout, mount, computed};
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Services\Avatar;

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

$allMentors = computed(function () {
    $query = MentorProfiles::with([
        'user.studentProfile.college',
        'user.studentProfile.degreeProgram',
        'user.studentProfile.yearLevel',
        'subjects',
        'availabilities',
    ]);

    return $query->get()->map(function ($mp) {
        $dayOrder   = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $activeDays = $mp->availabilities->pluck('day_of_week')->unique()
            ->sortBy(fn($day) => $dayOrder[strtolower($day)] ?? 99)
            ->map(fn($day) => ucfirst(substr($day, 0, 3)))
            ->values()->toArray();

        $schedule = $mp->availabilities
            ->groupBy(fn($item) => strtolower($item->day_of_week))
            ->map(fn($slots) => [
                'slots' => $slots->sortBy(fn($t) => \Carbon\Carbon::parse($t->start_time)->timestamp)
                    ->map(fn($t) => [
                        'start' => \Carbon\Carbon::parse($t->start_time)->format('g:i A'),
                        'end'   => \Carbon\Carbon::parse($t->end_time)->format('g:i A'),
                    ])->values()->toArray(),
            ])->toArray();

        if (empty($schedule)) $schedule = new \stdClass();

        return [
            'id'            => $mp->id,
            'user_id'       => $mp->user_id,
            'lastName'      => strtoupper($mp->user->lastName),
            'firstName'     => $mp->user->firstName,
            'middleInitial' => $mp->user->middleInitial ? $mp->user->middleInitial . '.' : '',
            'email'         => $mp->user->email,
            'avatar'        => $mp->user->avatar ?? app(Avatar::class)->placeholder($mp->user->firstName . ' ' . $mp->user->lastName),
            'subjects'      => $mp->subjects->unique('id')->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->sortBy('code')->values()->toArray(),
            'days'          => $activeDays,
            'schedule'      => $schedule,
            'yearLevel'     => $mp->user->studentProfile->yearLevel->name,
            'degreeProgram' => $mp->user->studentProfile->degreeProgram->name,
            'college'       => $mp->user->studentProfile->college->name,
            'bookingUrl'    => route('student.bookings', ['mentor' => $mp->user_id]),
        ];
    })->sortBy('lastName')->values();
});

$subjects = computed(function () {
    return Subjects::orderBy('code')->get();
});

?>

<style>
    .mentor-card {
        background: #fffffa;
        border: 1.5px solid #e5e7eb;
        border-radius: 16px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .mentor-card:hover {
        border-color: #1a3c2f;
        box-shadow: 0 8px 24px rgba(30,49,61,0.15);
        transform: translateY(-3px);
    }
    .day-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
    }
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 24px;
    }
    .avail-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
    .avail-day-header { font-size: 9px; font-weight: 800; text-align: center; color: #94a3b8; text-transform: uppercase; padding-bottom: 4px; }
    .avail-day-col   { display: flex; flex-direction: column; gap: 3px; }
    .avail-slot      { background: #d1fae5; color: #065f46; font-size: 9px; font-weight: 700; padding: 3px 4px; border-radius: 4px; text-align: center; line-height: 1.3; }
    .avail-empty     { background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 4px; height: 28px; }
</style>

<div x-data="mentorDirectory(@js($this->allMentors))">

    {{-- Header + filters --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Our Peer Mentors</h1>
            <p class="text-sm text-gray-400 mt-1">Browse available mentors and their expertise.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search mentors..."
                       class="pl-8 pr-3 py-2 text-xs border border-gray-200 rounded-lg bg-[#fffffa] outline-none focus:ring-1 focus:ring-red-800 w-64">
            </div>
            <select x-model="selectedDay" @change="currentPage = 1"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-xs text-slate-600 outline-none cursor-pointer focus:ring-1 focus:ring-red-800 bg-[#fffffa]">
                <option value="">Any Day</option>
                <option value="Mon">Monday</option>
                <option value="Tue">Tuesday</option>
                <option value="Wed">Wednesday</option>
                <option value="Thu">Thursday</option>
                <option value="Fri">Friday</option>
                <option value="Sat">Saturday</option>
            </select>
            <select x-model="selectedSubject" @change="currentPage = 1"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-xs text-slate-600 outline-none cursor-pointer focus:ring-1 focus:ring-red-800 bg-[#fffffa]">
                <option value="">All Subjects</option>
                @foreach($this->subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->code }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="text-xs text-gray-400 mb-4 font-medium"
       x-text="filteredMentors.length + ' mentor' + (filteredMentors.length !== 1 ? 's' : '') + ' found'"></p>

    {{-- Empty state --}}
    <div x-show="filteredMentors.length === 0" x-cloak
         class="bg-[#fffffa] rounded-xl border border-gray-100 py-20 text-center shadow-sm">
        <i class="fa-solid fa-chalkboard-user text-4xl text-gray-300 mb-4 block"></i>
        <p class="font-medium text-gray-500">No mentors found.</p>
        <p class="text-xs mt-1 text-gray-400">Try adjusting your search or filter.</p>
    </div>

    {{-- Mentor cards --}}
    <div x-show="filteredMentors.length > 0" x-cloak
         class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 max-w-7xl mx-auto">
        <template x-for="mentor in paginatedMentors" :key="mentor.id">
            <div class="mentor-card group flex flex-col h-full" @click="openModal(mentor)">

                <div class="p-3 flex gap-5 border-b border-gray-100 bg-[#fffffa]">
                    <div class="w-28 h-28 aspect-square rounded-2xl overflow-hidden flex-shrink-0 bg-gray-100 border border-gray-200 shadow-inner">
                        <img :src="mentor.avatar" :alt="mentor.lastName" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 flex flex-col min-w-0 pr-1">
                        <p class="font-black text-slate-800 text-2xl sm:text-3xl leading-none uppercase tracking-tighter truncate" x-text="mentor.lastName"></p>
                        <p class="font-bold text-slate-600 text-l leading-tight mt-0.5 truncate" x-text="mentor.firstName + ' ' + mentor.middleInitial"></p>
                        <p class="font-bold text-slate-400 text-xs leading-tight mt-1 truncate" x-text="mentor.email"></p>
                        <template x-if="mentor.yearLevel && mentor.degreeProgram">
                            <p class="text-gray-400 text-[10px] mt-2 leading-tight line-clamp-3"
                               x-html="mentor.yearLevel + '<br>' + mentor.degreeProgram"></p>
                        </template>
                    </div>
                </div>

                <div class="px-5 pt-2 pb-2 flex-1">
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Subjects</p>
                    <div class="flex flex-nowrap overflow-hidden gap-1">
                        <template x-for="(subject, index) in mentor.subjects.slice(0, 4)" :key="index">
                            <span class="bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap"
                                  x-text="subject.code"></span>
                        </template>
                        <template x-if="mentor.subjects.length > 4">
                            <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 whitespace-nowrap"
                                  x-text="'+' + (mentor.subjects.length - 4)"></span>
                        </template>
                    </div>
                </div>

                <div class="px-5 pb-6 pt-2 mt-auto flex justify-between items-end border-t border-gray-50 bg-[#fffffa] group-hover:bg-gray-50/50 transition-colors">
                    <div class="flex-1 pr-3">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Available Days</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="day in mentor.days"><span class="day-pill" x-text="day"></span></template>
                            <template x-if="mentor.days.length === 0">
                                <span class="text-[10px] text-gray-400 italic">None</span>
                            </template>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <span class="text-[12px] font-bold text-slate-400 group-hover:text-[#1a3c2f] transition-colors flex items-center gap-1 tracking-widest whitespace-nowrap">
                            View Profile <i class="fa-solid fa-chevron-right text-[10px] transition-transform group-hover:translate-x-1"></i>
                        </span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div class="mt-6 flex justify-center items-center gap-2" x-show="totalPages > 1" x-cloak>
        <button @click="currentPage--" :disabled="currentPage === 1"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-[#fffffa] text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <i class="fa-solid fa-chevron-left text-[10px]"></i>
        </button>
        <template x-for="page in pages" :key="page">
            <button @click="currentPage = page"
                    :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-[#fffffa] border border-gray-200 text-slate-500 hover:bg-gray-100'"
                    class="w-8 h-8 text-xs font-bold rounded-lg transition" x-text="page"></button>
        </template>
        <button @click="currentPage++" :disabled="currentPage === totalPages"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-[#fffffa] text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
        </button>
    </div>

    {{-- Mentor modal --}}
    <template x-teleport="body">
        <div class="modal-overlay" x-show="showModal" @click.self="showModal = false" x-cloak>
            <div class="bg-[#fffffa] w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
                <template x-if="selectedMentor">
                    <div class="contents">

                        {{-- Modal header --}}
                        <div class="flex-shrink-0 flex items-start gap-5 p-6 bg-[#1a3c2f]">
                            <div class="w-36 h-36 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-white/20 shadow-lg bg-gray-200">
                                <img :src="selectedMentor.avatar" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <p class="text-white font-black text-2xl leading-tight tracking-tight"
                                   x-text="selectedMentor.lastName + ', ' + selectedMentor.firstName + ' ' + selectedMentor.middleInitial"></p>
                                <template x-if="selectedMentor.yearLevel && selectedMentor.degreeProgram">
                                    <p class="text-white/60 text-xs mt-1"
                                       x-text="selectedMentor.yearLevel + ' — ' + selectedMentor.degreeProgram"></p>
                                </template>
                                <template x-if="selectedMentor.college">
                                    <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.college"></p>
                                </template>
                                <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.email"></p>
                            </div>
                            <button @click="showModal = false" class="text-white/50 hover:text-white transition flex-shrink-0 mt-1">
                                <i class="fa-solid fa-xmark text-xl"></i>
                            </button>
                        </div>

                        {{-- Modal body --}}
                        <div class="overflow-y-auto flex-1 p-6 space-y-6 bg-[#fffffa]">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Teachable Subjects</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(subject, index) in selectedMentor.subjects" :key="index">
                                        <span class="bg-red-50 text-red-700 border border-red-100 text-xs px-3 py-1 rounded font-bold"
                                              x-text="subject.code"></span>
                                    </template>
                                    <template x-if="selectedMentor.subjects.length === 0">
                                        <p class="text-xs text-gray-400">No subjects listed.</p>
                                    </template>
                                </div>
                            </div>

                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Weekly Availability</p>
                                <div class="avail-grid">
                                    <template x-for="day in weekDays" :key="day">
                                        <div>
                                            <div class="avail-day-header"
                                                 x-text="day.charAt(0).toUpperCase() + day.slice(1,3)"></div>
                                            <div class="avail-day-col">
                                                <template x-if="selectedMentor.schedule[day]">
                                                    <template x-for="(slot, i) in selectedMentor.schedule[day].slots" :key="i">
                                                        <div class="avail-slot"
                                                             x-html="slot.start + '<br>' + slot.end"></div>
                                                    </template>
                                                </template>
                                                <template x-if="!selectedMentor.schedule[day]">
                                                    <div class="avail-empty"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p class="text-[12px] mt-3 flex items-center justify-center gap-4 text-gray-500">
                                    <span><span class="inline-block w-3 h-3 rounded bg-[#d1fae5] mr-1 align-middle"></span> Available</span>
                                    <span><span class="inline-block w-3 h-3 rounded border border-dashed border-gray-200 bg-[#f8fafc] mr-1 align-middle"></span> Unavailable</span>
                                </p>
                            </div>
                        </div>

                        {{-- Book button --}}
                        <div class="flex-shrink-0 px-6 py-4 bg-gray-50 border-t border-gray-100">
                            <a :href="selectedMentor.bookingUrl"
                               class="block w-full text-center bg-[#1a3c2f] hover:bg-[#2d5c47] text-white text-sm font-bold py-3 rounded-xl transition shadow-sm">
                                <i class="fa-solid fa-calendar-check mr-2"></i> Book a Session
                            </a>
                        </div>

                    </div>
                </template>
            </div>
        </div>
    </template>

</div>


<script>
    function mentorDirectory(initialMentors) {
        return {
            mentors: initialMentors,
            searchQuery: '',
            selectedSubject: '',
            selectedDay: '',
            currentPage: 1,
            perPage: 6,
            showModal: false,
            selectedMentor: null,
            weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

            get filteredMentors() {
                return this.mentors.filter(mentor => {
                    const q        = this.searchQuery.toLowerCase();
                    const fullName = (mentor.firstName + ' ' + mentor.lastName).toLowerCase();
                    const matchSearch  = !q || fullName.includes(q);
                    const matchSub     = !this.selectedSubject || mentor.subjects.some(s => s.id == this.selectedSubject);
                    const matchDay     = !this.selectedDay    || mentor.days.includes(this.selectedDay);
                    return matchSearch && matchSub && matchDay;
                });
            },
            get paginatedMentors() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.filteredMentors.slice(start, start + this.perPage);
            },
            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredMentors.length / this.perPage));
            },
            get pages() {
                return Array.from({ length: this.totalPages }, (_, i) => i + 1);
            },
            openModal(mentor) {
                this.selectedMentor = mentor;
                this.showModal = true;
            },
        };
    }
</script>

