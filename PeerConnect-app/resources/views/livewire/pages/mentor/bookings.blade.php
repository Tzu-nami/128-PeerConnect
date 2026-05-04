<?php

use App\Models\Bookings;
use App\Models\Feedback;
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Models\TutorialMode;
use App\Models\StudentProfiles;
use App\Models\Colleges;
use App\Models\DegreePrograms;
use App\Models\YearLevels;
use App\Models\MentorSubjects;
use App\Models\MentorAvailabilities;
use Illuminate\Support\Facades\Mail;
use App\Mail\MentorBookingNotification;
use App\Mail\StudentCancelledSession;
use function Livewire\Volt\{layout, state, mount, action, computed};

// Mount
mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    if ($profile) {
        $this->student_num       = $profile->student_num;
        $this->college_id        = $profile->college_id;
        $this->degreeProgram_id  = $profile->degreeProgram_id;
        $this->yearLevel_id      = $profile->yearLevel_id;
        $this->toggleProfileOpen = false;
        $this->isProfileLocked   = true;

        $this->showFeedbackModal = Bookings::where('student_id', $profile->id)
            ->where('booking_status', 'completed')
            ->where(function ($q) {
                $q->whereNull('completed_at')
                    ->orWhere('completed_at', '>=', now()->subDays(2));
            })
            ->whereNotIn('id', fn($q) => $q->select('booking_id')->from('feedback'))
            ->exists();
    } else {
        $this->showFeedbackModal = false;
    }

    if (request()->has('mentor')) {
        $this->mentor_id      = (string) request('mentor');
        $this->isMentorLocked = true;
    }
});

// State
state([
    // Booking form
    'mentor_id'        => '',
    'isMentorLocked'   => false,
    'subject_id'       => '',
    'topic'            => '',
    'tutorialMode_id'  => '',
    'date'             => '',
    'schedule_start'   => '',
    'schedule_end'     => '',
    'successMessage'   => false,
    'cancelledMessage' => false,

    // Feedback modal + form
    'showFeedbackModal' => true,
    'feedbackText'      => '',
    'feedbackSubmitted' => false,
    'feedbackStep'      => 1,
    'q1'  => null,
    'q2'  => null,
    'q3'  => null,
    'q4'  => null,
    'q5'  => null,
    'q6'  => null,
    'q7'  => null,
    'q8'  => null,
    'q9'  => null,
    'q10' => null,

    // Student profile
    'toggleProfileOpen' => true,
    'isProfileLocked'   => false,
    'profileSaved'      => false,
    'student_num'       => '',
    'college_id'        => '',
    'degreeProgram_id'  => '',
    'yearLevel_id'      => '',
]);

// Computed Properties
$mentors = computed(function () {
    return MentorProfiles::with('user')
        ->get()
        ->filter(fn($m) => $m->user->id !== auth()->id())
        ->sortBy(fn($m) => $m->user->lastName)
        ->values()
        ->map(fn($m) => [
            'id'         => $m->user->id,
            'profile_id' => $m->id,
            'name'       => strtoupper($m->user->lastName) . ', ' . $m->user->firstName,
        ])
        ->toArray();
});

$mentorAvailabilities = computed(function () {
    return MentorAvailabilities::all()
        ->map(fn($avail) => [
            'mentorProfile_id' => $avail->mentor_id,
            'day_of_week'      => $avail->day_of_week,
            'start_time'       => $avail->start_time,
            'end_time'         => $avail->end_time,
        ])
        ->values()
        ->toArray();
});

$mentorSubjects = computed(function () {
    return MentorSubjects::all()
        ->map(fn($s) => [
            'mentorProfile_id' => $s->mentor_id,
            'subject_id'       => $s->subject_id,
        ])
        ->values()
        ->toArray();
});

$subjects = computed(function () {
    if ($this->isMentorLocked && $this->mentor_id) {
        $subjectIds = MentorSubjects::where('mentor_id', $this->mentor_id)->pluck('subject_id');
        return Subjects::whereIn('id', $subjectIds)->orderBy('code')->get();
    }
    return Subjects::orderBy('code')->get();
});

$tutorialModes = computed(function () {
    return TutorialMode::orderBy('id')->get();
});

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return collect();
    return Bookings::with(['mentor', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()
        ->take(5)
        ->get();
});

$completedBooking = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return null;
    return Bookings::with(['mentor.user', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->where('booking_status', 'completed')
        ->where(function ($q) {
            $q->whereNull('completed_at')
                ->orWhere('completed_at', '>=', now()->subDays(2));
        })
        ->whereNotIn('id', fn($q) => $q->select('booking_id')->from('feedback'))
        ->latest()
        ->first();
});

$activeBooking = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if (!$profile) return null;
    return Bookings::with(['mentor.user', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
        ->latest()
        ->first();
});

$colleges = computed(function () {
    return Colleges::orderBy('name')->get();
});

$degreePrograms = computed(function () {
    return DegreePrograms::orderBy('name')->get();
});

$yearLevels = computed(function () {
    return YearLevels::orderBy('name')->get();
});

// Student Profile Actions
$toggleProfile = action(function () {
    $this->toggleProfileOpen = !$this->toggleProfileOpen;
});

$saveProfile = action(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $validated = $this->validate(
        [
            'student_num'      => ['required', 'string', 'max:10', 'regex:/-/'],
            'college_id'       => ['required', 'exists:colleges,id'],
            'degreeProgram_id' => ['required', 'exists:degree_programs,id'],
            'yearLevel_id'     => ['required', 'exists:year_levels,id'],
        ],
        messages: [
            'student_num.regex' => 'The student number must include a hyphen (-).',
        ],
        attributes: [
            'student_num'      => 'student number',
            'college_id'       => 'college',
            'degreeProgram_id' => 'degree program',
            'yearLevel_id'     => 'year level',
        ]
    );

    StudentProfiles::updateOrCreate(
        ['user_id' => auth()->id()],
        $validated
    );

    $this->profileSaved      = true;
    $this->isProfileLocked   = true;
    $this->toggleProfileOpen = false;
    $this->dispatch('profile-updated');
});

// Booking Validation Rules
$bookingRules = [
    'mentor_id'       => ['required'],
    'subject_id'      => ['required', 'exists:subjects,id'],
    'topic'           => ['required', 'string', 'max:255'],
    'tutorialMode_id' => ['required', 'exists:tutorial_modes,id'],
    'date'            => [
        'required',
        'date',
        'after:today',
        function ($attribute, $value, $fail) {
            if (\Carbon\Carbon::parse($value)->format('l') === 'Sunday') {
                $fail('The session cannot be on a Sunday. Please select another date.');
            }
        },
    ],
    'schedule_start' => ['required', 'date_format:H:i'],
    'schedule_end'   => ['required', 'date_format:H:i', 'after:schedule_start'],
];

$bookingAttributes = [
    'mentor_id'       => 'mentor',
    'subject_id'      => 'subject',
    'topic'           => 'topic',
    'tutorialMode_id' => 'mode of tutorial',
    'date'            => 'date',
    'schedule_start'  => 'start time',
    'schedule_end'    => 'end time',
];

// Booking Actions
$validateBooking = action(function () use ($bookingRules, $bookingAttributes) {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $hasActive = Bookings::where('student_id', $profile->id)
        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
        ->exists();

    if ($hasActive) {
        $this->addError(
            'mentor_id',
            'You already have an active booking. Please wait for it to be completed or rejected before making a new one.'
        );
        return;
    }

    if ($this->mentor_id && $this->mentor_id !== 'any') {
        $selectedMentor = MentorProfiles::find($this->mentor_id);
        if ($selectedMentor && $selectedMentor->user_id === auth()->id()) {
            $this->addError('mentor_id', 'You cannot book yourself as a mentor.');
            return;
        }
    }

    $validated = $this->validate($bookingRules, [], $bookingAttributes);

    if ($validated['mentor_id'] === 'any') {
        $dayOfWeek = strtolower(\Carbon\Carbon::parse($validated['date'])->format('l'));

        $qualifiedMentors = \App\Models\User::whereHas('mentorProfile', function ($q) use ($validated, $dayOfWeek) {
            $q->whereHas('subjects', function ($sq) use ($validated) {
                $sq->where('subject_id', $validated['subject_id']);
            })
                ->whereHas('availabilities', function ($aq) use ($validated, $dayOfWeek) {
                    $aq->where('day_of_week', $dayOfWeek)
                        ->whereTime('start_time', '<=', $validated['schedule_start'])
                        ->whereTime('end_time', '>=', $validated['schedule_end']);
                });
        })
            ->where('id', '!=', auth()->id())
            ->exists();

        if (!$qualifiedMentors) {
            $this->addError('mentor_id', 'No mentors are available for this specific date and timeframe.');
            return;
        }
    }

    $this->dispatch('show-booking-confirm');
});

$submitBooking = action(function () use ($bookingRules, $bookingAttributes) {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $validated = $this->validate($bookingRules, [], $bookingAttributes);

    if ($validated['mentor_id'] === 'any') {
        $dayOfWeek = strtolower(\Carbon\Carbon::parse($validated['date'])->format('l'));

        $qualifiedMentors = \App\Models\User::whereHas('mentorProfile', function ($q) use ($validated, $dayOfWeek) {
            $q->whereHas('subjects', function ($sq) use ($validated) {
                $sq->where('subject_id', $validated['subject_id']);
            })
                ->whereHas('availabilities', function ($aq) use ($validated, $dayOfWeek) {
                    $aq->where('day_of_week', $dayOfWeek)
                        ->whereTime('start_time', '<=', $validated['schedule_start'])
                        ->whereTime('end_time', '>=', $validated['schedule_end']);
                });
        })
            ->where('id', '!=', auth()->id())
            ->get();

        if ($qualifiedMentors->isEmpty()) {
            $this->addError('mentor_id', 'No mentors are available for this specific date and timeframe.');
            return;
        }

        $booking = Bookings::create([
            ...$validated,
            'student_id'     => $profile->id,
            'mentor_id'      => null,
            'booking_status' => 'pending',
        ]);

        $emails = $qualifiedMentors->pluck('email')->filter()->toArray();
        if (!empty($emails)) {
            Mail::to($emails)->send(new MentorBookingNotification($booking));
        }
    } else {
        $booking = Bookings::create([
            ...$validated,
            'student_id'     => $profile->id,
            'booking_status' => 'pending',
        ]);

        $selectedMentor = MentorProfiles::find($validated['mentor_id']);
        if ($selectedMentor && $selectedMentor->user->email) {
            Mail::to($selectedMentor->user->email)->send(new MentorBookingNotification($booking));
        }
    }

    $this->reset(['mentor_id', 'subject_id', 'topic', 'tutorialMode_id', 'date', 'schedule_start', 'schedule_end']);
    $this->successMessage = true;
});

$cancelBooking = action(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $booking = Bookings::where('student_id', $profile->id)
        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
        ->latest()
        ->first();

    abort_if(!$booking, 404);

    $booking->update(['booking_status' => 'cancelled']);

    // Guard against null mentor (e.g. "any mentor" bookings not yet accepted)
    if ($booking->mentor && $booking->mentor->user && $booking->mentor->user->email) {
        Mail::to($booking->mentor->user->email)->send(new StudentCancelledSession($booking));
    }

    $this->cancelledMessage = true;
});

// Feedback Actions
$nextFeedbackStep = action(function () {
    if ($this->feedbackStep === 1) {
        $this->validate(
            [
                'q1' => ['required', 'integer', 'min:1', 'max:5'],
                'q2' => ['required', 'integer', 'min:1', 'max:5'],
                'q3' => ['required', 'integer', 'min:1', 'max:5'],
                'q4' => ['required', 'integer', 'min:1', 'max:5'],
                'q5' => ['required', 'integer', 'min:1', 'max:5'],
            ],
            attributes: [
                'q1' => 'Question 1',
                'q2' => 'Question 2',
                'q3' => 'Question 3',
                'q4' => 'Question 4',
                'q5' => 'Question 5',
            ]
        );
        $this->feedbackStep = 2;

    } elseif ($this->feedbackStep === 2) {
        $this->validate(
            [
                'q6'  => ['required', 'integer', 'min:1', 'max:5'],
                'q7'  => ['required', 'integer', 'min:1', 'max:5'],
                'q8'  => ['required', 'integer', 'min:1', 'max:5'],
                'q9'  => ['required', 'integer', 'min:1', 'max:5'],
                'q10' => ['required', 'in:0,1'],
            ],
            attributes: [
                'q6'  => 'Question 6',
                'q7'  => 'Question 7',
                'q8'  => 'Question 8',
                'q9'  => 'Question 9',
                'q10' => 'Question 10',
            ]
        );
        $this->feedbackStep = 3;
    }
});

$prevFeedbackStep = action(function () {
    if ($this->feedbackStep > 1) {
        $this->feedbackStep--;
    }
});

$submitFeedback = action(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $this->validate([
        'feedbackText' => ['nullable', 'string', 'max:2000'],
        'q1'  => ['required', 'integer', 'min:1', 'max:5'],
        'q2'  => ['required', 'integer', 'min:1', 'max:5'],
        'q3'  => ['required', 'integer', 'min:1', 'max:5'],
        'q4'  => ['required', 'integer', 'min:1', 'max:5'],
        'q5'  => ['required', 'integer', 'min:1', 'max:5'],
        'q6'  => ['required', 'integer', 'min:1', 'max:5'],
        'q7'  => ['required', 'integer', 'min:1', 'max:5'],
        'q8'  => ['required', 'integer', 'min:1', 'max:5'],
        'q9'  => ['required', 'integer', 'min:1', 'max:5'],
        'q10' => ['required', 'in:0,1'],
    ]);

    $booking = $this->completedBooking;
    abort_if(!$booking, 404);

    \DB::table('feedback')->insert([
        'id'             => (string) \Illuminate\Support\Str::uuid(),
        'booking_id'     => $booking->id,
        'feedback'       => $this->feedbackText ?: null,
        'subject'        => $booking->subject->code ?? null,
        'topic'          => $booking->topic ?? null,
        'date_submitted' => now(),
        'q1'             => (int) $this->q1,
        'q2'             => (int) $this->q2,
        'q3'             => (int) $this->q3,
        'q4'             => (int) $this->q4,
        'q5'             => (int) $this->q5,
        'q6'             => (int) $this->q6,
        'q7'             => (int) $this->q7,
        'q8'             => (int) $this->q8,
        'q9'             => (int) $this->q9,
        'q10'            => \DB::raw($this->q10 == '1' ? 'true' : 'false'),
    ]);

    $this->reset(['feedbackText', 'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9', 'q10']);
    $this->feedbackStep      = 1;
    $this->feedbackSubmitted = true;

    unset($this->completedBooking);
});

$skipFeedback = action(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $booking = $this->completedBooking;
    if (!$booking) return;

    \DB::table('feedback')->insert([
        'id'             => (string) \Illuminate\Support\Str::uuid(),
        'booking_id'     => $booking->id,
        'feedback'       => null,
        'subject'        => $booking->subject->code ?? null,
        'topic'          => $booking->topic ?? null,
        'date_submitted' => now(),
        'q1'  => null,
        'q2'  => null,
        'q3'  => null,
        'q4'  => null,
        'q5'  => null,
        'q6'  => null,
        'q7'  => null,
        'q8'  => null,
        'q9'  => null,
        'q10' => null,
    ]);

    $this->showFeedbackModal = false;

    unset($this->completedBooking);
});

?>
<div>
    {{-- ── Flash messages ── --}}
    @if ($successMessage)
        <div x-data="autoFade()"
             x-show="show"
             x-transition.opacity
             class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
            <span>Your session has been booked and is now <strong>pending</strong> approval.</span>
        </div>
    @endif

    @if ($cancelledMessage)
        <div x-data="autoFade()"
             x-show="show"
             x-transition.opacity
             class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
            <span>Your booking has been <strong>cancelled</strong>. You may now request a new session.</span>
        </div>
    @endif

    @if ($feedbackSubmitted)
        <div x-data="autoFade()"
             x-show="show"
             x-transition.opacity
             class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
            <span>
                <i class="fa-solid fa-circle-check mr-2"></i>
                Thank you for your feedback! You may now request a new session.
            </span>
        </div>
    @endif

    @if (!auth()->user()->studentProfile)
        <div class="mb-6 bg-yellow-100 border border-yellow-400 text-black-800 px-4 py-3 rounded">
            Please complete your <strong>Student Profile</strong> before booking a session.
        </div>
    @endif

    {{-- ── Session Complete Modal --}}
    <div
        x-data="{ show: {{ ($this->completedBooking && $showFeedbackModal) ? 'true' : 'false' }} }"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm">

        @if ($this->completedBooking)
            @php $cb = $this->completedBooking; @endphp
            <div class="session-complete-modal-box" id="sessionCompleteModalBox">

                <div class="scm-icon-wrap">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>

                <div class="scm-badge">
                    <i class="fa-solid fa-clipboard-list text-xs"></i>
                    Feedback Form
                </div>

                <div class="scm-title">Your session has been completed!</div>
                <p class="scm-subtitle">
                    Great news — your enrichment session has ended. We'd love to hear how it went!
                    Your feedback helps us improve the peer mentoring program.
                    <br><br>
                    <span class="font-semibold text-gray-700">Would you like to answer the Feedback Form?</span>
                    It only takes a minute, and it's completely optional.
                </p>

                <div class="scm-session-info">
                    <div class="si-row">
                        <span class="si-label">Subject</span>
                        <span class="si-value">{{ ($cb->subject->code ?? '—') . ($cb->subject->name ? ' — ' . $cb->subject->name : '') }}</span>
                    </div>
                    <div class="si-row">
                        <span class="si-label">Mentor</span>
                        <span class="si-value">{{ strtoupper($cb->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $cb->mentor->user->firstName ?? '' }}</span>
                    </div>
                    <div class="si-row">
                        <span class="si-label">Date</span>
                        <span class="si-value">{{ \Carbon\Carbon::parse($cb->date)->format('F j, Y') }}</span>
                    </div>
                    <div class="si-row">
                        <span class="si-label">Topic</span>
                        <span class="si-value truncate" style="max-width:180px;" title="{{ $cb->topic }}">{{ $cb->topic }}</span>
                    </div>
                </div>

                <div class="scm-actions">
                    <button type="button" class="scm-btn-skip"
                            wire:click="skipFeedback"
                            @click="show = false; window.location.reload()"
                            wire:loading.attr="disabled"
                            wire:target="skipFeedback">
                        <span wire:loading.remove wire:target="skipFeedback">
                            <i class="fa-solid fa-forward-step mr-1 text-xs"></i> Skip for now
                        </span>
                        <span wire:loading wire:target="skipFeedback">
                            <i class="fa-solid fa-spinner fa-spin mr-1 text-xs"></i> Skipping...
                        </span>
                    </button>
                    <button type="button" class="scm-btn-answer"
                            @click="
                                show = false;
                                $nextTick(() => {
                                    const card = document.getElementById('feedbackFormCard');
                                    if (card) {
                                        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                        card.style.transition = 'box-shadow 0.3s';
                                        card.style.boxShadow  = '0 0 0 4px rgba(22,163,74,0.35)';
                                        setTimeout(() => { card.style.boxShadow = ''; }, 1800);
                                    }
                                })"
                    >
                        <i class="fa-solid fa-clipboard-list mr-1.5 text-xs"></i> Answer Feedback Form
                    </button>
                </div>

                <p class="text-[10px] text-gray-400 text-center mt-4 leading-snug">
                    Skipping will dismiss this prompt permanently for this session.<br>
                    You will not be asked again for this specific session.
                </p>

            </div>
        @endif
    </div>

    {{-- ── Main content grid ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- LEFT COLUMN --}}
        <div class="lg:col-span-2">

            @php
                $questions = [
                    1 => 'The topics have been discussed very well.',
                    2 => 'I have learned a lot from the Tutorial Session.',
                    3 => 'The mentor is good enough in doing his/her tasks.',
                    4 => 'The mentor was able to clearly explain the topics I do not understand.',
                    5 => 'There were adequate exercises given.',
                    6 => 'The mentor has mastery of the subject matter.',
                    7 => 'The mentor introduces new techniques or simpler approach to the subject.',
                    8 => 'I will recommend the Tutorial Sessions to my classmates.',
                    9 => 'I am coming back to attend more Tutorial Sessions.',
                ];
            @endphp

            {{-- Feedback Form --}}
            @if ($this->completedBooking && $showFeedbackModal)
                @php $cb = $this->completedBooking; @endphp

                <div class="feedback-card" id="feedbackFormCard">
                    <div class="feedback-banner">
                        <div class="feedback-banner-icon">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-1">
                                <h2 class="text-xl font-extrabold tracking-tight text-green-900">
                                    Feedback Form
                                </h2>
                                <span class="inline-flex items-center gap-1 text-l font-bold px-3 py-0.5 rounded-full bg-green-200 text-green-800">
                                    Step {{ $feedbackStep }} of 3
                                </span>
                            </div>
                            <p class="text-sm text-green-800 leading-snug">
                                Please rate your enrichment session experience. Your feedback helps improve our peer mentoring program.
                            </p>
                        </div>
                    </div>

                    <div class="feedback-body">

                        {{-- Progress bar --}}
                        <div class="feedback-progress">
                            <div class="feedback-progress-step {{ $feedbackStep > 1 ? 'done' : 'active' }}"></div>
                            <div class="feedback-progress-step {{ $feedbackStep > 2 ? 'done' : ($feedbackStep === 2 ? 'active' : '') }}"></div>
                            <div class="feedback-progress-step {{ $feedbackStep === 3 ? 'active' : '' }}"></div>
                            <span class="feedback-progress-label">Step {{ $feedbackStep }} / 3</span>
                        </div>

                        {{-- Session summary --}}
                        <div class="feedback-session-summary">
                            <div class="fs-item">
                                <label>Subject</label>
                                <p>{{ ($cb->subject->code ?? '—') . ($cb->subject->name ? ' — ' . $cb->subject->name : '') }}</p>
                            </div>
                            <div class="fs-item">
                                <label>Mentor</label>
                                <p>{{ strtoupper($cb->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $cb->mentor->user->firstName ?? '' }}</p>
                            </div>
                            <div class="fs-item full min-w-0">
                                <label>Topic &amp; Date</label>
                                <p class="truncate" title="{{ $cb->topic }}">{{ $cb->topic }}</p>
                                <p class="shrink-0">{{ \Carbon\Carbon::parse($cb->date)->format('l, F j, Y') }}</p>
                            </div>
                        </div>

                        {{-- ── Step 1: Q1–Q5 ── --}}
                        @if ($feedbackStep === 1)
                            <p class="text-xs font-semibold text-green-700 mb-3 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-green-400"></i>
                                Rate each statement from 1 (Strongly Disagree) to 5 (Strongly Agree).
                            </p>

                            @foreach (array_slice($questions, 0, 5, true) as $num => $text)
                                @php $field = 'q' . $num; $val = $this->$field; @endphp
                                <div class="likert-question {{ $val ? 'answered' : '' }}">
                                    <div class="likert-question-num">Question {{ $num }} of 10</div>
                                    <div class="likert-question-text">{{ $text }}</div>
                                    <div class="likert-scale-labels">
                                        <span>Strongly Disagree</span>
                                        <span>Strongly Agree</span>
                                    </div>
                                    <div class="likert-options">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <input type="radio" id="q{{ $num }}_{{ $i }}" wire:model="q{{ $num }}" value="{{ $i }}">
                                            <label for="q{{ $num }}_{{ $i }}">{{ $i }}</label>
                                        @endfor
                                    </div>
                                </div>
                                @error('q' . $num)
                                <p class="text-xs text-red-500 -mt-1 mb-2 ml-1">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}
                                </p>
                                @enderror
                            @endforeach

                            <div class="feedback-nav">
                                <button type="button" wire:click="nextFeedbackStep"
                                        wire:loading.attr="disabled" wire:target="nextFeedbackStep"
                                        class="feedback-btn-next">
                                    <span wire:loading.remove wire:target="nextFeedbackStep">Continue &rarr;</span>
                                    <span wire:loading wire:target="nextFeedbackStep">
                                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking...
                                    </span>
                                </button>
                            </div>

                            {{-- ── Step 2: Q6–Q10 ── --}}
                        @elseif ($feedbackStep === 2)
                            <p class="text-xs font-semibold text-green-700 mb-3 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-green-400"></i>
                                Continue rating (1 = Strongly Disagree, 5 = Strongly Agree). Q10 is Yes/No.
                            </p>

                            @foreach (array_slice($questions, 5, 4, true) as $num => $text)
                                @php $field = 'q' . $num; $val = $this->$field; @endphp
                                <div class="likert-question {{ $val ? 'answered' : '' }}">
                                    <div class="likert-question-num">Question {{ $num }} of 10</div>
                                    <div class="likert-question-text">{{ $text }}</div>
                                    <div class="likert-scale-labels">
                                        <span>Strongly Disagree</span>
                                        <span>Strongly Agree</span>
                                    </div>
                                    <div class="likert-options">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <input type="radio" id="q{{ $num }}_{{ $i }}" wire:model="q{{ $num }}" value="{{ $i }}">
                                            <label for="q{{ $num }}_{{ $i }}">{{ $i }}</label>
                                        @endfor
                                    </div>
                                </div>
                                @error('q' . $num)
                                <p class="text-xs text-red-500 -mt-1 mb-2 ml-1">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}
                                </p>
                                @enderror
                            @endforeach

                            {{-- Q10: Boolean --}}
                            <div class="bool-question {{ !is_null($this->q10) ? 'answered' : '' }}">
                                <div class="likert-question-num">Question 10 of 10</div>
                                <div class="likert-question-text">The peer mentor started the session on time.</div>
                                <div class="bool-options">
                                    <input type="radio" id="q10_yes" wire:model="q10" value="1">
                                    <label for="q10_yes" class="yes"><i class="fa-solid fa-check mr-1"></i> Yes</label>
                                    <input type="radio" id="q10_no" wire:model="q10" value="0">
                                    <label for="q10_no" class="no"><i class="fa-solid fa-xmark mr-1"></i> No</label>
                                </div>
                            </div>
                            @error('q10')
                            <p class="text-xs text-red-500 -mt-1 mb-2 ml-1">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}
                            </p>
                            @enderror

                            <div class="feedback-nav">
                                <button type="button" wire:click="prevFeedbackStep"
                                        wire:loading.attr="disabled" wire:target="prevFeedbackStep"
                                        class="feedback-btn-back">
                                    <span wire:loading.remove wire:target="prevFeedbackStep">&larr; Back </span>
                                    <span wire:loading wire:target="prevFeedbackStep">
                                        <i class="fa-solid fa-spinner fa-spin mr-1"></i>
                                    </span>
                                </button>

                                <button type="button" wire:click="nextFeedbackStep"
                                        wire:loading.attr="disabled" wire:target="nextFeedbackStep"
                                        class="feedback-btn-next">
                                    <span wire:loading.remove wire:target="nextFeedbackStep">Continue &rarr;</span>
                                    <span wire:loading wire:target="nextFeedbackStep">
                                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking...
                                    </span>
                                </button>
                            </div>

                            {{-- ── Step 3: Remarks + submit ── --}}
                        @elseif ($feedbackStep === 3)
                            <p class="text-sm font-bold text-gray-700 mb-1 flex items-center gap-2">
                                <i class="fa-solid fa-pen-to-square text-green-500 text-xs"></i>
                                Additional Remarks
                                <span class="font-normal text-gray-400 text-xs">(optional)</span>
                            </p>
                            <p class="text-xs text-gray-400 mb-4">
                                Any other thoughts about the session? This is optional — you can submit without filling this in.
                            </p>

                            <form wire:submit.prevent="submitFeedback">
                                <textarea
                                    wire:model="feedbackText"
                                    class="feedback-textarea"
                                    placeholder="Share your thoughts about the session — what went well, what could be improved, or any other comments for your mentor..."
                                    rows="5"
                                    maxlength="2000"
                                ></textarea>
                                @error('feedbackText')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-400 mt-1 text-right"
                                   x-data
                                   x-text="'' + ($wire.feedbackText ? $wire.feedbackText.length : 0) + ' / 2000'">
                                </p>

                                <div class="feedback-nav">
                                    <button type="button" wire:click="prevFeedbackStep"
                                            wire:loading.attr="disabled" wire:target="prevFeedbackStep"
                                            class="feedback-btn-back">
                                        <span wire:loading.remove wire:target="prevFeedbackStep">&larr; Back</span>
                                        <span wire:loading wire:target="prevFeedbackStep">
                                            <i class="fa-solid fa-spinner fa-spin mr-1"></i>
                                        </span>
                                    </button>

                                    <button type="submit"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-60 cursor-not-allowed"
                                            wire:target="submitFeedback"
                                            class="feedback-btn-submit">
                                        <span wire:loading.remove wire:target="submitFeedback">
                                            <i class="fa-solid fa-paper-plane mr-2"></i>Submit Feedback
                                        </span>
                                        <span wire:loading wire:target="submitFeedback">
                                            <i class="fa-solid fa-spinner fa-spin mr-2"></i>Submitting...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Active Booking View --}}
            @if ($this->activeBooking)
                @php
                    $ab          = $this->activeBooking;
                    $isPending   = $ab->booking_status === 'pending';
                    $statusClass = $isPending ? 'pending' : 'accepted';
                    $statusLabel = $isPending ? 'Awaiting Approval' : 'Accepted';
                    $statusIcon  = $isPending
                        ? '<i class="fa-solid fa-hourglass-half"></i>'
                        : '<i class="fa-solid fa-circle-check"></i>';
                    $statusDesc  = $isPending
                        ? 'Your booking request has been submitted. You cannot make a new booking until this one is resolved.'
                        : 'Your session has been confirmed! Please be on time.';
                @endphp

                <div class="active-booking-card">
                    <div class="active-booking-banner {{ $statusClass }}">
                        <div class="active-booking-banner-icon w-12 h-12 text-xl">{!! $statusIcon !!}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-3 mb-1">
                                <h2 class="text-xl font-extrabold tracking-tight {{ $isPending ? 'text-yellow-900' : 'text-green-900' }}">
                                    You have an active booking
                                </h2>
                                <span class="inline-flex items-center gap-1 text-l font-bold px-3 py-0.5 rounded-full {{ $isPending ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800' }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="text-sm {{ $isPending ? 'text-yellow-800' : 'text-green-800' }} leading-snug">
                                {{ $statusDesc }}
                            </p>
                        </div>
                    </div>

                    <div class="active-booking-body">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Session Details</h2>
                        <div class="booking-detail-grid">
                            <div class="booking-detail-item min-w-0">
                                <label>Subject</label>
                                <p class="truncate" title="{{ $ab->subject->code ?? '—' }} — {{ $ab->subject->name ?? '' }}">
                                    {{ $ab->subject->code ?? '—' }} &mdash; {{ $ab->subject->name ?? '' }}
                                </p>
                            </div>
                            <div class="booking-detail-item min-w-0">
                                <label>Tutorial Mode</label>
                                <p class="truncate" title="{{ $ab->tutorialMode->mode ?? '—' }}">
                                    {{ $ab->tutorialMode->mode ?? '—' }}
                                </p>
                            </div>
                            <div class="booking-detail-item min-w-0">
                                <label>Topic</label>
                                <p class="line-clamp-1 break-words" title="{{ $ab->topic }}">{{ $ab->topic }}</p>
                            </div>
                            <div class="booking-detail-item min-w-0">
                                <label>Peer Mentor</label>
                                <p class="truncate" title="{{ strtoupper($ab->mentor->user->lastName ?? 'MENTOR') }}, {{ $ab->mentor->user->firstName ?? 'TBD' }}">
                                    {{ strtoupper($ab->mentor->user->lastName ?? 'MENTOR') }}, {{ $ab->mentor->user->firstName ?? 'TBD' }}
                                </p>
                            </div>
                            <div class="booking-detail-item min-w-0">
                                <label>Date</label>
                                <p>{{ \Carbon\Carbon::parse($ab->date)->format('l, F j, Y') }}</p>
                            </div>
                            <div class="booking-detail-item min-w-0">
                                <label>Time</label>
                                <p>
                                    {{ \Carbon\Carbon::parse($ab->schedule_start)->format('g:i A') }}
                                    &ndash;
                                    {{ \Carbon\Carbon::parse($ab->schedule_end)->format('g:i A') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between gap-4">
                            <p class="flex items-center gap-2 text-xs font-bold text-gray-500 flex-1">
                                <i class="fa-solid fa-circle-info text-gray-500 flex-shrink-0 font-bold"></i>
                                You may cancel this booking at any time.
                            </p>
                            <button type="button"
                                    @click="window.openConfirmModal({
                                        title:       'Cancel Booking?',
                                        body:        'Are you sure you want to cancel this session? You will need to submit a new request if you change your mind.',
                                        variant:     'cancel',
                                        confirmText: 'Confirm',
                                        loadingText: 'Cancelling...',
                                        onConfirm:   async () => { await $wire.cancelBooking(); }
                                    })"
                                    class="flex-shrink-0 flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 font-semibold text-sm rounded-lg border border-red-200 transition-colors">
                                <i class="fa-solid fa-ban"></i> Cancel Booking
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Booking Form --}}
            @elseif (!$this->completedBooking)
                <div class="flex-1 min-w-0 items-center gap-4 rounded-lg pb-6 pt-0">
                    <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                        Request An Enrichment Session
                    </h1>
                    <p class="text-sm font-medium text-slate-500 leading-snug mt-1">
                        Please fill out the details below. Your request will be reviewed by the peer mentor.
                    </p>
                </div>

                <div class="bg-white pl-6 pr-6 pb-6 pt-4 rounded-lg shadow-sm border-gray-200 overflow-visible"
                     x-data="{
                        subject_id:       $wire.entangle('subject_id'),
                        topic:            $wire.entangle('topic'),
                        tutorialMode_id:  $wire.entangle('tutorialMode_id'),
                        date:             $wire.entangle('date'),
                        start_time:       $wire.entangle('schedule_start'),
                        end_time:         $wire.entangle('schedule_end'),
                        mentor_id:        $wire.entangle('mentor_id'),
                        isMentorLocked:   $wire.entangle('isMentorLocked'),
                        dateError:        '',
                        timeError:        '',
                        clearedErrors:    [],

                        init() {
                            this.$watch('subject_id', () => {
                                if (!this.isMentorLocked) this.mentor_id = '';
                                this.clearError('subject_id');
                            });
                            this.$watch('topic',          () => this.clearError('topic'));
                            this.$watch('tutorialMode_id',() => this.clearError('tutorialMode_id'));
                            this.$watch('mentor_id',      () => this.clearError('mentor_id'));

                            this.$watch('date', value => {
                                if (!this.isMentorLocked) this.mentor_id = '';
                                this.dateError = '';
                                this.clearError('date');
                                if (!value) { this.validateTime(); return; }

                                const d = new Date(value + 'T00:00:00');
                                if (d.getDay() === 0) {
                                    this.dateError = 'The session cannot be on a Sunday.';
                                    return;
                                }

                                if (this.isMentorLocked) {
                                    const dayChosen = this.getDayOfWeek(value);
                                    const avails = this.allAvailabilities.filter(
                                        a => a.mentorProfile_id == this.mentor_id && a.day_of_week === dayChosen
                                    );
                                    if (avails.length === 0) {
                                        this.dateError = 'This mentor is not available on this day.';
                                        return;
                                    }
                                }
                                this.validateTime();
                            });

                            this.$watch('start_time', () => {
                                if (!this.isMentorLocked) this.mentor_id = '';
                                this.clearError('schedule_start');
                                this.validateTime();
                            });
                            this.$watch('end_time', () => {
                                if (!this.isMentorLocked) this.mentor_id = '';
                                this.clearError('schedule_end');
                                this.validateTime();
                            });
                        },

                        clearError(field) {
                            if (!this.clearedErrors.includes(field)) {
                                this.clearedErrors.push(field);
                            }
                        },

                        showError(field) {
                            return !this.clearedErrors.includes(field);
                        },

                        validateTime() {
                            this.timeError = '';
                            if (!this.start_time || !this.end_time) return;
                            if (this.end_time <= this.start_time) {
                                this.timeError = 'End time must be later than start time.';
                                return;
                            }
                            if (this.isMentorLocked && this.date) {
                                const dayChosen = this.getDayOfWeek(this.date);
                                const avails = this.allAvailabilities.filter(
                                    a => a.mentorProfile_id == this.mentor_id && a.day_of_week === dayChosen
                                );
                                if (avails.length > 0) {
                                    const fits = avails.some(a => {
                                        let start       = a.start_time.substring(0,5);
                                        let end         = a.end_time.substring(0,5);
                                        let startChosen = this.start_time.substring(0,5);
                                        let endChosen   = this.end_time.substring(0,5);
                                        return start <= startChosen && end >= endChosen;
                                    });
                                    if (!fits) this.timeError = 'Time does not fit their schedule.';
                                }
                            }
                        },

                        allMentors:        @js($this->mentors),
                        allSubjects:       @js($this->mentorSubjects),
                        allAvailabilities: @js($this->mentorAvailabilities),

                        getDayOfWeek(dateStr) {
                            const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                            return days[new Date(dateStr + 'T00:00:00').getDay()];
                        },

                        get filteredMentors() {
                            if (this.isMentorLocked && this.mentor_id) {
                                return this.allMentors.filter(m => m.profile_id == this.mentor_id);
                            }
                            let choices = this.allMentors;
                            if ($wire.subject_id) {
                                const validIds = this.allSubjects
                                    .filter(s => s.subject_id == $wire.subject_id)
                                    .map(s => s.mentorProfile_id);
                                choices = choices.filter(m => validIds.includes(m.profile_id));
                            }
                            if ($wire.date) {
                                const dayChosen = this.getDayOfWeek($wire.date);
                                choices = choices.filter(m => {
                                    const avails = this.allAvailabilities.filter(
                                        a => a.mentorProfile_id == m.profile_id && a.day_of_week === dayChosen
                                    );
                                    if (avails.length === 0) return false;
                                    if ($wire.schedule_start && $wire.schedule_end) {
                                        return avails.some(a => {
                                            let start       = a.start_time.substring(0,5);
                                            let end         = a.end_time.substring(0,5);
                                            let startChosen = $wire.schedule_start.substring(0,5);
                                            let endChosen   = $wire.schedule_end.substring(0,5);
                                            return start <= startChosen && end >= endChosen;
                                        });
                                    }
                                    return true;
                                });
                            }
                            return choices;
                        }
                    }">

                    <form id="bookingForm" class="space-y-3">

                        {{-- Subject --}}
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">
                                Subject<span class="text-red-500">*</span>
                            </label>
                            <select wire:model="subject_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1 transition-colors">
                                <option value="" disabled>--- Select a Subject ---</option>
                                @foreach ($this->subjects as $subject)
                                    <option value="{{ $subject->id }}">
                                        {{ strtoupper($subject->code) }} - {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('subject_id')
                            <span x-show="showError('subject_id')" x-cloak
                                  class="mt-1 text-xs text-red-600 block"
                                  wire:loading.class="hidden" wire:target="validateBooking">
                                {{ $message }}
                            </span>
                            @enderror
                        </div>

                        {{-- Topic --}}
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">
                                Topic<span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="topic"
                                   class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1 transition-colors"
                                   placeholder="e.g. Integration by Parts."
                                   maxlength="255">
                            @error('topic')
                            <span x-show="showError('topic')" x-cloak
                                  class="mt-1 text-xs text-red-600 block"
                                  wire:loading.class="hidden" wire:target="validateBooking">
                                {{ $message }}
                            </span>
                            @enderror
                        </div>

                        {{-- Tutorial Mode --}}
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">
                                Tutorial Mode<span class="text-red-500">*</span>
                            </label>
                            <select wire:model="tutorialMode_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1 transition-colors">
                                <option value="" disabled>--- Select Mode of Tutoring ---</option>
                                @foreach ($this->tutorialModes as $mode)
                                    <option value="{{ $mode->id }}">{{ $mode->mode }}</option>
                                @endforeach
                            </select>
                            @error('tutorialMode_id')
                            <span x-show="showError('tutorialMode_id')" x-cloak
                                  class="mt-1 text-xs text-red-600 block"
                                  wire:loading.class="hidden" wire:target="validateBooking">
                                {{ $message }}
                            </span>
                            @enderror
                        </div>

                        {{-- Date + Time row --}}
                        <div class="grid grid-cols-3 gap-4">

                            {{-- Preferred Day --}}
                            <div x-data="bookingDatePicker()" x-init="init()" @click.outside="close()">
                                <label class="block text-base font-medium text-gray-700 mb-1">
                                    Preferred Day<span class="text-red-500">*</span>
                                </label>
                                <div class="custom-date-picker">
                                    <div class="custom-date-display" :class="{ active: open }" @click="toggle()">
                                        <div class="date-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                        <span class="date-text text-sm">
                                            <template x-if="selectedLabel"><span x-text="selectedLabel"></span></template>
                                            <template x-if="!selectedLabel"><span class="date-placeholder">Pick a date</span></template>
                                        </span>
                                        <i class="fa-solid fa-chevron-down date-chevron"></i>
                                    </div>
                                    <div class="date-picker-dropdown" :class="{ show: open }">
                                        <div class="dp-nav">
                                            <button type="button" class="dp-nav-btn" @click.stop="prevMonth()">
                                                <i class="fa-solid fa-chevron-left"></i>
                                            </button>
                                            <span class="dp-month-label" x-text="monthLabel"></span>
                                            <button type="button" class="dp-nav-btn" @click.stop="nextMonth()">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </button>
                                        </div>
                                        <div class="dp-weekdays">
                                            <div class="dp-weekday">Su</div>
                                            <div class="dp-weekday">Mo</div>
                                            <div class="dp-weekday">Tu</div>
                                            <div class="dp-weekday">We</div>
                                            <div class="dp-weekday">Th</div>
                                            <div class="dp-weekday">Fr</div>
                                            <div class="dp-weekday">Sa</div>
                                        </div>
                                        <div class="dp-days">
                                            <template x-for="(day, idx) in calDays" :key="idx">
                                                <div class="dp-day"
                                                     :class="{
                                                        'dp-day-empty':    !day.date,
                                                        'dp-day-disabled': day.disabled,
                                                        'dp-day-today':    day.isToday,
                                                        'dp-day-selected': day.isSelected,
                                                        'dp-day-sunday':   day.isSunday && !day.disabled && !day.isSelected,
                                                    }"
                                                     @click="day.date && !day.disabled && selectDay(day)"
                                                     x-text="day.label">
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <input type="date" wire:model="date" id="bookingDateHidden" class="hidden"
                                       min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                                @error('date')
                                <span x-show="showError('date')" x-cloak
                                      class="mt-1 text-xs text-red-600 block"
                                      wire:loading.class="hidden" wire:target="validateBooking">
                                    {{ $message }}
                                </span>
                                @enderror
                                <span x-show="dateError" x-cloak class="mt-1 text-xs text-red-600 block" x-text="dateError"></span>
                            </div>

                            {{-- Start Time --}}
                            <div x-data="bookingTimePicker('schedule_start')" x-init="init()" @click.outside="close()">
                                <label class="block text-base font-medium text-gray-700 mb-1">Start Time<span class="text-red-500">*</span></label>
                                <div class="custom-time-picker">
                                    <div class="custom-time-display" :class="{ active: open }" @click="toggle()">
                                        <div class="time-icon"><i class="fa-regular fa-clock"></i></div>
                                        <span class="text-sm" :class="selectedTime ? 'font-semibold text-gray-800' : 'time-placeholder'" x-text="selectedTime || 'Start time'"></span>
                                    </div>
                                    <div class="time-picker-dropdown" :class="{ show: open }">
                                        <div class="tp-ampm">
                                            <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                            <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                        </div>
                                        <div class="tp-scroll-row">
                                            <div class="tp-col">
                                                <div class="tp-col-label">Hour</div>
                                                <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12"
                                                       @input="$el.value = $el.value.slice(0,2)"
                                                       :value="String(hour).padStart(2,'0')"
                                                       @change="onHourInput($event)"
                                                       @keydown.up.prevent="changeHour(1)"
                                                       @keydown.down.prevent="changeHour(-1)">
                                                <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                            </div>
                                            <div class="tp-sep">:</div>
                                            <div class="tp-col">
                                                <div class="tp-col-label">Min</div>
                                                <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                <input class="tp-manual-input tp-min-input" type="number" min="0" max="59"
                                                       @input="$el.value = $el.value.slice(0,2)"
                                                       :value="String(minute).padStart(2,'0')"
                                                       @change="onMinInput($event)"
                                                       @keydown.up.prevent="changeMin(1)"
                                                       @keydown.down.prevent="changeMin(-1)">
                                                <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="time" wire:model="schedule_start" id="startTimeHidden" class="hidden">
                                @error('schedule_start')
                                <span x-show="showError('schedule_start')" x-cloak class="mt-1 text-xs text-red-600 block" wire:loading.class="hidden" wire:target="validateBooking">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- End Time --}}
                            <div x-data="bookingTimePicker('schedule_end')" x-init="init()" @click.outside="close()">
                                <label class="block text-base font-medium text-gray-700 mb-1">End Time<span class="text-red-500">*</span></label>
                                <div class="custom-time-picker">
                                    <div class="custom-time-display" :class="{ active: open }" @click="toggle()">
                                        <div class="time-icon"><i class="fa-regular fa-clock"></i></div>
                                        <span class="text-sm" :class="selectedTime ? 'font-semibold text-gray-800' : 'time-placeholder'" x-text="selectedTime || 'End time'"></span>
                                    </div>
                                    <div class="time-picker-dropdown" :class="{ show: open }">
                                        <div class="tp-ampm">
                                            <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                            <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                        </div>
                                        <div class="tp-scroll-row">
                                            <div class="tp-col">
                                                <div class="tp-col-label">Hour</div>
                                                <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12"
                                                       @input="$el.value = $el.value.slice(0,2)"
                                                       :value="String(hour).padStart(2,'0')"
                                                       @change="onHourInput($event)"
                                                       @keydown.up.prevent="changeHour(1)"
                                                       @keydown.down.prevent="changeHour(-1)">
                                                <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                            </div>
                                            <div class="tp-sep">:</div>
                                            <div class="tp-col">
                                                <div class="tp-col-label">Min</div>
                                                <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                <input class="tp-manual-input tp-min-input" type="number" min="0" max="59"
                                                       @input="$el.value = $el.value.slice(0,2)"
                                                       :value="String(minute).padStart(2,'0')"
                                                       @change="onMinInput($event)"
                                                       @keydown.up.prevent="changeMin(1)"
                                                       @keydown.down.prevent="changeMin(-1)">
                                                <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="time" wire:model="schedule_end" id="endTimeHidden" class="hidden">
                                @error('schedule_end')
                                <span x-show="showError('schedule_end')" x-cloak class="mt-1 text-xs text-red-600 block" wire:loading.class="hidden" wire:target="validateBooking">{{ $message }}</span>
                                @enderror
                                <span x-show="timeError" x-cloak class="mt-1 text-xs text-red-600 block" x-text="timeError"></span>
                            </div>
                        </div>
                        {{-- ── End Date + Time row ── --}}

                        {{-- Preferred Mentor --}}
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">
                                Preferred Mentor<span class="text-red-500">*</span>
                            </label>
                            <select wire:model="mentor_id" :disabled="isMentorLocked"
                                    class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1 disabled:bg-gray-100 disabled:text-gray-900 disabled:cursor-not-allowed transition-colors">
                                <option value=""
                                        x-text="filteredMentors.length === 0
                                            ? '--- No mentors available. Please select a different date or time slot. ---'
                                            : '--- Select a mentor ---'"
                                        disabled>
                                </option>
                                <template x-if="filteredMentors.length > 0 && !isMentorLocked">
                                    <option value="any" class="bg-blue-100">ANY (Alerts all available mentors)</option>
                                </template>
                                <template x-for="mentor in filteredMentors" :key="mentor.profile_id">
                                    <option :value="mentor.profile_id" x-text="mentor.name"></option>
                                </template>
                            </select>

                            <div x-show="isMentorLocked" x-cloak class="mt-1.5 flex justify-between items-center px-1">
                                <span class="text-[11px] text-blue-600 font-bold">
                                    <i class="fa-solid fa-lock mr-1"></i> Mentor Locked.
                                </span>
                                <a href="{{ route('student.bookings') }}" class="text-[10px] text-gray-400 hover:text-red-600 underline">
                                    Unlock &amp; Clear
                                </a>
                            </div>

                            @error('mentor_id')
                            <span x-show="showError('mentor_id')" x-cloak
                                  class="mt-1 text-xs text-red-600 block"
                                  wire:loading.class="hidden" wire:target="validateBooking">
                                {{ $message }}
                            </span>
                            @enderror
                        </div>

                        {{-- Any-mentor notice --}}
                        <div x-show="mentor_id === 'any' && filteredMentors.length > 0" x-cloak
                             class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3 animate-[slideDown_0.2s_ease]">
                            <p class="text-xs font-bold text-blue-800 mb-1">
                                <i class="fa-solid fa-triangle-exclamation mr-1"></i> First Come First Serve
                            </p>
                            <p class="text-xs text-blue-800 mb-2 leading-tight">
                                Your request will be sent to the following mentors. The first to accept will take your session.
                            </p>
                            <ul class="text-xs font-semibold text-blue-800 space-y-0.5 pl-1">
                                <template x-for="mentor in filteredMentors" :key="mentor.profile_id">
                                    <li class="flex items-center gap-1.5">
                                        <span class="w-1 h-1 rounded-full bg-blue-400"></span>
                                        <span x-text="mentor.name"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Submit button --}}
                        <div class="pt-4">
                            <button type="button" id="bookingSubmitBtn"
                                    wire:click="validateBooking"
                                    @click="clearedErrors = []"
                                    @if (!auth()->user()->studentProfile) disabled @endif
                                    :disabled="dateError !== '' || timeError !== ''"
                                    class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-60 cursor-not-allowed"
                                    wire:target="validateBooking">
                                <span wire:loading.remove wire:target="validateBooking">Submit Booking Request</span>
                                <span wire:loading wire:target="validateBooking">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>Validating...
                                </span>
                            </button>
                        </div>

                    </form>
                </div>
            @endif

        </div>

        {{-- RIGHT COLUMN --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Student Profile --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
                 x-data="{
                    open: $wire.entangle('toggleProfileOpen'),
                    isLocked: $wire.entangle('isProfileLocked'),
                    student_num: $wire.entangle('student_num'),
                    college: $wire.entangle('college_id'),
                    degree: $wire.entangle('degreeProgram_id'),
                    year_level: $wire.entangle('yearLevel_id'),
                    showSuccess: false,
                    allDegrees: @js($this->degreePrograms),
                    original: { student_num: '', college: '', degree: '', year_level: '' },

                    init() {
                        this.original.student_num = this.student_num || '';
                        this.original.college     = this.college || '';
                        this.original.degree      = this.degree || '';
                        this.original.year_level  = this.year_level || '';

                        this.$watch('college', (val, oldVal) => {
                            if (oldVal !== undefined && oldVal !== '') { this.degree = ''; }
                        });

                        this.$nextTick(() => { let s = this.degree; this.degree = ''; this.degree = s; });
                    },

                    get filteredDeProgs() {
                        if (!this.college) return [];
                        return this.allDegrees.filter(deprog => deprog.college_id == this.college);
                    },

                    get hasChanges() {
                        return (this.student_num || '') != this.original.student_num ||
                               (this.college || '')     != this.original.college     ||
                               (this.degree || '')      != this.original.degree      ||
                               (this.year_level || '')  != this.original.year_level;
                    }
                }"
                 @profile-updated.window="
                    showSuccess = true;
                    original.student_num = student_num || '';
                    original.college     = college || '';
                    original.degree      = degree || '';
                    original.year_level  = year_level || '';
                    setTimeout(() => showSuccess = false, 10000);
                ">

                <button @click="open = !open" type="button"
                        class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-semibold text-gray-900">Student Profile</span>
                        @if (auth()->user()->studentProfile)
                            <span class="text-xs bg-green-200 px-2 py-1 rounded-full text-green-800 font-bold">Saved</span>
                        @else
                            <span class="text-xs bg-yellow-100 px-2 py-1 rounded-full text-yellow-800 font-bold">Required</span>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" style="display: none;" x-transition class="px-5 py-5 border-t border-gray-100">
                    <div x-show="showSuccess" style="display: none;" x-transition
                         class="mb-4 text-sm font-semibold text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        Profile Updated!
                    </div>
                    <form wire:submit.prevent="saveProfile" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Student Number<span class="text-red-500">*</span></label>
                            <input type="text" x-model="student_num" :disabled="isLocked"
                                   class="w-full rounded-lg border-gray-200 shadow-sm text-sm px-3 py-2 disabled:bg-gray-100 disabled:text-gray-500"
                                   placeholder="e.g 2023-00000" maxlength="10">
                            @error('student_num') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">College<span class="text-red-500">*</span></label>
                            <select x-model="college" :disabled="isLocked"
                                    class="w-full rounded-lg border-gray-200 shadow-sm text-sm px-3 py-2 disabled:bg-gray-100 disabled:text-gray-500">
                                <option value="">--- College ---</option>
                                @foreach ($this->colleges as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Degree Program<span class="text-red-500">*</span></label>
                            <select x-model="degree" :disabled="!college || isLocked"
                                    class="w-full rounded-lg border-gray-200 shadow-sm text-sm px-3 py-2 disabled:bg-gray-100 disabled:text-gray-500">
                                <option value="">--- Degree Program ---</option>
                                <template x-for="deprog in filteredDeProgs" :key="deprog.id">
                                    <option :value="deprog.id" x-text="deprog.name"></option>
                                </template>
                            </select>
                            @error('degreeProgram_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year Level<span class="text-red-500">*</span></label>
                            <select x-model="year_level" :disabled="isLocked"
                                    class="w-full rounded-lg border-gray-200 shadow-sm text-sm px-3 py-2 disabled:bg-gray-100 disabled:text-gray-500">
                                <option value="">--- Year Level ---</option>
                                @foreach ($this->yearLevels as $level)
                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                @endforeach
                            </select>
                            @error('yearLevel_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>

                        <div class="mt-2">
                            <template x-if="isLocked">
                                <button type="button" @click="isLocked = false"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors">
                                    Edit Profile
                                </button>
                            </template>
                            <template x-if="!isLocked">
                                <button type="submit"
                                        :disabled="!hasChanges"
                                        class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors"
                                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="saveProfile">
                                    <span wire:loading.remove wire:target="saveProfile">{{ auth()->user()->studentProfile ? 'Update Profile' : 'Save Profile' }}</span>
                                    <span wire:loading wire:target="saveProfile"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Saving...</span>
                                </button>
                            </template>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Recent Bookings --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" type="button"
                        class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <span class="text-base font-semibold text-gray-900">Recent Bookings</span>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" style="display: none;" x-transition class="px-5 pb-5 pt-2 border-t border-gray-100">
                    @forelse ($this->studentBookings as $booking)
                        <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-800">{{ strtoupper($booking->subject->code) }}</p>

                                    <p class="mentor-name text-xs font-medium text-gray-500 mt-0.5 truncate"
                                       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = '{{ addslashes(strtoupper($booking->mentor->user->lastName ?? 'MENTOR') . ', ' . ($booking->mentor->user->firstName ?? 'TBD')) }}' })">
                                        Mentor: {{ strtoupper($booking->mentor->user->lastName ?? 'MENTOR') }}, {{ $booking->mentor->user->firstName ?? 'TBD' }}
                                    </p>

                                    <p class="text-xs font-medium text-gray-500 truncate mt-0.5"
                                       x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = '{{ addslashes($booking->topic) }}' })">
                                        Topic: {{ $booking->topic }}
                                    </p>

                                    <p class="text-xs font-medium text-gray-400 mt-0.5">
                                        <i class="fa-solid fa-location-dot mr-1 text-gray-300"></i>
                                        {{ $booking->tutorialMode->mode ?? '—' }}
                                    </p>
                                </div>

                                <div class="flex-shrink-0 mt-1">
                                    @php
                                        $statusColors = match ($booking->booking_status) {
                                            'pending'   => 'bg-yellow-100 text-yellow-800',
                                            'accepted'  => 'bg-green-100 text-green-800',
                                            'rejected'  => 'bg-red-100 text-red-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'closed'    => 'bg-purple-100 text-purple-800',
                                            'no-show'   => 'bg-red-100 text-red-800',
                                            default     => 'bg-gray-100 text-gray-800',
                                        };
                                    @endphp
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full capitalize {{ $statusColors }}">
                                        {{ str_replace('_', ' ', $booking->booking_status) }}
                                    </span>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 mt-1 font-medium">
                                <i class="fa-regular fa-calendar mr-1"></i>
                                {{ \Carbon\Carbon::parse($booking->date)->format('M j, Y (D)') }}
                                &bull;
                                <i class="fa-regular fa-clock mx-1"></i>
                                {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }}
                                -
                                {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 text-center py-4">No recent bookings found.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- Confirmation Modal --}}
    <div id="confirmModal" style="display:none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-[#ffffff] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
            <div class="flex justify-end gap-3">
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    Cancel
                </button>
                <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                    Confirm
                </button>
            </div>
        </div>
    </div>

</div>

<script>
    // ── Alpine component registrations ────────────────────────────────────────
    document.addEventListener('alpine:init', () => {

        Alpine.data('bookingDatePicker', () => ({
            open:          false,
            viewYear:      0,
            viewMonth:     0,
            selectedDate:  null,
            selectedLabel: '',
            today:         null,

            init() {
                const t = new Date();
                this.today     = new Date(t.getFullYear(), t.getMonth(), t.getDate());
                this.viewYear  = this.today.getFullYear();
                this.viewMonth = this.today.getMonth();

                this.$watch('$wire.date', val => {
                    if (val) {
                        const d = new Date(val + 'T00:00:00');
                        this.selectedDate  = d;
                        this.selectedLabel = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        this.viewYear      = d.getFullYear();
                        this.viewMonth     = d.getMonth();
                    } else {
                        this.selectedDate  = null;
                        this.selectedLabel = '';
                    }
                });
            },

            toggle() { if (this.open) { this.close(); return; } this.open = true; },
            close()  { this.open = false; },

            get monthLabel() {
                return new Date(this.viewYear, this.viewMonth, 1)
                    .toLocaleString('en-US', { month: 'long', year: 'numeric' });
            },

            prevMonth() {
                if (this.viewMonth === 0) { this.viewMonth = 11; this.viewYear--; }
                else this.viewMonth--;
            },

            nextMonth() {
                if (this.viewMonth === 11) { this.viewMonth = 0; this.viewYear++; }
                else this.viewMonth++;
            },

            get calDays() {
                const firstDay    = new Date(this.viewYear, this.viewMonth, 1).getDay();
                const daysInMonth = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();
                const tomorrow    = new Date(this.today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                const days = [];
                for (let i = 0; i < firstDay; i++) days.push({ label: '', date: null });
                for (let d = 1; d <= daysInMonth; d++) {
                    const date   = new Date(this.viewYear, this.viewMonth, d);
                    const isPast = date < tomorrow;
                    const isSun  = date.getDay() === 0;
                    days.push({
                        label:      d,
                        date,
                        isSunday:   isSun,
                        disabled:   isPast,
                        isToday:    date.getTime() === this.today.getTime(),
                        isSelected: this.selectedDate && date.getTime() === this.selectedDate.getTime(),
                    });
                }
                return days;
            },

            selectDay(day) {
                this.selectedDate  = day.date;
                const yyyy = day.date.getFullYear();
                const mm   = String(day.date.getMonth() + 1).padStart(2, '0');
                const dd   = String(day.date.getDate()).padStart(2, '0');
                this.selectedLabel = day.date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const hidden = document.getElementById('bookingDateHidden');
                if (hidden) {
                    hidden.value = `${yyyy}-${mm}-${dd}`;
                    hidden.dispatchEvent(new Event('input'));
                    hidden.dispatchEvent(new Event('change'));
                }
                this.open = false;
            },
        }));

        Alpine.data('bookingTimePicker', (wireField) => ({
            open:         false,
            hour:         8,
            minute:       0,
            ampm:         'AM',
            selectedTime: '',

            init() {
                this.$watch(`$wire.${wireField}`, val => {
                    if (val) {
                        const [h, m] = val.split(':').map(Number);
                        this.ampm   = h >= 12 ? 'PM' : 'AM';
                        this.hour   = h % 12 || 12;
                        this.minute = m;
                        this.updateDisplay();
                    }
                });
            },

            toggle() {
                if (this.open) { this.close(); return; }
                this.open = true;
                this.$nextTick(() => this.position());
            },

            position() {
                const trigger = this.$el.querySelector('.custom-time-display');
                const drop    = this.$el.querySelector('.time-picker-dropdown');
                if (!trigger || !drop) return;
                const rect  = trigger.getBoundingClientRect();
                const dropH = drop.offsetHeight || 240;
                const dropW = drop.offsetWidth  || 220;
                drop.style.top  = (rect.top - dropH - 6) + 'px';
                let left = rect.left;
                if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
                drop.style.left = Math.max(8, left) + 'px';
            },

            close() { this.open = false; },

            changeHour(dir) { this.hour = ((this.hour - 1 + dir + 12) % 12) + 1; this.syncHourInput(); this.commit(); },
            changeMin(dir)  { this.minute = (this.minute + dir * 15 + 60) % 60; this.syncMinInput(); this.commit(); },
            setAmpm(val)    { this.ampm = val; this.commit(); },

            onHourInput(e) {
                let val = parseInt(e.target.value) || 1;
                if (val < 1)  val = 1;
                if (val > 12) val = 12;
                this.hour = val;
                e.target.value = String(val).padStart(2, '0');
                this.commit();
            },
            onMinInput(e) {
                let val = parseInt(e.target.value);
                if (isNaN(val) || val < 0) val = 0;
                if (val > 59) val = 59;
                this.minute = val;
                e.target.value = String(val).padStart(2, '0');
                this.commit();
            },

            syncHourInput() { const el = this.$el.querySelector('.tp-hour-input'); if (el) el.value = String(this.hour).padStart(2, '0'); },
            syncMinInput()  { const el = this.$el.querySelector('.tp-min-input');  if (el) el.value = String(this.minute).padStart(2, '0'); },

            commit() {
                let h24    = this.hour % 12;
                if (this.ampm === 'PM') h24 += 12;
                const val      = `${String(h24).padStart(2, '0')}:${String(this.minute).padStart(2, '0')}`;
                const hiddenId = wireField === 'schedule_start' ? 'startTimeHidden' : 'endTimeHidden';
                const hidden   = document.getElementById(hiddenId);
                if (hidden) {
                    hidden.value = val;
                    hidden.dispatchEvent(new Event('input'));
                    hidden.dispatchEvent(new Event('change'));
                }
                this.updateDisplay();
            },

            updateDisplay() {
                const h = String(this.hour).padStart(2, '0');
                const m = String(this.minute).padStart(2, '0');
                this.selectedTime = `${h}:${m} ${this.ampm}`;
            },
        }));
    });

    // ── Confirmation modal ────────────────────────────────────────────────────
    (function () {
        const confirmModal     = document.getElementById('confirmModal');
        const confirmModalBox  = document.getElementById('confirmModalBox');
        const confirmTitle     = document.getElementById('confirmTitle');
        const confirmBody      = document.getElementById('confirmBody');
        const confirmMeta      = document.getElementById('confirmMeta');
        const confirmOkBtn     = document.getElementById('confirmOkBtn');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmIconWrap  = document.getElementById('confirmIconWrap');

        function iconCheck(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
        }
        function iconX(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`;
        }
        function iconInfo(color) {
            return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/><path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${color}"/></svg>`;
        }

        window.closeConfirmModal = function () {
            confirmModal.style.display = 'none';
            confirmOkBtn.onclick = null;
        };

        window.openConfirmModal = function ({ title, body, meta, confirmText, loadingText, variant, onConfirm }) {
            const variants = {
                accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
                reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
                neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800',       label: 'Confirm' },
                cancel:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-700 hover:bg-red-800',         label: 'Cancel'  },
            };
            const v = variants[variant] || variants.neutral;

            confirmIconWrap.style.background = v.iconBg;
            confirmIconWrap.innerHTML        = v.iconHtml;
            confirmTitle.textContent         = title;
            confirmBody.innerHTML            = body;
            confirmMeta.innerHTML            = meta || '';
            confirmMeta.style.display        = meta ? 'block' : 'none';
            confirmOkBtn.className           = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
            confirmOkBtn.textContent         = confirmText || v.label;

            confirmOkBtn.onclick = async () => {
                const originalText = confirmOkBtn.textContent;
                confirmOkBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${loadingText || 'Processing...'}`;
                confirmOkBtn.classList.add('opacity-70', 'cursor-not-allowed');
                confirmOkBtn.style.pointerEvents = 'none';
                confirmCancelBtn.disabled = true;
                confirmCancelBtn.classList.add('opacity-50', 'cursor-not-allowed');

                try {
                    const result = onConfirm();
                    if (result && typeof result.then === 'function') await result;
                } finally {
                    confirmOkBtn.textContent = originalText;
                    confirmOkBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                    confirmOkBtn.style.pointerEvents = 'auto';
                    confirmCancelBtn.disabled = false;
                    confirmCancelBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    window.closeConfirmModal();
                }
            };

            confirmModal.style.display = 'flex';
        };

        confirmModal.addEventListener('click', (e) => {
            if (!confirmModalBox.contains(e.target)) window.closeConfirmModal();
        });
        confirmCancelBtn.addEventListener('click', window.closeConfirmModal);
    })();

    // ── Booking submit intercept ──────────────────────────────────────────────
    window.addEventListener('show-booking-confirm', function () {
        const subjectEl      = document.querySelector('[wire\\:model="subject_id"]');
        const topicEl        = document.querySelector('[wire\\:model="topic"]');
        const tutorialModeEl = document.querySelector('[wire\\:model="tutorialMode_id"]');
        const dateEl         = document.querySelector('[wire\\:model="date"]');
        const startEl        = document.querySelector('[wire\\:model="schedule_start"]');
        const endEl          = document.querySelector('[wire\\:model="schedule_end"]');
        const mentorEl       = document.querySelector('[wire\\:model="mentor_id"]');

        const subjectText      = subjectEl?.options[subjectEl.selectedIndex]?.text || '—';
        const topicText        = topicEl?.value || '—';
        const tutorialModeText = tutorialModeEl?.options[tutorialModeEl.selectedIndex]?.text || '—';
        const dateText         = dateEl?.value
            ? new Date(dateEl.value + 'T00:00:00').toLocaleDateString('en-US', {
                weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
            })
            : '—';

        function formatTime(t) {
            if (!t) return '';
            const [h, m] = t.split(':').map(Number);
            const ampm   = h >= 12 ? 'PM' : 'AM';
            const hr     = h % 12 || 12;
            return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
        }

        const startText = formatTime(startEl?.value) || '—';
        const endText   = formatTime(endEl?.value)   || '—';

        let mentorText = '—';
        if (mentorEl && mentorEl.selectedIndex >= 0 && mentorEl.options[mentorEl.selectedIndex].value !== '') {
            mentorText = mentorEl.options[mentorEl.selectedIndex].text;
        } else {
            const rootEl = document.querySelector('[wire\\:id]');
            if (rootEl) {
                const wire = Livewire.find(rootEl.getAttribute('wire:id'));
                if (wire && wire.get('isMentorLocked')) {
                    const lockedId  = wire.get('mentor_id');
                    const mentorObj = wire.get('mentors').find(m => m.profile_id == lockedId);
                    if (mentorObj) mentorText = mentorObj.name;
                }
            }
        }

        const metaHtml = `
            <div class="flex justify-between items-start gap-4 mb-1">
                <span class="text-gray-400 shrink-0">Subject</span>
                <span class="font-semibold text-gray-700 text-right truncate">${subjectText}</span>
            </div>
            <div class="flex justify-between items-start gap-4 mb-1">
                <span class="text-gray-400 shrink-0">Topic</span>
                <span class="font-semibold text-gray-700 text-right truncate" style="max-width:190px;">${topicText}</span>
            </div>
            <div class="flex justify-between items-start gap-4 mb-1">
                <span class="text-gray-400 shrink-0">Mode</span>
                <span class="font-semibold text-gray-700 text-right truncate">${tutorialModeText}</span>
            </div>
            <div class="flex justify-between items-start gap-4 mb-1">
                <span class="text-gray-400 shrink-0">Mentor</span>
                <span class="font-semibold text-gray-700 text-right truncate">${mentorText}</span>
            </div>
            <div class="flex justify-between items-start gap-4 mb-1">
                <span class="text-gray-400 shrink-0">Date</span>
                <span class="font-semibold text-gray-700 text-right">${dateText}</span>
            </div>
            <div class="flex justify-between items-start gap-4">
                <span class="text-gray-400 shrink-0">Time</span>
                <span class="font-semibold text-gray-700 text-right">${startText} – ${endText}</span>
            </div>
        `;

        window.openConfirmModal({
            title:       'Confirm booking request?',
            body:        'Please review your session details before submitting. Your request will be reviewed by the peer mentor.',
            meta:        metaHtml,
            variant:     'accept',
            confirmText: 'Submit Booking',
            loadingText: 'Submitting...',
            onConfirm: async () => {
                const root = document.getElementById('bookingForm').closest('[wire\\:id]');
                const wire = Livewire.find(root.getAttribute('wire:id'));
                await wire.submitBooking();
            },
        });
    });
</script>
