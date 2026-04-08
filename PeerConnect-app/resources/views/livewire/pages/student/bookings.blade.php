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
use function Livewire\Volt\{layout, state, mount, action, computed};

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
    }
});

state([
    'mentor_id'       => '',
    'subject_id'      => '',
    'topic'           => '',
    'tutorialMode_id' => '',
    'date'            => '',
    'schedule_start'  => '',
    'schedule_end'    => '',
    'successMessage'  => false,
]);

state([
    'toggleProfileOpen' => true,
    'profileSaved'      => false,
    'student_num'       => '',
    'college_id'        => '',
    'degreeProgram_id'  => '',
    'yearLevel_id'      => '',
]);

$mentors = computed(function () {
    return MentorProfiles::with('user')
        ->get()
        ->filter(fn($mp) => $mp->user->id !== auth()->id())
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

$subjects       = computed(fn() => Subjects::orderBy('code')->get());
$tutorialModes  = computed(fn() => TutorialMode::orderBy('id')->get());
$colleges       = computed(fn() => Colleges::orderBy('name')->get());
$degreePrograms = computed(fn() => DegreePrograms::orderBy('name')->get());
$yearLevels     = computed(fn() => YearLevels::orderBy('name')->get());

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return collect();
    return Bookings::with(['mentor', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()->take(3)->get();
});

$toggleProfile = action(fn() => $this->toggleProfileOpen = !$this->toggleProfileOpen);

$saveProfile = action(function () {
    abort_if(!auth()->user()->isStudent(), 403);

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
        [
            'student_num'      => $this->student_num,
            'college_id'       => $this->college_id,
            'degreeProgram_id' => $this->degreeProgram_id,
            'yearLevel_id'     => $this->yearLevel_id,
        ]
    );

    $this->profileSaved      = true;
    $this->toggleProfileOpen = false;
    $this->dispatch('profile-updated');
});

$submitBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403);
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
        'tutorialMode_id' => 'mode of tutorial',
        'schedule_start'  => 'start time',
        'schedule_end'    => 'end time',
    ]);

    $profile        = StudentProfiles::where('user_id', auth()->id())->first();
    $selectedMentor = MentorProfiles::find($validated['mentor_id']);

    if ($selectedMentor && $selectedMentor->user_id === auth()->id()) {
        $this->addError('mentor_id', 'You cannot book yourself as a mentor.');
        return;
    }

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

<div>
    {{-- Success banner --}}
    @if($successMessage)
        <div class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg">
            <span>Your session has been booked and is now <strong>pending</strong> approval.</span>
            <button wire:click="dismissSuccessMessage" class="text-green-600 hover:text-green-800 font-bold ml-4">✕</button>
        </div>
    @endif

    @if(!auth()->user()->studentProfile)
        <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-900 px-4 py-3 rounded-lg">
            Please complete your <strong>Student Profile</strong> before booking a session.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- ── BOOKING FORM ── --}}
        <div class="lg:col-span-2">
            <div class="bg-[#fffffa] p-6 rounded-xl shadow-sm border border-gray-200"
                 x-data="{
                    allMentors:        @js($this->mentors),
                    allSubjects:       @js($this->mentorSubjects),
                    allAvailabilities: @js($this->mentorAvailabilities),

                    getDayOfWeek(dateStr) {
                        const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                        return days[new Date(dateStr + 'T00:00:00').getDay()];
                    },

                    get filteredMentors() {
                        let list = this.allMentors;
                        if ($wire.subject_id) {
                            const validIds = this.allSubjects
                                .filter(s => s.subject_id == $wire.subject_id)
                                .map(s => s.mentorProfile_id);
                            list = list.filter(m => validIds.includes(m.profile_id));
                        }
                        if ($wire.date) {
                            const day = this.getDayOfWeek($wire.date);
                            list = list.filter(m => {
                                const avails = this.allAvailabilities.filter(a =>
                                    a.mentorProfile_id == m.profile_id && a.day_of_week === day
                                );
                                if (!avails.length) return false;
                                if ($wire.schedule_start && $wire.schedule_end) {
                                    return avails.some(a =>
                                        a.start_time.slice(0,5) <= $wire.schedule_start.slice(0,5) &&
                                        a.end_time.slice(0,5)   >= $wire.schedule_end.slice(0,5)
                                    );
                                }
                                return true;
                            });
                        }
                        return list;
                    }
                }">

                <h2 class="text-lg font-semibold text-gray-900 mb-1">Request an Enrichment Session!</h2>
                <p class="text-gray-500 text-sm mb-6">Fill out all required fields. Your request will be reviewed by the peer mentor.</p>

                <form id="bookingForm" wire:submit.prevent="submitBooking" class="space-y-4">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                        <select wire:model="subject_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                            <option value="">--- Select a Subject ---</option>
                            @foreach($this->subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->code }} – {{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @error('subject_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Topic <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="topic" placeholder="e.g. Integration by Parts"
                               class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2" maxlength="255">
                        @error('topic') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tutorial Mode <span class="text-red-500">*</span></label>
                        <select wire:model="tutorialMode_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                            <option value="">--- Select Mode ---</option>
                            @foreach($this->tutorialModes as $mode)
                                <option value="{{ $mode->id }}">{{ $mode->mode }}</option>
                            @endforeach
                        </select>
                        @error('tutorialMode_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Day <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="date"
                               min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                        @error('date') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time <span class="text-red-500">*</span></label>
                            <input type="time" wire:model="schedule_start" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                            @error('schedule_start') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time <span class="text-red-500">*</span></label>
                            <input type="time" wire:model="schedule_end" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                            @error('schedule_end') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Mentor <span class="text-red-500">*</span></label>
                        <select wire:model="mentor_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                            <option value=""
                                    x-text="filteredMentors.length === 0
                                        ? '--- No mentors available for this date/time ---'
                                        : '--- Select a Mentor ---'">
                            </option>
                            <template x-for="mentor in filteredMentors" :key="mentor.profile_id">
                                <option :value="mentor.profile_id" x-text="mentor.name"></option>
                            </template>
                        </select>
                        @error('mentor_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-2">
                        <button type="button" id="bookingSubmitBtn"
                                @if(!auth()->user()->studentProfile) disabled @endif
                                class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-colors"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-60 cursor-not-allowed"
                                wire:target="submitBooking">
                            <span wire:loading.remove wire:target="submitBooking">Submit Booking Request</span>
                            <span wire:loading wire:target="submitBooking">Submitting…</span>
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- SIDEBAR PANELS --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Student Profile --}}
            <div class="bg-[#fffffa] rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                 x-data="{
                    open:   $wire.entangle('toggleProfileOpen'),
                    college: $wire.entangle('college_id'),
                    degree:  $wire.entangle('degreeProgram_id'),
                    showSuccess: false,
                    allDegrees: @js($this->degreePrograms),
                    get filteredDeProgs() {
                        if (!this.college) return [];
                        return this.allDegrees.filter(d => d.college_id == this.college);
                    }
                 }"
                 @profile-updated.window="showSuccess = true; setTimeout(() => showSuccess = false, 5000)"
                 x-init="
                    $watch('college', (val, oldVal) => {
                        if (oldVal !== undefined && oldVal !== '') degree = '';
                    });
                    $nextTick(() => { let saved = degree; degree = ''; degree = saved; });
                 ">

                <button @click="open = !open" type="button"
                        class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900">Student Profile</span>
                        @if(auth()->user()->studentProfile)
                            <span class="text-xs bg-green-200 px-2 py-0.5 rounded-full text-green-800 font-bold">Saved</span>
                        @else
                            <span class="text-xs bg-yellow-100 px-2 py-0.5 rounded-full text-yellow-800 font-bold">Required</span>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" style="display:none;" x-transition class="px-5 pb-5 border-t border-gray-100">
                    <div x-show="showSuccess" style="display:none;" x-transition
                         class="mt-3 mb-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        Profile Updated!
                    </div>

                    <form wire:submit.prevent="saveProfile" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Student Number <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="student_num" placeholder="e.g. 2023-00000"
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2" maxlength="10">
                            @error('student_num') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">College <span class="text-red-500">*</span></label>
                            <select x-model="college" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                                <option value="">--- College ---</option>
                                @foreach($this->colleges as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Degree Program <span class="text-red-500">*</span></label>
                            <select x-model="degree" :disabled="!college"
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2 disabled:bg-gray-100">
                                <option value="">--- Degree Program ---</option>
                                <template x-for="deprog in filteredDeProgs" :key="deprog.id">
                                    <option :value="deprog.id" x-text="deprog.name"></option>
                                </template>
                            </select>
                            @error('degreeProgram_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year Level <span class="text-red-500">*</span></label>
                            <select wire:model="yearLevel_id" class="w-full rounded-lg border-gray-300 shadow-sm text-sm px-2 py-2">
                                <option value="">--- Year Level ---</option>
                                @foreach($this->yearLevels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </select>
                            @error('yearLevel_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-4 rounded-lg text-sm transition-colors"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60" wire:target="saveProfile">
                            <span wire:loading.remove wire:target="saveProfile">
                                {{ auth()->user()->studentProfile ? 'Update Profile' : 'Save Profile' }}
                            </span>
                            <span wire:loading wire:target="saveProfile">Saving…</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Recent Bookings --}}
            <div class="bg-[#fffffa] rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Recent Bookings</h3>
                @forelse($this->studentBookings as $booking)
                    <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $booking->subject->code }}</p>
                                <p class="text-xs text-gray-700">{{ $booking->topic }}</p>
                                <p class="text-xs text-gray-700">
                                    {{ strtoupper($booking->mentor->user->lastName ?? 'UNKNOWN') }},
                                    {{ $booking->mentor->user->firstName ?? 'Mentor' }}
                                </p>
                            </div>
                            @php
                                $statusColor = match($booking->booking_status) {
                                    'pending'   => 'bg-yellow-100 text-yellow-800',
                                    'approved'  => 'bg-green-100 text-green-800',
                                    'rejected'  => 'bg-red-100 text-red-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'no-show'   => 'bg-red-100 text-red-800',
                                    default     => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <span class="text-xs font-medium px-2 py-1 rounded-full capitalize {{ $statusColor }}">
                                {{ str_replace('_', ' ', $booking->booking_status) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }},
                            {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }} –
                            {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No recent bookings.</p>
                @endforelse
            </div>

        </div>
    </div>

    {{-- Confirmation modal --}}
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
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                <button id="confirmOkBtn"     class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
            </div>
        </div>
    </div>
</div>

@script
<script>
    const confirmModal     = document.getElementById('confirmModal');
    const confirmModalBox  = document.getElementById('confirmModalBox');
    const confirmTitle     = document.getElementById('confirmTitle');
    const confirmBody      = document.getElementById('confirmBody');
    const confirmMeta      = document.getElementById('confirmMeta');
    const confirmOkBtn     = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmIconWrap  = document.getElementById('confirmIconWrap');

    confirmModal.addEventListener('click', e => { if (!confirmModalBox.contains(e.target)) closeModal(); });
    confirmCancelBtn.addEventListener('click', closeModal);

    function closeModal() { confirmModal.style.display = 'none'; confirmOkBtn.onclick = null; }

    function openConfirmModal({ title, body, meta, variant, onConfirm }) {
        const v = {
            accept:  { icon: iconCheck('#059669'), bg: '#d1fae5', btnCls: 'bg-emerald-600 hover:bg-emerald-700' },
            reject:  { icon: iconX('#dc2626'),     bg: '#fee2e2', btnCls: 'bg-red-600 hover:bg-red-700' },
            neutral: { icon: iconInfo('#64748b'),  bg: '#f1f5f9', btnCls: 'bg-gray-700 hover:bg-gray-800' },
        }[variant] || {};

        confirmIconWrap.style.background = v.bg;
        confirmIconWrap.innerHTML        = v.icon;
        confirmTitle.textContent         = title;
        confirmBody.innerHTML            = body;
        confirmMeta.innerHTML            = meta || '';
        confirmMeta.style.display        = meta ? 'block' : 'none';
        confirmOkBtn.className           = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnCls}`;
        confirmOkBtn.textContent         = 'Confirm';
        confirmOkBtn.onclick             = () => { closeModal(); onConfirm(); };
        confirmModal.style.display       = 'flex';
    }

    function iconCheck(c) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function iconX(c)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${c}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function iconInfo(c)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${c}" stroke-width="1.5"/><path d="M10 9v5" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r=".8" fill="${c}"/></svg>`; }

    function fmtTime(t) {
        if (!t) return '';
        const [h, m] = t.split(':').map(Number);
        return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
    }

    document.getElementById('bookingSubmitBtn').addEventListener('click', function () {
        const sub    = document.querySelector('[wire\\:model="subject_id"]');
        const topic  = document.querySelector('[wire\\:model="topic"]')?.value || '—';
        const date   = document.querySelector('[wire\\:model="date"]')?.value;
        const start  = document.querySelector('[wire\\:model="schedule_start"]')?.value;
        const end    = document.querySelector('[wire\\:model="schedule_end"]')?.value;
        const mentor = document.querySelector('[wire\\:model="mentor_id"]');

        const subjectText = sub?.options[sub.selectedIndex]?.text     || '—';
        const mentorText  = mentor?.options[mentor.selectedIndex]?.text || '—';
        const dateText    = date
            ? new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
            : '—';

        const metaHtml = `
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Subject</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${subjectText}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Topic</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${topic}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Mentor</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${mentorText}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Date</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${dateText}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Time</span><span style="font-weight:600;color:#374151;">${fmtTime(start)} – ${fmtTime(end)}</span></div>
        `;

        openConfirmModal({
            title: 'Confirm booking request?',
            body:  'Please review your session details before submitting.',
            meta:  metaHtml,
            variant: 'accept',
            onConfirm: () => document.getElementById('bookingForm').dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true })
            ),
        });
    });
</script>
@endscript
