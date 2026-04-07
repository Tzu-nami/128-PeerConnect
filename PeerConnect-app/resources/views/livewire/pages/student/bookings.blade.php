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
use Illuminate\Validation\Rule;
use function Livewire\Volt\{layout, state, mount, action, computed, updated};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    if($profile) {
        $this->student_num = $profile->student_num;
        $this->college_id = $profile->college_id;
        $this->degreeProgram_id = $profile->degreeProgram_id;
        $this->yearLevel_id = $profile->yearLevel_id;
        $this->toggleProfileOpen = false;
    }
});

// For booking forms inputs
state([
    'mentor_id' => '',
    'subject_id' => '',
    'topic' => '',
    'tutorialMode_id' => '',
    'date' => '',
    'schedule_start' => '',
    'schedule_end' => '',
    'successMessage' => false,
    'cancelledMessage' => false,
    // Feedback form state
    'feedbackText' => '',
    'feedbackSubmitted' => false,
    // Multi-step feedback
    'feedbackStep' => 1,   // 1 = Q1-Q5, 2 = Q6-Q10, 3 = remarks
    'q1' => null,
    'q2' => null,
    'q3' => null,
    'q4' => null,
    'q5' => null,
    'q6' => null,
    'q7' => null,
    'q8' => null,
    'q9' => null,
    'q10' => null,  // bool: null | "1" | "0"
]);

$mentors = computed(function () {
    return MentorProfiles::with('user')
        ->get()
        ->filter(fn($mentorProfiles) => $mentorProfiles->user->id !== auth()->id())
        ->sortBy(fn($lName) => $lName->user->lastName)
        ->values()
        ->map(fn($mentorProfiles) => [
            'id' => $mentorProfiles->user->id,
            'profile_id' => $mentorProfiles->id,
            'name' => strtoupper($mentorProfiles->user->lastName). ', ' . $mentorProfiles->user->firstName,
        ])
        ->toArray();
});

$mentorAvailabilities = computed(function () {
    return MentorAvailabilities::all() -> map(fn($avail)=> [
        'mentorProfile_id' => $avail->mentor_id,
        'day_of_week' => $avail->day_of_week,
        'start_time' => $avail->start_time,
        'end_time' => $avail->end_time,
    ]) -> values() -> toArray();
});

$mentorSubjects = computed(function () {
    return MentorSubjects::all() -> map(fn($subs) => [
        'mentorProfile_id' => $subs->mentor_id,
        'subject_id' => $subs->subject_id,
    ]) -> values() -> toArray();
});

$subjects = computed(function () {
    return Subjects::orderBy('code')->get();
});

$tutorialModes = computed(function () {
    return TutorialMode::orderBy('id')->get();
});

$studentBookings = computed(function () {
    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    if(!$profile) {return collect();}
    return Bookings::with(['mentor', 'subject', 'tutorialMode'])
        ->where('student_id', $profile->id)
        ->latest()
        ->take(5)
        ->get();
});

// For student profile inputs
state([
    'toggleProfileOpen' => true,
    'profileSaved' => false,
    'student_num' => '',
    'college_id' => '',
    'degreeProgram_id' => '',
    'yearLevel_id' => '',
]);

$colleges = computed(function () {
    return Colleges::orderBy('name')->get();
});

$degreePrograms = computed(function() {
    return DegreePrograms::orderBy('name')->get();
});

$yearLevels = computed(function () {
    return YearLevels::orderBy('name')->get();
});

$toggleProfile = action(function () {
    $this->toggleProfileOpen = !$this->toggleProfileOpen;
});

$saveProfile = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $validated = $this->validate([
        'student_num' => ['required', 'string', 'max:10', 'regex:/-/'],
        'college_id' => ['required', 'exists:colleges,id'],
        'degreeProgram_id' => ['required', 'exists:degree_programs,id'],
        'yearLevel_id' => ['required', 'exists:year_levels,id'],
    ],  messages: [
        'student_num.regex' => 'The student number must include a hyphen (-)',
    ],  attributes: [
        'student_num' => 'student number',
        'college_id' => 'college',
        'degreeProgram_id' => 'degree program',
        'yearLevel_id' => 'year level',
    ]);

    StudentProfiles::updateOrCreate(
        ['user_id' => auth()->id()],
        $validated
    );

    $this->profileSaved = true;
    $this->toggleProfileOpen = false;
    $this->dispatch('profile-updated');
});

// Advance feedback step with per-step validation
$nextFeedbackStep = action(function () {
    if ($this->feedbackStep === 1) {
        $this->validate([
            'q1' => ['required', 'integer', 'min:1', 'max:5'],
            'q2' => ['required', 'integer', 'min:1', 'max:5'],
            'q3' => ['required', 'integer', 'min:1', 'max:5'],
            'q4' => ['required', 'integer', 'min:1', 'max:5'],
            'q5' => ['required', 'integer', 'min:1', 'max:5'],
        ], attributes: [
            'q1' => 'Question 1', 'q2' => 'Question 2', 'q3' => 'Question 3',
            'q4' => 'Question 4', 'q5' => 'Question 5',
        ]);
        $this->feedbackStep = 2;
    } elseif ($this->feedbackStep === 2) {
        $this->validate([
            'q6'  => ['required', 'integer', 'min:1', 'max:5'],
            'q7'  => ['required', 'integer', 'min:1', 'max:5'],
            'q8'  => ['required', 'integer', 'min:1', 'max:5'],
            'q9'  => ['required', 'integer', 'min:1', 'max:5'],
            'q10' => ['required', 'in:0,1'],
        ], attributes: [
            'q6' => 'Question 6', 'q7' => 'Question 7', 'q8' => 'Question 8',
            'q9' => 'Question 9', 'q10' => 'Question 10',
        ]);
        $this->feedbackStep = 3;
    }
});

$prevFeedbackStep = action(function () {
    if ($this->feedbackStep > 1) {
        $this->feedbackStep--;
    }
});

// Submit booking form
$submitBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
    abort_if(!auth()->user()->studentProfile, 422);

    $profile = StudentProfiles::where('user_id', auth()->id())->first();

    $hasActive = Bookings::where('student_id', $profile->id)
        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
        ->exists();

    if ($hasActive) {
        session()->flash('error', 'You already have an active booking. Please wait for it to be completed or rejected before making a new one.');
        return;
    }

    $hasPendingFeedback = Bookings::where('student_id', $profile->id)
        ->whereRaw("booking_status::text = 'completed'")
        ->exists();

    if ($hasPendingFeedback) {
        session()->flash('error', 'Please submit your session feedback before making a new booking.');
        return;
    }

    $validated = $this->validate([
        'mentor_id' => ['required', 'exists:mentor_profiles,id'],
        'subject_id' => ['required', 'exists:subjects,id'],
        'topic' => ['required', 'string', 'max:255'],
        'tutorialMode_id' => ['required', 'exists:tutorial_modes,id'],
        'date' => ['required', 'date', 'after:today', function($attribute, $value, $fail) {
            $sessionDate = \Carbon\Carbon::parse($value)->format('l, F j, Y');
            if ($sessionDate === 'Sunday') {
                $fail('The session cannot be on a Sunday. Please select another date.');
            }
        }],
        'schedule_start' => ['required', 'date_format:H:i'],
        'schedule_end' => ['required', 'date_format:H:i', 'after:schedule_start'],
    ],  attributes: [
        'mentor_id' => 'mentor',
        'subject_id' => 'subject',
        'topic' => 'topic',
        'tutorialMode_id' => 'mode of tutorial',
        'date' => 'date',
        'schedule_start' => 'start time',
        'schedule_end' => 'end time',
    ]);

    $selectedMentor = MentorProfiles::find($validated['mentor_id']);

    if ($selectedMentor && $selectedMentor->user_id === auth()->id()) {
        $this->addError('mentor_id', 'You cannot book yourself as a mentor.');
        return;
    }

    Bookings::create([
        ...$validated,
        'student_id' => $profile->id,
        'booking_status' => 'pending',
    ]);

    $this->reset(['mentor_id', 'subject_id', 'topic', 'tutorialMode_id', 'date', 'schedule_start', 'schedule_end']);
    $this->successMessage = true;
});

$dismissSuccessMessage = action(function () {
    $this->successMessage = false;
});

$cancelBooking = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

    $profile = StudentProfiles::where('user_id', auth()->id())->first();
    abort_if(!$profile, 422);

    $booking = Bookings::where('student_id', $profile->id)
        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
        ->latest()
        ->first();

    abort_if(!$booking, 404);
    $booking->update(['booking_status' => 'cancelled']);
    $this->cancelledMessage = true;
});

$dismissCancelledMessage = action(function () {
    $this->cancelledMessage = false;
});

// Submit full feedback (called on step 3)
$submitFeedback = action(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');

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

    $booking = Bookings::with(['subject'])
        ->where('student_id', $profile->id)
        ->whereRaw("booking_status::text = 'completed'")
        ->latest()
        ->first();

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

    $booking->update(['booking_status' => 'closed']);

    $this->reset(['feedbackText','q1','q2','q3','q4','q5','q6','q7','q8','q9','q10']);
    $this->feedbackStep = 1;
    $this->feedbackSubmitted = true;
});

$dismissFeedbackSubmitted = action(function () {
    $this->feedbackSubmitted = false;
});

?>

<div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-green: #1a3c2f;
            --header-maroon: #7b1d1d;
            --bg-light: #f4f7f6;
            --header-height: 80px;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        .sidebar {
            width: var(--sidebar-width); background: var(--sidebar-green); flex-shrink: 0;
            display: flex; flex-direction: column; color: white; height: 100vh;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 30;
            position: relative; overflow: visible;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-logo-container {
            height: var(--header-height); display: flex; align-items: center; justify-content: center;
            padding: 0 20px; gap: 12px; flex-shrink: 0; overflow: hidden;
            transition: padding 0.3s, justify-content 0.3s;
        }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }
            .logo-icon { flex-shrink: 0; font-size: 1.3rem; width: 32px; text-align: center; }

            .logo-text {
                font-size: 1.2rem;
                font-weight: 700;
                white-space: nowrap;
                overflow: hidden;
                opacity: 1;
                max-width: 200px;
                transition: opacity 0.2s, max-width 0.3s;
            }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }

            .nav-item {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 18px 20px;
                color: rgba(255,255,255,0.7);
                text-decoration: none;
                transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
                white-space: nowrap;
                position: relative;
                text-align: left;
                background: transparent;
                border: none;
                width: 100%;
                cursor: pointer;
                font-size: 1.04rem;
                justify-content: flex-start;
            }
            .sidebar.collapsed .nav-item { justify-content: center; padding: 18px 0; }

            .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
        .sidebar.collapsed .nav-item i { width: 32px; margin: 0; }
        .nav-item span { overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }
        .nav-item::after {
            content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 14px; background: rgba(0,0,0,0.85); color: white; padding: 5px 12px;
            border-radius: 4px; font-size: 12px; font-weight: 500; white-space: nowrap;
            opacity: 0; visibility: hidden; transition: opacity 0.2s; pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }
        .sidebar-footer { padding: 6px 0; border-top: 1px solid rgba(255,255,255,0.1); }

        .sidebar-toggle-btn {
            position: absolute; right: -16px; top: 3%; width: 32px; height: 32px; border-radius: 50%;
            background: #ffffff; border: none; cursor: pointer; display: flex; align-items: center;
            justify-content: center; color: #7b1d1d; font-size: 13px; z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25); transition: background 0.2s; flex-shrink: 0;
        }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }
        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }

        /* ── ACTIVE BOOKING CARD ── */
        .active-booking-card { background: #fffffa; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; }
        .active-booking-banner { display: flex; align-items: center; gap: 12px; padding: 16px 24px; }
        .active-booking-banner.pending  { background: linear-gradient(135deg,#fef9c3,#fef3c7); border-bottom: 1px solid #fde68a; }
        .active-booking-banner.accepted { background: linear-gradient(135deg,#d1fae5,#a7f3d0); border-bottom: 1px solid #6ee7b7; }
        .active-booking-banner-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .active-booking-banner.pending  .active-booking-banner-icon { background: #fde68a; color: #92400e; }
        .active-booking-banner.accepted .active-booking-banner-icon { background: #6ee7b7; color: #065f46; }
        .active-booking-body { padding: 24px; }
        .booking-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .booking-detail-item label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; display: block; margin-bottom: 3px; }
        .booking-detail-item p { font-size: 14px; font-weight: 600; color: #1f2937; margin: 0; }
        .booking-detail-item.full { grid-column: 1 / -1; }

        /* ── FEEDBACK CARD ── */
        .feedback-card { background: #fffffa; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); overflow: hidden; border: 2px solid #93c5fd; }
        .feedback-banner { display: flex; align-items: center; gap: 12px; padding: 16px 24px; background: linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom: 1px solid #bfdbfe; }
        .feedback-banner-icon { width: 40px; height: 40px; border-radius: 50%; background: #93c5fd; color: #1e3a8a; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }
        .feedback-body { padding: 24px; }

        /* ── PROGRESS BAR ── */
        .feedback-progress { display: flex; align-items: center; gap: 8px; margin-bottom: 22px; }
        .feedback-progress-step { flex: 1; height: 5px; border-radius: 3px; background: #e2e8f0; transition: background 0.3s; }
        .feedback-progress-step.done { background: #2563eb; }
        .feedback-progress-step.active { background: #93c5fd; }
        .feedback-progress-label { font-size: 11px; font-weight: 700; color: #64748b; white-space: nowrap; }

        /* ── SESSION SUMMARY ── */
        .feedback-session-summary {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 12px 16px; margin-bottom: 20px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
        }
        .feedback-session-summary .fs-item label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; display: block; margin-bottom: 2px; }
        .feedback-session-summary .fs-item p { font-size: 12px; font-weight: 600; color: #1f2937; margin: 0; }
        .feedback-session-summary .fs-item.full { grid-column: 1 / -1; }

        /* ── LIKERT QUESTION ── */
        .likert-question {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 16px 18px; margin-bottom: 10px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .likert-question.answered { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,0.15); }
        .likert-question-num { font-size: 10px; font-weight: 700; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px; }
        .likert-question-text { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 14px; line-height: 1.5; }
        .likert-scale-labels { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .likert-scale-labels span { font-size: 9px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .likert-options { display: flex; gap: 6px; }
        .likert-options input[type="radio"] { display: none; }
        .likert-options label {
            flex: 1; text-align: center; padding: 8px 4px; border-radius: 8px;
            border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 700;
            color: #94a3b8; cursor: pointer; transition: all 0.15s; background: #f8fafc; user-select: none;
        }
        .likert-options label:hover { border-color: #93c5fd; color: #2563eb; background: #eff6ff; }
        .likert-options input[type="radio"]:checked + label { background: #2563eb; border-color: #2563eb; color: white; box-shadow: 0 2px 6px rgba(37,99,235,0.3); }

        /* ── BOOL QUESTION ── */
        .bool-question {
            background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 16px 18px; margin-bottom: 10px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .bool-question.answered { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,0.15); }
        .bool-options { display: flex; gap: 10px; margin-top: 12px; }
        .bool-options input[type="radio"] { display: none; }
        .bool-options label {
            flex: 1; text-align: center; padding: 10px; border-radius: 10px;
            border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 700;
            color: #64748b; cursor: pointer; transition: all 0.15s; background: #f8fafc;
        }
        .bool-options label.yes:hover { border-color: #86efac; color: #15803d; background: #f0fdf4; }
        .bool-options label.no:hover  { border-color: #fca5a5; color: #b91c1c; background: #fef2f2; }
        .bool-options input[type="radio"]:checked + label.yes { background: #16a34a; border-color: #16a34a; color: white; box-shadow: 0 2px 6px rgba(22,163,74,0.3); }
        .bool-options input[type="radio"]:checked + label.no  { background: #dc2626; border-color: #dc2626; color: white; box-shadow: 0 2px 6px rgba(220,38,38,0.3); }

        /* ── REMARKS ── */
        .feedback-textarea {
            width: 100%; min-height: 120px; border-radius: 10px; border: 1.5px solid #d1d5db;
            padding: 12px 14px; font-size: 14px; font-family: inherit; color: #374151;
            resize: vertical; transition: border-color 0.2s; outline: none;
        }
        .feedback-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

        /* ── NAV BUTTONS ── */
        .feedback-nav { display: flex; gap: 10px; margin-top: 18px; }
        .feedback-btn-back {
            flex: 0 0 auto; padding: 10px 20px; border-radius: 8px; border: 1.5px solid #e2e8f0;
            background: white; font-size: 13px; font-weight: 600; color: #64748b;
            cursor: pointer; transition: all 0.15s;
        }
        .feedback-btn-back:hover { background: #f1f5f9; border-color: #94a3b8; }
        .feedback-btn-next {
            flex: 1; padding: 10px 20px; border-radius: 8px; border: none;
            background: #2563eb; font-size: 13px; font-weight: 700; color: white;
            cursor: pointer; transition: background 0.15s;
        }
        .feedback-btn-next:hover { background: #1d4ed8; }
        .feedback-btn-next:disabled { opacity: 0.6; cursor: not-allowed; }
        .feedback-btn-submit {
            flex: 1; padding: 10px 20px; border-radius: 8px; border: none;
            background: #16a34a; font-size: 13px; font-weight: 700; color: white;
            cursor: pointer; transition: background 0.15s;
        }
        .feedback-btn-submit:hover { background: #15803d; }
        .feedback-btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>

    <div class="app-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>

            <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="toggle-icon"><i class="fa-solid fa-chevron-right"></i></span>
            </button>

            <nav class="flex-grow">
                <a href="{{ route('student.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.mentors') }}" class="nav-item" data-tooltip="Mentors">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentors</span>
                </a>
                <a href="{{ route('student.bookings') }}" class="nav-item active" data-tooltip="Bookings">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Bookings</span>
                </a>
                <a href="{{ route('student.history') }}" class="nav-item" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i><span>History</span>
                </a>
                <a href="{{ route('student.about') }}" class="nav-item" data-tooltip="About Us">
                    <i class="fa-solid fa-circle-info w-5"></i><span>About Us</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-content">
            <header class="top-header relative">
                <div class="text-lg">Welcome, {{ auth()->user()->user_roles }} <span class="font-bold">{{ auth()->user()->name }}</span></div>
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name,0,2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"></i>
                </button>
                <div id="profileDropdown" class="profile-dropdown">
                    <div class="p-4 border-b border-gray-100 bg-slate-50">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Signed in as</p>
                        <p class="text-sm font-bold text-slate-800 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item w-full border-t border-gray-50 text-red-600 font-semibold">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">

        @if($successMessage)
            <div class="mb-6 flex items-center justify-between bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                <span>Your session has been booked and is now <strong>pending</strong> approval.</span>
                <button wire:click="dismissSuccessMessage" class="text-green-600 hover:text-green-800 font-bold ml-4">X</button>
            </div>
        @endif

        @if($cancelledMessage)
            <div class="mb-6 flex items-center justify-between bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded">
                <span><i class="fa-solid fa-circle-xmark mr-2"></i>Your booking has been <strong>cancelled</strong>. You may now request a new session.</span>
                <button wire:click="dismissCancelledMessage" class="text-red-500 hover:text-red-700 font-bold ml-4">X</button>
            </div>
        @endif

        @if($feedbackSubmitted)
            <div class="mb-6 flex items-center justify-between bg-blue-50 border border-blue-300 text-blue-800 px-4 py-3 rounded">
                <span><i class="fa-solid fa-circle-check mr-2"></i>Thank you for your feedback! You may now request a new session.</span>
                <button wire:click="dismissFeedbackSubmitted" class="text-blue-500 hover:text-blue-700 font-bold ml-4">X</button>
            </div>
        @endif

        @if(!auth()->user()->studentProfile)
            <div class="mb-6 bg-yellow-100 border border-yellow-400 text-black-800 px-4 py-3 rounded">
                Please complete your <strong>Student Profile</strong> before booking a session.
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">

            @php
                $studentProfileForCheck = \App\Models\StudentProfiles::where('user_id', auth()->id())->first();

                $activeBooking = $studentProfileForCheck
                    ? \App\Models\Bookings::with(['mentor.user', 'subject', 'tutorialMode'])
                        ->where('student_id', $studentProfileForCheck->id)
                        ->whereRaw("booking_status::text IN ('pending', 'accepted')")
                        ->latest()->first()
                    : null;

                $completedBooking = $studentProfileForCheck
                    ? \App\Models\Bookings::with(['mentor.user', 'subject', 'tutorialMode'])
                        ->where('student_id', $studentProfileForCheck->id)
                        ->whereRaw("booking_status::text = 'completed'")
                        ->latest()->first()
                    : null;

                // All 10 questions
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
                    // Q10 is boolean, handled separately
                ];
            @endphp

            {{-- ══════════════════════════════════
                 MULTI-STEP FEEDBACK FORM
                 ══════════════════════════════════ --}}
            @if($completedBooking && !$activeBooking)
                @php $cb = $completedBooking; @endphp

                <div class="feedback-card">
                    <div class="feedback-banner">
                        <div class="feedback-banner-icon">
                            <i class="fa-solid fa-comment-dots"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-blue-900 mb-0.5">
                                Your session is complete &mdash;
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-200 text-blue-800">
                                    Feedback Required
                                </span>
                            </p>
                            <p class="text-xs text-blue-700 leading-snug">
                                Please rate your experience before booking again. Step {{ $feedbackStep }} of 3.
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
                                <p>{{ ($cb->subject->code ?? '—') . ($cb->subject->name ? ' — '.$cb->subject->name : '') }}</p>
                            </div>
                            <div class="fs-item">
                                <label>Mentor</label>
                                <p>{{ strtoupper($cb->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $cb->mentor->user->firstName ?? '' }}</p>
                            </div>
                            <div class="fs-item full">
                                <label>Topic &amp; Date</label>
                                <p>{{ $cb->topic }} &mdash; {{ \Carbon\Carbon::parse($cb->date)->format('F j, Y') }}</p>
                            </div>
                        </div>

                        {{-- ── STEP 1: Q1–Q5 ── --}}
                        @if($feedbackStep === 1)
                            <p class="text-xs font-semibold text-blue-700 mb-3 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-blue-300"></i>
                                Rate each statement from 1 (Strongly Disagree) to 5 (Strongly Agree).
                            </p>

                            @foreach(array_slice($questions, 0, 5, true) as $num => $text)
                                @php $field = 'q'.$num; $val = $this->$field; @endphp
                                <div class="likert-question {{ $val ? 'answered' : '' }}">
                                    <div class="likert-question-num">Question {{ $num }} of 10</div>
                                    <div class="likert-question-text">{{ $text }}</div>
                                    <div class="likert-scale-labels">
                                        <span>Strongly Disagree</span>
                                        <span>Strongly Agree</span>
                                    </div>
                                    <div class="likert-options">
                                        @for($i = 1; $i <= 5; $i++)
                                            <input type="radio" id="q{{ $num }}_{{ $i }}" wire:model="q{{ $num }}" value="{{ $i }}">
                                            <label for="q{{ $num }}_{{ $i }}">{{ $i }}</label>
                                        @endfor
                                    </div>
                                </div>
                                @error('q'.$num)
                                    <p class="text-xs text-red-500 -mt-1 mb-2 ml-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}</p>
                                @enderror
                            @endforeach

                            <div class="feedback-nav">
                                <button type="button" wire:click="nextFeedbackStep"
                                    wire:loading.attr="disabled" wire:target="nextFeedbackStep"
                                    class="feedback-btn-next">
                                    <span wire:loading.remove wire:target="nextFeedbackStep">Continue &rarr;</span>
                                    <span wire:loading wire:target="nextFeedbackStep"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking...</span>
                                </button>
                            </div>

                        {{-- ── STEP 2: Q6–Q10 ── --}}
                        @elseif($feedbackStep === 2)
                            <p class="text-xs font-semibold text-blue-700 mb-3 flex items-center gap-1">
                                <i class="fa-solid fa-circle-info text-blue-300"></i>
                                Continue rating (1 = Strongly Disagree, 5 = Strongly Agree). Q10 is Yes/No.
                            </p>

                            @foreach(array_slice($questions, 5, 4, true) as $num => $text)
                                @php $field = 'q'.$num; $val = $this->$field; @endphp
                                <div class="likert-question {{ $val ? 'answered' : '' }}">
                                    <div class="likert-question-num">Question {{ $num }} of 10</div>
                                    <div class="likert-question-text">{{ $text }}</div>
                                    <div class="likert-scale-labels">
                                        <span>Strongly Disagree</span>
                                        <span>Strongly Agree</span>
                                    </div>
                                    <div class="likert-options">
                                        @for($i = 1; $i <= 5; $i++)
                                            <input type="radio" id="q{{ $num }}_{{ $i }}" wire:model="q{{ $num }}" value="{{ $i }}">
                                            <label for="q{{ $num }}_{{ $i }}">{{ $i }}</label>
                                        @endfor
                                    </div>
                                </div>
                                @error('q'.$num)
                                    <p class="text-xs text-red-500 -mt-1 mb-2 ml-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}</p>
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
                                <p class="text-xs text-red-500 -mt-1 mb-2 ml-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>{{ $message }}</p>
                            @enderror

                            <div class="feedback-nav">
                                <button type="button" wire:click="prevFeedbackStep" class="feedback-btn-back">&larr; Back</button>
                                <button type="button" wire:click="nextFeedbackStep"
                                    wire:loading.attr="disabled" wire:target="nextFeedbackStep"
                                    class="feedback-btn-next">
                                    <span wire:loading.remove wire:target="nextFeedbackStep">Continue &rarr;</span>
                                    <span wire:loading wire:target="nextFeedbackStep"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking...</span>
                                </button>
                            </div>

                        {{-- ── STEP 3: Remarks + Submit ── --}}
                        @elseif($feedbackStep === 3)
                            <p class="text-sm font-bold text-gray-700 mb-1 flex items-center gap-2">
                                <i class="fa-solid fa-pen-to-square text-blue-400 text-xs"></i>
                                Additional Remarks
                                <span class="font-normal text-gray-400 text-xs">(optional)</span>
                            </p>
                            <p class="text-xs text-gray-400 mb-4">Any other thoughts about the session? This is optional — you can submit without filling this in.</p>

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
                                   x-data x-text="'' + ($wire.feedbackText ? $wire.feedbackText.length : 0) + ' / 2000'">
                                </p>

                                <div class="feedback-nav">
                                    <button type="button" wire:click="prevFeedbackStep" class="feedback-btn-back">&larr; Back</button>
                                    <button type="submit"
                                        wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed"
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

            {{-- ══ ACTIVE BOOKING VIEW ══ --}}
            @elseif($activeBooking)
                @php
                    $ab        = $activeBooking;
                    $isPending = $ab->booking_status === 'pending';
                    $statusClass = $isPending ? 'pending' : 'accepted';
                    $statusLabel = $isPending ? 'Awaiting Approval' : 'Accepted';
                    $statusIcon  = $isPending ? '<i class="fa-solid fa-hourglass-half"></i>' : '<i class="fa-solid fa-circle-check"></i>';
                    $statusDesc  = $isPending
                        ? 'Your booking request has been submitted and is waiting for the mentor\'s confirmation. You cannot make a new booking until this one is resolved.'
                        : 'Your session has been confirmed! Please be on time. You cannot make a new booking until this session is completed.';
                @endphp

                <div class="active-booking-card" x-data="{ confirmCancel: false }">
                    <div class="active-booking-banner {{ $statusClass }}">
                        <div class="active-booking-banner-icon">{!! $statusIcon !!}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold {{ $isPending ? 'text-yellow-900' : 'text-green-900' }} mb-0.5">
                                You have an active booking &mdash;
                                <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full {{ $isPending ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800' }}">
                                    {{ $statusLabel }}
                                </span>
                            </p>
                            <p class="text-xs {{ $isPending ? 'text-yellow-700' : 'text-green-700' }} leading-snug">{{ $statusDesc }}</p>
                        </div>
                    </div>

                    <div class="active-booking-body">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Session Details</h2>
                        <div class="booking-detail-grid">
                            <div class="booking-detail-item"><label>Subject</label><p>{{ $ab->subject->code ?? '—' }} &mdash; {{ $ab->subject->name ?? '' }}</p></div>
                            <div class="booking-detail-item"><label>Tutorial Mode</label><p>{{ $ab->tutorialMode->mode ?? '—' }}</p></div>
                            <div class="booking-detail-item full"><label>Topic</label><p>{{ $ab->topic }}</p></div>
                            <div class="booking-detail-item full"><label>Peer Mentor</label><p>{{ strtoupper($ab->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $ab->mentor->user->firstName ?? 'Mentor' }}</p></div>
                            <div class="booking-detail-item"><label>Date</label><p>{{ \Carbon\Carbon::parse($ab->date)->format('l, F j, Y') }}</p></div>
                            <div class="booking-detail-item"><label>Time</label><p>{{ \Carbon\Carbon::parse($ab->schedule_start)->format('g:i A') }} &ndash; {{ \Carbon\Carbon::parse($ab->schedule_end)->format('g:i A') }}</p></div>
                        </div>

                        <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between gap-4">
                            <p class="flex items-center gap-2 text-xs text-gray-400 flex-1">
                                <i class="fa-solid fa-circle-info text-gray-300 flex-shrink-0"></i>
                                You may cancel this booking at any time while it is <strong class="text-gray-500">{{ $ab->booking_status }}</strong>.
                            </p>
                            <div x-show="!confirmCancel">
                                <button type="button" @click="confirmCancel = true"
                                    class="flex-shrink-0 flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 font-semibold text-sm rounded-lg border border-red-200 transition-colors">
                                    <i class="fa-solid fa-ban"></i> Cancel Booking
                                </button>
                            </div>
                            <div x-show="confirmCancel" style="display:none;" class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-red-700">Are you sure?</span>
                                <button type="button" wire:click="cancelBooking"
                                    wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="cancelBooking"
                                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition-colors">
                                    <span wire:loading.remove wire:target="cancelBooking">Yes, Cancel</span>
                                    <span wire:loading wire:target="cancelBooking"><i class="fa-solid fa-spinner fa-spin"></i> Cancelling...</span>
                                </button>
                                <button type="button" @click="confirmCancel = false"
                                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg transition-colors">
                                    Go Back
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            {{-- ══ BOOKING FORM ══ --}}
            @else
            <div class="bg-[#fffffa] p-6 rounded-lg shadow-sm border-gray-200"
                x-data="{
                    allMentors: @js($this->mentors),
                    allSubjects: @js($this->mentorSubjects),
                    allAvailabilities: @js($this->mentorAvailabilities),
                    getDayOfWeek(dateStr) {
                        const days = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                        const d = new Date(dateStr + 'T00:00:00');
                        return days[d.getDay()];
                    },
                    get filteredMentors() {
                        let choices = this.allMentors;
                        if ($wire.subject_id) {
                            const validIds = this.allSubjects.filter(s => s.subject_id == $wire.subject_id).map(s => s.mentorProfile_id);
                            choices = choices.filter(m => validIds.includes(m.profile_id));
                        }
                        if ($wire.date) {
                            const dayChosen = this.getDayOfWeek($wire.date);
                            choices = choices.filter(m => {
                                const avails = this.allAvailabilities.filter(a => a.mentorProfile_id == m.profile_id && a.day_of_week === dayChosen);
                                if (avails.length === 0) return false;
                                if ($wire.schedule_start && $wire.schedule_end) {
                                    return avails.some(a => {
                                        let start = a.start_time.substring(0,5), end = a.end_time.substring(0,5);
                                        let startChosen = $wire.schedule_start.substring(0,5), endChosen = $wire.schedule_end.substring(0,5);
                                        return start <= startChosen && end >= endChosen;
                                    });
                                }
                                return true;
                            });
                        }
                        return choices;
                    }
                }">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Request an Enrichment Session!</h2>
                <p class="text-gray-500 text-sm mb-6">Please fill out all required fields. Your request will then be reviewed by the peer mentor.</p>

                <form id="bookingForm" wire:submit.prevent="submitBooking" class="space-y-2">
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Subject<span class="text-red-500">*</span></label>
                        <select wire:model="subject_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                            <option value="">--- Select a Subject ---</option>
                            @foreach($this->subjects as $subject)
                                <option value="{{ $subject['id'] }}">{{ $subject['code'] }} - {{ $subject['name'] }}</option>
                            @endforeach
                        </select>
                        @error('subject_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Topic<span class="text-red-500">*</span></label>
                        <input type="text" wire:model="topic" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1" placeholder="e.g. Integration by Parts." maxlength="255">
                        @error('topic') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Tutorial Mode<span class="text-red-500">*</span></label>
                        <select wire:model="tutorialMode_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                            <option value="">--- Select Mode of Tutoring ---</option>
                            @foreach($this->tutorialModes as $mode)
                                <option value="{{ $mode['id'] }}">{{ $mode['mode'] }}</option>
                            @endforeach
                        </select>
                        @error('tutorialMode_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Preferred Day<span class="text-red-500">*</span></label>
                        <input type="date" wire:model="date" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1" min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                        @error('date') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Start Time<span class="text-red-500">*</span></label>
                            <input type="time" wire:model="schedule_start" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                            @error('schedule_start') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">End Time<span class="text-red-500">*</span></label>
                            <input type="time" wire:model="schedule_end" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                            @error('schedule_end') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 mb-1">Preferred Mentor<span class="text-red-500">*</span></label>
                        <select wire:model="mentor_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                            <option value="" x-text="filteredMentors.length === 0 ? '--- No mentors available. Please select a different date or timeframe. ---' : '--- Select a mentor ---'"></option>
                            <template x-for="mentor in filteredMentors" :key="mentor.profile_id">
                                <option :value="mentor.profile_id" x-text="mentor.name"></option>
                            </template>
                        </select>
                        @error('mentor_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div class="pt-4">
                        <button type="button" id="bookingSubmitBtn"
                            @if(!auth()->user()->studentProfile) disabled @endif
                            class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
                            wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="submitBooking">
                            <span wire:loading.remove wire:target="submitBooking">Submit Booking Request</span>
                            <span wire:loading wire:target="submitBooking">Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>
            @endif

        </div>

        <div class="lg:col-span-1 space-y-6">
            <div class="bg-[#fffffa] rounded-xl shadow-sm border-gray-200 overflow-hidden p-6" 
                x-data="{ 
                    open: $wire.entangle('toggleProfileOpen'),
                    college: $wire.entangle('college_id'),
                    degree: $wire.entangle('degreeProgram_id'),
                    showSuccess: false,
                    allDegrees: @js($this->degreePrograms),
                    get filteredDeProgs() {
                        if (!this.college) return [];
                        return this.allDegrees.filter(deprog => deprog.college_id == this.college);
                    }
                }"
                @profile-updated.window="showSuccess = true; setTimeout(() => showSuccess = false, 10000)"
                x-init="
                    $watch('college', (val, oldVal) => { if (oldVal !== undefined && oldVal !== '') { degree = ''; } });
                    $nextTick(() => { let s = degree; degree = ''; degree = s; });
                ">
                
                <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-semibold text-gray-900">Student Profile</span>
                        @if(auth()->user()->studentProfile)
                            <span class="text-xs bg-green-200 px-2 py-1 rounded-full text-green-800 font-bold">Saved</span>
                        @else
                            <span class="text-xs bg-yellow-100 px-2 py-1 rounded-full text-yellow-800 font-bold">Required</span>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" style="display: none;" x-transition class="px-5 pb-5 border-t border-gray-100">
                    <div x-show="showSuccess" style="display: none;" x-transition class="mt-3 mb-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">Profile Updated!</div>
                    <form wire:submit.prevent="saveProfile" class="space-y-4 mt-4">
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Student Number<span class="text-red-500">*</span></label>
                            <input type="text" wire:model="student_num" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1" placeholder="e.g 2023-00000" maxlength="10">
                            @error('student_num') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">College<span class="text-red-500">*</span></label>
                            <select x-model="college" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                                <option value="">--- College ---</option>
                                @foreach($this->colleges as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                            @error('college_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Degree Program<span class="text-red-500">*</span></label>
                            <select x-model="degree" x-bind:disabled="!college" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1 disabled:bg-gray-100">
                                <option value="">--- Degree Program ---</option>
                                <template x-for="deprog in filteredDeProgs" :key="deprog.id">
                                    <option :value="deprog.id" x-text="deprog.name"></option>
                                </template>
                            </select>
                            @error('degreeProgram_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-1">Year Level<span class="text-red-500">*</span></label>
                            <select wire:model="yearLevel_id" class="w-full rounded-lg border-gray-300 shadow-sm text-base px-2 py-1">
                                <option value="">--- Year Level ---</option>
                                @foreach($this->yearLevels as $level)
                                    <option value="{{ $level['id'] }}">{{ $level['name'] }}</option>
                                @endforeach
                            </select>
                            @error('yearLevel_id') <span class="mt-1 text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
                            wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed" wire:target="saveProfile">
                            <span wire:loading.remove wire:target="saveProfile">{{ auth()->user()->studentProfile ? 'Update Profile' : 'Save Profile' }}</span>
                            <span wire:loading wire:target="saveProfile">Saving...</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-[#fffffa] rounded-xl shadow-sm border-gray-200 overflow-hidden p-6">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Recent Bookings</h3>
                @forelse($this->studentBookings as $booking)
                    <div class="mb-4 pb-4 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $booking->subject->code }}</p>
                                <p class="text-xs font-medium text-gray-800">{{ $booking->topic }}</p>
                                <p class="text-xs font-medium text-gray-800">{{ strtoupper($booking->mentor->user->lastName ?? 'UNKNOWN') }}, {{ $booking->mentor->user->firstName ?? 'Mentor' }}</p>
                            </div>
                            @php
                                $statusColors = match($booking->booking_status) {
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'closed'    => 'bg-purple-100 text-purple-800',
                                    'no-show'   => 'bg-red-100 text-red-800',
                                    default     => 'bg-gray-100 text-gray-800',
                                };
                            @endphp
                            <span class="text-xs font-medium px-2 py-1 rounded-full capitalize {{ $statusColors }}">
                                {{ str_replace('_', ' ', $booking->booking_status) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ \Carbon\Carbon::parse($booking->date)->format('l, F j, Y') }},
                            {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }} - {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No recent bookings.</p>
                @endforelse
            </div>
        </div>
        </div>
            </main>
        </div>
    </div>

<!-- CONFIRMATION MODAL -->
    <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
            <div class="flex justify-end gap-3">
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
            </div>
        </div>
    </div>

    </div>

<script>
    const sidebar = document.getElementById('sidebar');
    document.getElementById('sidebarToggle').addEventListener('click', () => sidebar.classList.toggle('collapsed'));

    const profileTrigger  = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    window.addEventListener('click', () => { if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); });

    /* ── CONFIRMATION MODAL ── */
    const confirmModal     = document.getElementById('confirmModal');
    const confirmModalBox  = document.getElementById('confirmModalBox');
    const confirmTitle     = document.getElementById('confirmTitle');
    const confirmBody      = document.getElementById('confirmBody');
    const confirmMeta      = document.getElementById('confirmMeta');
    const confirmOkBtn     = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmIconWrap  = document.getElementById('confirmIconWrap');

    confirmModal.addEventListener('click', (e) => { if (!confirmModalBox.contains(e.target)) closeConfirmModal(); });
    confirmCancelBtn.addEventListener('click', closeConfirmModal);

    function closeConfirmModal() { confirmModal.style.display = 'none'; confirmOkBtn.onclick = null; }

    function openConfirmModal({ title, body, meta, variant, onConfirm }) {
        const variants = {
            accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
            reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
            neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800',       label: 'Confirm' },
        };
        const v = variants[variant] || variants.neutral;
        confirmIconWrap.style.background = v.iconBg;
        confirmIconWrap.innerHTML        = v.iconHtml;
        confirmTitle.textContent         = title;
        confirmBody.innerHTML            = body;
        confirmMeta.innerHTML            = meta || '';
        confirmMeta.style.display        = meta ? 'block' : 'none';
        confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
        confirmOkBtn.textContent = v.label;
        confirmOkBtn.onclick     = () => { closeConfirmModal(); onConfirm(); };
        confirmModal.style.display = 'flex';
    }

    function iconCheck(color) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function iconX(color)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function iconInfo(color)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/><path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${color}"/></svg>`; }

    /* ── BOOKING SUBMIT INTERCEPT ── */
    document.addEventListener('click', function (e) {
        const bookingSubmitBtn = e.target.closest('#bookingSubmitBtn');
        if (!bookingSubmitBtn) return;

        const subjectEl = document.querySelector('[wire\\:model="subject_id"]');
        const topicEl   = document.querySelector('[wire\\:model="topic"]');
        const dateEl    = document.querySelector('[wire\\:model="date"]');
        const startEl   = document.querySelector('[wire\\:model="schedule_start"]');
        const endEl     = document.querySelector('[wire\\:model="schedule_end"]');
        const mentorEl  = document.querySelector('[wire\\:model="mentor_id"]');

        const subjectText = subjectEl?.options[subjectEl.selectedIndex]?.text || '—';
        const topicText   = topicEl?.value || '—';
        const dateText    = dateEl?.value ? new Date(dateEl.value + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : '—';
        const startText   = formatTime(startEl?.value) || '—';
        const endText     = formatTime(endEl?.value)   || '—';
        const mentorText  = mentorEl?.options[mentorEl.selectedIndex]?.text || '—';

        function formatTime(t) {
            if (!t) return '';
            const [h, m] = t.split(':').map(Number);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const hr   = h % 12 || 12;
            return `${hr}:${String(m).padStart(2,'0')} ${ampm}`;
        }

        const metaHtml = `
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Subject</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${subjectText}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Topic</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${topicText}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Mentor</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${mentorText}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#9ca3af;">Date</span><span style="font-weight:600;color:#374151;text-align:right;max-width:60%;">${dateText}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#9ca3af;">Time</span><span style="font-weight:600;color:#374151;">${startText} – ${endText}</span></div>
        `;

        openConfirmModal({
            title:     'Confirm booking request?',
            body:      'Please review your session details before submitting. Your request will be reviewed by the peer mentor.',
            meta:      metaHtml,
            variant:   'accept',
            onConfirm: () => {
                document.getElementById('bookingForm').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            },
        });
    });
</script>

    </div>
