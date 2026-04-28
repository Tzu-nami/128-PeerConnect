<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use App\Models\MentorProfiles;
use App\Models\MentorAvailabilities;
use App\Models\MentorSubjects;
use App\Models\Subjects;
use App\Models\User;
use App\Services\Avatar;

uses([WithFileUploads::class]);

layout('layouts.app');

state([
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
$mentorStats = computed(function () {
    $now = \Carbon\Carbon::now();
    $weekStart = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $weekEnd   = $now->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

    $bookings = \App\Models\Bookings::with(['mentor.user'])
        ->get();

    $weekBookings = $bookings->filter(fn($b) =>
        $b->date && \Carbon\Carbon::parse($b->date)->between($weekStart, $weekEnd)
    );

    $acceptedThisWeek = $weekBookings
        ->filter(fn($b) => $b->booking_status === 'accepted' && $b->mentor_id)
        ->pluck('mentor_id')->unique()->count();

    $pendingThisWeek = $weekBookings
        ->filter(fn($b) => $b->booking_status === 'pending' && $b->mentor_id)
        ->pluck('mentor_id')->unique()->count();

    $completedByMentor = $bookings
        ->filter(fn($b) => $b->booking_status === 'completed' && $b->mentor?->user)
        ->groupBy('mentor_id')
        ->map(fn($group) => [
            'name'  => $group->first()->mentor->user->firstName . ' ' . $group->first()->mentor->user->lastName,
            'count' => $group->count(),
        ])
        ->sortByDesc('count')
        ->first();

    return [
        'total'            => MentorProfiles::count(),
        'acceptedThisWeek' => $acceptedThisWeek,
        'pendingThisWeek'  => $pendingThisWeek,
        'mostActive'       => $completedByMentor
            ? $completedByMentor['name'] . ' (' . $completedByMentor['count'] . ')'
            : '—',
    ];
});
// Live Search logic - Optimized with Filtered computed property
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
            'id' => $mp->id,
            'user_id' => $mp->user_id,
            'lastName' => strtoupper($mp->user->lastName),
            'firstName' => $mp->user->firstName,
            'middleInitial' => $mp->user->middleInitial ? $mp->user->middleInitial . '.' : '',
            'email' => $mp->user->email,
            'student_num' => $mp->user->studentProfile->student_num,
            'avatar' => $mp->user->avatar ?? app(Avatar::class)->placeholder($mp->user->firstName . ' ' . $mp->user->lastName),
            'subjects' => $mp->subjects->unique('id')->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->sortBy('code')->values()->toArray(),
            'subjectsTable' => $mp->subjects->sortBy('code')->pluck('code')->join(', '),
            'days' => $activeDays,
            'schedule' => $schedule,
            'yearLevel' => $mp->user->studentProfile->yearLevel->name,
            'degreeProgram' => $mp->user->studentProfile->degreeProgram->name,
            'college' => $mp->user->studentProfile->college->name,
        ];
    })->sortBy('lastName')->values()->toArray();
});

// Display all subjects available
$allSubjects = computed(function () {
    return Subjects::orderBy('code')->get()
        ->map(fn($subs) => ['id' => $subs->id, 'code' => $subs->code, 'name' => $subs->name])
        ->toArray();
});

// ── ADD MENTOR ────────────────────────────────────────────────
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

// ── EDIT MENTOR ───────────────────────────────────────────────
$editMentor = action(function ($id) {
    $mentor = MentorProfiles::with(['user', 'subjects', 'availabilities'])->find($id);

    $this->editMentorId = $mentor->id;
    $this->editMentorName = $mentor->user->lastName . ', ' . $mentor->user->firstName;
    $this->editMentorEmail = $mentor->user->email;
    $this->editAvatarPreview = $mentor->user->avatar ?? app(Avatar::class)->placeholder($mentor->user->firstName . ' ' . $mentor->user->lastName);
    $this->editAvatar = null;

    $this->selectedSubjects = $mentor->subjects->pluck('id')->map(fn($id) => (string) $id)->toArray();

    if ($mentor->availabilities->count() > 0) {
        $this->availabilities = $mentor->availabilities->map(function ($avail) {
            return [
                'day_of_week' => strtolower($avail->day_of_week),
                'start_time' => \Carbon\Carbon::parse($avail->start_time)->format('H:i'),
                'end_time' => \Carbon\Carbon::parse($avail->end_time)->format('H:i'),
            ];
        })->toArray();
    } else {
        $this->availabilities = [['day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    }

    $this->showEditModal = true;
});

$confirmEdit = action(function ($id, $subjects, $availabilities) {
    $this->editMentorId = $id;
    $this->selectedSubjects = $subjects;
    $this->availabilities = $availabilities;

    $this->validate([
        'editAvatar' => ['nullable', 'image', 'max:2048'],
        'selectedSubjects' => ['required', 'array', 'min:1'],
        'selectedSubjects.*' => ['exists:subjects,id'],
        'availabilities' => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time' => ['required', 'date_format:H:i'],
        'availabilities.*.end_time' => ['required', 'date_format:H:i'],
    ], [], [
        'editAvatar' => 'profile picture',
        'selectedSubjects' => 'subjects',
        'availabilities' => 'availabilities',
    ]);

    foreach ($this->availabilities as $i => $row) {
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }
    }
    $this->showEditConfirm = true;
});

$updateMentor = action(function () {
    $mentorNew = MentorProfiles::with('user')->find($this->editMentorId);

    if ($this->editAvatar) {
        $baseUrl = rtrim(config('filesystems.disks.s3.public_url'), '/');
        $oldFile = str_replace($baseUrl . '/', '', $mentorNew->user->avatar);
        Storage::disk('s3')->delete($oldFile);

        $filename = $this->editAvatar->hashName();
        $this->editAvatar->storeAs('', $filename, 's3');
        $url = $baseUrl . '/' . $filename;
        $mentorNew->user->update(['avatar' => $url]);
    }

    MentorSubjects::where('mentor_id', $mentorNew->id)->delete();
    foreach ($this->selectedSubjects as $subjectId) {
        MentorSubjects::create(['mentor_id' => $mentorNew->id, 'subject_id' => $subjectId]);
    }

    MentorAvailabilities::where('mentor_id', $mentorNew->id)->delete();
    foreach ($this->availabilities as $sched) {
        MentorAvailabilities::create([
            'mentor_id' => $mentorNew->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time' => $sched['start_time'],
            'end_time' => $sched['end_time'],
        ]);
    }

    $this->showEditModal = false;
    $this->showEditConfirm = false;
    session()->flash('successMessage', "The profile has been updated.");
    $this->redirect(route('admin.mentors'), navigate: true);
});

$closeEditModal = action(function () {
    $this->showEditModal = false;
    $this->showEditConfirm = false;
    $this->editAvatar = null;
    $this->selectedSubjects = [];
    $this->availabilities = [];
});

// ── SUBJECTS ──────────────────────────────────────────────────
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
});

// ── DELETE MENTOR ─────────────────────────────────────────────
$deleteMentor = action(function ($id) {
    $mentorProf = MentorProfiles::with('user')->findOrFail($id);
    $mentorProf->user->update(['user_roles' => 'student']);
    MentorSubjects::where('mentor_id', $mentorProf->id)->delete();
    MentorAvailabilities::where('mentor_id', $mentorProf->id)->delete();
    $mentorProf->delete();

    session()->flash('successMessage', "The mentor has been successfully removed.");
    $this->redirect(route('admin.mentors'), navigate: true);
});

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

?>

<div class="livewire-root-scope" x-data="mentorManagement(@js($this->allMentors), $wire)">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>

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
    .app-wrapper { display: flex; height: 100vh; width: 100%; overflow: hidden; }

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
    .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; position: relative; }
    .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

    .profile-dropdown {
        position: absolute; top: 75px; right: 40px; background: white; border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
        flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
    }
    .profile-dropdown.show { display: flex; }
    .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; text-decoration: none; }
    .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

    .table-filter-select { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
    .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; background: white; cursor: pointer; }
    .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: #7b1d1d; border-color: #7b1d1d; }

    .hover-tooltip { position: relative; cursor: pointer; }
    .hover-tooltip::after {
        content: attr(data-full);
        position: absolute; left: 0; top: 110%;
        background: rgba(0,0,0,0.85); color: #fff;
        padding: 6px 10px; border-radius: 6px; font-size: 11px; line-height: 1.4;
        white-space: normal; word-break: break-word; overflow-wrap: break-word;
        width: max-content; max-width: 220px;
        opacity: 0; pointer-events: none;
        transform: translateY(5px); transition: 0.15s ease; z-index: 9999;
    }
    .hover-tooltip:hover::after { opacity: 1; transform: translateY(0); }

    /* Hover-reveal action buttons */
    .mentor-row .action-buttons {
        opacity: 0;
        transform: translateX(6px);
        transition: opacity 0.15s ease, transform 0.15s ease;
        pointer-events: none;
    }
    .mentor-row:hover .action-buttons {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }
    .mentor-row .action-idle {
        opacity: 1;
        transition: opacity 0.15s ease;
    }
    .mentor-row:hover .action-idle {
        opacity: 0;
    }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .form-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
    .form-input:focus { border-color: var(--header-maroon); }
    .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }

    .avail-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
    .avail-day-header { font-size: 9px; font-weight: 800; text-align: center; color: #94a3b8; text-transform: uppercase; padding-bottom: 4px; }
    .avail-day-col { display: flex; flex-direction: column; gap: 3px; }
    .avail-slot { background: #d1fae5; color: #065f46; font-size: 9px; font-weight: 700; padding: 3px 4px; border-radius: 4px; text-align: center; line-height: 1.3; }
    .avail-empty { background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 4px; height: 28px; }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
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
                <a href="{{ route('admin.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('admin.mentors') }}" class="nav-item active" data-tooltip="Mentor Management">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
                </a>
                <a href="{{ route('admin.courses') }}" class="nav-item" data-tooltip="Course Management">
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
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
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

{{-- Success Message --}}
@if(session('successMessage'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl"
        style="animation: slideDown 0.2s ease;"
    >
        {{ session('successMessage') }}
    </div>
@endif

                {{-- Page heading --}}
                <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                            Mentor Management
                        </h1>
                        <p class="text-sm font-medium text-slate-500 mt-1">LRC Registry of Peer Mentors</p>
                    </div>
                </div>

{{-- Summary Stat Cards --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-chalkboard-user text-slate-500"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Total Mentors</h3>
            <p class="text-2xl font-black text-slate-800">{{ $this->mentorStats['total'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-circle-check text-green-600"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Accepted This Week</h3>
            <p class="text-2xl font-black text-slate-800">{{ $this->mentorStats['acceptedThisWeek'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Pending This Week</h3>
            <p class="text-2xl font-black text-slate-800">{{ $this->mentorStats['pendingThisWeek'] }}</p>
        </div>
    </div>
    <div class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-4">
        <div class="text-2xl"><i class="fa-solid fa-trophy text-purple-600"></i></div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase leading-none">Most Active</h3>
            <p class="text-lg font-black text-slate-800 leading-tight">{{ $this->mentorStats['mostActive'] }}</p>
        </div>
    </div>
</div>

                {{-- Table Card --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

                    {{-- Table header / filters --}}
                    <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                        <div>
                            <h2 class="font-bold text-slate-800 text-m">All Mentors</h2>
                            <p class="text-xs text-gray-400 font-medium" id="mentorCountLabel">— mentors found</p>
                        </div>
                        <div class="flex gap-2 items-center flex-wrap">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search mentors..."
                                    class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                            </div>

                            <button wire:click="openModal" @click="$wire.showModal = true"
                                class="flex items-center gap-2 bg-slate-800 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px]">
                                <i class="fa-solid fa-user-plus text-[10px]"></i> Add Mentor
                            </button>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div style="overflow:visible;">
                        <table class="w-full text-left text-sm table-fixed" style="overflow:visible;">
<thead class="bg-slate-50 border-b border-gray-100">
    <tr>
        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:4%;">#</th>
        <th @click="setSort('name')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:22%;">
            <div class="flex items-center gap-1 hover:text-red-800 transition">Mentor Name<span x-text="sortIndicator('name')" class="text-[10px]"></span></div>
        </th>
        <th @click="setSort('student_num')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:13%;">
            <div class="flex items-center gap-1 hover:text-red-800 transition">Student No.<span x-text="sortIndicator('student_num')" class="text-[10px]"></span></div>
        </th>
        <th @click="setSort('email')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:20%;">
            <div class="flex items-center gap-1 hover:text-red-800 transition">UP Mail<span x-text="sortIndicator('email')" class="text-[10px]"></span></div>
        </th>
        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:27%;">Subjects</th>
        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:14%;">
            <div class="flex items-center justify-center">Actions</div>
        </th>
    </tr>
</thead>
                            <tbody>
                                <template x-for="(mentor, idx) in paginatedMentors" :key="mentor.id">
                                    <tr class="mentor-row border-b border-gray-50 hover:bg-slate-50 transition">

                                        <td class="px-5 py-4 align-middle text-gray-400 text-xs font-medium" style="width:4%;">
                                            <span x-text="(currentPage - 1) * perPage + idx + 1"></span>
                                        </td>

                                        <td class="px-5 py-4 align-middle" style="width:22%;">
                                            <div class="hover-tooltip" :data-full="mentor.lastName + ', ' + mentor.firstName + ' ' + mentor.middleInitial + '\n' + mentor.yearLevel + ' — ' + mentor.degreeProgram">
                                                <p class="font-bold text-slate-700 text-xs truncate" x-text="mentor.lastName + ', ' + mentor.firstName + ' ' + mentor.middleInitial"></p>
                                                <p class="text-[10px] text-gray-400 mt-0.5 truncate" x-text="mentor.yearLevel + ' — ' + mentor.degreeProgram"></p>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 align-middle text-xs text-slate-600" style="width:13%;" x-text="mentor.student_num"></td>

                                        <td class="px-5 py-4 align-middle" style="width:20%;">
                                            <div class="hover-tooltip" :data-full="mentor.email">
                                                <p class="text-xs text-slate-500 truncate" x-text="mentor.email"></p>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 align-middle" style="width:27%;">
                                            <div x-data="{ subs: mentor.subjectsTable ? mentor.subjectsTable.split(',').map(s=>s.trim()).filter(Boolean) : [] }" class="flex items-center flex-nowrap gap-1">
                                                <template x-for="(sub, index) in subs.slice(0, 3)" :key="index">
                                                    <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100 whitespace-nowrap" x-text="sub"></span>
                                                </template>
                                                <template x-if="subs.length > 3">
                                                    <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 whitespace-nowrap"
                                                        x-text="'+' + (subs.length - 3)"
                                                        :title="mentor.subjects.slice(3, 10).map(s => s.code).join('\n') + (mentor.subjects.length > 8 ? '\n...and more' : '')"></span>
                                                </template>
                                                <template x-if="subs.length === 0">
                                                    <span class="text-[10px] text-gray-300 italic">No subjects</span>
                                                </template>
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 align-middle text-center" style="width:14%;">
                                            <div class="relative flex items-center justify-center" style="min-height:28px;">

                                                {{-- idle dot --}}
                                                <div class="action-idle absolute inset-0 flex items-center justify-center pointer-events-none">
                                                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                                                </div>

                                                {{-- revealed buttons --}}
                                                <div class="action-buttons flex items-center justify-center gap-1">
                                                    <div class="hover-tooltip" data-full="View Details">
                                                        <button @click="openViewModal(mentor)"
                                                            class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-slate-600 hover:text-slate-800 flex items-center justify-center transition-all hover:scale-110">
                                                            <i class="fa-solid fa-eye" style="font-size:11px;"></i>
                                                        </button>
                                                    </div>

                                                    <div class="hover-tooltip" data-full="Edit Profile">
                                                        <button @click="openEditModal(mentor)"
                                                            class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 hover:text-blue-700 flex items-center justify-center transition-all hover:scale-110">
                                                            <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                                                        </button>
                                                    </div>

                                                    <div class="hover-tooltip" data-full="Remove Mentor">
                                                        <button @click="openDeleteModal(mentor)"
                                                            class="w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 hover:text-red-600 flex items-center justify-center transition-all hover:scale-110">
                                                            <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                            </div>
                                        </td>

                                    </tr>
                                </template>
                                <tr x-show="filteredMentors.length === 0" x-cloak>
                                    <td colspan="6" class="text-center py-16 text-gray-400 text-xs italic">No mentors match your search.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination footer --}}
                    <div class="pb-4 pt-3 flex flex-col items-center gap-2" x-show="totalPages >= 1" x-cloak>
                        <div class="flex items-center gap-2">
                            <button @click="currentPage--" :disabled="currentPage === 1"
                                class="pagination-btn" :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed' : ''">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </button>
                            <template x-for="(page, index) in pages" :key="index">
                                <div class="contents">
                                    <button x-show="page !== '...'" @click="currentPage = page"
                                        :class="currentPage === page
                                            ? 'w-8 h-8 text-xs font-bold rounded-lg bg-[#1a3c2f] text-white shadow-sm border border-[#1a3c2f]'
                                            : 'w-8 h-8 text-xs font-bold rounded-lg bg-white border border-gray-200 text-slate-500 hover:bg-gray-100 transition'"
                                        x-text="page"></button>
                                    <span x-show="page === '...'" class="w-8 h-8 flex items-center justify-center text-[11px] font-bold text-gray-400">...</span>
                                </div>
                            </template>
                            <button @click="currentPage++" :disabled="currentPage === totalPages"
                                class="pagination-btn" :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : ''">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        </div>
                        <span class="text-[11px] text-gray-400 font-medium"
                            x-text="((currentPage - 1) * perPage + 1) + '–' + Math.min(currentPage * perPage, filteredMentors.length) + ' of ' + filteredMentors.length"></span>
                    </div>

                </div>

                {{-- View Details Modal --}}
                <template x-teleport="body">
                    <div class="modal-overlay" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
                        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
                            <template x-if="selectedMentor">
                                <div class="contents">
                                    <div class="flex-shrink-0 flex items-start gap-5 p-6 bg-[#1a3c2f]">
                                        <div class="w-36 h-36 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-white/20 shadow-lg bg-gray-200">
                                            <img :src="selectedMentor.avatar" alt="avatar" class="w-full h-full object-cover bg-white" />
                                        </div>
                                        <div class="flex-1 min-w-0 pt-1">
                                            <p class="text-white font-black text-2xl leading-tight tracking-tight" x-text="selectedMentor.lastName + ', ' + selectedMentor.firstName + ' ' + selectedMentor.middleInitial"></p>
                                            <template x-if="selectedMentor.yearLevel && selectedMentor.degreeProgram">
                                                <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.yearLevel + ' — ' + selectedMentor.degreeProgram"></p>
                                            </template>
                                            <template x-if="selectedMentor.college">
                                                <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.college"></p>
                                            </template>
                                            <p class="text-white/60 text-xs mt-1" x-text="selectedMentor.email"></p>
                                        </div>
                                        <button @click="showViewModal = false" class="text-white/50 hover:text-white transition flex-shrink-0 mt-1">
                                            <i class="fa-solid fa-xmark text-xl"></i>
                                        </button>
                                    </div>
                                    <div class="overflow-y-auto flex-1 p-6 space-y-6 bg-white">
                                        <div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Teachable Subjects</p>
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="(subject, index) in selectedMentor.subjects" :key="index">
                                                    <span class="bg-red-50 text-red-700 border border-red-100 text-xs px-3 py-1 rounded font-bold" x-text="subject.code"></span>
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
                                                        <div class="avail-day-header" x-text="day.charAt(0).toUpperCase() + day.slice(1,3)"></div>
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
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

            </main>
        </div>
    </div>

    {{-- ── ADD MENTOR MODAL ── --}}
    <div x-show="$wire.showModal" x-cloak class="modal-overlay">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 border-b flex justify-between items-center flex-shrink-0 bg-white">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Register Mentor</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Add their email, assign their subjects, then set their availabilities.</p>
                </div>
                <button wire:click="closeModal" @click="$wire.showModal = false" class="text-gray-400 hover:text-red-600 transition">
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
                            <button wire:click="checkEmail" type="button"
                                class="w-full px-4 py-2.5 bg-slate-800 text-white text-xs font-bold rounded-lg hover:bg-black transition"
                                wire:loading.attr="disabled" wire:target="checkEmail">
                                <span wire:loading.remove wire:target="checkEmail">Find Email</span>
                                <span wire:loading wire:target="checkEmail"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Verifying...</span>
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
                        <div class="flex items-start gap-4" x-data="{ fileName: '' }">
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
                    <button type="button" wire:click="closeModal" @click="$wire.showModal = false"
                        class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmMentor"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                        wire:loading.attr="disabled" wire:target="confirmMentor">
                        <span wire:loading.remove wire:target="confirmMentor">Register Mentor</span>
                        <span wire:loading wire:target="confirmMentor"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
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
            <div class="flex gap-3">
                <button type="button" @click="$wire.showConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                <button type="button" wire:click="saveMentor"
                    class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                    wire:loading.attr="disabled" wire:target="saveMentor">
                    <span wire:loading.remove wire:target="saveMentor">Save</span>
                    <span wire:loading wire:target="saveMentor"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── EDIT MENTOR MODAL ── --}}
    <div x-show="showEditModal" x-cloak class="modal-overlay">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 bg-white border-b flex justify-between items-center flex-shrink-0">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Edit Mentor Profile</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Update their profile picture, teachable subjects, or their availabilities.</p>
                </div>
                <button @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div id="editModalScroll" class="px-8 py-6 space-y-5 overflow-y-auto bg-white">
                <template x-if="editingMentor">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Locked student info --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0"><i class="fa-solid fa-lock text-[8px]"></i></span>
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Student Information</h3>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg px-4 py-4 flex items-center gap-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate" x-text="editingMentor.lastName + ', ' + editingMentor.firstName"></p>
                                    <p class="text-xs text-gray-500 truncate" x-text="editingMentor.email"></p>
                                </div>
                            </div>
                        </div>

                        {{-- Step 1: Update picture --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">1</span>
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Update Picture</h3>
                            </div>
                            <div class="flex items-start gap-4" x-data="{ fileName: '' }">
                                <div class="flex-shrink-0">
                                    @if($editAvatar)
                                        <img src="{{ $editAvatar->temporaryUrl() }}" class="w-32 h-32 rounded-xl object-cover border border-gray-200 shadow-sm">
                                    @else
                                        <img :src="editingMentor.avatar" class="w-32 h-32 rounded-xl object-cover border border-gray-200 shadow-sm">
                                    @endif
                                </div>
                                <div class="flex-1 pt-1 flex flex-col justify-center h-32 min-w-0">
                                    <input type="file" id="edit-avatar-upload" wire:model="editAvatar" accept="image/*" class="hidden"
                                        @change="fileName = $event.target.files[0].name" />
                                    <label for="edit-avatar-upload" class="block w-full text-center py-2.5 px-4 rounded-lg text-xs font-bold bg-slate-800 text-white hover:bg-black cursor-pointer transition shadow-sm">
                                        <span wire:loading.remove wire:target="editAvatar">Upload New Picture</span>
                                        <span wire:loading.inline-block wire:target="editAvatar"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Uploading...</span>
                                    </label>
                                    <div class="mt-3 text-[10px] text-center w-full">
                                        <p x-show="fileName" class="text-slate-700 font-bold truncate px-2 block w-full" x-text="fileName"></p>
                                    </div>
                                    @error('editAvatar') <p class="mt-1 text-xs text-red-600 text-center">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Step 2: Subjects --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">2</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Teachable Subjects</h3>
                    </div>
                    <div class="border border-gray-200 rounded-xl overflow-hidden" wire:ignore>
                        <div class="max-h-44 overflow-y-auto divide-y divide-gray-50 bg-white" x-data="{ availableSubjects: @js($this->allSubjects) }">
                            <template x-for="subject in availableSubjects" :key="subject.id">
                                <div class="flex items-center px-4 hover:bg-gray-50">
                                    <input type="checkbox" :id="'edit-subject-' + subject.id" x-model="editForm.subjects" :value="subject.id.toString()"
                                        class="rounded border-gray-300 text-slate-800 focus:ring-slate-800 w-4 h-4 cursor-pointer flex-shrink-0" />
                                    <label :for="'edit-subject-' + subject.id" class="flex items-center gap-3 ml-3 py-2.5 cursor-pointer flex-1">
                                        <span class="text-xs font-bold text-slate-700 w-16" x-text="subject.code"></span>
                                        <span class="text-xs text-gray-400" x-text="subject.name"></span>
                                    </label>
                                </div>
                            </template>
                        </div>
                    </div>
                    @error('selectedSubjects') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Step 3: Availabilities --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="flex items-center justify-center w-5 h-5 bg-slate-800 text-white rounded-full text-[10px] font-bold shrink-0">3</span>
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest m-0">Availability Schedule</h3>
                    </div>
                    <div wire:ignore>
                        <div class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 px-1 mb-1">
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Day</label>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">Start Time</label>
                            <label class="text-[10px] font-bold text-slate-500 uppercase">End Time</label>
                            <div class="w-8"></div>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(row, index) in editForm.availabilities" :key="row.id">
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
                                        <template x-if="editForm.availabilities.length > 1">
                                            <button type="button" @click="editForm.availabilities.splice(index, 1)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        </template>
                                        <template x-if="editForm.availabilities.length <= 1">
                                            <div class="w-8"></div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <button @click="editForm.availabilities.push({id: Date.now(), day_of_week: '', start_time: '', end_time: ''})" type="button"
                            class="mt-3 flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                            <i class="fa-solid fa-plus text-[10px]"></i> Add more days or time slots
                        </button>
                    </div>
                    @if($errors->hasAny(['availabilities', 'availabilities.*']))
                        <div class="mt-2 p-3 rounded-lg bg-red-50 border border-red-200">
                            <p class="text-xs text-red-700 font-medium leading-relaxed">Please ensure all schedule slots are completely and properly filled out.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0">
                <div class="flex gap-3">
                    <button type="button" @click="showEditModal = false; $wire.closeEditModal()"
                        class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                    <button type="button" @click="$wire.confirmEdit(editingMentor.id, editForm.subjects, editForm.availabilities)"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                        wire:loading.attr="disabled" wire:target="confirmEdit">
                        <span wire:loading.remove wire:target="confirmEdit">Save Changes</span>
                        <span wire:loading wire:target="confirmEdit"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM EDIT ── --}}
    <div x-show="$wire.showEditConfirm" x-cloak class="modal-overlay flex items-center justify-center" style="z-index: 1100;" wire:click.self="$set('showEditConfirm', false)">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-pen-to-square text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Changes</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will update the mentor's profile information.</p>
            <div class="flex gap-3">
                <button type="button" @click="$wire.showEditConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                <button type="button" wire:click="updateMentor"
                    class="flex-1 bg-blue-600 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-blue-800 transition"
                    wire:loading.attr="disabled" wire:target="updateMentor">
                    <span wire:loading.remove wire:target="updateMentor">Save</span>
                    <span wire:loading wire:target="updateMentor"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── SUBJECT MODAL ── --}}
    <div x-show="$wire.showSubjectModal" x-cloak class="modal-overlay">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-8 py-5 bg-white border-b flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Add New Subject</h2>
                    <p class="text-xs text-gray-400 mt-0.5">This subject will become available for mentor assignments.</p>
                </div>
                <button wire:click="closeSubjectModal" @click="$wire.showSubjectModal = false" class="text-gray-400 hover:text-red-600 transition">
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
                    <button type="button" wire:click="closeSubjectModal" @click="$wire.showSubjectModal = false"
                        class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                    <button type="button" wire:click="confirmSubject"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                        wire:loading.attr="disabled" wire:target="confirmSubject">
                        <span wire:loading.remove wire:target="confirmSubject">Add Subject</span>
                        <span wire:loading wire:target="confirmSubject"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM SUBJECT ── --}}
    <div x-show="$wire.showSubjectConfirm" x-cloak class="modal-overlay flex items-center justify-center" style="z-index: 1100;" wire:click.self="$set('showSubjectConfirm', false)">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-8 text-center m-4">
            <div class="w-16 h-16 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-book text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm New Subject</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will be added to the list of available subjects.</p>
            <div class="flex gap-3">
                <button type="button" @click="$wire.showSubjectConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                <button type="button" wire:click="saveSubject"
                    class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-800 transition"
                    wire:loading.attr="disabled" wire:target="saveSubject">
                    <span wire:loading.remove wire:target="saveSubject">Save</span>
                    <span wire:loading wire:target="saveSubject"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── DELETE CONFIRM MODAL ── --}}
    <template x-teleport="body">
        <div x-show="showDeleteConfirm" x-cloak class="modal-overlay flex items-center justify-center" style="z-index: 1100;" @click.self="showDeleteConfirm = false">
            <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-8 text-center m-4">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800">Remove Mentor?</h3>
                <p class="text-sm text-gray-500 mt-2 mb-8">Are you sure you want to remove this mentor? Their schedule and subjects will be deleted.</p>
                <div class="flex gap-3">
                    <button type="button" @click="showDeleteConfirm = false" class="flex-1 py-3 text-xs font-bold text-gray-800 bg-gray-200 hover:bg-gray-300 rounded-xl transition">Cancel</button>
                    <button type="button" wire:click="deleteMentor(mentorToDelete.id).then(() => showDeleteConfirm = false)"
                        class="flex-1 bg-red-900 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-600 transition"
                        wire:loading.attr="disabled" wire:target="deleteMentor">
                        <span wire:loading.remove wire:target="deleteMentor">Confirm</span>
                        <span wire:loading wire:target="deleteMentor"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script>
function mentorManagement(initialMentors, wire) {
    return {
        mentors: initialMentors,
        searchQuery: '',
        currentPage: 1,
        perPage: 10,
        sortColumn: 'name',
        sortDirection: 'asc',
        showViewModal: false,
        selectedMentor: null,
        showEditModal: false,
        editingMentor: null,
        editForm: { subjects: [], availabilities: [] },
        showDeleteConfirm: false,
        mentorToDelete: null,
        weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

        setSort(col) {
            if (this.sortColumn === col) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = col;
                this.sortDirection = 'asc';
            }
            this.currentPage = 1;
        },

        sortIndicator(col) {
            if (this.sortColumn !== col) return '';
            return this.sortDirection === 'asc' ? ' ↑' : ' ↓';
        },

        get filteredMentors() {
            const q = this.searchQuery.toLowerCase();
            let list = this.mentors.filter(m => {
                const str = [m.firstName, m.lastName, m.email, m.student_num || '', m.subjectsTable, m.degreeProgram, m.yearLevel].join(' ').toLowerCase();
                return str.includes(q);
            });

            const col = this.sortColumn;
            const dir = this.sortDirection === 'asc' ? 1 : -1;

            list = [...list].sort((a, b) => {
                let valA, valB;
                if (col === 'name') {
                    valA = (a.lastName + ' ' + a.firstName).toLowerCase();
                    valB = (b.lastName + ' ' + b.firstName).toLowerCase();
                } else if (col === 'student_num') {
                    valA = (a.student_num || '').toLowerCase();
                    valB = (b.student_num || '').toLowerCase();
                } else if (col === 'email') {
                    valA = (a.email || '').toLowerCase();
                    valB = (b.email || '').toLowerCase();
                } else {
                    valA = ''; valB = '';
                }
                if (valA < valB) return -1 * dir;
                if (valA > valB) return 1 * dir;
                return 0;
            });

            return list;
        },

        get paginatedMentors() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredMentors.slice(start, start + this.perPage);
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredMentors.length / this.perPage));
        },

        get pages() {
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
            if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
            if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
            return [1, '...', current - 1, current, current + 1, '...', total];
        },

        init() {
            this.$watch('filteredMentors', () => {
                const label = document.getElementById('mentorCountLabel');
                if (label) label.textContent = this.filteredMentors.length + ' Mentor' + (this.filteredMentors.length !== 1 ? 's' : '') + ' found';
            });
            this.$nextTick(() => {
                const label = document.getElementById('mentorCountLabel');
                if (label) label.textContent = this.filteredMentors.length + ' Mentor' + (this.filteredMentors.length !== 1 ? 's' : '') + ' found';
            });
        },

        convertTime(timeStr) {
            if (!timeStr) return '';
            const [time, modifier] = timeStr.split(' ');
            let [hours, minutes] = time.split(':');
            if (hours === '12') hours = '00';
            if (modifier === 'PM') hours = (parseInt(hours, 10) + 12).toString();
            return `${hours.padStart(2, '0')}:${minutes}`;
        },

        openViewModal(mentor) {
            this.selectedMentor = mentor;
            this.showViewModal = true;
        },

        openEditModal(mentor) {
            this.editingMentor = mentor;
            this.editForm.subjects = mentor.subjects.map(s => s.id.toString());
            let avails = [];
            for (const day in mentor.schedule) {
                mentor.schedule[day].slots.forEach(slot => {
                    avails.push({
                        id: Date.now() + Math.random(),
                        day_of_week: day.toLowerCase(),
                        start_time: this.convertTime(slot.start),
                        end_time: this.convertTime(slot.end)
                    });
                });
            }
            if (avails.length === 0) {
                avails.push({ id: Date.now() + Math.random(), day_of_week: '', start_time: '', end_time: '' });
            }
            this.editForm.availabilities = avails;
            this.showEditModal = true;
            this.$nextTick(() => {
                const scrollBox = document.getElementById('editModalScroll');
                if (scrollBox) scrollBox.scrollTop = 0;
            });
        },

        openDeleteModal(mentor) {
            this.mentorToDelete = mentor;
            this.showDeleteConfirm = true;
        }
    };
}

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('sidebarToggle').addEventListener('click', () => {
                document.getElementById('sidebar').classList.toggle('collapsed');
            });

            const profileTrigger  = document.getElementById('profileTrigger');
            const profileDropdown = document.getElementById('profileDropdown');

            profileTrigger.addEventListener('click', e => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            window.addEventListener('click', () => {
                profileDropdown.classList.remove('show');
            });
        });
    </script>
</div>
