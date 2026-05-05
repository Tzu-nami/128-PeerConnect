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
            'name'  => $group->first()->mentor->user->lastName . ', ' . substr($group->first()->mentor->user->firstName, 0, 1) . '.',
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

    $groupedSchedule = [];

    foreach ($this->availabilities as $i => $row) {
        // Check if start time is before end time
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }

        // Check for overlaps with other slots on the same day
        $day = $row['day_of_week'];
        foreach ($groupedSchedule[$day] ?? [] as $existing) {
            if ($row['start_time'] < $existing['end_time'] && $row['end_time'] > $existing['start_time']) {
                $this->addError("availabilities.{$i}.start_time", "This time overlaps with another slot on " . ucfirst($day) . ".");
                return;
            }
        }
        $groupedSchedule[$day][] = [
            'start_time' => $row['start_time'], 
            'end_time' => $row['end_time']
        ];
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

$confirmMentor = action(function () {
    $this->checkEmail();

    if (!$this->newMentor) {
        $this->emailError = 'Please input a valid student email.';
        return;
    }

    if ($this->emailError || !$this->newMentor) {
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

    $groupedSchedule = [];

    foreach ($this->availabilities as $i => $row) {
        // Check if start time is before end time
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }

        // Check for overlaps with other slots on the same day
        $day = $row['day_of_week'];
        foreach ($groupedSchedule[$day] ?? [] as $existing) {
            if ($row['start_time'] < $existing['end_time'] && $row['end_time'] > $existing['start_time']) {
                $this->addError("availabilities.{$i}.start_time", "This time overlaps with another slot on " . ucfirst($day) . ".");
                return;
            }
        }
        $groupedSchedule[$day][] = [
            'start_time' => $row['start_time'], 
            'end_time' => $row['end_time']
        ];
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
    $user = $mentorProf->user;
    if ($user->avatar && \Illuminate\Support\Str::contains($user->avatar, config('filesystems.disks.s3.public_url'))) {
        $filename = basename($user->avatar);
        Storage::disk('s3')->delete($filename);
        $user->update(['avatar' => null]);
    }

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

<div x-data="mentorManagement(@js($this->allMentors), $wire)">
    
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
    <div class="grid grid-cols-[repeat(autofit,_minmax(250px,_1fr))] sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-chalkboard-user text-slate-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Mentors</h3>
                <p class="text-xl lg:text-2xl font-black text-slate-800 truncate">{{ $this->mentorStats['total'] }}</p>
            </div>
        </div>
        
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-circle-check text-green-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Accepted This Week</h3>
                <p class="text-xl lg:text-2xl font-black text-slate-800 truncate">{{ $this->mentorStats['acceptedThisWeek'] }}</p>
            </div>
        </div>
        
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-hourglass-half text-yellow-500"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Pending This Week</h3>
                <p class="text-xl lg:text-2xl font-black text-slate-800 truncate">{{ $this->mentorStats['pendingThisWeek'] }}</p>
            </div>
        </div>
        
        <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-purple-600 flex items-center gap-3 lg:gap-4">
            <div class="text-2xl flex-shrink-0"><i class="fa-solid fa-trophy text-purple-600"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Most Active</h3>
                <p class="text-base lg:text-lg font-black text-slate-800 leading-tight truncate">{{ $this->mentorStats['mostActive'] }}</p>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

        {{-- Table header / filters --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-m">All Mentors</h2>
                <p class="text-xs text-gray-400 font-medium" x-text="`${filteredMentors.length} Mentor${filteredMentors.length !== 1 ? 's' : ''} found`"></p>
            </div>
            <div class="flex gap-2 items-center flex-wrap" x-data="{ opening: null }">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search mentors, email, subjects..."
                        class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-60 h-[34px] transition-shadow">
                </div>

                <button type="button" 
                    @click="opening = 'subject'; $wire.openSubjectModal().finally(() => opening = null)"
                    x-bind:disabled="opening !== null"
                    class="flex items-center justify-center bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px] w-[120px] disabled:cursor-not-allowed">
                    <span x-show="opening !== 'subject'" class="flex items-center gap-2">
                        <i class="fa-solid fa-book"></i> Add Subject
                    </span>
                    <span x-show="opening === 'subject'" style="display: none;">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Opening...
                    </span>
                </button>

                <button type="button" 
                    @click="opening = 'mentor'; $wire.openModal().finally(() => opening = null)"
                    x-bind:disabled="opening !== null"
                    class="flex items-center justify-center bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px] w-[120px] disabled:cursor-not-allowed">
                    
                    <span x-show="opening !== 'mentor'" class="flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-[10px]"></i> Add Mentor
                    </span>
                    <span x-show="opening === 'mentor'" style="display: none;">
                        <i class="fa-solid fa-spinner fa-spin mr-1"></i> Opening...
                    </span>
                </button>
            </div>
        </div>

        @if(session('successMessage'))
            <div x-data="{ show: true }" 
                 x-cloak
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="mx-5 mt-4 mb-2">
                
                <div class="flex items-center justify-between px-4 py-3 rounded-lg border bg-emerald-50 border-emerald-200 text-emerald-800">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <span class="text-sm font-semibold">{{ session('successMessage') }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Table --}}
        <div style="overflow:visible;">
            <table class="w-full text-left text-sm table-fixed" style="overflow:visible;">
                <thead class="bg-slate-50 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:5%;">#</th>
                        <th @click="setSort('name')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:22%;">
                            <div class="flex items-center gap-1 hover:text-red-800 transition">Mentor Name<span x-text="sortIndicator('name')" class="text-[10px]"></span></div>
                        </th>
                        <th @click="setSort('student_num')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:13%;">
                            <div class="flex items-center gap-1 hover:text-red-800 transition">Student No.<span x-text="sortIndicator('student_num')" class="text-[10px]"></span></div>
                        </th>
                        <th @click="setSort('email')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:20%;">
                            <div class="flex items-center gap-1 hover:text-red-800 transition">UP Mail<span x-text="sortIndicator('email')" class="text-[10px]"></span></div>
                        </th>
                        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:25%;">Subjects</th>
                        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider" style="width:15%;">
                            <div class="flex items-center justify-center">Actions</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(mentor, idx) in paginatedMentors" :key="mentor.id">
                        <tr class="mentor-row border-b border-gray-50 hover:bg-slate-50 transition">

                            <td class="px-5 py-4 align-middle text-gray-400 text-xs font-medium" style="width:5%;">
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

                            <td class="px-5 py-4 align-middle" style="width:25%;">
                                <div x-data="{ subs: mentor.subjectsTable ? mentor.subjectsTable.split(',').map(s=>s.trim()).filter(Boolean) : [] }" class="flex items-center flex-wrap gap-1">
                                    <template x-for="(sub, index) in subs.slice(0, 3)" :key="index">
                                        <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100 whitespace-nowrap" x-text="sub"></span>
                                    </template>
                                    <template x-if="subs.length > 3">
                                        <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-bold border border-slate-200 whitespace-nowrap hover-tooltip" x-text="'+' + (subs.length - 3)" :data-full="mentor.subjects.slice(3).map(s => s.code).join(', ')"></span>
                                    </template>
                                    <template x-if="subs.length === 0">
                                        <span class="text-[10px] text-gray-300 italic">No subjects</span>
                                    </template>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-middle text-center" style="width:15%;">
                                <div class="relative flex items-center justify-center flex-wrap" style="min-height:28px;">

                                    {{-- idle dot --}}
                                    <div class="action-idle absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <span class="w-2 h-2 rounded-full bg-gray-100 inline-block"></span>
                                    </div>

                                    {{-- revealed buttons --}}
                                    <div class="action-buttons flex items-center justify-center flex-wrap gap-1">
                                        <div class="hover-tooltip" data-full="View Details">
                                            <button @click="openViewModal(mentor)"
                                                class="icon-btn icon-btn-view">
                                                <i class="fa-solid fa-eye" style="font-size:11px;"></i>
                                            </button>
                                        </div>

                                        <div class="hover-tooltip" data-full="Edit Profile">
                                            <button @click="openEditModal(mentor)"
                                                class="icon-btn icon-btn-edit">
                                                <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                                            </button>
                                        </div>

                                        <div class="hover-tooltip" data-full="Remove Mentor">
                                            <button @click="openDeleteModal(mentor)"
                                                class="icon-btn icon-btn-delete">
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
        <div class="p-6 border-t border-gray-100 flex flex-col justify-center items-center gap-2 bg-white" x-show="totalPages > 1" x-cloak>
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
                x-text="filteredMentors.length === 0 ? '' : pageStart + '–' + pageEnd + ' of ' + filteredMentors.length">
            </span>
        </div>

    </div>

    {{-- View Details Modal --}}
    <template x-teleport="body">
        <div class="modal-overlay" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
            <div class="modal-box-crud max-w-2xl flex flex-col" style="max-height: 90vh;">
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

    {{-- Add Mentor Modal --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showModal" x-cloak
        x-data="{ fileName: '', isVerifying: false }" 
        x-init="$watch('$wire.showModal', val => { if (!val) { fileName = ''; document.getElementById('avatar-upload').value = ''; } })">
        <div class="modal-box-crud max-w-2xl flex flex-col" style="max-height: 90vh;">
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
                            <span class="step-badge">1</span>
                            <h3 class="step-title">Student Email</h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div>
                                <input type="email" wire:model="up_mail" placeholder="student@up.edu.ph" class="form-input" wire:keydown.enter.prevent="checkEmail" />
                                @error('up_mail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @if($emailError) <p class="mt-1 text-xs text-red-600">{{ $emailError }}</p> @endif
                            </div>
                            <button type="button"
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
                            <span class="step-badge">2</span>
                            <h3 class="step-title">Profile Picture</h3>
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
                        <span class="step-badge">3</span>
                        <h3 class="step-title">Teachable Subjects</h3>
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
                        <span class="step-badge">4</span>
                        <h3 class="step-title">Availability Schedule</h3>
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
                            @if($errors->hasAny(['availabilities', 'availabilities.*']))
                            <div class="mt-2 p-3 rounded-lg bg-red-50 border border-red-200">
                                <p class="text-xs text-red-700 font-medium leading-relaxed">Please check if all slots are filled out or if there are overlapping times on the same day.</p>
                            </div>
                            @endif
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0">
                <div class="flex gap-3">
                    <button type="button" wire:click="closeModal" @click="$wire.showModal = false" x-bind:disabled="isVerifying"
                        class="btn-modal btn-modal-cancel">
                        Cancel
                    </button>
                    <button type="button" @click="isVerifying = true; $wire.confirmMentor().finally(() => isVerifying = false)"
                        x-bind:disabled="isVerifying"
                        class="btn-modal btn-modal-green">
                        <span x-show="!isVerifying">Register Mentor</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM ADD MENTOR ── --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showConfirm" wire:click.self="$set('showConfirm', false)">
        <div class="modal-box-crud max-w-sm p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-user-plus text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Mentor Registration</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will register the student as a peer mentor and will allow them access to the mentor module.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.saveMentor().finally(() => isSaving = false)"
                    class="btn-modal btn-modal-green" x-bind:disabled="isSaving"
                    >
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── EDIT MENTOR MODAL ── --}}
    <div x-cloak class="modal-overlay" x-show="showEditModal"
        x-data="{ fileName: '', isVerifying: false,
        // Check if there are changes to any input
        get hasChanges() {
            if (this.fileName !== '') return true;
            
            const origSubs = [...originalForm.subjects].sort().join(',');
            const newSubs = [...editForm.subjects].sort().join(',');
            if (origSubs !== newSubs) return true;
            
            const clean = arr => arr.map(a => `${a.day_of_week}-${a.start_time}-${a.end_time}`).sort().join('|');
            if (clean(originalForm.availabilities) !== clean(editForm.availabilities)) return true;
            
            return false;
            }
        }" 
        x-init="$watch('showEditModal', val => { if (!val) { fileName = ''; document.getElementById('edit-avatar-upload').value = ''; } })">
        <div class="modal-box-crud max-w-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 bg-white border-b flex justify-between items-center flex-shrink-0">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Edit Mentor Profile</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Update their profile picture, teachable subjects, or their availabilities.</p>
                </div>
                <button @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition disabled:cursor-not-allowed" x-bind:disabled="isVerifying">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div id="editModalScroll" class="px-8 py-6 space-y-5 overflow-y-auto bg-white">
                <template x-if="editingMentor">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Locked student info --}}
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="step-badge"><i class="fa-solid fa-lock text-[8px]"></i></span>
                                <h3 class="step-title">Student Information</h3>
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
                                <span class="step-badge">1</span>
                                <h3 class="step-title">Update Picture</h3>
                            </div>
                            <div class="flex items-start gap-4">
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
                        <span class="step-badge">2</span>
                        <h3 class="step-title">Teachable Subjects</h3>
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
                        <span class="step-badge">3</span>
                        <h3 class="step-title">Availability Schedule</h3>
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
                            <p class="text-xs text-red-700 font-medium leading-relaxed">Please check if all slots are filled out or if there are overlapping times on the same day.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0">
                <div class="flex gap-3">
                    <button type="button" @click="showEditModal = false; $wire.closeEditModal()" x-bind:disabled="isVerifying"
                        class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="button" @click="if(hasChanges) { isVerifying = true; $wire.confirmEdit(editingMentor.id, editForm.subjects, editForm.availabilities).finally(() => isVerifying = false) }" x-bind:disabled="isVerifying || !hasChanges"
                        :class="(!hasChanges || isVerifying) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                        class="flex-1 bg-blue-600 text-white py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-blue-700 transition"
                        >
                        <span x-show="!isVerifying">Save Changes</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM EDIT ── --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showEditConfirm" wire:click.self="$set('showEditConfirm', false)">
        <div class="modal-box-crud max-w-sm p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-pen-to-square text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Changes</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will update the mentor's profile information.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showEditConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.updateMentor().finally(() => isSaving = false)"
                    class="btn-modal btn-modal-blue"
                    wire:loading.attr="disabled" wire:target="updateMentor.finally(() => isSaving = false)" x-bind:disabled="isSaving">
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── SUBJECT MODAL ── --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showSubjectModal" x-data="{ isVerifying: false }">
        <div class="modal-box-crud max-w-md">
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
                        class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="button" @click="isVerifying = true; $wire.confirmSubject().finally(() => isVerifying = false)" x-bind:disabled="isVerifying"
                        class="btn-modal btn-modal-green"
                        >
                        <span x-show="!isVerifying">Add Subject</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRM SUBJECT ── --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showSubjectConfirm" wire:click.self="$set('showSubjectConfirm', false)">
        <div class="modal-box-crud max-w-sm p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-book text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm New Subject</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will be added to the list of available subjects.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showSubjectConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.saveSubject().finally(() => isSaving = false)" x-bind:disabled="isSaving" 
                    class="btn-modal btn-modal-green">
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── DELETE CONFIRM MODAL ── --}}
    <template x-teleport="body">
        <div x-cloak class="modal-overlay" x-show="showDeleteConfirm" @click.self="if(!isSaving) showDeleteConfirm = false" x-data="{ isSaving: false }">
            <div class="modal-box-crud max-w-sm p-8 text-center m-4">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800">Remove Mentor?</h3>
                <p class="text-sm text-gray-500 mt-2 mb-8">Are you sure you want to remove this mentor? Their schedule and subjects will be deleted.</p>
                <div class="flex gap-3">
                    <button type="button" @click="showDeleteConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                    <button type="button" @click="isSaving = true; $wire.deleteMentor(mentorToDelete.id).then(() => showDeleteConfirm = false).finally(() => isSaving  = false)" x-bind:disabled="isSaving"
                        class="btn-modal btn-modal-red"
                        >
                        <span x-show="!isSaving">Confirm</span>
                        <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>