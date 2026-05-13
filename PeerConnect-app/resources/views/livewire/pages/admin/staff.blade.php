<?php

use function Livewire\Volt\{state, mount, computed, action, uses};
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use App\Models\StaffProfiles;
use App\Models\StaffAvailabilities;

uses([WithFileUploads::class]);

state([
    // Form states
    'showModal' => false,
    'showConfirm' => false,

    // Edit states
    'showEditModal' => false,
    'showEditConfirm' => false,
    'editStaffId' => null,
    'editFirstName' => '',
    'editLastName' => '',
    'editMiddleInitial' => '',
    'editEmail' => '',
    'editRole' => '',
    'editAvatarPreview' => '',
    'editAvatar' => null,

    // Add states
    'firstName' => '',
    'lastName' => '',
    'middleInitial' => '',
    'email' => '',
    'role' => '',
    'avatar' => null,

    'availabilities' => [
        ['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']
    ],
]);

$allStaff = computed(function () {
    return StaffProfiles::with('availabilities')->get()->map(function ($staff) {
        $schedule = $staff->availabilities
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
            'id'            => $staff->id,
            'firstName'     => $staff->firstName,
            'lastName'      => $staff->lastName,
            'middleInitial' => $staff->middleInitial,
            'email'         => $staff->email,
            'role'          => $staff->role,
            'avatar'        => $staff->avatar,
            'schedule'      => $schedule,
        ];
    })->values()->toArray();
});

// ── ADD STAFF ────────────────────────────────────────────────
$openModal = action(function () {
    $this->reset(['firstName', 'lastName', 'middleInitial', 'email', 'role', 'avatar', 'showConfirm']);
    $this->availabilities = [['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    $this->showModal = true;
});

$closeModal = action(function () {
    $this->showModal = false;
    $this->showConfirm = false;
    $this->resetErrorBag();
    $this->reset(['firstName', 'lastName', 'middleInitial', 'email', 'role', 'avatar']);
    $this->availabilities = [['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
});

$confirmStaff = action(function () {
    $this->availabilities = collect($this->availabilities)
        ->map(fn($row) => [
            'day_of_week' => strtolower(trim($row['day_of_week'] ?? '')),
            'start_time'  => trim($row['start_time'] ?? ''),
            'end_time'    => trim($row['end_time'] ?? ''),
        ])
        ->values()
        ->toArray();

    $this->validate([
        'firstName'     => ['required', 'string', 'max:255'],
        'lastName'      => ['required', 'string', 'max:255'],
        'middleInitial' => ['nullable', 'string', 'max:2'],
        'email'         => ['required', 'email', 'max:255'],
        'role'          => ['required', 'string', 'max:255'],
        'avatar'        => ['required', 'image', 'max:2048'],
        'availabilities'               => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time'  => ['required', 'date_format:H:i'],
        'availabilities.*.end_time'    => ['required', 'date_format:H:i'],
    ], [], [
        'firstName'     => 'first name',
        'lastName'      => 'last name',
        'email'         => 'email',
        'role'          => 'role',
        'avatar'        => 'profile picture',
        'availabilities' => 'availabilities',
    ]);

    $groupedSchedule = [];

    foreach ($this->availabilities as $i => $row) {
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }

        $day = $row['day_of_week'];
        foreach ($groupedSchedule[$day] ?? [] as $existing) {
            if ($row['start_time'] < $existing['end_time'] && $row['end_time'] > $existing['start_time']) {
                $this->addError("availabilities.{$i}.start_time", "This time overlaps with another slot on " . ucfirst($day) . ".");
                return;
            }
        }
        $groupedSchedule[$day][] = [
            'start_time' => $row['start_time'],
            'end_time'   => $row['end_time'],
        ];
    }

    $this->showConfirm = true;
});

$saveStaff = action(function () {
    $staff = StaffProfiles::create([
        'firstName'     => trim($this->firstName),
        'lastName'      => trim($this->lastName),
        'middleInitial' => trim($this->middleInitial) ?: null,
        'email'         => trim($this->email),
        'role'          => trim($this->role),
    ]);

    if ($this->avatar) {
        $filename = $this->avatar->hashName();
        $this->avatar->storeAs('', $filename, 's3_staff');
        $url = rtrim(config('filesystems.disks.s3_staff.public_url'), '/') . '/' . $filename;
        $staff->update(['avatar' => $url]);
    }

    foreach ($this->availabilities as $sched) {
        StaffAvailabilities::create([
            'staff_id'    => $staff->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time'  => $sched['start_time'],
            'end_time'    => $sched['end_time'],
        ]);
    }

    $this->showModal = false;
    $this->showConfirm = false;
    session()->flash('successMessage', "The staff member has been added.");
    $this->redirect(route('admin.staff'), navigate: true);
});

// ── EDIT STAFF ───────────────────────────────────────────────
$editStaff = action(function ($id) {
    $staff = StaffProfiles::with('availabilities')->find($id);

    $this->editStaffId      = $staff->id;
    $this->editFirstName    = $staff->firstName;
    $this->editLastName     = $staff->lastName;
    $this->editMiddleInitial = $staff->middleInitial;
    $this->editEmail        = $staff->email;
    $this->editRole         = $staff->role;
    $this->editAvatarPreview = $staff->avatar;
    $this->editAvatar       = null;

    if ($staff->availabilities->count() > 0) {
        $this->availabilities = $staff->availabilities->map(function ($avail) {
            return [
                'id'          => $avail->id,
                'day_of_week' => strtolower($avail->day_of_week),
                'start_time'  => \Carbon\Carbon::parse($avail->start_time)->format('H:i'),
                'end_time'    => \Carbon\Carbon::parse($avail->end_time)->format('H:i'),
            ];
        })->toArray();
    } else {
        $this->availabilities = [['id' => '1', 'day_of_week' => '', 'start_time' => '', 'end_time' => '']];
    }

    $this->showEditModal = true;
});

$confirmEdit = action(function ($id, $availabilities) {
    $availabilities = collect($availabilities)
        ->map(fn($row) => [
            'day_of_week' => strtolower(trim($row['day_of_week'] ?? '')),
            'start_time'  => trim($row['start_time'] ?? ''),
            'end_time'    => trim($row['end_time'] ?? ''),
        ])
        ->values()
        ->toArray();

    $this->editStaffId    = $id;
    $this->availabilities = $availabilities;

    $this->validate([
        'editFirstName'     => ['required', 'string', 'max:255'],
        'editLastName'      => ['required', 'string', 'max:255'],
        'editMiddleInitial' => ['nullable', 'string', 'max:2'],
        'editEmail'         => ['required', 'email', 'max:255'],
        'editRole'          => ['required', 'string', 'max:255'],
        'editAvatar'        => ['nullable', 'image', 'max:2048'],
        'availabilities'               => ['required', 'array', 'min:1'],
        'availabilities.*.day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday'],
        'availabilities.*.start_time'  => ['required', 'date_format:H:i'],
        'availabilities.*.end_time'    => ['required', 'date_format:H:i'],
    ], [], [
        'editFirstName'     => 'first name',
        'editLastName'      => 'last name',
        'editEmail'         => 'email',
        'editRole'          => 'role',
        'editAvatar'        => 'profile picture',
        'availabilities'    => 'availabilities',
    ]);

    $groupedSchedule = [];

    foreach ($this->availabilities as $i => $row) {
        if ($row['end_time'] <= $row['start_time']) {
            $this->addError("availabilities.{$i}.end_time", 'Start time should be earlier than end time.');
            return;
        }

        $day = $row['day_of_week'];
        foreach ($groupedSchedule[$day] ?? [] as $existing) {
            if ($row['start_time'] < $existing['end_time'] && $row['end_time'] > $existing['start_time']) {
                $this->addError("availabilities.{$i}.start_time", "This time overlaps with another slot on " . ucfirst($day) . ".");
                return;
            }
        }
        $groupedSchedule[$day][] = [
            'start_time' => $row['start_time'],
            'end_time'   => $row['end_time'],
        ];
    }

    $this->showEditConfirm = true;
});

$updateStaff = action(function () {
    $staff = StaffProfiles::find($this->editStaffId);

    $staff->update([
        'firstName'     => trim($this->editFirstName),
        'lastName'      => trim($this->editLastName),
        'middleInitial' => trim($this->editMiddleInitial) ?: null,
        'email'         => trim($this->editEmail),
        'role'          => trim($this->editRole),
    ]);

    if ($this->editAvatar) {
        $baseUrl = rtrim(config('filesystems.disks.s3_staff.public_url'), '/');
        if ($staff->avatar) {
            $oldFile = str_replace($baseUrl . '/', '', $staff->avatar);
            Storage::disk('s3_staff')->delete($oldFile);
        }
        $filename = $this->editAvatar->hashName();
        $this->editAvatar->storeAs('', $filename, 's3_staff');
        $staff->update(['avatar' => $baseUrl . '/' . $filename]);
    }

    StaffAvailabilities::where('staff_id', $staff->id)->delete();
    foreach ($this->availabilities as $sched) {
        StaffAvailabilities::create([
            'staff_id'    => $staff->id,
            'day_of_week' => $sched['day_of_week'],
            'start_time'  => $sched['start_time'],
            'end_time'    => $sched['end_time'],
        ]);
    }

    $this->showEditModal    = false;
    $this->showEditConfirm  = false;
    session()->flash('successMessage', "The staff profile has been updated.");
    $this->redirect(route('admin.staff'), navigate: true);
});

$closeEditModal = action(function () {
    $this->showEditModal   = false;
    $this->showEditConfirm = false;
    $this->resetErrorBag();
    $this->editAvatar      = null;
    $this->availabilities  = [];
});

// ── DELETE STAFF ─────────────────────────────────────────────
$deleteStaff = action(function ($id) {
    $staff = StaffProfiles::findOrFail($id);

    if ($staff->avatar && \Illuminate\Support\Str::contains($staff->avatar, config('filesystems.disks.s3_staff.public_url'))) {
        $filename = basename($staff->avatar);
        Storage::disk('s3_staff')->delete($filename);
    }

    $staff->delete();

    session()->flash('successMessage', "The staff member has been successfully removed.");
    $this->redirect(route('admin.staff'), navigate: true);
});

mount(function () {
    abort_if(!auth()->check(), 401, 'Unauthenticated');
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});
?>

<div x-data="staffManagement(@js($this->allStaff), $wire)">
    {{-- Page heading --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Staff Management
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">LRC Registry of Staff</p>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

        {{-- Table header --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-m">All Staff</h2>
                <p class="text-xs text-gray-400 font-medium">{{ count($this->allStaff) }} Staff Member{{ count($this->allStaff) !== 1 ? 's' : '' }} found</p>
            </div>
            <div class="flex gap-2 items-center flex-wrap" x-data="{ opening: null }">
                <button type="button"
                        @click="opening = 'staff'; $wire.openModal().finally(() => opening = null)"
                        x-bind:disabled="opening !== null"
                        class="flex items-center justify-center bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px] w-[120px] disabled:cursor-not-allowed">
                <span x-show="opening !== 'staff'" class="flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-[10px]"></i> Add Staff
                </span>
                    <span x-show="opening === 'staff'" style="display: none;">
                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Opening...
                </span>
                </button>
            </div>
        </div>

        {{-- Success message --}}
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
        <div class="overflow-visible">
            <table class="w-full text-left text-sm table-fixed overflow-visible">
                <thead class="bg-slate-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[30%]">Staff Name</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[25%]">Position</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[30%]">Email</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[15%]">
                        <div class="flex items-center justify-center">Actions</div>
                    </th>
                </tr>
                </thead>
                <tbody>
                @forelse($this->allStaff as $staff)
                    <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                        <td class="px-5 py-4 align-middle w-[30%]">
                            <p class="font-bold text-slate-700 text-xs truncate"
                               x-data
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = '{{ $staff['lastName'] }}, {{ $staff['firstName'] }}{{ $staff['middleInitial'] ? ' ' . $staff['middleInitial'] . '.' : '' }}' })">
                                {{ $staff['lastName'] }}, {{ $staff['firstName'] }}{{ $staff['middleInitial'] ? ' ' . $staff['middleInitial'] . '.' : '' }}
                            </p>
                        </td>
                        <td class="px-5 py-4 align-middle w-[25%]">
                            <p class="text-xs text-slate-600 truncate"
                               x-data
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = '{{ $staff['role'] }}' })">
                                {{ $staff['role'] }}
                            </p>
                        </td>
                        <td class="px-5 py-4 align-middle w-[30%]">
                            <p class="text-xs text-slate-500 truncate"
                               x-data
                               x-init="$nextTick(() => { if ($el.scrollWidth > $el.clientWidth) $el.title = '{{ $staff['email'] }}' })">
                                {{ $staff['email'] }}
                            </p>
                        </td>
                        <td class="px-5 py-4 align-middle text-center w-[15%]">
                            <div class="relative flex items-center justify-center flex-wrap min-h-[28px]">
                                <div class="action-idle absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <span class="w-2 h-2 rounded-full bg-gray-100 inline-block"></span>
                                </div>
                                <div class="action-buttons flex items-center justify-center flex-wrap gap-1">
                                    <div class="hover-tooltip" data-full="View Details">
                                        <button @click="openViewModal(@js($staff))" class="icon-btn icon-btn-view">
                                            <i class="fa-solid fa-eye" style="font-size:11px;"></i>
                                        </button>
                                    </div>
                                    <div class="hover-tooltip" data-full="Edit Profile">
                                        <button @click="openEditModal(@js($staff))" class="icon-btn icon-btn-edit">
                                            <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                                        </button>
                                    </div>
                                    <div class="hover-tooltip" data-full="Remove Staff">
                                        <button @click="openDeleteModal(@js($staff))" class="icon-btn icon-btn-delete">
                                            <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-16 text-gray-400 text-xs italic">No staff found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- View Details Modal --}}
    <template x-teleport="body">
        <div class="modal-overlay" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
            <div class="modal-box-crud max-w-2xl flex flex-col" style="max-height: 90vh;">
                <template x-if="selectedStaff">
                    <div class="contents">
                        <div class="flex-shrink-0 flex items-start gap-5 p-6 bg-[#1a3c2f]">
                            <div class="w-36 h-36 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-white/20 shadow-lg bg-gray-200">
                                <img :src="selectedStaff.avatar" alt="avatar" class="w-full h-full object-cover bg-white" />
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <p class="text-white font-black text-2xl leading-tight tracking-tight"
                                   x-text="selectedStaff.lastName + ', ' + selectedStaff.firstName + (selectedStaff.middleInitial ? ' ' + selectedStaff.middleInitial + '.' : '')"></p>
                                <p class="text-white/60 text-xs mt-1" x-text="selectedStaff.role"></p>
                                <p class="text-white/60 text-xs mt-1" x-text="selectedStaff.email"></p>
                            </div>
                            <button @click="showViewModal = false" class="text-white/50 hover:text-white transition flex-shrink-0 mt-1">
                                <i class="fa-solid fa-xmark text-xl"></i>
                            </button>
                        </div>
                        <div class="overflow-y-auto flex-1 p-6 space-y-6 bg-white">
                            <div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Weekly Availability</p>
                                <div class="avail-grid">
                                    <template x-for="day in weekDays" :key="day">
                                        <div>
                                            <div class="avail-day-header" x-text="day.charAt(0).toUpperCase() + day.slice(1,3)"></div>
                                            <div class="avail-day-col">
                                                <template x-if="selectedStaff.schedule[day]">
                                                    <template x-for="(slot, index) in selectedStaff.schedule[day].slots" :key="index">
                                                        <div class="avail-slot" x-html="slot.start + '<br>' + slot.end"></div>
                                                    </template>
                                                </template>
                                                <template x-if="!selectedStaff.schedule[day]">
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

    {{-- Add Staff Modal --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showModal"
         x-data="{ fileName: '', isVerifying: false }"
         x-init="$watch('$wire.showModal', val => { if (!val) { fileName = ''; document.getElementById('avatar-upload').value = ''; } })">
        <div class="modal-box-crud max-w-2xl flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 border-b flex justify-between items-center flex-shrink-0 bg-white">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Add Staff Member</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Fill in their information, then set their availabilities.</p>
                </div>
                <button wire:click="closeModal" @click="$wire.showModal = false" class="text-gray-400 hover:text-red-600 transition" x-bind:disabled="isVerifying">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-5 overflow-y-auto bg-white">

                {{-- Step 1: Basic Info + Photo --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="step-badge">1</span>
                            <h3 class="step-title">Staff Information</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">First Name</label>
                                    <input type="text" wire:model="firstName" class="form-input text-xs w-full mt-1" maxlength="255" />
                                    @error('firstName') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Last Name</label>
                                    <input type="text" wire:model="lastName" class="form-input text-xs w-full mt-1" maxlength="255" />
                                    @error('lastName') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-[50px_1fr] gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase truncate" title="Middle Initial">M.I</label>
                                    <input type="text" wire:model="middleInitial" class="form-input text-xs w-full mt-1" maxlength="2" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Email</label>
                                    <input type="email" wire:model="email" class="form-input text-xs w-full mt-1" />
                                    @error('email') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Role / Position</label>
                                <input type="text" wire:model="role" placeholder="e.g. LRC Head" class="form-input text-xs w-full mt-1" maxlength="255" />
                                @error('role') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                            </div>
                        </div>
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

                {{-- Step 3: Availabilities --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="step-badge">3</span>
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
                                        <div x-data="mentorTimePicker()" x-modelable="timeValue" x-model="row.start_time" @click.outside="close()" class="w-full relative">
                                            <div class="custom-time-picker">
                                                <div class="custom-time-display form-input text-xs h-10 w-full flex items-center gap-2 cursor-pointer bg-white" :class="{ 'ring-1 ring-up-maroon border-up-maroon': open }" @click="toggle()">
                                                    <div class="time-icon text-gray-400"><i class="fa-regular fa-clock"></i></div>
                                                    <span :class="selectedTime ? 'font-semibold text-gray-800' : 'text-gray-400'" x-text="selectedTime || 'Start'"></span>
                                                </div>
                                                <div class="time-picker-dropdown" :class="{ show: open }" style="z-index: 50;">
                                                    <div class="tp-ampm">
                                                        <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                                        <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                                    </div>
                                                    <div class="tp-scroll-row">
                                                        <div class="tp-col">
                                                            <div class="tp-col-label">Hour</div>
                                                            <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                            <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12" @input="$el.value = $el.value.slice(0,2)" :value="String(hour).padStart(2,'0')" @change="onHourInput($event)" @keydown.up.prevent="changeHour(1)" @keydown.down.prevent="changeHour(-1)">
                                                            <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                        </div>
                                                        <div class="tp-sep">:</div>
                                                        <div class="tp-col">
                                                            <div class="tp-col-label">Min</div>
                                                            <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                            <input class="tp-manual-input tp-min-input" type="number" min="0" max="59" @input="$el.value = $el.value.slice(0,2)" :value="String(minute).padStart(2,'0')" @change="onMinInput($event)" @keydown.up.prevent="changeMin(1)" @keydown.down.prevent="changeMin(-1)">
                                                            <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div x-data="mentorTimePicker()" x-modelable="timeValue" x-model="row.end_time" @click.outside="close()" class="w-full relative">
                                            <div class="custom-time-picker">
                                                <div class="custom-time-display form-input text-xs h-10 w-full flex items-center gap-2 cursor-pointer bg-white" :class="{ 'ring-1 ring-up-maroon border-up-maroon': open }" @click="toggle()">
                                                    <div class="time-icon text-gray-400"><i class="fa-regular fa-clock"></i></div>
                                                    <span :class="selectedTime ? 'font-semibold text-gray-800' : 'text-gray-400'" x-text="selectedTime || 'End'"></span>
                                                </div>
                                                <div class="time-picker-dropdown" :class="{ show: open }" style="z-index: 50;">
                                                    <div class="tp-ampm">
                                                        <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                                        <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                                    </div>
                                                    <div class="tp-scroll-row">
                                                        <div class="tp-col">
                                                            <div class="tp-col-label">Hour</div>
                                                            <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                            <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12" @input="$el.value = $el.value.slice(0,2)" :value="String(hour).padStart(2,'0')" @change="onHourInput($event)" @keydown.up.prevent="changeHour(1)" @keydown.down.prevent="changeHour(-1)">
                                                            <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                        </div>
                                                        <div class="tp-sep">:</div>
                                                        <div class="tp-col">
                                                            <div class="tp-col-label">Min</div>
                                                            <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                            <input class="tp-manual-input tp-min-input" type="number" min="0" max="59" @input="$el.value = $el.value.slice(0,2)" :value="String(minute).padStart(2,'0')" @change="onMinInput($event)" @keydown.up.prevent="changeMin(1)" @keydown.down.prevent="changeMin(-1)">
                                                            <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                                <p class="text-xs text-red-700 font-medium leading-relaxed">Please check if all slots are filled out properly or if there are overlapping times on the same day.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0"
                 x-data="{
                avatarReady: $wire.entangle('avatar'),
                availsReady: $wire.entangle('availabilities'),
                firstNameReady: $wire.entangle('firstName'),
                lastNameReady: $wire.entangle('lastName'),
                emailReady: $wire.entangle('email'),
                roleReady: $wire.entangle('role'),
                get canSubmit() {
                    return this.firstNameReady && this.lastNameReady
                        && this.emailReady && this.roleReady
                        && this.avatarReady
                        && this.availsReady && this.availsReady.some(a => a.day_of_week && a.start_time && a.end_time);
                }
            }">
                <div class="flex gap-3">
                    <button type="button" wire:click="closeModal" @click="$wire.showModal = false" x-bind:disabled="isVerifying"
                            class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="button" @click="isVerifying = true; $wire.confirmStaff().finally(() => isVerifying = false)"
                            x-bind:disabled="isVerifying || !canSubmit"
                            :class="(isVerifying || !canSubmit) ? 'opacity-50 cursor-not-allowed' : ''"
                            class="btn-modal btn-modal-green">
                        <span x-show="!isVerifying">Add Staff Member</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Confirm Add Staff --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showConfirm" wire:click.self="$set('showConfirm', false)">
        <div class="modal-box-crud max-w-sm p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-user-plus text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Add Staff</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will add the staff member to the system.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.saveStaff().finally(() => isSaving = false)"
                        class="btn-modal btn-modal-green" x-bind:disabled="isSaving">
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Edit Staff Modal --}}
    <div x-cloak class="modal-overlay" x-show="showEditModal" wire:ignore.self
         x-data="{ fileName: '', isVerifying: false,
    get hasChanges() {
        if (this.fileName !== '') return true;
        if ($wire.editFirstName !== editingStaff.firstName) return true;
        if ($wire.editLastName !== editingStaff.lastName) return true;
        if ($wire.editEmail !== editingStaff.email) return true;
        if ($wire.editRole !== editingStaff.role) return true;
        let origMI = (editingStaff.middleInitial || '').replace('.', '').trim();
        let newMI = ($wire.editMiddleInitial || '').trim();
        if (newMI !== origMI) return true;
        const clean = arr => arr.map(a => `${a.day_of_week}-${a.start_time}-${a.end_time}`).sort().join('|');
        if (clean(originalForm.availabilities) !== clean(editForm.availabilities)) return true;
        return false;
    }}"
         x-init="$watch('$wire.showEditModal', val => { if (!val) { fileName = ''; document.getElementById('edit-avatar-upload').value = ''; } })">
        <div class="modal-box-crud max-w-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
            <div class="px-8 py-6 bg-white border-b flex justify-between items-center flex-shrink-0">
                <div>
                    <h2 class="text-xl font-black text-slate-800">Edit Staff Profile</h2>
                    <p class="text-sm text-gray-400 mt-0.5">Update their information, photo, or availability.</p>
                </div>
                <button @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition" x-bind:disabled="isVerifying">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div id="editModalScroll" class="px-8 py-6 space-y-5 overflow-y-auto bg-white">
                <template x-if="editingStaff">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="step-badge">1</span>
                                <h3 class="step-title">Staff Information</h3>
                            </div>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">First Name</label>
                                        <input type="text" wire:model="editFirstName" class="form-input text-xs w-full mt-1" maxlength="255" />
                                        @error('editFirstName') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Last Name</label>
                                        <input type="text" wire:model="editLastName" class="form-input text-xs w-full mt-1" maxlength="255" />
                                        @error('editLastName') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-[50px_1fr] gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase truncate" title="Middle Initial">M.I</label>
                                        <input type="text" wire:model="editMiddleInitial" class="form-input text-xs w-full mt-1" maxlength="2" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Email</label>
                                        <input type="email" wire:model="editEmail" class="form-input text-xs w-full mt-1" />
                                        @error('editEmail') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase">Role / Position</label>
                                    <input type="text" wire:model="editRole" class="form-input text-xs w-full mt-1" maxlength="255" />
                                    @error('editRole') <span class="text-[10px] text-red-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col h-full">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="step-badge">2</span>
                                <h3 class="step-title">Update Picture</h3>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0">
                                    @if($editAvatar)
                                        <img src="{{ $editAvatar->temporaryUrl() }}" class="w-32 h-32 rounded-xl object-cover border border-gray-200 shadow-sm">
                                    @else
                                        <img :src="editingStaff.avatar" class="w-32 h-32 rounded-xl object-cover border border-gray-200 shadow-sm">
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

                {{-- Availabilities --}}
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
                                    <div x-data="mentorTimePicker()" x-modelable="timeValue" x-model="row.start_time" @click.outside="close()" class="w-full relative">
                                        <div class="custom-time-picker">
                                            <div class="custom-time-display form-input text-xs h-10 w-full flex items-center gap-2 cursor-pointer bg-white" :class="{ 'ring-1 ring-up-maroon border-up-maroon': open }" @click="toggle()">
                                                <div class="time-icon text-gray-400"><i class="fa-regular fa-clock"></i></div>
                                                <span :class="selectedTime ? 'font-semibold text-gray-800' : 'text-gray-400'" x-text="selectedTime || 'Start'"></span>
                                            </div>
                                            <div class="time-picker-dropdown" :class="{ show: open }" style="z-index: 50;">
                                                <div class="tp-ampm">
                                                    <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                                    <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                                </div>
                                                <div class="tp-scroll-row">
                                                    <div class="tp-col">
                                                        <div class="tp-col-label">Hour</div>
                                                        <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                        <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12" @input="$el.value = $el.value.slice(0,2)" :value="String(hour).padStart(2,'0')" @change="onHourInput($event)" @keydown.up.prevent="changeHour(1)" @keydown.down.prevent="changeHour(-1)">
                                                        <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                    </div>
                                                    <div class="tp-sep">:</div>
                                                    <div class="tp-col">
                                                        <div class="tp-col-label">Min</div>
                                                        <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                        <input class="tp-manual-input tp-min-input" type="number" min="0" max="59" @input="$el.value = $el.value.slice(0,2)" :value="String(minute).padStart(2,'0')" @change="onMinInput($event)" @keydown.up.prevent="changeMin(1)" @keydown.down.prevent="changeMin(-1)">
                                                        <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div x-data="mentorTimePicker()" x-modelable="timeValue" x-model="row.end_time" @click.outside="close()" class="w-full relative">
                                        <div class="custom-time-picker">
                                            <div class="custom-time-display form-input text-xs h-10 w-full flex items-center gap-2 cursor-pointer bg-white" :class="{ 'ring-1 ring-up-maroon border-up-maroon': open }" @click="toggle()">
                                                <div class="time-icon text-gray-400"><i class="fa-regular fa-clock"></i></div>
                                                <span :class="selectedTime ? 'font-semibold text-gray-800' : 'text-gray-400'" x-text="selectedTime || 'End'"></span>
                                            </div>
                                            <div class="time-picker-dropdown" :class="{ show: open }" style="z-index: 50;">
                                                <div class="tp-ampm">
                                                    <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'AM' }" @click="setAmpm('AM')">AM</button>
                                                    <button type="button" class="tp-ampm-btn" :class="{ active: ampm === 'PM' }" @click="setAmpm('PM')">PM</button>
                                                </div>
                                                <div class="tp-scroll-row">
                                                    <div class="tp-col">
                                                        <div class="tp-col-label">Hour</div>
                                                        <button type="button" class="tp-btn" @click="changeHour(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                        <input class="tp-manual-input tp-hour-input" type="number" min="1" max="12" @input="$el.value = $el.value.slice(0,2)" :value="String(hour).padStart(2,'0')" @change="onHourInput($event)" @keydown.up.prevent="changeHour(1)" @keydown.down.prevent="changeHour(-1)">
                                                        <button type="button" class="tp-btn" @click="changeHour(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                    </div>
                                                    <div class="tp-sep">:</div>
                                                    <div class="tp-col">
                                                        <div class="tp-col-label">Min</div>
                                                        <button type="button" class="tp-btn" @click="changeMin(1)"><i class="fa-solid fa-chevron-up"></i></button>
                                                        <input class="tp-manual-input tp-min-input" type="number" min="0" max="59" @input="$el.value = $el.value.slice(0,2)" :value="String(minute).padStart(2,'0')" @change="onMinInput($event)" @keydown.up.prevent="changeMin(1)" @keydown.down.prevent="changeMin(-1)">
                                                        <button type="button" class="tp-btn" @click="changeMin(-1)"><i class="fa-solid fa-chevron-down"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                            <p class="text-xs text-red-700 font-medium leading-relaxed">Please check if all slots are filled out properly or if there are overlapping times on the same day.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="px-8 py-5 bg-white border-t flex-shrink-0">
                <div class="flex gap-3">
                    <button type="button" @click="showEditModal = false; $wire.closeEditModal()" x-bind:disabled="isVerifying"
                            class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="button" @click="if(hasChanges) { isVerifying = true; $wire.confirmEdit(editingStaff.id, editForm.availabilities).finally(() => isVerifying = false) }"
                            x-bind:disabled="isVerifying || !hasChanges"
                            :class="(!hasChanges || isVerifying) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                            class="flex-1 bg-blue-600 text-white py-3 rounded-xl text-xs font-bold shadow-lg transition">
                        <span x-show="!isVerifying">Save Changes</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Confirm Edit --}}
    <div x-cloak class="modal-overlay" x-show="$wire.showEditConfirm" wire:click.self="$set('showEditConfirm', false)">
        <div class="modal-box-crud max-w-sm p-8 text-center m-4">
            <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fa-solid fa-pen-to-square text-3xl"></i>
            </div>
            <h3 class="text-xl font-black text-slate-800">Confirm Changes</h3>
            <p class="text-sm text-gray-500 mt-2 mb-8">This will update the staff member's profile.</p>
            <div class="flex gap-3" x-data="{ isSaving: false }">
                <button type="button" @click="$wire.showEditConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                <button type="button" @click="isSaving = true; $wire.updateStaff().finally(() => isSaving = false)"
                        class="btn-modal btn-modal-blue" x-bind:disabled="isSaving">
                    <span x-show="!isSaving">Save</span>
                    <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Saving...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Delete Confirm --}}
    <template x-teleport="body">
        <div x-cloak class="modal-overlay" x-show="showDeleteConfirm" @click.self="if(!isSaving) showDeleteConfirm = false" x-data="{ isSaving: false }">
            <div class="modal-box-crud max-w-sm p-8 text-center m-4">
                <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-5">
                    <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800">Remove Staff Member?</h3>
                <p class="text-sm text-gray-500 mt-2 mb-8">Are you sure you want to remove this staff member? Their availability schedule will also be deleted.</p>
                <div class="flex gap-3">
                    <button type="button" @click="showDeleteConfirm = false" class="btn-modal btn-modal-cancel" x-bind:disabled="isSaving">Cancel</button>
                    <button type="button" @click="isSaving = true; $wire.deleteStaff(staffToDelete.id).then(() => showDeleteConfirm = false).finally(() => isSaving = false)"
                            x-bind:disabled="isSaving" class="btn-modal btn-modal-red">
                        <span x-show="!isSaving">Confirm</span>
                        <span x-show="isSaving" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
