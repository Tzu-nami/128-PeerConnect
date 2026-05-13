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
            'id'      => $b->id,
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
            'id'           => $b->id,
            'mentor_id'    => $b->mentor_id,
            'initials'     => strtoupper(substr($b->student->user->name ?? 'U', 0, 2)),
            'name'         => $b->student->user->name ?? 'Unknown Student',
            'email'        => $b->student->user->email ?? '',
            'type'         => 'Session Booking',
            'subject'      => $b->subject->code       ?? 'N/A',
            'subject_name' => $b->subject->name       ?? '',
            'mentor'       => $b->mentor->user->name  ?? 'Unknown Mentor',
            'date'         => \Carbon\Carbon::parse($b->date)->format('M j'),
            'date_full'    => \Carbon\Carbon::parse($b->date)->format('F j, Y'),
            'date_raw'     => \Carbon\Carbon::parse($b->date)->format('Y-m-d'),
            'time_start'   => \Carbon\Carbon::parse($b->schedule_start)->format('h:i A'),
            'time_end'     => \Carbon\Carbon::parse($b->schedule_end)->format('h:i A'),
            'start_24'     => \Carbon\Carbon::parse($b->schedule_start)->format('H:i'),
            'end_24'       => \Carbon\Carbon::parse($b->schedule_end)->format('H:i'),
            'topic'        => $b->topic ?? '—',
            'mode'         => $b->mode ?? 'One-on-One Tutorial',
            'notes'        => $b->notes ?? null,
            'created_at'   => $b->created_at->format('M j, Y g:i A'),
        ])
        ->toArray();

    $this->allSessions = Bookings::with(['mentor.user', 'student.user', 'subject'])
        ->orderBy('date')
        ->get()
        ->map(fn($b) => [
            'id'        => $b->id,
            'mentor_id' => $b->mentor_id,
            'date'      => \Carbon\Carbon::parse($b->date)->format('Y-m-d'),
            'mentor'    => $b->mentor->user->name  ?? 'Unknown',
            'mentee'    => $b->student->user->name ?? 'Unknown',
            'subject'   => $b->subject->code       ?? 'N/A',
            'topic'     => $b->topic               ?? '—',
            'time'      => Carbon::parse($b->schedule_start)->format('h:i A'),
            'status'    => $b->booking_status,
            'start'     => \Carbon\Carbon::parse($b->schedule_start)->format('Y-m-d\TH:i:s'),
            'end'       => \Carbon\Carbon::parse($b->schedule_end)->format('Y-m-d\TH:i:s'),
            'start_24'  => \Carbon\Carbon::parse($b->schedule_start)->format('H:i'),
            'end_24'    => \Carbon\Carbon::parse($b->schedule_end)->format('H:i'),
            'mode'      => $b->mode                ?? 'One-on-One Tutorial',
        ])
        ->toArray();
});

$searchIndex = computed(function () {
    $index = [];

    $mentors = \App\Models\User::with([
        'studentProfile.yearLevel',
        'studentProfile.degreeProgram'
    ])->where('user_roles', 'mentor')->get();

    foreach ($mentors as $m) {
        $year   = $m->studentProfile->yearLevel->name ?? '';
        $deprog = $m->studentProfile->degreeProgram->name ?? '';
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
    if (!$b->subject) continue;
    $mentorName  = $b->mentor?->user
        ? ($b->mentor->user->lastName . ', ' . $b->mentor->user->firstName)
        : 'Unknown Mentor';
    $sessionDate = \Carbon\Carbon::parse($b->date)->format('F j, Y');
    $subjectCode = $b->subject->code ?? 'N/A';
    $index[] = [
        'group' => 'Sessions',
        'label' => $b->topic ?: 'Tutorial Session',
        'detail' => $sessionDate . ' -- Subject: ' . $subjectCode . ' -- Mentor: ' . $mentorName . ' -- Status: ' . ucfirst($b->booking_status),
        'icon' => 'fa-calendar-days',
        'bg' => '#d1fae5', 'color' => '#065f46',
        'url' => route('admin.sessions'),
        'searchString' => strtolower($b->topic . ' ' . $mentorName . ' ' . $b->booking_status . ' ' . $subjectCode . ' ' . $sessionDate)
    ];
}

$feedbacks = \App\Models\Feedback::with(['booking.subject', 'booking.mentor.user'])->latest('id')->take(50)->get();
foreach ($feedbacks as $fb) {
    if (!$fb->booking) continue;
    $subjectCode = $fb->booking->subject->code ?? 'N/A';
    $mentorName  = $fb->booking->mentor?->user
        ? ($fb->booking->mentor->user->lastName . ', ' . $fb->booking->mentor->user->firstName)
        : 'Unknown Mentor';
        $comment     = $fb->feedback ?? 'No comment provided.';
        $date        = isset($fb->date_submitted) ? \Carbon\Carbon::parse($fb->date_submitted)->format('F j, Y') : '';
        $topic       = $fb->topic ?? 'Session Feedback';
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
    $weeks = [];
    for ($i = 3; $i >= 0; $i--) {
        $start = Carbon::now()->startOfWeek()->subWeeks($i);
        $end   = Carbon::now()->startOfWeek()->subWeeks($i)->endOfWeek();
        $count = Bookings::whereBetween('date', [$start, $end])->count();
        $weeks[] = $count;
    }
    return $weeks;
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
        $answered  = array_filter($questions, fn($q) => !is_null($q));
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
    $all           = \DB::table('feedback')->get();
    $totalSessions = \DB::table('bookings')->where('booking_status', 'completed')->count();

    if ($all->isEmpty()) {
        return ['avg' => '0.0', 'total' => 0, 'sessions' => number_format($totalSessions)];
    }

    $totalScores = [];
    foreach ($all as $fb) {
        $scores  = array_filter([$fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5, $fb->q6, $fb->q7, $fb->q8, $fb->q9], fn($v) => !is_null($v));
        if (count($scores) > 0) $totalScores[] = array_sum($scores) / count($scores);
    }

    return [
        'avg' => number_format(count($totalScores) > 0 ? array_sum($totalScores) / count($totalScores) : 0, 1),
    ];
});

$allSubjects = computed(function () {
    return Subjects::orderBy('code')->get()
        ->map(fn($subs) => ['id' => $subs->id, 'code' => $subs->code, 'name' => $subs->name])
        ->toArray();
});

$openModal = action(function () {
    $this->reset(['up_mail', 'newMentor', 'emailError', 'showConfirm', 'avatar']);
    $this->selectedSubjects = [];
    $this->availabilities   = [['id' => uniqid(), 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    $this->showModal        = true;
});

$closeModal = action(function () {
    $this->showModal    = false;
    $this->showConfirm  = false;
    $this->reset(['up_mail', 'newMentor', 'emailError', 'avatar']);
    $this->selectedSubjects = [];
    $this->availabilities   = [['id' => uniqid(), 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
});

$openSubjectModal = action(function () {
    $this->reset(['newSubjectCode', 'newSubjectName', 'showSubjectConfirm']);
    $this->showSubjectModal = true;
});

$closeSubjectModal = action(function () {
    $this->showSubjectModal    = false;
    $this->showSubjectConfirm  = false;
    $this->reset(['newSubjectCode', 'newSubjectName']);
});

$checkEmail = action(function () {
    $this->emailError = '';
    $this->newMentor  = null;

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
        'id'    => $userEmail->id,
        'name'  => $userEmail->name,
        'email' => $userEmail->email,
    ];
});

$toggleAvailabilityOn = action(function () {
    $this->availabilities[] = ['id' => uniqid(), 'day_of_week' => '', 'start_time' => '', 'end_time' => ''];
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

    $this->availabilities = collect($this->availabilities)->map(function ($row) {
        foreach (['start_time', 'end_time'] as $field) {
            $val = trim($row[$field] ?? '');
            if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $val, $m)) {
                $h = (int)$m[1];
                $min = $m[2];
                $meridiem = strtoupper($m[3]);
                if ($meridiem === 'PM' && $h !== 12) $h += 12;
                if ($meridiem === 'AM' && $h === 12) $h = 0;
                $row[$field] = sprintf('%02d:%02d', $h, (int)$min);
            }
        }
        return $row;
    })->toArray();

    $this->validate([
        'avatar'                          => ['required', 'image', 'max:2048'],
        'selectedSubjects'                => ['required', 'array', 'min:1'],
        'selectedSubjects.*'              => ['exists:subjects,id'],
        'availabilities'                  => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week'    => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time'     => ['required', 'date_format:H:i'],
        'availabilities.*.end_time'       => ['required', 'date_format:H:i'],
    ], [], [
        'avatar'            => 'profile picture',
        'selectedSubjects'  => 'subjects',
        'availabilities'    => 'availabilities',
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
            'mentor_id'   => $mentorProf->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time'  => $sched['start_time'],
            'end_time'    => $sched['end_time'],
        ]);
    }

    $this->showModal   = false;
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
    $this->showSubjectModal   = false;
    $this->showSubjectConfirm = false;
    session()->flash('successMessage', "{$this->newSubjectCode} has been added.");
    $this->redirect(route('admin.courses'), navigate: true);
});

$reloadPendingList = action(function () {
    $this->pendingApprovalsList = Bookings::with(['student.user', 'mentor.user', 'subject'])
        ->where('booking_status', 'pending')
        ->latest('created_at')
        ->get()
        ->map(fn($b) => [
            'id'           => $b->id,
            'mentor_id'    => $b->mentor_id,
            'initials'     => strtoupper(substr($b->student->user->name ?? 'U', 0, 2)),
            'name'         => $b->student->user->name ?? 'Unknown Student',
            'email'        => $b->student->user->email ?? '',
            'type'         => 'Session Booking',
            'subject'      => $b->subject->code       ?? 'N/A',
            'subject_name' => $b->subject->name       ?? '',
            'mentor'       => $b->mentor->user->name  ?? 'Unknown Mentor',
            'date'         => \Carbon\Carbon::parse($b->date)->format('M j'),
            'date_full'    => \Carbon\Carbon::parse($b->date)->format('F j, Y'),
            'date_raw'     => \Carbon\Carbon::parse($b->date)->format('Y-m-d'),
            'time_start'   => \Carbon\Carbon::parse($b->schedule_start)->format('h:i A'),
            'time_end'     => \Carbon\Carbon::parse($b->schedule_end)->format('h:i A'),
            'start_24'     => \Carbon\Carbon::parse($b->schedule_start)->format('H:i'),
            'end_24'       => \Carbon\Carbon::parse($b->schedule_end)->format('H:i'),
            'topic'        => $b->topic ?? '—',
            'mode'         => $b->mode ?? 'One-on-One Tutorial',
            'notes'        => $b->notes ?? null,
            'created_at'   => $b->created_at->format('M j, Y g:i A'),
        ])
        ->toArray();

    $this->allSessions = Bookings::with(['mentor.user', 'student.user', 'subject'])
        ->orderBy('date')
        ->get()
        ->map(fn($b) => [
            'id'        => $b->id,
            'mentor_id' => $b->mentor_id,
            'date'      => \Carbon\Carbon::parse($b->date)->format('Y-m-d'),
            'mentor'    => $b->mentor->user->name  ?? 'Unknown',
            'mentee'    => $b->student->user->name ?? 'Unknown',
            'subject'   => $b->subject->code       ?? 'N/A',
            'topic'     => $b->topic               ?? '—',
            'time'      => Carbon::parse($b->schedule_start)->format('h:i A'),
            'status'    => $b->booking_status,
            'start'     => \Carbon\Carbon::parse($b->schedule_start)->format('Y-m-d\TH:i:s'),
            'end'       => \Carbon\Carbon::parse($b->schedule_end)->format('Y-m-d\TH:i:s'),
            'start_24'  => \Carbon\Carbon::parse($b->schedule_start)->format('H:i'),
            'end_24'    => \Carbon\Carbon::parse($b->schedule_end)->format('H:i'),
            'mode'      => $b->mode                ?? 'One-on-One Tutorial',
        ])
        ->toArray();

    $this->pendingBookings = Bookings::where('booking_status', 'pending')->count();
});

$acceptBooking = action(function (string $id) {
    $booking = Bookings::with(['mentor.user', 'subject', 'student.user'])->findOrFail($id);

    $conflict = Bookings::where('mentor_id', $booking->mentor_id)
        ->where('id', '!=', $booking->id)
        ->where('booking_status', 'accepted')
        ->whereDate('date', $booking->date)
        ->where(function ($q) use ($booking) {
            $q->where('schedule_start', '<', $booking->schedule_end)
              ->where('schedule_end',   '>', $booking->schedule_start);
        })
        ->with(['student.user', 'subject'])
        ->first();

    if ($conflict) {
        $this->dispatch('booking-conflict-detected', [
            'pendingId'       => $id,
            'conflictStudent' => $conflict->student->user->name ?? 'Unknown Student',
            'conflictSubject' => $conflict->subject->code ?? 'N/A',
            'conflictStart'   => \Carbon\Carbon::parse($conflict->schedule_start)->format('h:i A'),
            'conflictEnd'     => \Carbon\Carbon::parse($conflict->schedule_end)->format('h:i A'),
            'conflictDate'    => \Carbon\Carbon::parse($conflict->date)->format('F j, Y'),
            'mentorName'      => $booking->mentor->user->name ?? 'Unknown Mentor',
        ]);
        return;
    }

    Bookings::where('id', $id)->update(['booking_status' => 'accepted']);

    $autoRejected = Bookings::where('mentor_id', $booking->mentor_id)
        ->where('id', '!=', $id)
        ->where('booking_status', 'pending')
        ->whereDate('date', $booking->date)
        ->where(function ($q) use ($booking) {
            $q->where('schedule_start', '<', $booking->schedule_end)
              ->where('schedule_end',   '>', $booking->schedule_start);
        })
        ->get();

    if ($autoRejected->isNotEmpty()) {
        Bookings::whereIn('id', $autoRejected->pluck('id'))
            ->update(['booking_status' => 'rejected']);

        $this->dispatch('bookings-auto-rejected', [
            'count' => $autoRejected->count(),
        ]);
    }

    $this->reloadPendingList();
});

$rejectBooking = action(function (string $id) {
    Bookings::where('id', $id)->update(['booking_status' => 'rejected']);
    $this->reloadPendingList();
});
?>

<div>

    {{-- ═══════════════════════════════════════════════════════════════════
         GLOBAL SEARCH BAR
    ════════════════════════════════════════════════════════════════════════ --}}
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 relative"
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

        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input type="text" x-model="query"
                @focus="open = true"
                @keydown.escape.window="open = false; query = ''"
                placeholder="Search mentors, courses, recent sessions, or feedbacks..."
                class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700
                          placeholder-gray-400 border border-gray-200 rounded-lg bg-white
                          outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon
                          h-[34px] transition-shadow" autocomplete="off">
        </div>

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
                    <div x-text="group" style="padding:10px 14px;font-size:10px;font-weight:900;color:#000000;text-transform:uppercase;letter-spacing:.05em;background:#f0f0f0;"></div>
                    <template x-for="item in items" :key="item.label + item.detail">
                        <a :href="item.url" class="block group"
                            style="display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .15s;text-decoration:none;"
                            onmouseover="this.style.background='#f4f5f7'" onmouseout="this.style.background='transparent'">
                            <span :style="`font-size:11px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;flex-shrink:0;background:${item.bg};color:${item.color};`">
                                <i class="fa-solid" :class="item.icon"></i>
                            </span>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.label"></div>
                                <div style="font-size:11px;font-weight:500;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;" x-text="item.detail"></div>
                            </div>
                            <i class="fa-solid fa-arrow-up-right-from-square opacity-0 group-hover:opacity-100 transition-opacity" style="font-size:10px;color:#cbd5e1;flex-shrink:0;"></i>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- STAT CARDS--}}
    <div class="grid grid-cols-[repeat(autofit,_minmax(250px,_1fr))] sm:grid-cols-5 gap-4 mb-8">
        <a href="{{ route('admin.mentors') }}" wire:navigate
            class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-users text-green-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Mentors</h3>
                <p class="text-2xl font-black truncate">{{ $totalMentors }}</p>
            </div>
        </a>

        <a href="{{ route('admin.sessions') }}" wire:navigate
            class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-calendar-day text-blue-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Sessions Today</h3>
                <p class="text-2xl font-black truncate">{{ $sessionsToday }}</p>
            </div>
        </a>

        <a href="{{ route('admin.sessions') }}" wire:navigate
            class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-clock text-yellow-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending</h3>
                <p class="text-2xl font-black truncate">{{ $pendingBookings }}</p>
            </div>
        </a>

        <a href="{{ route('admin.feedbacks') }}" wire:navigate
            class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-red-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-star text-red-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Average Ratings</h3>
                <p class="text-2xl font-black truncate">{{ $this->dashboardStats['avg'] }}</p>
            </div>
        </a>

        <a href="{{ route('admin.mentors') }}" wire:navigate
            class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-pink-600 flex items-center gap-4 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-user-graduate text-pink-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Mentees</h3>
                <p class="text-2xl font-black truncate">{{ $totalStudents }}</p>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-3 gap-8">

        {{-- LEFT / MAIN COLUMN ──────────────────────────────────────────── --}}
        <div class="col-span-2 space-y-8">

            {{-- Today's Schedule table --}}
            <div wire:ignore class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col" id="section-schedule">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-calendar-check"></i>
                            <h2 class="text-lg font-bold text-slate-800" id="tableTitle">Today's Schedule</h2>
                        </div>
                        <p class="text-xs text-gray-400" id="tableSubtitle"></p>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative w-48">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" id="liveSearchInput" placeholder="Search..."
                                class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                        </div>
                        <select id="statusFilter" class="table-filter-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                            <option value="no_show">No Show</option>
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
                                <th class="pb-3 text-[10px] tracking-wider pl-4" style="width:20%">
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
                        </button>
                        <button id="nextBtn" class="pagination-btn">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Analytics --}}
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
                    <div class="stats-column"><div class="stats-column-title">Most Booked Subjects</div><div class="h-44"><canvas id="topSubjectsChart"></canvas></div></div>
                </div>
            </div>
        </div>

        {{-- RIGHT SIDEBAR COLUMN ────────────────────────────────────────── --}}
        <div class="flex flex-col gap-6">

            {{-- ── QUICK ACTIONS CARD ── --}}
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100" id="section-quickactions">
                <h3 class="font-bold mb-3 text-slate-800 text-sm tracking-tight">Quick Actions</h3>

                <div class="flex flex-col gap-2"
                    x-data="{
                        mentorOpening: false,
                        subjectOpening: false
                    }">

                    <div class="grid grid-cols-2 gap-2">

                        {{-- Add Mentor --}}
<button
    type="button"
    onclick="window.location.href='{{ route('admin.mentors', ['open' => 'mentor']) }}'"
    class="w-full border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold
           hover:bg-gray-50 transition flex items-center justify-center gap-1.5">

    <span class="flex items-center gap-1.5">
        <i class="fa-solid fa-user-plus text-[10px]"></i>
        Add Mentor
    </span>
</button>

                        {{-- Add Subject --}}
<button
    type="button"
    onclick="window.location.href='{{ route('admin.mentors', ['open' => 'subject']) }}'"
    class="w-full border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold
           hover:bg-gray-50 transition flex items-center justify-center gap-1.5">

    <span class="flex items-center gap-1.5">
        <i class="fa-solid fa-book-open text-[10px]"></i>
        Add Subject
    </span>
</button>

                    </div>

                    <button onclick="openReportModal()"
                        class="w-full border border-slate-300 p-2.5 rounded-lg text-[11px] font-bold
                               hover:bg-gray-50 transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-file-invoice text-[10px]"></i>
                        Generate Report
                    </button>
                </div>
            </div>

            {{-- ── CALENDAR + CLOCK ── --}}
            <div wire:ignore class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="bg-slate-900 rounded-t-xl px-4 py-3 flex items-center justify-between">
                    <div id="liveDate" class="text-[10px] font-medium text-slate-400 uppercase tracking-widest"></div>
                    <div id="liveClock" class="text-sm font-bold text-white tracking-widest" style="font-family:'Inter',sans-serif;font-variant-numeric:tabular-nums;"></div>
                </div>
                <div class="p-4">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <button onclick="changeMonth(-1)"
                            class="w-6 h-6 flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-400 hover:text-slate-700 transition">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>
                        <span id="monthDisplay" class="text-sm font-bold text-slate-800 text-center min-w-[120px]"></span>
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

            {{-- ── PENDING REQUESTS ── --}}
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100" id="section-approvals"
                x-data="{
                    processingId: null,
                    doneIds: {},
                    modalOpen: false,
                    selected: null,
                    openDetail(item) {
                        this.selected = item;
                        this.modalOpen = true;
                    }
                }">

                {{-- Detail Modal --}}
                <div x-show="modalOpen" x-cloak x-transition
                    class="fixed inset-0 z-[1200] flex items-center justify-center p-6"
                    style="background:rgba(0,0,0,0.45);"
                    @click.self="modalOpen = false">
                    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" @click.stop>
                        <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
                            <div class="w-11 h-11 rounded-full bg-amber-100 text-yellow-600 flex items-center justify-center text-sm font-bold flex-shrink-0"
                                x-text="selected?.initials"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-black text-slate-800 truncate" x-text="selected?.name"></p>
                                <p class="text-[11px] text-gray-400 truncate" x-text="selected?.email"></p>
                            </div>
                            <button @click="modalOpen = false" class="text-gray-400 hover:text-red-600 transition">
                                <i class="fa-solid fa-xmark text-lg"></i>
                            </button>
                        </div>
                        <div class="px-6 py-5 space-y-4">
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex items-start gap-3">
                                <i class="fa-solid fa-calendar-days text-slate-400 mt-0.5 text-sm flex-shrink-0"></i>
                                <div>
                                    <p class="text-xs font-bold text-slate-700 mb-0.5" x-text="selected?.date_full"></p>
                                    <p class="text-xs text-slate-500" x-text="(selected?.time_start ?? '') + ' – ' + (selected?.time_end ?? '')"></p>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100 text-xs">
                                <div class="flex justify-between py-2.5">
                                    <span class="text-gray-400 font-medium">Subject</span>
                                    <span class="text-slate-700 font-bold text-right">
                                        <span x-text="selected?.subject"></span>
                                        <span class="text-gray-400 font-normal ml-1" x-text="selected?.subject_name ? '(' + selected.subject_name + ')' : ''"></span>
                                    </span>
                                </div>
                                <div class="flex justify-between py-2.5">
                                    <span class="text-gray-400 font-medium">Mentor</span>
                                    <span class="text-slate-700 font-bold" x-text="selected?.mentor"></span>
                                </div>
                                <div class="flex justify-between py-2.5">
                                    <span class="text-gray-400 font-medium">Mode</span>
                                    <span class="text-slate-700 font-bold" x-text="selected?.mode"></span>
                                </div>
                                <div class="flex justify-between py-2.5">
                                    <span class="text-gray-400 font-medium">Topic</span>
                                    <span class="text-slate-700 font-bold text-right max-w-[60%]" x-text="selected?.topic"></span>
                                </div>
                                <template x-if="selected?.notes">
                                    <div class="py-2.5">
                                        <p class="text-gray-400 font-medium mb-1.5">Notes</p>
                                        <p class="text-slate-600 leading-relaxed" x-text="selected?.notes"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex gap-3"
                            x-show="selected && !doneIds[selected?.id]">
                            <button
                                @click="
                                    if (!selected) return;
                                    const id = selected.id;
                                    processingId = id;
                                    modalOpen = false;
                                    $wire.rejectBooking(id).then(() => {
                                        doneIds[id] = 'rejected';
                                        processingId = null;
                                    })
                                "
                                class="flex-1 py-2.5 text-xs font-bold text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 rounded-xl transition">
                                <i class="fa-solid fa-xmark mr-1"></i> Reject
                            </button>
                            <button
                                @click="
                                    if (!selected) return;
                                    const id = selected.id;
                                    processingId = id;
                                    modalOpen = false;
                                    $wire.acceptBooking(id).then(() => {
                                        processingId = null;
                                    })
                                "
                                class="flex-1 py-2.5 text-xs font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl transition shadow-sm">
                                <i class="fa-solid fa-check mr-1"></i> Accept
                            </button>
                        </div>
                        <div x-show="selected && doneIds[selected?.id]"
                            class="px-6 py-4 bg-gray-50 border-t border-gray-100 text-center text-xs font-bold"
                            :class="doneIds[selected?.id] === 'accepted' ? 'text-green-600' : 'text-red-500'"
                            x-text="doneIds[selected?.id] === 'accepted' ? 'Booking accepted ✓' : 'Booking rejected ✗'">
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-bold text-slate-800 text-sm tracking-tight">Pending Requests</h3>
                    <span id="pendingBadge"
                        class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                        {{ count($pendingApprovalsList) }} {{ count($pendingApprovalsList) !== 1 ? 'Requests' : 'Request' }}
                    </span>
                </div>

                <div id="adminPendingBannerArea" class="flex flex-col gap-2"></div>

                <div id="adminPendingList" class="flex flex-col gap-0">
                    @forelse($pendingApprovalsList as $item)
                        <div class="session-row flex items-center gap-3 rounded-xl px-2 py-1.5 transition-all duration-200 hover:bg-slate-50 cursor-pointer group"
                            wire:key="pending-{{ $item['id'] }}"
                            @click="openDetail({{ json_encode($item) }})">
                            <div class="w-7 h-7 rounded-full bg-amber-100 text-yellow-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                                {{ $item['initials'] }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-bold text-slate-700 truncate group-hover:text-slate-900 transition-colors">{{ $item['name'] }}</p>
                                <p class="text-[9px] text-gray-400 font-medium truncate">
                                    {{ $item['subject'] }} &mdash; {{ $item['mentor'] }}
                                </p>
                                <p class="text-[9px] text-blue-500 font-semibold mt-0.5 truncate">
                                    <i class="fa-regular fa-clock text-[8px] mr-0.5"></i>
                                    {{ $item['date_full'] }}, {{ $item['time_start'] }} – {{ $item['time_end'] }}
                                </p>
                            </div>
                            <div class="relative flex items-center justify-end" style="min-height:28px;" @click.stop>
                                <div class="action-idle absolute right-0 flex items-center gap-1 pointer-events-none">
                                    <span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span>
                                </div>
                                <template x-if="processingId === '{{ $item['id'] }}' && !doneIds['{{ $item['id'] }}']">
                                    <div class="w-16 flex items-center justify-center">
                                        <i class="fa-solid fa-spinner fa-spin text-slate-400 text-xs"></i>
                                    </div>
                                </template>
                                <template x-if="doneIds['{{ $item['id'] }}'] === 'accepted'">
                                    <span class="text-green-600 text-[9px] font-bold bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">Accepted ✓</span>
                                </template>
                                <template x-if="doneIds['{{ $item['id'] }}'] === 'rejected'">
                                    <span class="text-red-500 text-[9px] font-bold bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">Rejected ✗</span>
                                </template>
                                <template x-if="processingId !== '{{ $item['id'] }}' && !doneIds['{{ $item['id'] }}']">
                                    <div class="action-buttons flex items-center gap-1 justify-end">
                                        <div class="hover-tooltip" data-full="Accept">
                                            <button title="Accept"
                                                @click="
                                                    processingId = '{{ $item['id'] }}';
                                                    $wire.acceptBooking('{{ $item['id'] }}').then(() => {
                                                        processingId = null;
                                                    })
                                                "
                                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-700 transition-all hover:scale-110 hover:shadow-sm"
                                                style="flex-shrink:0;">
                                                <i class="fa-solid fa-check" style="font-size:11px;"></i>
                                            </button>
                                        </div>
                                        <div class="hover-tooltip" data-full="Reject">
                                            <button title="Reject"
                                                @click="
                                                    processingId = '{{ $item['id'] }}';
                                                    $wire.rejectBooking('{{ $item['id'] }}').then(() => {
                                                        doneIds['{{ $item['id'] }}'] = 'rejected';
                                                        processingId = null;
                                                    })
                                                "
                                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-100 hover:bg-red-200 text-red-600 transition-all hover:scale-110 hover:shadow-sm"
                                                style="flex-shrink:0;">
                                                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @empty
                        <div class="py-4">
                            <p class="text-xs text-gray-400 italic">No pending requests.</p>
                        </div>
                    @endforelse
                </div>

                @if(count($pendingApprovalsList) > 5)
                    <button id="toggleRequestsBtn"
                        class="w-full mt-4 py-2 text-[10px] font-bold text-slate-400 hover:text-slate-600 border-t border-gray-50 transition text-center">
                        View All Requests
                    </button>
                @endif
            </div>

        </div>{{-- end right column --}}
    </div>{{-- end grid --}}


    {{-- ── GENERATE REPORT MODAL ────────────────────────────────────────── --}}
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


    {{-- ── CONFLICT WARNING MODAL ───────────────────────────────────────── --}}
    <div id="conflictModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1200; align-items:center; justify-content:center; padding:24px;">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-extrabold text-slate-800">Schedule Conflict</h2>
                    <p class="text-xs text-slate-500 leading-snug mt-0.5">This booking cannot be accepted.</p>
                </div>
                <button onclick="closeConflictModal()" class="text-gray-400 hover:text-red-600 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <p class="text-xs text-slate-600 leading-relaxed">
                    <strong id="conflictMentorName" class="text-slate-800"></strong> already has an accepted session that overlaps with this booking:
                </p>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500 font-medium">Student</span>
                        <span class="text-slate-700 font-bold" id="conflictStudentName"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 font-medium">Subject</span>
                        <span class="text-slate-700 font-bold" id="conflictSubject"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 font-medium">Date</span>
                        <span class="text-slate-700 font-bold" id="conflictDate"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 font-medium">Time</span>
                        <span class="text-slate-700 font-bold" id="conflictTime"></span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    To resolve this conflict, cancel or reject the existing accepted session first, then try accepting this booking again.
                </p>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button onclick="closeConflictModal()"
                    class="w-full py-2.5 text-sm font-bold text-white bg-red-900 hover:bg-red-800 rounded-xl transition">
                    Got it
                </button>
            </div>
        </div>
    </div>


    {{-- ── AUTO-REJECT NOTIFICATION MODAL ──────────────────────────────── --}}
    <div id="autoRejectModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); z-index:1200; align-items:center; justify-content:center; padding:24px;">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center gap-4 px-6 py-5 border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-circle-info"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-lg font-extrabold text-slate-800">Booking Accepted</h2>
                    <p class="text-xs text-slate-500 leading-snug mt-0.5">Overlapping requests were handled.</p>
                </div>
            </div>
            <div class="px-6 py-5">
                <p class="text-xs text-slate-600 leading-relaxed" id="autoRejectBody"></p>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <button onclick="closeAutoRejectModal()"
                    class="w-full py-2.5 text-sm font-bold text-slate-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>

        function normalizeTime(val) {
            if (!val) return '';
            val = val.trim();
            const plain = val.match(/^(\d{1,2}):(\d{2})$/);
            if (plain) {
                return String(parseInt(plain[1], 10)).padStart(2, '0') + ':' + plain[2];
            }
            const ampm = val.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
            if (ampm) {
                let h = parseInt(ampm[1], 10);
                const m = ampm[2];
                const meridiem = ampm[3].toUpperCase();
                if (meridiem === 'PM' && h !== 12) h += 12;
                if (meridiem === 'AM' && h === 12) h = 0;
                return String(h).padStart(2, '0') + ':' + m;
            }
            return val;
        }

        // ── STATUS HELPERS ──────────────────────────────────────────────────────
        function getStatusColor(status) {
            const map = {
                pending:   'text-yellow-500',
                accepted:  'text-green-600',
                active:    'text-green-600',
                upcoming:  'text-orange-500',
                completed: 'text-gray-500',
                rejected:  'text-red-500',
                cancelled: 'text-red-600',
                closed:    'text-purple-700',
                no_show:   'text-orange-600',
            };
            return map[status] ?? 'text-slate-400';
        }

        function getStatusLabel(status) {
            const map = {
                no_show:   'No Show',
                accepted:  'Accepted',
                completed: 'Completed',
                closed:    'Closed',
                rejected:  'Rejected',
                cancelled: 'Cancelled',
                pending:   'Pending',
            };
            return map[status] ?? (status ? status.charAt(0).toUpperCase() + status.slice(1) : '—');
        }

        function timeToMinutes(t) {
            if (!t) return 0;
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        }

        function sessionsOverlap(a, b) {
            return timeToMinutes(a.start_24) < timeToMinutes(b.end_24)
                && timeToMinutes(a.end_24)   > timeToMinutes(b.start_24);
        }

        function openConflictModal(data) {
            document.getElementById('conflictMentorName').textContent  = data.mentorName;
            document.getElementById('conflictStudentName').textContent = data.conflictStudent;
            document.getElementById('conflictSubject').textContent     = data.conflictSubject;
            document.getElementById('conflictDate').textContent        = data.conflictDate;
            document.getElementById('conflictTime').textContent        = data.conflictStart + ' – ' + data.conflictEnd;
            document.getElementById('conflictModal').style.display     = 'flex';
        }

        function closeConflictModal() {
            document.getElementById('conflictModal').style.display = 'none';
        }

        function openAutoRejectModal(count) {
            document.getElementById('autoRejectBody').innerHTML =
                `<strong>${count} overlapping pending ${count === 1 ? 'request was' : 'requests were'} automatically rejected</strong> `
                + `because they conflicted with the session you just accepted. The affected students can submit a new booking request.`;
            document.getElementById('autoRejectModal').style.display = 'flex';
        }

        function closeAutoRejectModal() {
            document.getElementById('autoRejectModal').style.display = 'none';
        }

        function getWeekRange() {
            const now = new Date();
            const end = new Date(now);
            end.setHours(23, 59, 59, 999);
            const start = new Date(now);
            start.setDate(now.getDate() - 6);
            start.setHours(0, 0, 0, 0);
            return { mon: start, sun: end };
        }

        function formatDisplay(d) {
            return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }

        function toDateStr(d) {
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        }

        function openReportModal() {
            const { mon, sun } = getWeekRange();
            const shortLabel  = `${formatDisplay(mon)} – ${formatDisplay(sun)}`;
            const detailLabel = `${mon.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })} – ${sun.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}`;
            document.getElementById('reportWeekLabel').textContent  = shortLabel;
            document.getElementById('reportWeekDetail').textContent = detailLabel;
            document.getElementById('reportModal').style.display    = 'flex';
        }

        function submitReport() {
            if (typeof XLSX === 'undefined') {
                alert('Spreadsheet export library is loading or missing.');
                return;
            }

            const { mon, sun } = getWeekRange();
            const fromStr = toDateStr(mon);
            const toStr   = toDateStr(sun);

            const topMentors   = @json($this->topMentors);
            const topSubjects  = @json($this->topSubjects);
            const satisfaction = @json($this->satisfactionRate);
            const collegeData  = @json($this->collegeActivity);
            const monthlyData  = @json($this->monthlyTrends);

            const filtered = allSessions.filter(row => {
                const datePart = String(row.date).substring(0, 10);
                const d = new Date(datePart + 'T00:00:00');
                return d >= mon && d <= sun;
            });

            const wb = XLSX.utils.book_new();
            const sessionHeader = ['Student', 'Mentor', 'Subject', 'Topic', 'Date & Time', 'Mode'];
            const sessionRows = filtered.length
                ? filtered.map(r => {
                    const start = new Date(r.start.includes('T') ? r.start : r.start.replace(' ', 'T'));
                    const end   = new Date(r.end.includes('T')   ? r.end   : r.end.replace(' ', 'T'));
                    const fmt   = t => t.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    const dateFormatted = new Date(r.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                    const mins  = Math.max(0, (end - start) / 60000);
                    const hrs   = Math.floor(mins / 60);
                    const rem   = Math.round(mins % 60);
                    const dur   = hrs > 0 ? `${hrs} hr${hrs > 1 ? 's' : ''}` : `${rem} min`;
                    const dateTime = `${dateFormatted}  ${fmt(start)} – ${fmt(end)}  (${dur})`;
                    return [r.mentee ?? 'Unknown', r.mentor ?? 'Unknown', r.subject ?? 'N/A', r.topic ?? '—', dateTime, r.mode ?? 'One-on-One Tutorial'];
                })
                : [['No sessions found for this week.', '', '', '', '', '']];

            const sessionSheet = XLSX.utils.aoa_to_sheet([sessionHeader, ...sessionRows]);
            sessionSheet['!cols'] = [{ wch: 24 }, { wch: 24 }, { wch: 14 }, { wch: 24 }, { wch: 36 }, { wch: 28 }];
            XLSX.utils.book_append_sheet(wb, sessionSheet, 'Weekly Sessions');

            const statusSummary = { completed: 0, accepted: 0, pending: 0, rejected: 0 };
            filtered.forEach(r => { const s = (r.status ?? '').toLowerCase(); if (statusSummary[s] !== undefined) statusSummary[s]++; });

            let totalMins = 0;
            filtered.forEach(r => {
                const s = new Date(r.start.includes('T') ? r.start : r.start.replace(' ', 'T'));
                const e = new Date(r.end.includes('T')   ? r.end   : r.end.replace(' ', 'T'));
                totalMins += Math.max(0, (e - s) / 60000);
            });
            const totalH = Math.floor(totalMins / 60);
            const totalM = Math.round(totalMins % 60);

            const overviewRows = [
                ['LRC PEERCONNECT — WEEKLY SESSION REPORT'], [],
                ['Report Period', `${formatDisplay(mon)}  to  ${formatDisplay(sun)}`],
                ['Generated on',  new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })], [],
                ['SUMMARY'],
                ['Total Sessions', filtered.length], ['Completed', statusSummary.completed],
                ['Accepted', statusSummary.accepted], ['Pending', statusSummary.pending],
                ['Rejected', statusSummary.rejected], ['Total Hours', `${totalH}h ${totalM}m`], [],
                ['TOP MENTORS (ALL-TIME)'], ['Rank', 'Mentor', 'Sessions'],
                ...topMentors.map((m, i) => [i + 1, m.name, m.count]), [],
                ['TOP SUBJECTS (ALL-TIME)'], ['Rank', 'Subject', 'Bookings'],
                ...topSubjects.map((s, i) => [i + 1, s.name, s.count]), [],
                ['COLLEGE ACTIVITY'], ['College', 'Students'],
                ...Object.entries(collegeData).map(([c, n]) => [c, n]),
            ];
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(overviewRows), 'Overview');
            XLSX.writeFile(wb, `lrc-weekly-report-${fromStr}-to-${toStr}.xlsx`);
            document.getElementById('reportModal').style.display = 'none';
        }

        // ── CORE STATE ──────────────────────────────────────────────────────────
        const searchInput  = document.getElementById('liveSearchInput');
        const statusFilter = document.getElementById('statusFilter');
        const charts = [];
        let currentPage = 1;

        let allSessions = @json($allSessions);

        const _now = new Date();
        let viewDate = window._calViewDate ?? new Date(_now.getFullYear(), _now.getMonth(), 1);
        let selectedDateStr = window._calSelectedDate ?? 
        `${_now.getFullYear()}-${String(_now.getMonth()+1).padStart(2,'0')}-${String(_now.getDate()).padStart(2,'0')}`;
        window._calViewDate     = viewDate;
        window._calSelectedDate = selectedDateStr;

        const monthlyData  = @json($this->monthlyTrends);
        const topMentors   = @json($this->topMentors);
        const satisfaction = @json($this->satisfactionRate);
        const collegeData  = @json($this->collegeActivity);
        const topSubjects  = @json($this->topSubjects);

        // ── LIVEWIRE EVENT LISTENERS ────────────────────────────────────────────
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('booking-conflict-detected', ([data]) => {
                openConflictModal(data);
            });

            Livewire.on('bookings-auto-rejected', ([data]) => {
                openAutoRejectModal(data.count);
                applyFilters();
                renderCalendar();
            });

            Livewire.on('mentor-saved', () => { initCharts(); });
        });

        // ── CLOCK ──────────────────────────────────────────────────────────────
        function updateClock() {
            const now     = new Date();
            const clockEl = document.getElementById('liveClock');
            const dateEl  = document.getElementById('liveDate');
            if (clockEl) clockEl.innerText = now.toLocaleTimeString('en-US', { hour12: false });
            if (dateEl)  dateEl.innerText  = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        setInterval(updateClock, 1000);

        // ── CHARTS ─────────────────────────────────────────────────────────────
        function initCharts() {
            if (typeof Chart === 'undefined') return;

            charts.forEach(c => c.destroy());
            charts.length = 0;

            charts.push(new Chart(document.getElementById('lineChart'), {
                type: 'line',
                data: {
                    labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                    datasets: [{
                        label: 'Sessions', data: monthlyData, borderColor: '#7b1d1d',
                        backgroundColor: 'rgba(123,29,29,0.08)', tension: 0.4, fill: true,
                        pointBackgroundColor: '#7b1d1d', pointRadius: 4, pointHoverRadius: 6,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: ctx => {
                                    const weekIndex = ctx[0].dataIndex;
                                    const start = new Date();
                                    start.setDate(start.getDate() - start.getDay() + 1 - ((3 - weekIndex) * 7));
                                    const end = new Date(start);
                                    end.setDate(start.getDate() + 6);
                                    const fmt = d => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                                    return `${ctx[0].label}  (${fmt(start)} – ${fmt(end)})`;
                                },
                                label: ctx => ` ${ctx.parsed.y} session${ctx.parsed.y !== 1 ? 's' : ''}`
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 }, stepSize: 1, precision: 0 } }
                    }
                }
            }));

            charts.push(new Chart(document.getElementById('pieChart'), {
                type: 'pie',
                data: {
                    labels: topMentors.map(m => m.name),
                    datasets: [{ data: topMentors.map(m => m.count), backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1'] }]
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } } }
            }));

            charts.push(new Chart(document.getElementById('topSubjectsChart'), {
                type: 'bar',
                data: {
                    labels: topSubjects.map(s => s.name),
                    datasets: [{ label: 'Bookings', data: topSubjects.map(s => s.count), backgroundColor: ['#1a3c2f', '#7b1d1d', '#94a3b8', '#cbd5e1', '#fef3c7'], borderRadius: 4, barThickness: 20 }]
                },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 9 } } }, y: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } } } }
            }));

            charts.push(new Chart(document.getElementById('activeCollegeChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(collegeData),
                    datasets: [{ label: 'Active Students', data: Object.values(collegeData), backgroundColor: ['#94a3b8', '#1a3c2f', '#7b1d1d'], borderRadius: 4, barThickness: 20 }]
                },
                options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 9 } } }, y: { grid: { display: false }, ticks: { font: { size: 9 } } } } }
            }));
        }

        // ── TABLE / FILTERS ────────────────────────────────────────────────────
        function applyFilters() {
            const tbody = document.getElementById('tableBody');
            if (!tbody) return;

            const searchTerm     = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;

            document.getElementById('tableTitle').textContent    = "Today's Schedule";
            document.getElementById('tableSubtitle').textContent = new Date(selectedDateStr + 'T00:00:00').toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric',
            });

            const filtered = allSessions.filter(item => {
                const matchesDate   = item.date === selectedDateStr;
                const matchesSearch = !searchTerm || [item.mentor, item.mentee, item.subject].some(v =>
                    (v ?? '').toLowerCase().includes(searchTerm));
                const matchesStatus = !selectedStatus || item.status === selectedStatus;
                return matchesDate && matchesSearch && matchesStatus;
            });

            const perPage    = 4;
            const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
            if (currentPage > totalPages) currentPage = 1;

            const start     = (currentPage - 1) * perPage;
            const paginated = filtered.slice(start, start + perPage);

            if (!paginated.length) {
                tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center">
                    <div class="flex flex-col items-center gap-2">
                        <i class="fa-regular fa-calendar-xmark text-gray-300 text-2xl"></i>
                        <p class="text-xs text-gray-400 italic">No sessions found for this date.</p>
                    </div></td></tr>`;
            } else {
                tbody.innerHTML = paginated.map(row => {
                    const rawStatus = row.status;
                    const colorCls  = getStatusColor(rawStatus);
                    const label     = getStatusLabel(rawStatus);
                    const statusCell = `<span class="${colorCls} font-bold text-[10px] bg-gray-50 px-2 py-1 rounded border border-current opacity-80 capitalize">${label}</span>`;
                    return `<tr class="border-b last:border-0 hover:bg-slate-50 transition">
                        <td class="py-3 max-w-0" style="width:22%;" title="${row.mentee}"><div class="truncate text-xs font-bold text-slate-700">${row.mentee}</div></td>
                        <td class="py-3 max-w-0" style="width:22%;" title="${row.mentor}"><div class="truncate text-xs text-slate-600">${row.mentor}</div></td>
                        <td class="py-3 max-w-0 text-xs text-slate-500" style="width:16%;" title="${row.subject}"><div class="truncate">${row.subject}</div></td>
                        <td class="py-3 pl-4 max-w-0 text-xs text-slate-500" style="width:20%;" title="${row.time}"><div class="truncate">${row.time}</div></td>
                        <td class="py-3 text-center" style="width:20%;">${statusCell}</td>
                    </tr>`;
                }).join('');
            }

            const showing = filtered.length === 0 ? '0' : `${start + 1}–${Math.min(start + perPage, filtered.length)}`;
            document.getElementById('pageIndicator').innerText = `Showing ${showing} of ${filtered.length} result${filtered.length !== 1 ? 's' : ''}`;
            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
            document.getElementById('prevBtn').classList.toggle('opacity-30', currentPage <= 1);
            document.getElementById('nextBtn').classList.toggle('opacity-30', currentPage >= totalPages);
        }

        // ── CALENDAR ───────────────────────────────────────────────────────────
        function hasAcceptedOnDate(dateStr) {
            return allSessions.some(s => s.date === dateStr && s.status === 'accepted');
        }

        function hasCompletedOnDate(dateStr) {
            return allSessions.some(s => s.date === dateStr && s.status === 'completed');
        }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            if (!grid) return;

            const localToday = new Date();
            localToday.setHours(0, 0, 0, 0);
            const todayStr  = `${localToday.getFullYear()}-${String(localToday.getMonth() + 1).padStart(2, '0')}-${String(localToday.getDate()).padStart(2, '0')}`;
            const monthDisp = document.getElementById('monthDisplay');

            grid.innerHTML = '';
            monthDisp.innerText = viewDate.toLocaleString('default', { month: 'long', year: 'numeric' });

            const lastDay  = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
            const startDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1).getDay();

            for (let i = 0; i < startDay; i++) grid.innerHTML += '<div></div>';

            for (let i = 1; i <= lastDay; i++) {
                const dateStr = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                const dateObj = new Date(viewDate.getFullYear(), viewDate.getMonth(), i);
                dateObj.setHours(0, 0, 0, 0);

                const isPast     = dateObj < localToday;
                const isToday    = dateStr === todayStr;
                const isSelected = dateStr === selectedDateStr;
                const hasAccepted  = hasAcceptedOnDate(dateStr);
                const hasCompleted = hasCompletedOnDate(dateStr);

                const dayEl = document.createElement('div');
                dayEl.className = `cal-day${isToday ? ' cal-today' : ''}${isSelected ? ' cal-selected' : ''}`;
                if (isPast && !isToday) dayEl.style.color = '#9ca3af';

                dayEl.innerHTML = `
                    ${hasAccepted  ? '<span style="position:absolute;top:2px;right:2px;width:6px;height:6px;background:#22c55e;border-radius:50%;border:1px solid white;"></span>' : ''}
                    ${hasCompleted && !hasAccepted ? '<span style="position:absolute;top:2px;right:2px;width:6px;height:6px;background:#9ca3af;border-radius:50%;border:1px solid white;"></span>' : ''}
                    <span style="position:relative;z-index:1;">${i}</span>`;

                dayEl.onclick = () => {
    selectedDateStr         = dateStr;
    window._calSelectedDate = dateStr;
    currentPage             = 1;
    applyFilters();
    renderCalendar();
};
                grid.appendChild(dayEl);
            }
        }

        function changeMonth(dir) {
    viewDate.setMonth(viewDate.getMonth() + dir);
    window._calViewDate = viewDate;
    renderCalendar();
}

        let _dashboardInited = false;

        function initDashboard() {
            if (_dashboardInited) {
                charts.forEach(c => c.destroy());
                charts.length = 0;
            }
            _dashboardInited = true;

            initCharts();
            renderCalendar();
            applyFilters();
            updateClock();

            const si = document.getElementById('liveSearchInput');
            const sf = document.getElementById('statusFilter');
            const pb = document.getElementById('prevBtn');
            const nb = document.getElementById('nextBtn');

            if (si) si.oninput  = () => { currentPage = 1; applyFilters(); };
            if (sf) sf.onchange = () => { currentPage = 1; applyFilters(); };
            if (pb) pb.onclick  = () => { if (currentPage > 1) { currentPage--; applyFilters(); } };
            if (nb) nb.onclick  = () => { currentPage++; applyFilters(); };
        }

        document.addEventListener('livewire:navigated', initDashboard);
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initDashboard();
} else {
    document.addEventListener('DOMContentLoaded', initDashboard);
}
    </script>
</div>
