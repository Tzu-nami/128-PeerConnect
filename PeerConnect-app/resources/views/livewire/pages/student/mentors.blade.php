<?php
use function Livewire\Volt\{layout, mount, computed, uses};
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Services\Avatar;

$allMentors = computed(function () {
    $query = MentorProfiles::with([
        'user.studentProfile.college',
        'user.studentProfile.degreeProgram',
        'user.studentProfile.yearLevel',
        'subjects',
        'availabilities',
    ]);

    return $query->get()->map(function ($mp) {
        $dayOrder = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $activeDays = $mp->availabilities->pluck('day_of_week')->unique()->sortBy(fn($day) => $dayOrder[strtolower($day)] ?? 99)->map(fn($day) => ucfirst(substr($day, 0, 3)))->values()->toArray();
        $schedule = $mp->availabilities
            ->groupBy(fn($item) => strtolower($item->day_of_week))
            ->map(fn($slots, $day) => [
                'slots' => $slots->sortBy(fn($time) => \Carbon\Carbon::parse($time->start_time)->timestamp)->map(fn($time) => [
                    'start' => \Carbon\Carbon::parse($time->start_time)->format('g:i A'),
                    'end'   => \Carbon\Carbon::parse($time->end_time)->format('g:i A'),
                ])->values()->toArray(),
            ])->toArray();

        if (empty($schedule)) {
            $schedule = new \stdClass();
        }

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
            'bookingUrl'    => route('student.bookings', ['mentor' => $mp->id]),
        ];
    })->sortBy('lastName')->values();
});

$subjects = computed(function () {
    return Subjects::orderBy('code')->get();
});

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});
?>

<div x-data="mentorDirectory(@js($this->allMentors))">
    <div class="mb-3 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4 animate-fade-up">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Our Peer Mentors
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">Browse available mentors and their expertise.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="relative shadow-sm">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" x-model="searchQuery" @input="currentPage = 1"
                       placeholder="Search by name..."
                       class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
            </div>

            {{-- Day filter --}}
            <div class="flex items-center gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200 shadow-sm">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 pl-2 pr-1">Day</span>
                <div class="flex gap-1">
                    <button @click="selectedDay = ''; currentPage = 1"
                            :class="selectedDay === '' ? 'bg-up-maroon text-white shadow-md' : 'bg-white text-slate-600 border border-gray-200 hover:bg-gray-100'"
                            class="px-3 py-1.5 text-xs font-bold rounded transition">All</button>
                    <template x-for="day in ['Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                        <button @click="selectedDay = day; currentPage = 1"
                                :class="selectedDay === day ? 'bg-up-maroon text-white shadow-md' : 'bg-white text-slate-600 border border-gray-200 hover:bg-gray-100'"
                                class="px-3 py-1.5 text-xs font-bold rounded transition"
                                x-text="day"></button>
                    </template>
                </div>
            </div>

            {{-- Subject dropdown --}}
            <div class="relative shadow-sm">
                <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <select x-model="selectedSubject" @change="currentPage = 1"
                        class="appearance-none border border-gray-200 rounded-lg pl-8 pr-8 py-1.5 text-xs font-medium text-slate-700 outline-none cursor-pointer focus:ring-1 focus:border-up-maroon focus:ring-up-maroon bg-white h-[34px]">
                    <option value="">All Subjects</option>
                    @foreach($this->subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Count --}}
    <div class="pb-4">
    <span class="ml-auto text-sm font-medium text-slate-500"
          x-text="'Showing ' + filteredMentors.length + ' mentor' + (filteredMentors.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- Empty state --}}
    <div x-show="filteredMentors.length === 0" x-cloak
         class="bg-white rounded-xl border border-gray-100 py-20 text-center shadow-sm">
        <i class="fa-solid fa-chalkboard-user text-4xl text-gray-300 mb-4 block"></i>
        <p class="font-medium text-gray-500">No mentors found.</p>
        <p class="text-xs mt-1 text-gray-400">Try adjusting your search or filter.</p>
    </div>

    {{-- Mentor cards --}}
    <div x-show="filteredMentors.length > 0" x-cloak
         class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 justify-items-center animate-fade-up [animation-delay:150ms]">
        <template x-for="mentor in paginatedMentors" :key="mentor.id">
            <div class="mentor-card group flex flex-col w-full" @click="openModal(mentor)">

                {{-- Card header --}}
                <div class="p-3 flex gap-3 border-b border-gray-100 bg-white overflow-hidden">
                    <div class="w-20 h-20 flex-shrink-0 rounded-2xl overflow-hidden bg-gray-100 border border-gray-200 shadow-inner">
                        <img :src="mentor.avatar" :alt="mentor.lastName" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1 flex flex-col justify-center min-w-0 overflow-hidden">
                        <p class="font-black text-slate-800 text-lg leading-none uppercase tracking-tighter truncate w-full"
                           x-text="mentor.lastName" :title="mentor.lastName"></p>
                        <p class="font-bold text-slate-600 text-sm leading-tight mt-1 truncate w-full"
                           x-text="mentor.firstName + ' ' + mentor.middleInitial" :title="mentor.firstName + ' ' + mentor.middleInitial"></p>
                        <p class="font-bold text-slate-400 text-xs leading-tight mt-1 truncate w-full"
                           x-text="mentor.email" :title="mentor.email"></p>
                        <template x-if="mentor.yearLevel && mentor.degreeProgram">
                            <p class="text-gray-400 text-[10px] mt-2 leading-tight line-clamp-2 break-words"
                               x-html="mentor.yearLevel + '<br>' + mentor.degreeProgram"></p>
                        </template>
                    </div>
                </div>

                {{-- Subjects --}}
                <div class="px-4 pt-3 pb-2 flex-1">
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Subjects</p>
                    <div class="flex gap-1">
                        <template x-for="(subject, index) in mentor.subjects.slice(0, 3)" :key="index">
                        <span class="bg-red-50 text-red-700 border border-red-100 px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap"
                              x-text="subject.code"></span>
                        </template>
                        <template x-if="mentor.subjects.length > 3">
                        <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 whitespace-nowrap"
                              x-text="'+' + (mentor.subjects.length - 3)"
                              :title="mentor.subjects.slice(3, 10).map(s => s.code).join('\n') + (mentor.subjects.length > 8 ? '\n...and more' : '')"></span>
                        </template>
                    </div>
                </div>

                {{-- Available days --}}
                <div class="px-4 pb-4 pt-2 mt-auto flex justify-between items-end border-t border-gray-50 bg-white group-hover:bg-gray-50/50 transition-colors">
                    <div class="flex-1 pr-2 min-w-0">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Available Days</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="day in mentor.days" :key="day">
                                <span class="day-pill" :title="day" x-text="day === 'Thu' ? 'Th' : day.charAt(0)"></span>
                            </template>
                            <template x-if="mentor.days.length === 0">
                                <span class="text-[10px] text-gray-400 italic">None</span>
                            </template>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                    <span class="text-[11px] font-bold text-slate-400 group-hover:text-[#1a3c2f] transition-colors flex items-center gap-1 tracking-widest whitespace-nowrap">
                        View <i class="fa-solid fa-chevron-right text-[9px] transition-transform group-hover:translate-x-1"></i>
                    </span>
                    </div>
                </div>

            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex justify-center items-center gap-2" x-show="totalPages >= 1" x-cloak>
        <button @click="currentPage--" :disabled="currentPage === 1"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <i class="fa-solid fa-chevron-left text-[10px]"></i>
        </button>
        <template x-for="(page, index) in pages" :key="index">
            <div class="contents">
                <button @click="if (page !== '...') currentPage = page"
                        :class="currentPage === page ? 'bg-[#1a3c2f] text-white shadow-sm' : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'"
                        class="w-8 h-8 text-xs font-bold rounded-lg transition"
                        x-text="page"
                        x-show="page !== '...'"></button>
                <span x-show="page === '...'"
                      class="w-7 h-7 flex items-center justify-center text-[11px] font-bold text-gray-400 tracking-widest shrink-0">...</span>
            </div>
        </template>
        <button @click="currentPage++" :disabled="currentPage === totalPages"
                class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 bg-white text-slate-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition">
            <i class="fa-solid fa-chevron-right text-[10px]"></i>
        </button>
    </div>

    {{-- Modal --}}
    <template x-teleport="body">
        <div class="modal-overlay" x-show="showModal" @click.self="showModal = false" x-cloak>
            <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
                <template x-if="selectedMentor">
                    <div class="contents">
                        {{-- Modal Header --}}
                        <div class="flex-shrink-0 flex items-start gap-5 p-6 bg-[#1a3c2f]">
                            <div class="w-36 h-36 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-white/20 shadow-lg bg-gray-200">
                                <img :src="selectedMentor.avatar" :alt="selectedMentor.lastName" class="w-full h-full object-cover bg-white" />
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <p class="text-white font-black text-2xl leading-tight tracking-tight truncate"
                                   x-text="selectedMentor.lastName + ', ' + selectedMentor.firstName + ' ' + selectedMentor.middleInitial"
                                   :title="selectedMentor.lastName + ', ' + selectedMentor.firstName + ' ' + selectedMentor.middleInitial"></p>
                                <template x-if="selectedMentor.yearLevel && selectedMentor.degreeProgram">
                                    <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.yearLevel + ' — ' + selectedMentor.degreeProgram"></p>
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

                        {{-- Modal Body --}}
                        <div class="overflow-y-auto flex-1 p-6 space-y-6 bg-white">
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

                            {{-- Availability --}}
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Weekly Availability</p>
                                <div class="avail-grid">
                                    <template x-for="day in weekDays" :key="day">
                                        <div>
                                            <div class="avail-day-header" x-text="day.charAt(0).toUpperCase() + day.slice(1, 3)"></div>
                                            <div class="avail-day-col">
                                                <template x-if="selectedMentor.schedule[day]">
                                                    <template x-for="(slot, index) in selectedMentor.schedule[day].slots" :key="index">
                                                        <div class="avail-slot" x-html="slot.start + '<br>' + slot.end"></div>
                                                    </template>
                                                </template>
                                                <template x-if="!selectedMentor.schedule[day]">
                                                    <div class="avail-empty"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <p class="text-[12px] mt-3 flex items-center justify-center gap-4">
                                    <span><span class="inline-block w-3 h-3 rounded bg-[#d1fae5] mr-1 align-middle"></span> Available</span>
                                    <span><span class="inline-block w-3 h-3 rounded border border-dashed border-gray-200 bg-[#f8fafc] mr-1 align-middle"></span> Unavailable</span>
                                </p>
                            </div>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="flex-shrink-0 px-6 py-4 bg-[#fffffa] border-t border-gray-100">
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
