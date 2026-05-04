<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use Livewire\WithFileUploads;
use App\Models\MentorProfiles;
use App\Models\StudentProfiles;
use App\Models\Bookings;
use App\Models\Subjects;
use App\Models\User;
use App\Models\Feedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\MentorAvailabilities;
use App\Models\MentorSubjects;
use App\Services\Avatar;

layout('layouts.app');

uses([WithFileUploads::class]);

state([
    'totalMentors' => 0,
    'sessionsToday' => 0,
    'pendingBookings' => 0,
    'totalStudents' => 0,
    'todaySessions' => [],
    'globalSearchTerm' => '',
    'pendingApprovalsList' => [],
    'allSessions' => [],

    // Form states
    'showModal' => false,
    'showSubjectModal' => false,
    'showConfirm' => false,
    'showSubjectConfirm' => false,

    // Edit states
    'showEditModal' => false,
    'showEditConfirm' => false,
    'editMentorId' => null,
    'editMentorName' => '',
    'editMentorEmail' => '',
    'editAvatarPreview' => '',
    'editAvatar' => null,

    'up_mail' => '',
    'newMentor' => null,
    'emailError' => '',
    'selectedSubjects' => [],
    'avatar' => null,

    'availabilities' => [
        ['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']
    ],

    'newSubjectCode' => '',
    'newSubjectName' => '',
]);

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
    $this->totalMentors = MentorProfiles::count();
    $this->sessionsToday = Bookings::whereDate('date', Carbon::today())->count();
    $this->pendingBookings = Bookings::where('booking_status', 'pending')->count();
    $this->totalStudents = StudentProfiles::count();

$this->todaySessions = Bookings::with(['mentor.user', 'student.user', 'subject'])
    ->whereDate('date', Carbon::today())
    ->orderBy('schedule_start')
    ->get()
    ->map(fn($b) => [
        'date'    => $b->date,
        'mentor'  => $b->mentor->user->name  ?? 'Unknown Mentor',
        'mentee'  => $b->student->user->name ?? 'Unknown Mentee',
        'subject' => $b->subject->code       ?? 'N/A',
        'time'    => Carbon::parse($b->schedule_start)->format('h:i A'),
        'status'  => ucfirst($b->booking_status),
    ])
    ->toArray();

        $this->pendingApprovalsList = Bookings::with(['student.user', 'mentor.user', 'subject'])
    ->where('booking_status', 'pending')
    ->latest('created_at')
    ->get()
    ->map(fn($b) => [
        'initials' => strtoupper(substr($b->student->user->name ?? 'U', 0, 2)),
        'name'     => $b->student->user->name ?? 'Unknown Student',
        'type'     => 'Session Booking',
        'subject'  => $b->subject->code       ?? 'N/A',
        'mentor'   => $b->mentor->user->name  ?? 'Unknown Mentor',
        'date'     => \Carbon\Carbon::parse($b->date)->format('M j'),
    ])
    ->toArray();

    $this->allSessions = Bookings::with(['mentor.user', 'student.user', 'subject'])
    ->orderBy('date')
    ->get()
    ->map(fn($b) => [
        'date'    => $b->date,
        'mentor'  => $b->mentor->user->name  ?? 'Unknown',
        'mentee'  => $b->student->user->name ?? 'Unknown',
        'subject' => $b->subject->code       ?? 'N/A',
        'topic'   => $b->topic               ?? '—',
        'time'    => Carbon::parse($b->schedule_start)->format('h:i A'),
        'status'  => ucfirst($b->booking_status),
        'start'   => $b->schedule_start,
        'end'     => $b->schedule_end,
        'mode'    => $b->mode                ?? 'One-on-One Tutorial',
    ])
    ->toArray();

        
}); 

$searchIndex = computed(function () {
    $index = [];

    $mentors = \App\Models\User::where('user_roles', 'mentor')->get();
    foreach ($mentors as $m) {
        $year = $m->studentProfile->yearLevel->name;
        $deprog = $m->studentProfile->degreeProgram->name;
        $index[] = [
            'group' => 'Mentors',
            'label' => $m->lastName . ', ' . $m->firstName,
            'detail' => $m->email . ' -- ' . $year . ' ' . $deprog,
            'icon' => 'fa-chalkboard-user',
            'bg' => '#dbeafe', 'color' => '#1e40af',
            'url' => route('admin.mentors'),
            'searchString' => strtolower($m->firstName . ' ' . $m->lastName . ' ' . $m->email . ' ' . $year . ' ' . $deprog)
        ];
    }

    $subjects = \App\Models\Subjects::all();
    foreach ($subjects as $s) {
        $index[] = [
            'group' => 'Courses',
            'label' => strtoupper($s->code),
            'icon' => 'fa-book-open',
            'bg' => '#fef3c7', 'color' => '#92400e',
            'url' => route('admin.courses'),
            'searchString' => strtolower($s->code)
        ];
    }

    $bookings = \App\Models\Bookings::with('mentor.user', 'subject')->latest()->take(50)->get();
    foreach ($bookings as $b) {
        $mentorName = $b->mentor ? ($b->mentor->user->lastName . ', ' . $b->mentor->user->firstName ?? 'Unknown Mentor') : 'Unknown Mentor';
        $sessionDate = \Carbon\Carbon::parse($b->date)->format('F j, Y');
        $index[] = [
            'group' => 'Sessions',
            'label' => $b->topic ?: 'Tutorial Session',
            'detail' => $sessionDate . ' -- Subject: ' . $b->subject->code . ' -- Mentor: ' . $mentorName . ' -- Status: ' . ucfirst($b->booking_status),
            'icon' => 'fa-calendar-days',
            'bg' => '#d1fae5', 'color' => '#065f46',
            'url' => route('admin.sessions'),
            'searchString' => strtolower($b->topic . ' ' . $mentorName . ' ' . $b->booking_status . ' ' . $b->subject->code . ' ' . $sessionDate)
        ];
    }

    $feedbacks = \App\Models\Feedback::with(['booking.subject', 'booking.mentor.user'])->latest('id')->take(50)->get();
    foreach ($feedbacks as $fb) {
        $subjectCode = $fb->booking->subject->code ?? 'N/A';
        $mentorName = $fb->booking->mentor->user ? ($fb->booking->mentor->user->lastName . ', ' . $fb->booking->mentor->user->firstName) : 'Unknown Mentor';
        $comment = $fb->feedback ?? 'No comment provided.';
        $date = isset($fb->date_submitted) ? \Carbon\Carbon::parse($fb->date_submitted)->format('F j, Y') : '';
        $topic = $fb->topic ?? 'Session Feedback';
        $index[] = [
            'group' => 'Feedback',
            'label' => \Illuminate\Support\Str::limit($comment, 40),
            'detail' => "{$date} -- Subject: {$subjectCode} -- Mentor: {$mentorName} -- Topic: {$topic}",
            'icon' => 'fa-comment-dots',
            'bg' => '#f3e8ff', 'color' => '#7e22ce',
            'url' => route('admin.feedbacks'),
            'searchString' => strtolower($comment . ' ' . $mentorName . ' ' . $subjectCode . ' ' . $topic . ' ' . $date)
        ];
    }

    return $index;
});

$monthlyTrends = computed(function () {
    return Bookings::selectRaw("EXTRACT(WEEK FROM date) as week, COUNT(*) as count")
        ->whereMonth('date', Carbon::now()->month)
        ->groupBy('week')
        ->orderBy('week')
        ->pluck('count', 'week')
        ->values()
        ->toArray();
});

$topMentors = computed(function () {
    return Bookings::with('mentor.user')
        ->selectRaw('mentor_id, COUNT(*) as session_count')
        ->groupBy('mentor_id')
        ->orderByDesc('session_count')
        ->take(4)
        ->get()
        ->map(fn($b) => [
            'name'  => $b->mentor->user->lastName ?? 'Unknown',
            'count' => $b->session_count,
        ])
        ->toArray();
});

$satisfactionRate = computed(function () {
    $feedbacks = Feedback::whereNotNull('q1')->get();

    if ($feedbacks->isEmpty()) {
        return [0, 0, 0];
    }

    $scores = $feedbacks->map(function ($fb) {
        $questions = [$fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5,
                      $fb->q6, $fb->q7, $fb->q8, $fb->q9, $fb->q10];
        $answered = array_filter($questions, fn($q) => !is_null($q));
        return count($answered) > 0 ? array_sum($answered) / count($answered) : null;
    })->filter()->values();

    $total     = $scores->count();
    $excellent = $scores->filter(fn($s) => $s >= 4)->count();
    $good      = $scores->filter(fn($s) => $s >= 3 && $s < 4)->count();
    $average   = $scores->filter(fn($s) => $s < 3)->count();

    return [
        round($excellent / $total * 100),
        round($good      / $total * 100),
        round($average   / $total * 100),
    ];
});

$topSubjects = computed(function () {
    return Bookings::with('subject')
        ->selectRaw('subject_id, COUNT(*) as booking_count')
        ->groupBy('subject_id')
        ->orderByDesc('booking_count')
        ->take(5)
        ->get()
        ->map(fn($b) => [
            'name'  => $b->subject->code ?? 'Unknown',
            'count' => $b->booking_count,
        ])
        ->toArray();
});

$collegeActivity = computed(function () {
    return StudentProfiles::join('degree_programs', 'student_profiles.degreeProgram_id', '=', 'degree_programs.id')
        ->join('colleges', 'degree_programs.college_id', '=', 'colleges.id')
        ->selectRaw('colleges.code as college, COUNT(*) as count')
        ->groupBy('colleges.id', 'colleges.code')
        ->orderByDesc('count')
        ->take(3)
        ->pluck('count', 'college')
        ->toArray();
});

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
    ];
});

$allSubjects = computed(function () {
    return Subjects::orderBy('code')->get()
        ->map(fn($subs) => ['id' => $subs->id, 'code' => $subs->code, 'name' => $subs->name])
        ->toArray();
});

$openModal = action(function () {
    $this->reset(['up_mail', 'newMentor', 'emailError', 'availabilities', 'showConfirm', 'avatar']);
    $this->selectedSubjects = [];
    $this->availabilities = [['day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    $this->showModal = true;
});

$closeModal = action(function () {
    $this->showModal = false;
    $this->showConfirm = false;
    $this->reset(['up_mail', 'newMentor', 'emailError', 'avatar']);
    $this->selectedSubjects = [];
    $this->availabilities = [['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
});

$openSubjectModal = action(function () {
    $this->reset(['newSubjectCode', 'newSubjectName', 'showSubjectConfirm']);
    $this->showSubjectModal = true;
});

$closeSubjectModal = action(function () {
    $this->showSubjectModal = false;
    $this->showSubjectConfirm = false;
    $this->reset(['newSubjectCode', 'newSubjectName']);
});

$checkEmail = action(function () {
    $this->emailError = '';
    $this->newMentor = null;

    $this->validate(['up_mail' => ['required', 'email']]);
    $userEmail = User::with('studentProfile')->where('email', $this->up_mail)->first();

    if (!$userEmail) {
        $this->emailError = 'The student with this email does not exist.';
        return;
    }

    if (!$userEmail->studentProfile || empty($userEmail->studentProfile->student_num)) {
        $this->emailError = 'The student must complete their student profile first. Please tell the student to login to the system, then go to the booking forms to complete their student profile.';
        return;
    }

    if ($userEmail->isMentor()) {
        $this->emailError = 'This student is already a peer mentor';
        return;
    }

    $this->newMentor = [
        'id' => $userEmail->id,
        'name' => $userEmail->name,
        'email' => $userEmail->email,
    ];
});

$toggleAvailabilityOn = action(function () {
    $this->availabilities[] = ['day_of_week' => '', 'start_time' => '', 'end_time' => ''];
});

$toggleAvailabilityOff = action(function (int $index) {
    array_splice($this->availabilities, $index, 1);
    $this->availabilities = array_values($this->availabilities);
});

$confirmMentor = action(function () {
    if (!$this->newMentor) {
        $this->emailError = 'Please input a valid student email.';
        return;
    }

    $this->validate([
        'avatar' => ['required', 'image', 'max:2048'],
        'selectedSubjects' => ['required', 'array', 'min:1'],
        'selectedSubjects.*' => ['exists:subjects,id'],
        'availabilities' => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time' => ['required', 'date_format:H:i'],
        'availabilities.*.end_time' => ['required', 'date_format:H:i'],
    ], [], [
        'avatar' => 'profile picture',
        'selectedSubjects' => 'subjects',
        'availabilities' => 'availabilities',
    ]);

    foreach ($this->availabilities as $i => $row) {
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }
    }
    $this->showConfirm = true;
});

$saveMentor = action(function () {
    if (!$this->newMentor) return;

    $userMentor = User::findOrFail($this->newMentor['id']);
    $userMentor->update(['user_roles' => 'mentor']);

    if ($this->avatar) {
        $filename = $this->avatar->hashName();
        $this->avatar->storeAs('', $filename, 's3');
        $url = rtrim(config('filesystems.disks.s3.public_url'), '/') . '/' . $filename;
        $userMentor->update(['avatar' => $url]);
    }

    $mentorProf = MentorProfiles::create(['user_id' => $userMentor->id]);

    foreach ($this->selectedSubjects as $subjectId) {
        MentorSubjects::create(['mentor_id' => $mentorProf->id, 'subject_id' => $subjectId]);
    }

    foreach ($this->availabilities as $sched) {
        MentorAvailabilities::create([
            'mentor_id' => $mentorProf->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time' => $sched['start_time'],
            'end_time' => $sched['end_time'],
        ]);
    }

    $this->showModal = false;
    $this->showConfirm = false;
    session()->flash('successMessage', "{$userMentor->name} has been registered as a mentor.");
    $this->redirect(route('admin.mentors'), navigate: true);
});

$confirmSubject = action(function () {
    $this->validate([
        'newSubjectCode' => ['required', 'string', 'max:20', 'unique:subjects,code'],
        'newSubjectName' => ['required', 'string', 'max:255'],
    ], [], [
        'newSubjectCode' => 'subject_code',
        'newSubjectName' => 'subject_name',
    ]);
    $this->showSubjectConfirm = true;
});

$saveSubject = action(function () {
    Subjects::create([
        'code' => trim($this->newSubjectCode),
        'name' => trim($this->newSubjectName),
    ]);
    $this->showSubjectModal = false;
    $this->showSubjectConfirm = false;
    session()->flash('successMessage', "{$this->newSubjectCode} has been added.");
    $this->redirect(route('admin.courses'), navigate: true);
});

$acceptBooking = action(function (string $id) {
    Bookings::where('id', $id)->update(['booking_status' => 'accepted']);
    $this->pendingApprovalsList = Bookings::with(['student.user', 'mentor.user', 'subject'])
        ->where('booking_status', 'pending')
        ->latest('created_at')
        ->get()
        ->map(fn($b) => [
            'initials' => strtoupper(substr($b->student->user->name ?? 'U', 0, 2)),
            'name'     => $b->student->user->name ?? 'Unknown Student',
            'type'     => 'Session Booking',
            'subject'  => $b->subject->code       ?? 'N/A',
            'mentor'   => $b->mentor->user->name  ?? 'Unknown Mentor',
            'date'     => \Carbon\Carbon::parse($b->date)->format('M j'),
        ])
        ->toArray();
    $this->pendingBookings = Bookings::where('booking_status', 'pending')->count();
});

$rejectBooking = action(function (string $id) {
    Bookings::where('id', $id)->update(['booking_status' => 'rejected']);
    $this->pendingApprovalsList = Bookings::with(['student.user', 'mentor.user', 'subject'])
        ->where('booking_status', 'pending')
        ->latest('created_at')
        ->get()
        ->map(fn($b) => [
            'initials' => strtoupper(substr($b->student->user->name ?? 'U', 0, 2)),
            'name'     => $b->student->user->name ?? 'Unknown Student',
            'type'     => 'Session Booking',
            'subject'  => $b->subject->code       ?? 'N/A',
            'mentor'   => $b->mentor->user->name  ?? 'Unknown Mentor',
            'date'     => \Carbon\Carbon::parse($b->date)->format('M j'),
        ])
        ->toArray();
    $this->pendingBookings = Bookings::where('booking_status', 'pending')->count();
});


?>
    <div> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; } /* font-family defined here; move to layouts.app once shared */
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-green);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            color: white;
            height: 100vh;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            position: relative;
            overflow: visible;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        /* ── Logo row ── */
        .sidebar-logo-container {
            height: var(--header-height);
            display: flex; align-items: center; justify-content: center;
            padding: 0 20px; gap: 12px; flex-shrink: 0; overflow: hidden;
            transition: padding 0.3s, justify-content 0.3s;
        }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }
        .logo-icon { flex-shrink: 0; font-size: 27px; width: auto; text-align: center; }
        .logo-text { font-size: 1.24rem; font-weight: 700; white-space: nowrap; overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }
        .sidebar.collapsed .sidebar-logo-container { justify-content: center; padding: 0; width: 100%; }
        .sidebar.collapsed .logo-content { gap: 0; justify-content: center; width: 100%; }

        /* ── Nav items ── */
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 16px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
            white-space: nowrap; position: relative; text-align: left;
            background: transparent; border: none; width: 100%;
            cursor: pointer; font-size: 0.95rem; justify-content: flex-start;
        }
        .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
        .nav-item span { overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }

        .sidebar.collapsed .nav-item { display: flex; align-items: center; justify-content: center; padding: 16px 0; width: 100%; gap: 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; text-align: center; flex-shrink: 0; font-size: 22px; }
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

        .nav-item::after {
            content: attr(data-tooltip);
            position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
            padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
            white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
            pointer-events: none; z-index: 100;
        }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

        .sidebar-footer { padding: 0; border-top: 1px solid rgba(255,255,255,0.1); }

        .sidebar-toggle-btn {
            position: absolute; right: -16px; top: 50%;
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--header-maroon); border: 2px solid white;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 13px; z-index: 50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25); transition: background 0.2s; flex-shrink: 0;
        }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 16px 32px; width: 100%; }

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .cal-header-day {
    font-size: 10px;
    font-weight: 800;
    text-align: center;
    color: #94a3b8;
    text-transform: uppercase;
    padding-bottom: 4px;
}
.cal-day {
    aspect-ratio: 1/1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.15s;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    width: 100%;
}
.cal-day:hover { background: #f1f5f9; color: #1e293b; }
.cal-today { background: #fee2e2 !important; color: #7b1d1d !important; font-weight: 800; }
.cal-selected { border: 2px solid #7b1d1d; background: #f8fafc; }

        .stats-overview-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb; width: 100%; }
        .stats-header { padding: 12px 24px; background: #f8fafc; font-weight: 700; font-size: 0.9rem; color: #1e293b; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
        .stats-body { display: grid; grid-template-columns: repeat(2, 1fr); background: white; width: 100%; }
        .stats-column { padding: 24px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; min-width: 0; }
        .stats-column:nth-child(2n) { border-right: none; }
        .stats-column-title { font-weight: 600; margin-bottom: 15px; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }

        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--sidebar-green); }
        .stat-card i { font-size: 24px; color: var(--sidebar-green); }

        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
        .pagination-btn:disabled {
    background-color: #f3f4f6; /* Light gray */
    color: #9ca3af;            /* Faded icon color */
    cursor: not-allowed;       /* Shows a "prohibited" cursor */
    border-color: #e5e7eb;
}

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .form-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
    .form-input:focus { border-color: var(--header-maroon); }
    .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    
    /* MAIN CONTENT SEARCH BAR */
.main-search-container {
    background: white;
    padding: 10px 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    border: 1px solid #eef2f3;
}

.main-search-input {
    width: 100%;
    padding: 9px 16px 9px 40px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.82rem;
    outline: none;
    transition: all 0.2s;
}

.main-search-wrapper {
    position: relative;
    flex: 1;
}

.main-search-wrapper i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.9rem;
}

.main-search-input:focus {
    border-color: var(--header-maroon);
    box-shadow: 0 0 0 3px rgba(123, 29, 29, 0.05);
}

.tooltip-wrap { position: relative; }
.tooltip-wrap .tooltip-text {
    visibility: hidden;
    opacity: 0;
    background: #1e293b;
    color: #fff;
    font-size: 11px;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 6px;
    position: absolute;
    bottom: calc(100% + 4px);
    left: 0;
    white-space: nowrap;
    z-index: 50;
    transition: opacity 0.1s ease;
    pointer-events: none;
}
.tooltip-wrap:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}
    </style>

    <div class="app-wrapper">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo-container">
                    <div class="logo-content">
                        <i class="fa-solid fa-graduation-cap logo-icon"></i>
                        <span class="logo-text">LRC PeerConnect</span>
                    </div>
                </div>

                <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                    <span class="toggle-icon">
                        <i class="fa-solid fa-chevron-right" id="toggleIcon"></i>
                    </span>
                </button>

            <nav class="flex-grow">
                <a href="{{ route('admin.dashboard') }}" class="nav-item active" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>                
                <a href="{{ route('admin.mentors') }}" class="nav-item" data-tooltip="Mentor Management">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
                </a>
                <a href="{{ route('admin.courses') }}" class="nav-item " data-tooltip="Course Management">
                    <i class="fa-solid fa-book-open w-5"></i><span>Course Management</span>
                </a>
                <a href="{{ route('admin.sessions') }}" class="nav-item" data-tooltip="Session Management">
                    <i class="fa-solid fa-calendar-days w-5"></i><span>Session Management</span>
                </a>
                <a href="{{ route('admin.feedbacks') }}" class="nav-item" data-tooltip="Student Feedback">
                    <i class="fa-solid fa-comments w-5"></i><span>Student Feedback</span>
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
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                <div class="flex items-center gap-2">
                <x-admin-notifications />
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name,0,2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"></i>
                </button>
                </div>

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
    <div class="main-search-container mb-8" style="position: relative; z-index: 10;" 
        x-data="{ 
            query: '',
            open: false,
            index: @js($this->searchIndex),
            
            get filteredResults() {
                const term = this.query.toLowerCase();
                const matches = this.index.filter(item => item.searchString.includes(term));

                const grouped = {};
                matches.forEach(m => {
                    if (!grouped[m.group]) grouped[m.group] = [];
                    grouped[m.group].push(m);
                });
                return grouped;
            }
        }" 
        @click.outside="open = false">
        
        <div class="main-search-wrapper flex-1" style="position: relative;">
            <i class="fa-solid fa-magnifying-glass"></i>
            
            <input type="text" x-model="query"
                @focus="open = true"
                @keydown.escape.window="open = false; query = ''"
                placeholder="Search mentors, courses, recent sessions, or feedbacks..."
                class="main-search-input" autocomplete="off">
        </div>

        {{-- Dropdown Results --}}
        <div x-show="open && query.length >= 1" x-cloak x-transition
            class="absolute left-0 right-0 bg-white rounded-xl shadow-xl border border-gray-100 overflow-y-auto"
            style="top: calc(100% + 6px); max-height: 420px; z-index: 20;">
            
            <template x-if="Object.keys(filteredResults).length === 0">
                <div style="padding:20px;text-align:center;font-size:13px;color:#9ca3af;font-style:italic;">
                    No matches found for "<strong x-text="query"></strong>"
                </div>
            </template>

            <template x-for="(items, group) in filteredResults" :key="group">
                <div>
                    <div x-text="group" style="padding:10px 14px;font-size:10px;font-weight:900;color:#000000;text-transform:uppercase;letter-spacing:.05em; background: #f0f0f0;"></div>
                    
                    <template x-for="item in items" :key="item.label + item.detail">
                        <a :href="item.url" class="block group" style="display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .15s; text-decoration:none;" onmouseover="this.style.background='#f4f5f7'" onmouseout="this.style.background='transparent'">
                            
                            {{-- Icon Badge --}}
                            <span :style="`font-size:11px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;flex-shrink:0;background:${item.bg};color:${item.color};`">
                                <i class="fa-solid" :class="item.icon"></i>
                            </span>
                            
                            {{-- Text Content --}}
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                <div style="font-size:11px;font-weight:500;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;" x-text="item.detail"></div>
                            </div>
                            
                            <i class="fa-solid fa-arrow-up-right-from-square opacity-0 group-hover:opacity-100 transition-opacity" style="font-size:10px;color:#cbd5e1;flex-shrink:0; transform: translateX(-5px); group-hover:transform: translateX(0);"></i>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

<div class="grid grid-cols-[repeat(autofit,_minmax(250px, 1fr))] sm:grid-cols-5 gap-4 mb-8">
  
  <a href="{{ route('admin.mentors') }}" wire:navigate
    class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl flex-shrink-0">
      <i class="fa-solid fa-users text-green-600"></i>
    </div>
    <div class="min-w-0 flex-1">
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Mentors</h3>
      <p class="text-2xl font-black truncate">{{ $totalMentors }}</p>
    </div>
  </a>

  <a href="{{ route('admin.sessions') }}" wire:navigate
    class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl flex-shrink-0">
      <i class="fa-solid fa-calendar-day text-blue-600"></i>
    </div>
    <div class="min-w-0 flex-1">
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Sessions Today</h3>
      <p class="text-2xl font-black truncate">{{ $sessionsToday }}</p>
    </div>
  </a>

  <a href="{{ route('admin.sessions') }}" wire:navigate
    class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl flex-shrink-0">
      <i class="fa-solid fa-clock text-yellow-500"></i>
    </div>
    <div class="min-w-0 flex-1">
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending</h3>
      <p class="text-2xl font-black truncate">{{ $pendingBookings }}</p>
    </div>
  </a>

  <a href="{{ route('admin.feedbacks') }}" wire:navigate
    class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-red-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl flex-shrink-0">
      <i class="fa-solid fa-star text-red-600"></i>
    </div>
    <div class="min-w-0 flex-1">
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Average Ratings</h3>
      <p class="text-2xl font-black truncate">{{ $this->dashboardStats['avg'] }}</p>
    </div>
  </a>

  <a href="{{ route('admin.mentors') }}" wire:navigate
    class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-pink-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
    <div class="text-2xl flex-shrink-0">
      <i class="fa-solid fa-user-graduate text-pink-600"></i>
    </div>
    <div class="min-w-0 flex-1">
      <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Mentees</h3>
      <p class="text-2xl font-black truncate">{{ $totalStudents }}</p>
    </div>
  </a>

</div>

                <div class="grid grid-cols-3 gap-8">
                    <div class="col-span-2 space-y-8">
                        <div wire:ignore class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col" id="section-schedule">                            
                            <div class="flex justify-between items-center mb-6">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-calendar-check"></i>
                                            <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
</div>    <p class="text-xs text-gray-400" id="tableSubtitle"></p>
</div>


<script>
function updateDate() {
    const today = new Date();

    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    };

    const formattedDate = today.toLocaleDateString('en-US', options);

    document.getElementById("tableSubtitle").textContent = formattedDate;
}

// run when page loads
updateDate();
</script>
                                <div class="flex gap-2">
                                    <div class="relative w-48">
                                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                        <input type="text" id="liveSearchInput" placeholder="Search names..." class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                                    </div>
                                    <select id="statusFilter" class="table-filter-select">
                                        <option value="All">All Status</option>
                                        <option value="Active">Active</option>
                                        <option value="Upcoming">Upcoming</option>
                                        <option value="Completed">Completed</option>
                                    </select>
                                </div>
                            </div>

<div>
                                <table class="w-full text-left text-sm table-fixed">
<thead class="text-gray-400 border-b">
    <tr>
        <th class="pb-3 text-[10px] tracking-wider" style="width:22%">
            <span class="flex items-center gap-1 font-semibold uppercase" style="color:#94a3b8;">Student</span>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:22%">
            <span class="flex items-center gap-1 font-semibold uppercase" style="color:#94a3b8;">Mentor</span>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:16%">
            <span class="flex items-center gap-1 font-semibold uppercase" style="color:#94a3b8;">Subject</span>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
            <span class="flex items-center gap-1 font-semibold uppercase" style="color:#94a3b8;">Time</span>
        </th>
        <th class="pb-3 text-[10px] tracking-wider" style="width:20%">
            <span class="flex items-center justify-center gap-1 font-semibold uppercase w-full" style="color:#94a3b8;">Status</span>
        </th>
    </tr>
</thead>
                                    <tbody id="tableBody"></tbody>
                                </table>
                            </div>

<div class="mt-2 pt-2 border-t border-gray-50 flex items-center justify-between">
                                    <div class="text-[11px] text-gray-400 font-medium" id="pageIndicator">Showing 0 results</div>
                                <div class="flex gap-2">
<button id="prevBtn" class="pagination-btn" disabled>
    <i class="fa-solid fa-chevron-left"></i>
</button>                                    <button id="nextBtn" class="pagination-btn"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                            </div>
                        </div>

<div wire:ignore class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-chart-line"></i>
            <span class="text-lg font-bold text-slate-800">LRC Performance Analytics</span>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-6">
                                <div class="stats-column"><div class="stats-column-title">Monthly Session Trends</div><div class="h-44"><canvas id="lineChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Top Mentors</div><div class="h-44"><canvas id="pieChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Most Active Colleges (CS, CSS, CAC)</div><div class="h-44"><canvas id="activeCollegeChart"></canvas></div></div>
                                <div class="stats-column"><div class="stats-column-title">Most Booked Subjects</div><div class="h-44"><canvas id="topSubjectsChart"></canvas></div>
</div>
                            </div>
                        </div>
                    </div>

 <div class="flex flex-col gap-6">

<div>

{{-- ── QUICK ACTIONS CARD ── --}}
<div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100" id="section-quickactions">
    <h3 class="font-bold mb-3 text-slate-800 text-sm tracking-tight">Quick Actions</h3>
    <div class="flex flex-col gap-2">
        <div class="grid grid-cols-2 gap-2">
            <button wire:click="openModal" @click="$wire.showModal = true"
                class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-user-plus text-[10px]"></i> Add Mentor
            </button>
            
            <button wire:click="openSubjectModal" @click="$wire.showSubjectModal = true"
                class="border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-book-open text-[10px]"></i> Add Subject
            </button>
        </div>
        <button onclick="openReportModal()"
            class="w-full border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-file-invoice text-[10px]"></i> Generate Report
        </button>
    </div>
</div>
{{-- ── GENERATE REPORT MODAL ── --}}
<div id="reportModal"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:24px;">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">

        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="fa-solid fa-file-arrow-down"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Generate Weekly Report</h2>
                <p class="text-xs text-slate-500 leading-snug" id="reportWeekLabel">Loading week range...</p>
            </div>
            <button onclick="document.getElementById('reportModal').style.display='none'"
                class="text-gray-400 hover:text-red-600 transition">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <div class="px-6 py-5">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-start gap-3">
                <i class="fa-solid fa-calendar-week text-slate-400 mt-0.5 text-sm flex-shrink-0"></i>
                <div>
                    <p class="text-xs font-bold text-slate-700 mb-0.5">Current Week</p>
                    <p class="text-xs text-slate-500" id="reportWeekDetail">—</p>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3 leading-relaxed">
                Exports all sessions for this week (Monday – Sunday) with student, mentor, subject, topic, date &amp; time, and session mode.
            </p>
        </div>

        <div class="px-6 py-5 bg-gray-50 border-t border-gray-100">
            <div class="flex gap-3">
                <button type="button"
                    onclick="document.getElementById('reportModal').style.display='none'"
                    class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="button" onclick="submitReport()"
                    class="flex-1 bg-red-900 text-white py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-red-800 transition">
                    <i class="fa-solid fa-download mr-1"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function getWeekRange() {
    const now = new Date();
    
    const mon = new Date(now);
    mon.setDate(now.getDate() - 6); 
    mon.setHours(0, 0, 0, 0);

    const end = new Date(now);
    end.setHours(23, 59, 59, 999);

    return { mon, sun: end };
}

function formatDisplay(d) {
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}

function toDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function openReportModal() {
    const { mon, sun } = getWeekRange();

    // Debug — remove after confirming it works
    console.log('mon:', mon.toString());
    console.log('sun:', sun.toString());

    const label = `${formatDisplay(mon)} – ${formatDisplay(sun)}`;
    document.getElementById('reportWeekLabel').textContent = label;
    document.getElementById('reportWeekDetail').textContent = label;
    document.getElementById('reportModal').style.display = 'flex';
}

function submitReport() {
    const { mon, sun } = getWeekRange();
    const fromStr = toDateStr(mon);
    const toStr   = toDateStr(sun);

    const allSessions  = @json($allSessions);
    const topMentors   = @json($this->topMentors);
    const topSubjects  = @json($this->topSubjects);
    const satisfaction = @json($this->satisfactionRate);
    const collegeData  = @json($this->collegeActivity);
    const monthlyData  = @json($this->monthlyTrends);

    const filtered = allSessions.filter(row => {
        const d = new Date(row.date + 'T00:00:00');
        return d >= mon && d <= sun;
    });

    const wb = XLSX.utils.book_new();

    const sessionHeader = ['Student', 'Mentor', 'Subject', 'Topic', 'Date & Time', 'Mode'];
    const sessionRows = filtered.length
        ? filtered.map(r => {
            const start = new Date(`1970-01-01T${r.start}`);
            const end   = new Date(`1970-01-01T${r.end}`);
            const fmt   = t => t.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const dateFormatted = new Date(r.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const mins  = Math.max(0, (end - start) / 60000);
            const hrs   = Math.floor(mins / 60);
            const rem   = Math.round(mins % 60);
            const dur   = hrs > 0 ? `${hrs} hr${hrs > 1 ? 's' : ''}` : `${rem} min`;
            const dateTime = `${dateFormatted}  ${fmt(start)} – ${fmt(end)}  (${dur})`;
            return [
                r.mentee  ?? 'Unknown',
                r.mentor  ?? 'Unknown',
                r.subject ?? 'N/A',
                r.topic   ?? '—',
                dateTime,
                r.mode    ?? 'One-on-One Tutorial',
            ];
        })
        : [['No sessions found for this week.', '', '', '', '', '']];

    const sessionSheet = XLSX.utils.aoa_to_sheet([sessionHeader, ...sessionRows]);
    sessionSheet['!cols'] = [
        { wch: 24 }, { wch: 24 }, { wch: 14 }, { wch: 24 }, { wch: 36 }, { wch: 28 },
    ];
    XLSX.utils.book_append_sheet(wb, sessionSheet, 'Weekly Sessions');

    const statusSummary = { completed: 0, accepted: 0, pending: 0, rejected: 0 };
    filtered.forEach(r => {
        const s = (r.status ?? '').toLowerCase();
        if (statusSummary[s] !== undefined) statusSummary[s]++;
    });

    let totalMins = 0;
    filtered.forEach(r => {
        const s = new Date(`1970-01-01T${r.start}`);
        const e = new Date(`1970-01-01T${r.end}`);
        totalMins += Math.max(0, (e - s) / 60000);
    });
    const totalH = Math.floor(totalMins / 60);
    const totalM = Math.round(totalMins % 60);

    const overviewRows = [
        ['LRC PEERCONNECT — WEEKLY SESSION REPORT'],
        [],
        ['Report Period', `${formatDisplay(mon)}  to  ${formatDisplay(sun)}`],
        ['Generated on',  new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })],
        [],
        ['SUMMARY'],
        ['Total Sessions',   filtered.length],
        ['Completed',        statusSummary.completed],
        ['Accepted',         statusSummary.accepted],
        ['Pending',          statusSummary.pending],
        ['Rejected',         statusSummary.rejected],
        ['Total Hours',      `${totalH}h ${totalM}m`],
        [],
        ['TOP MENTORS (ALL-TIME)'],
        ['Rank', 'Mentor', 'Sessions'],
        ...topMentors.map((m, i) => [i + 1, m.name, m.count]),
        [],
        ['TOP SUBJECTS (ALL-TIME)'],
        ['Rank', 'Subject', 'Bookings'],
        ...topSubjects.map((s, i) => [i + 1, s.name, s.count]),
        [],
        ['COLLEGE ACTIVITY'],
        ['College', 'Students'],
        ...Object.entries(collegeData).map(([c, n]) => [c, n]),
    ];
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(overviewRows), 'Overview');

    XLSX.writeFile(wb, `lrc-weekly-report-${fromStr}-to-${toStr}.xlsx`);
    document.getElementById('reportModal').style.display = 'none';
}
</script>

    {{-- ── ADD MENTOR MODAL ── --}}
    <div x-show="$wire.showModal" x-cloak class="modal-overlay" 
        x-data="{ fileName: '', isVerifying: false }" 
        x-init="$watch('$wire.showModal', val => { if (!val) { fileName = ''; document.getElementById('avatar-upload').value = ''; } })">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 border-b flex justify-between items-center flex-shrink-0 bg-white">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Register Mentor</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Add their email, assign their subjects, then set their availabilities.</p>
                </div>
                <button wire:click="closeModal" @click="$wire.showModal = false" class="text-gray-400 hover:text-red-600 transition disabled:cursor-not-allowed" x-bind:disabled="isVerifying">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-5 overflow-y-auto bg-white">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {{-- Step 1: Email --}}
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">1</span>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Student Email</h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div>
                                <input type="email" wire:model="up_mail" placeholder="student@up.edu.ph" class="form-input" wire:keydown.enter.prevent="checkEmail" />
                                @error('up_mail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @if($emailError) <p class="mt-1 text-xs text-red-600">{{ $emailError }}</p> @endif
                            </div>
                            <button type="button"
                                x-data="{ isVerifying: false }"
                                @click="isVerifying = true; $wire.checkEmail().finally(() => isVerifying = false)"
                                x-bind:disabled="isVerifying"
                                class="w-full px-4 py-2.5 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-black transition disabled:cursor-not-allowed">
                                <span x-show="!isVerifying">Find Email</span>
                                <span x-show="isVerifying" style="display: none;">
                                    <i class="fa-solid fa-spinner fa-spin mr-1"></i>Verifying...
                                </span>
                            </button>
                        </div>
                        @if($newMentor)
                            <div class="mt-3 flex items-center gap-3 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">{{ substr($newMentor['name'], 0, 1) }}</div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">{{ $newMentor['name'] }}</p>
                                    <p class="text-[10px] text-gray-500 truncate">{{ $newMentor['email'] }}</p>
                                </div>
                                <i class="fa-solid fa-circle-check text-green-500 ml-auto text-lg"></i>
                            </div>
                        @endif
                    </div>

                    {{-- Step 2: Profile Picture --}}
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">2</span>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Profile Picture</h3>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                @if($avatar)
                                    <img src="{{ $avatar->temporaryUrl() }}" class="w-32 h-32 rounded-xl object-cover border border-gray-200 shadow-sm">
                                @else
                                    <div class="w-32 h-32 rounded-xl bg-white border border-dashed border-gray-300 flex items-center justify-center text-gray-400 shadow-sm">
                                        <i class="fa-solid fa-image text-2xl" wire:loading.remove wire:target="avatar"></i>
                                        <i class="fa-solid fa-circle-notch fa-spin text-2xl text-slate-800" wire:loading wire:target="avatar"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 pt-1 flex flex-col justify-center h-32 min-w-0">
                                <input type="file" id="avatar-upload" wire:model="avatar" accept="image/*" class="hidden"
                                    @change="fileName = $event.target.files[0].name" />
                                <label for="avatar-upload" class="block w-full text-center py-2.5 px-4 rounded-lg text-xs font-bold bg-slate-800 text-white hover:bg-black cursor-pointer transition shadow-sm">
                                    <span wire:loading.remove wire:target="avatar">Choose File</span>
                                    <span wire:loading wire:target="avatar"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Uploading...</span>
                                </label>
                                <div class="mt-3 text-[10px] text-center w-full">
                                    <p x-show="fileName" class="text-slate-700 font-bold truncate px-2 block w-full" x-text="fileName"></p>
                                </div>
                                @error('avatar') <p class="mt-1 text-xs text-red-600 text-center">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Subjects --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">3</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Teachable Subjects</h3>
                    </div>
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="max-h-44 overflow-y-auto divide-y divide-gray-50 bg-white">
                            @forelse($this->allSubjects as $subject)
                                <div class="flex items-center px-4 hover:bg-gray-50" wire:key="sub-{{ $subject['id'] }}">
                                    <input type="checkbox" id="subject-{{ $subject['id'] }}" wire:model="selectedSubjects" value="{{ $subject['id'] }}"
                                        class="rounded border-gray-300 text-slate-800 focus:ring-slate-800 w-4 h-4 cursor-pointer flex-shrink-0" />
                                    <label for="subject-{{ $subject['id'] }}" class="flex items-center gap-3 ml-3 py-2.5 cursor-pointer flex-1">
                                        <span class="text-xs font-bold text-slate-700 w-16">{{ $subject['code'] }}</span>
                                        <span class="text-xs text-gray-400">{{ $subject['name'] }}</span>
                                    </label>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 text-center py-4">No subjects yet. Please add the subject first.</p>
                            @endforelse
                        </div>
                    </div>
                    @error('selectedSubjects') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Step 4: Availabilities --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">4</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Availability Schedule</h3>
                    </div>
                    <div x-data="{ avails: $wire.entangle('availabilities') }">
                        <div wire:ignore>
                            <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 px-1 mb-1">
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Day</label>
                                <label class="text-[10px] font-bold text-slate-500 uppercase">Start Time</label>
                                <label class="text-[10px] font-bold text-slate-500 uppercase">End Time</label>
                                <div class="w-8"></div>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(row, index) in avails" :key="row.id">
                                    <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center">
                                        <select x-model="row.day_of_week" class="form-input text-xs h-10 w-full">
                                            <option value="">- Day -</option>
                                            <option value="monday">Monday</option>
                                            <option value="tuesday">Tuesday</option>
                                            <option value="wednesday">Wednesday</option>
                                            <option value="thursday">Thursday</option>
                                            <option value="friday">Friday</option>
                                            <option value="saturday">Saturday</option>
                                        </select>
                                        <input type="time" x-model="row.start_time" class="form-input text-xs h-10 w-full" />
                                        <input type="time" x-model="row.end_time" class="form-input text-xs h-10 w-full" />
                                        <div class="flex items-center justify-center">
                                            <template x-if="avails.length > 1">
                                                <button type="button" @click="avails.splice(index, 1)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition">
                                                    <i class="fa-solid fa-xmark text-xs"></i>
                                                </button>
                                            </template>
                                            <template x-if="avails.length <= 1">
                                                <div class="w-8"></div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <button @click="avails.push({id: Date.now() + Math.random(), day_of_week: '', start_time: '', end_time: ''})" type="button"
                                class="mt-3 flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                                <i class="fa-solid fa-plus text-[10px]"></i> Add more days or time slots
                            </button>
                        </div>
                        @error('availabilities') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0">
                <div class="flex gap-3">
                    <button type="button" wire:click="closeModal" @click="$wire.showModal = false" x-bind:disabled="isVerifying"
                        class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition disabled:cursor-not-allowed">
                        Cancel
                    </button>
                    <button type="button" @click="isVerifying = true; $wire.confirmMentor().finally(() => isVerifying = false)"
                        x-bind:disabled="isVerifying"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition disabled:cursor-not-allowed">
                        <span x-show="!isVerifying">Register Mentor</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM ADD MENTOR ── --}}
    <div x-show="$wire.showConfirm" x-cloak class="modal-overlay flex items-center justify-center" style="z-index: 1100;" wire:click.self="$set('showConfirm', false)">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-8 text-center m-4">
            <div class="w-16 h-16 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-user-plus text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Mentor Registration</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will register the student as a peer mentor and will allow them access to the mentor module.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition disabled:cursor-not-allowed" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.saveMentor().finally(() => isSaving = false)"
                    class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition disabled:cursor-not-allowed" x-bind:disabled="isSaving"
                    >
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Add Subject Modal --}}
    <div x-show="$wire.showSubjectModal" x-cloak class="modal-overlay" x-data="{ isVerifying: false }">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-8 py-5 bg-white border-b flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Add New Subject</h2>
                    <p class="text-xs text-gray-400 mt-0.5">This subject will become available for mentor assignments.</p>
                </div>
                <button wire:click="closeSubjectModal" @click="$wire.showSubjectModal = false"  x-bind:disabled="isVerifying" class="text-gray-400 hover:text-red-600 transition disabled:cursor-not-allowed">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="px-8 py-4 space-y-4">
                <div>
                    <label class="form-label">Subject Code <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newSubjectCode" placeholder="e.g. Math 54" class="form-input" />
                    @error('newSubjectCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Subject Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newSubjectName" placeholder="e.g. Elementary Analysis II" class="form-input" />
                    @error('newSubjectName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="px-8 py-5 bg-white border-t">
                <div class="flex gap-3">
                    <button type="button" wire:click="closeSubjectModal" @click="$wire.showSubjectModal = false" x-bind:disabled="isVerifying"
                        class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition disabled:cursor-not-allowed">Cancel</button>
                    <button type="button" @click="isVerifying = true; $wire.confirmSubject().finally(() => isVerifying = false)" x-bind:disabled="isVerifying"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition disabled:cursor-not-allowed"
                        >
                        <span x-show="!isVerifying">Add Subject</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM ADD SUBJECT MODAL ── --}}
    <div x-show="$wire.showSubjectConfirm" x-cloak class="modal-overlay flex items-center justify-center" style="z-index: 1100;" wire:click.self="$set('showSubjectConfirm', false)">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-8 text-center m-4">
            <div class="w-16 h-16 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-book text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm New Subject</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will be added to the list of available subjects.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showSubjectConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition disabled:cursor-not-allowed" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.saveSubject().finally(() => isSaving = false)" x-bind:disabled="isSaving" 
                    class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition disabled:cursor-not-allowed">
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

</div>

<div wire:ignore class="bg-white rounded-xl shadow-sm border border-gray-100">
    
    {{-- Clock --}}
    <div class="bg-slate-900 rounded-t-xl px-4 py-3 flex items-center justify-between">
        <div id="liveDate" class="text-[10px] font-medium text-slate-400 uppercase tracking-widest"></div>
        <div id="liveClock" class="text-sm font-bold text-white tracking-widest" style="font-family:'Inter',sans-serif;font-variant-numeric:tabular-nums;"></div>
    </div>

                                {{-- Calendar --}}
                                <div class="p-4">
<div class="flex items-center justify-center gap-3 mb-4">

    <!-- Previous -->
    <button onclick="changeMonth(-1)"
        class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
        <i class="fa-solid fa-chevron-left text-[10px]"></i>
    </button>

    <!-- Month -->
    <span id="monthDisplay"
        class="text-sm font-bold text-slate-800 text-center min-w-[120px]">
    </span>

    <!-- Next -->
    <button onclick="changeMonth(1)"
        class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
    </button>

</div>

                                    <div class="grid grid-cols-7 gap-1 mb-1">
                                        <div class="cal-header-day">S</div>
                                        <div class="cal-header-day">M</div>
                                        <div class="cal-header-day">T</div>
                                        <div class="cal-header-day">W</div>
                                        <div class="cal-header-day">T</div>
                                        <div class="cal-header-day">F</div>
                                        <div class="cal-header-day">S</div>
                                    </div>
                                    <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
                                </div>
                            </div>
                        <div wire:ignore class="bg-white p-6 rounded-xl shadow-sm border border-gray-100" id="section-approvals">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Bookings</h3>
        <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">     
            {{ count($pendingApprovalsList) }} Pending
        </span>
    </div>

    <div class="flex flex-col gap-4">
        @forelse($pendingApprovalsList as $item)
            <div class="flex items-center gap-3">
                {{-- Avatar initials --}}
                <div class="w-8 h-8 rounded-full bg-amber-100 text-yellow-500 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                    {{ $item['initials'] }}
                </div>

                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold text-slate-700 truncate">{{ $item['name'] }}</p>
                    <p class="text-[9px] text-gray-400 font-medium truncate">
                        {{ $item['type'] }} &mdash; {{ $item['subject'] }} &mdash; {{ $item['date'] }}
                    </p>
                    <p class="text-[9px] text-gray-400 truncate">Mentor: {{ $item['mentor'] }}</p>
                </div>

                {{-- Pending badge only, no actions --}}
                <span class="text-yellow-600 text-[9px] font-bold bg-yellow-50 border border-yellow-200 px-2 py-0.5 rounded-full flex-shrink-0">
                    Pending
                </span>
            </div>
        @empty
            <div class="text-center py-6">
                <i class="fa-solid fa-circle-check text-green-400 text-2xl mb-2"></i>
                <p class="text-xs text-gray-400 font-medium-italic">No pending bookings right now.</p>
            </div>
        @endforelse
    </div>

    @if(count($pendingApprovalsList) > 0)
        <a href="{{ route('admin.sessions') }}"
            class="block w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">
            View All in Session Management →
        </a>
    @endif
</div>
</div>
 
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        const searchInput = document.getElementById('liveSearchInput');
        const statusFilter = document.getElementById('statusFilter');
        const charts = [];
        let currentPage = 1;

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        window.addEventListener('click', () => {
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        });

        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
            document.getElementById('liveDate').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);

        // Local State
        const allSessions = @json($todaySessions).map(s => ({
    ...s,
    color: {
        'Accepted':    'text-green-600',
        'Completed': 'text-green-600',
        'Pending':   'text-yellow-500',
        'Upcoming':  'text-orange-500',
    }[s.status] ?? 'text-red-800'
}));

const _now = new Date();
let selectedDateStr = `${_now.getFullYear()}-${String(_now.getMonth() + 1).padStart(2, '0')}-${String(_now.getDate()).padStart(2, '0')}`;
let viewDate = new Date(_now.getFullYear(), _now.getMonth(), 1);

        // Chart Configs
const monthlyData  = @json($this->monthlyTrends);
const topMentors   = @json($this->topMentors);
const satisfaction = @json($this->satisfactionRate);
const collegeData  = @json($this->collegeActivity);
const topSubjects = @json($this->topSubjects);

const linearOptions = {
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
        y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } }
    }
};

function initCharts() {
    charts.push(new Chart(document.getElementById('lineChart'), {
        type: 'line',
        data: {
            labels: monthlyData.map((_, i) => `W${i + 1}`),
            datasets: [{ data: monthlyData, borderColor: '#7b1d1d', tension: 0.4 }]
        },
        options: linearOptions
    }));

    charts.push(new Chart(document.getElementById('pieChart'), {
        type: 'pie',
        data: {
            labels: topMentors.map(m => m.name),
            datasets: [{
                data: topMentors.map(m => m.count),
                backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1']
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } }
        }
    }));

    charts.push(new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            labels: ['Excl', 'Good', 'Avg'],
            datasets: [{ data: satisfaction, backgroundColor: ['#1a3c2f', '#7b1d1d', '#cbd5e1'], borderWidth: 0 }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } } }
        }
    }));

    charts.push(new Chart(document.getElementById('topSubjectsChart'), {
    type: 'bar',
    data: {
        labels: topSubjects.map(s => s.name),
        datasets: [{
            label: 'Bookings',
            data: topSubjects.map(s => s.count),
            backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1', '#fef3c7'],
            borderRadius: 4,
            barThickness: 20
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 9 } } },
            y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } }
        }
    }
}));

    charts.push(new Chart(document.getElementById('activeCollegeChart'), {
        type: 'bar',
        data: {
            labels: Object.keys(collegeData),
            datasets: [{
                label: 'Active Students',
                data: Object.values(collegeData),
                backgroundColor: ['#94a3b8', '#1a3c2f', '#7b1d1d'],
                borderRadius: 4,
                barThickness: 20
            }]
        },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } },
                y: { grid: { display: false }, ticks: { font: { size: 9 } } }
            }
        }
    }));
}

        // Table Logic
function applyFilters() {
    const tbody = document.getElementById('tableBody');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const selectedStatus = statusFilter.value;

    const filtered = allSessions.filter(item => {
const matchesSearch = searchTerm
    ? item.mentor.toLowerCase().includes(searchTerm) ||
      item.mentee.toLowerCase().includes(searchTerm) ||
      item.subject.toLowerCase().includes(searchTerm)
    : true;
        const matchesStatus = selectedStatus === 'All' || item.status === selectedStatus;
        return matchesSearch && matchesStatus;
    });

    const perPage = 4;
    const totalPages = Math.ceil(filtered.length / perPage);
    if (currentPage > totalPages) currentPage = 1;

    const start = (currentPage - 1) * perPage;
    const paginated = filtered.slice(start, start + perPage);

    if (paginated.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-gray-400 italic">No matching sessions found.</td></tr>`;
    } else {
tbody.innerHTML = paginated.map(row => `
    <tr class="border-b last:border-0 hover:bg-slate-50 transition">
        <td class="py-3 max-w-0" style="width:22%;">
            <div class="tooltip-wrap">
                <div class="truncate text-xs font-bold text-slate-700">${row.mentee}</div>
                <span class="tooltip-text">${row.mentee}</span>
            </div>
        </td>
        <td class="py-3 max-w-0" style="width:22%;">
            <div class="tooltip-wrap">
                <div class="truncate text-xs text-slate-600">${row.mentor}</div>
                <span class="tooltip-text">${row.mentor}</span>
            </div>
        </td>
        <td class="py-3 text-xs text-slate-500" style="width:16%;">${row.subject}</td>
        <td class="py-3 text-xs text-slate-500" style="width:20%;">${row.time}</td>
        <td class="py-3 text-center" style="width:20%;">
            <span class="${row.color} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80">${row.status}</span>
        </td>
    </tr>
`).join('');
    }

    document.getElementById('pageIndicator').innerText = `Showing ${start + 1}–${Math.min(start + perPage, filtered.length)} of ${filtered.length} result${filtered.length !== 1 ? 's' : ''}`;

    document.getElementById('prevBtn').disabled = currentPage === 1;
document.getElementById('nextBtn').disabled = currentPage >= totalPages;
}
        // Calendar Logic
function renderCalendar() {
    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    const grid = document.getElementById('calendarGrid');
    const monthDisp = document.getElementById('monthDisplay');
    grid.innerHTML = '';
    monthDisp.innerText = viewDate.toLocaleString('default', { month: 'long', year: 'numeric' });

    const lastDay = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
    const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

    for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

    for (let i = 1; i <= lastDay; i++) {
        const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
        const isToday = dateStr === todayStr;
        const isSelected = dateStr === selectedDateStr;
        const dayEl = document.createElement('div');
        dayEl.className = `cal-day ${isToday ? 'cal-today' : ''} ${isSelected ? 'cal-selected' : ''}`;
        dayEl.innerHTML = `<span>${i}</span>`;
        dayEl.onclick = () => { selectedDateStr = dateStr; applyFilters(); renderCalendar(); };
        grid.appendChild(dayEl);
    }
}

        function changeMonth(dir) {
            viewDate.setMonth(viewDate.getMonth() + dir);
            renderCalendar();
        }

// Listeners
searchInput.addEventListener('input', applyFilters);
statusFilter.addEventListener('change', applyFilters);
document.getElementById('prevBtn').addEventListener('click', () => { currentPage--; applyFilters(); });
document.getElementById('nextBtn').addEventListener('click', () => { currentPage++; applyFilters(); });

document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('toggleIcon');
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
    setTimeout(() => { charts.forEach(c => c.resize()); }, 310);
});

        // Bootstrap
        initCharts();
        renderCalendar();
        applyFilters();
        updateClock();

        function updatePaginationUI() {
    const prevBtn = document.getElementById('prevBtn');
    
    if (currentPage === 1) {
        prevBtn.disabled = true;
    } else {
        prevBtn.disabled = false;
    }
    nextBtn.disabled = (currentPage === totalPages);
}
    </script>

<script>
    const mainSearch = document.getElementById('mainDashboardSearch');

    mainSearch.addEventListener('input', function () {
    });
</script>

<script>
(function () {
    const INDEX = [

        // ── STAT CARDS ──
        { group: 'Stats',    label: 'Total Mentors',           detail: '{{ $totalMentors }} mentors registered',         target: 'section-stats',     keywords: ['total','mentors','mentor','count','registered'] },
        { group: 'Stats',    label: 'Sessions Today',          detail: '{{ $sessionsToday }} sessions scheduled today',  target: 'section-stats',     keywords: ['sessions','today','scheduled','daily'] },
        { group: 'Stats',    label: 'Pending Bookings',        detail: '{{ $pendingBookings }} awaiting approval',        target: 'section-approvals', keywords: ['pending','bookings','booking','approval','awaiting'] },
        { group: 'Stats',    label: 'Average Rating',          detail: '4.9 overall satisfaction',                        target: 'section-analytics', keywords: ['rating','ratings','satisfaction','stars','average','4.9'] },
        { group: 'Stats',    label: 'Total Mentees',           detail: '{{ $totalStudents }} students enrolled',          target: 'section-stats',     keywords: ['mentees','students','student','enrolled','total'] },

        // ── TODAY'S SCHEDULE / SESSIONS ──
        { group: 'Sessions', label: 'Daniel Dyoco',            detail: 'Mentoring Frian Nabo · 09:00 AM · Completed',    target: 'section-schedule',  keywords: ['daniel','dyoco','frian','nabo','completed','09:00','09','am'] },
        { group: 'Sessions', label: 'Rhona Shayne Lopez',      detail: 'Mentoring Mark Tuan · 10:30 AM · Active',        target: 'section-schedule',  keywords: ['rhona','shayne','lopez','mark','tuan','active','10:30','10'] },
        { group: 'Sessions', label: 'Chezka Sinco',            detail: 'Mentoring Uno Dos Thirdy · 11:00 AM · Active',   target: 'section-schedule',  keywords: ['chezka','sinco','uno','dos','thirdy','active','11:00','11'] },
        { group: 'Sessions', label: 'Arielle Mae Solis',       detail: 'Mentoring Kevin Hart · 01:00 PM · Pending',      target: 'section-schedule',  keywords: ['arielle','mae','solis','kevin','hart','pending','1:00','01:00','pm'] },
        { group: 'Sessions', label: "Ax'l Conchada",           detail: 'Mentoring Alice Blue · 02:30 PM · Upcoming',     target: 'section-schedule',  keywords: ["ax'l",'axl','conchada','alice','blue','upcoming','2:30','02:30'] },

        // ── SESSION STATUSES (searchable words) ──
        { group: 'Sessions', label: 'Active Sessions',         detail: 'View all currently active sessions',              target: 'section-schedule',  keywords: ['active','ongoing','live'] },
        { group: 'Sessions', label: 'Completed Sessions',      detail: 'View all completed sessions',                    target: 'section-schedule',  keywords: ['completed','done','finished'] },
        { group: 'Sessions', label: 'Upcoming Sessions',       detail: 'View all upcoming sessions',                     target: 'section-schedule',  keywords: ['upcoming','future','scheduled'] },
        { group: 'Sessions', label: 'Pending Sessions',        detail: 'View sessions awaiting confirmation',             target: 'section-schedule',  keywords: ['pending','waiting','unconfirmed'] },


        // ── NAVIGATION ──
        { group: 'Navigation', label: 'Dashboard',             detail: 'Go to the main dashboard',                       target: 'section-stats',     keywords: ['dashboard','home','overview','main'] },
        { group: 'Navigation', label: 'Mentor Management',     detail: 'Manage and register mentors',                    target: 'section-stats',     keywords: ['mentor management','manage mentors','mentors page'] },
        { group: 'Navigation', label: 'Session Management',    detail: 'View and manage all sessions',                   target: 'section-schedule',  keywords: ['session management','sessions page','all sessions'] },
        { group: 'Navigation', label: 'Student Feedback',      detail: 'View student ratings and feedback',              target: 'section-analytics', keywords: ['feedback','ratings','student feedback','reviews'] },
    ];

    // ── BADGE STYLES ────────────────────────────────────────────────────────
    const BADGE = {
        'Stats':         { bg: '#f1f5f9', color: '#475569', text: 'Stat' },
        'Sessions':      { bg: '#dbeafe', color: '#1e40af', text: 'Session' },
        'Approvals':     { bg: '#fef3c7', color: '#92400e', text: 'Approval' },
        'Analytics':     { bg: '#d1fae5', color: '#065f46', text: 'Analytics' },
        'Quick Actions': { bg: '#ede9fe', color: '#5b21b6', text: 'Action' },
        'Navigation':    { bg: '#fee2e2', color: '#991b1b', text: 'Nav' },
    };

    // ── HIGHLIGHT ───────────────────────────────────────────────────────────
    function hl(text, q) {
        if (!q) return text;
        const re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return text.replace(re, '<mark style="background:#fef08a;border-radius:2px;padding:0 1px;color:inherit;">$1</mark>');
    }

    // ── SCROLL + HIGHLIGHT TARGET ────────────────────────────────────────────
    function scrollTo(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.transition = 'box-shadow 0.3s';
        el.style.boxShadow = '0 0 0 3px rgba(123,29,29,0.4)';
        setTimeout(() => { el.style.boxShadow = ''; }, 1800);
    }

    // ── RENDER ───────────────────────────────────────────────────────────────
    function render(q, matches) {
        const resultsEl = document.getElementById('globalSearchResults');

        if (!matches.length) {
            resultsEl.innerHTML = `<div style="padding:20px;text-align:center;font-size:13px;color:#9ca3af;font-style:italic;">No results for "<strong>${q}</strong>"</div>`;
            resultsEl.classList.remove('hidden');
            return;
        }

        // Group
        const groups = {};
        matches.forEach(m => {
            if (!groups[m.group]) groups[m.group] = [];
            groups[m.group].push(m);
        });

        let html = '';
        for (const [grp, items] of Object.entries(groups)) {
            const b = BADGE[grp] || BADGE['Stats'];
            html += `<div style="padding:8px 14px 4px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;">${grp}</div>`;
            items.forEach(item => {
                html += `
                <div class="global-result-item"
                    data-target="${item.target}"
                    style="display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .15s;">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;white-space:nowrap;flex-shrink:0;background:${b.bg};color:${b.color};">${b.text}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${hl(item.label, q)}</div>
                        <div style="font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${hl(item.detail, q)}</div>
                    </div>
                    <i class="fa-solid fa-arrow-right" style="font-size:10px;color:#cbd5e1;flex-shrink:0;"></i>
                </div>`;
            });
        }

        resultsEl.innerHTML = html;
        resultsEl.classList.remove('hidden');

        // Click handlers
        resultsEl.querySelectorAll('.global-result-item').forEach(el => {
            el.addEventListener('mouseenter', () => el.style.background = '#f8fafc');
            el.addEventListener('mouseleave', () => el.style.background = '');
            el.addEventListener('click', () => {
                scrollTo(el.dataset.target);
                resultsEl.classList.add('hidden');
                document.getElementById('mainDashboardSearch').value = '';
            });
        });
    }

    // ── SEARCH ───────────────────────────────────────────────────────────────
    document.getElementById('mainDashboardSearch').addEventListener('input', function () {
        const q = this.value.trim();
        const resultsEl = document.getElementById('globalSearchResults');

        if (!q) { resultsEl.classList.add('hidden'); return; }

        const lower = q.toLowerCase();
        const matches = INDEX.filter(item =>
            item.label.toLowerCase().includes(lower) ||
            item.detail.toLowerCase().includes(lower) ||
            item.keywords.some(k => k.includes(lower))
        );

        render(q, matches);
    });

    // ── CLOSE ON OUTSIDE CLICK ───────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.main-search-container')) {
            document.getElementById('globalSearchResults').classList.add('hidden');
        }
    });

    // ── KEYBOARD: ESCAPE TO CLOSE ────────────────────────────────────────────
    document.getElementById('mainDashboardSearch').addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.getElementById('globalSearchResults').classList.add('hidden');
            this.value = '';
        }
    });

})();

document.addEventListener('livewire:navigated', () => { 
    initCharts();
    renderCalendar();
    applyFilters();
});

document.addEventListener('livewire:initialized', () => {
    Livewire.on('mentor-saved', (event) => {
        initCharts(); 
    });
});

</script>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</div>
