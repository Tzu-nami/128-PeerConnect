<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use App\Models\Subjects;
use App\Models\MentorSubjects;
use Illuminate\Validation\Rule;
use App\Services\Avatar;

layout('layouts.app');

state([
    // Form States
    //'showSubjectModal' => false,
    'newSubjectCode' => '',
    'newSubjectName' => '',

    // Edit States
    'showEditModal' => false,
    'editSubjectId' => null,
    'editSubjectCode' => '',
    'editSubjectName' => '',
]);

// Display all subjects and attach mentors teaching them
$allSubjects = computed(function () {
    $subjects = Subjects::orderBy('code')->get();
    $mentorSubjects = MentorSubjects::with('mentor.user')->get()->groupBy('subject_id');

    return $subjects->map(function ($subject) use ($mentorSubjects) {
        $mentors = [];

        if ($mentorSubjects->has($subject->id)) {
            $mentors = $mentorSubjects[$subject->id]->map(function ($ms) {
                $user = $ms->mentor->user;
                return [
                    'name' => strtoupper($user->lastName) . ', ' . $user->firstName,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? app(Avatar::class)->placeholder($user->firstName . ' ' . $user->lastName)
                ];
            })->sortBy('name')->values()->toArray();
        }

        return [
            'id' => $subject->id,
            'code' => $subject->code,
            'name' => $subject->name,
            'mentors' => $mentors,
            'mentorCount' => count($mentors)
        ];
    })->toArray();
});


// ── ADD SUBJECT ──────────────────────────────────────────────
$resetSubjectForm = action(function () {
    $this->reset(['newSubjectCode', 'newSubjectName']);
    //$this->showSubjectModal = true;
});

// $closeSubjectModal = action(function () {
//     $this->showSubjectModal = false;
//     $this->reset(['newSubjectCode', 'newSubjectName', 'showSubjectModal']);
// });

// Validate only — dispatches event to JS so confirmation modal can open first
$validateSubject = action(function () {
    $this->validate([
        'newSubjectCode' => ['required', 'string', 'max:20', 'unique:subjects,code'],
        'newSubjectName' => ['required', 'string', 'max:255'],
    ], [], [
        'newSubjectCode' => 'subject_code',
        'newSubjectName' => 'subject_name',
    ]);
    $this->dispatch('validation-passed');
});

$saveSubject = action(function () {
    Subjects::create([
        'code' => trim($this->newSubjectCode),
        'name' => trim($this->newSubjectName),
    ]);
    session()->flash('successMessage', "{$this->newSubjectCode} has been successfully added.");
    //$this->closeSubjectModal();
    $this->redirect(route('admin.courses'), navigate: true);
});


// ── EDIT SUBJECT ─────────────────────────────────────────────
$closeEditModal = action(function () {
    $this->showEditModal = false;
    $this->reset(['editSubjectId', 'editSubjectCode', 'editSubjectName', 'showEditModal']);
});

// Validate only — dispatches event to JS so confirmation modal can open first
$validateEditSubject = action(function ($id, $code, $name) {
    $this->editSubjectId = $id;
    $this->editSubjectCode = $code;
    $this->editSubjectName = $name;

    $this->validate([
        'editSubjectCode' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($this->editSubjectId)],
        'editSubjectName' => ['required', 'string', 'max:255'],
    ], [], [
        'editSubjectCode' => 'subject_code',
        'editSubjectName' => 'subject_name',
    ]);
    $this->dispatch('edit-validation-passed');
});

$updateSubject = action(function () {
    $subject = Subjects::findOrFail($this->editSubjectId);
    $subject->update([
        'code' => trim($this->editSubjectCode),
        'name' => trim($this->editSubjectName),
    ]);
    session()->flash('successMessage', "{$this->editSubjectCode} has been successfully updated.");
    $this->redirect(route('admin.courses'), navigate: true);
});


// ── DELETE SUBJECT ────────────────────────────────────────────
$deleteSubject = action(function ($id) {
    $subject = Subjects::findOrFail($id);
    $code = $subject->code;

    MentorSubjects::where('subject_id', $subject->id)->delete();
    $subject->delete();

    session()->flash('successMessage', "{$code} has been successfully removed.");
    $this->redirect(route('admin.courses'), navigate: true);
});

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

?>

<div class="app-wrapper"
     x-data="courseManagement(@js($this->allSubjects), $wire)"
     @validation-passed.window="openConfirmModal({
         title: 'Confirm New Subject',
         body: 'Are you sure you want to add this subject to the system registry?',
         variant: 'accept',
         confirmText: 'Save Subject',
         loadingText: 'Saving...',
         onConfirm: async () => {
             showSubjectModal = false;
             await $wire.saveSubject();
         }
     })"
     @edit-validation-passed.window="openConfirmModal({
         title: 'Update Subject?',
         body: 'Are you sure you want to save the changes made to this subject?',
         variant: 'accept',
         confirmText: 'Save Changes',
         loadingText: 'Saving...',
         onConfirm: async () => {
             showEditModal = false;
             await $wire.updateSubject();
         }
     })">

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

    .hover-tooltip { position: relative; }
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
    .subject-row .action-buttons {
        opacity: 0;
        transform: translateX(6px);
        transition: opacity 0.15s ease, transform 0.15s ease;
        pointer-events: none;
    }
    .subject-row:hover .action-buttons {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }
    .subject-row .action-idle {
        opacity: 1;
        transition: opacity 0.15s ease;
    }
    .subject-row:hover .action-idle {
        opacity: 0;
    }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .form-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
    .form-input:focus { border-color: var(--header-maroon); }
    .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }

    #confirmMeta { max-height: 200px; overflow-y: auto; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

    <aside class="sidebar" id="sidebar" :class="sidebarCollapsed ? 'collapsed' : ''">
        <div class="sidebar-logo-container">
            <div class="logo-content">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>
        </div>

        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar" @click.stop="sidebarCollapsed = !sidebarCollapsed">
            <span class="toggle-icon">
                <i class="fa-solid fa-chevron-right" id="toggleIcon"></i>
            </span>
        </button>

        <nav class="flex-grow">
            <a href="{{ route('admin.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('admin.mentors') }}" class="nav-item" data-tooltip="Mentor Management">
                <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
            </a>
            <a href="{{ route('admin.courses') }}" class="nav-item active" data-tooltip="Course Management">
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

                <button @click.stop="profileOpen = !profileOpen" id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"></i>
                </button>
            </div>

            <div class="profile-dropdown" id="profileDropdown" :class="profileOpen ? 'show' : ''">
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
    <div id="flashSuccessBanner" class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl" style="animation:slideDown 0.2s ease;">
        {{ session('successMessage') }}
    </div>
    <script>
        setTimeout(() => {
            const b = document.getElementById('flashSuccessBanner');
            if (b) {
                b.style.transition = 'opacity 0.4s ease';
                b.style.opacity = '0';
                setTimeout(() => b.remove(), 400);
            }
        }, 5000);
    </script>
@endif

            {{-- Page heading --}}
            <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                        Course Management
                    </h1>
                    <p class="text-sm font-medium text-slate-500 mt-1">LRC Registry of Subjects</p>
                </div>
            </div>

            {{-- Summary Stat Cards --}}
            <div class="grid grid-cols-[repeat(autofit,_minmax(250px, 1fr))] sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-slate-400 flex items-center gap-3 lg:gap-4">
                    <div class="text-2xl flex-shrink-0">
                        <i class="fa-solid fa-book-open text-slate-500"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Total Subjects</h3>
                        <p class="text-xl lg:text-2xl font-black text-slate-800 truncate" x-text="subjects.length"></p>
                    </div>
                </div>

                <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-green-600 flex items-center gap-3 lg:gap-4">
                    <div class="text-2xl flex-shrink-0">
                        <i class="fa-solid fa-chalkboard-user text-green-600"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Subjects With Mentors</h3>
                        <p class="text-xl lg:text-2xl font-black text-slate-800 truncate" x-text="subjects.filter(s => s.mentorCount > 0).length"></p>
                    </div>
                </div>

                <div class="bg-white p-4 lg:p-5 rounded-xl shadow-sm border-l-4 border-yellow-500 flex items-center gap-3 lg:gap-4">
                    <div class="text-2xl flex-shrink-0">
                        <i class="fa-solid fa-user-slash text-yellow-500"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase leading-none truncate">Subjects With No Mentors</h3>
                        <p class="text-xl lg:text-2xl font-black text-slate-800 truncate" x-text="subjects.filter(s => s.mentorCount === 0).length"></p>
                    </div>
                </div>
            </div>

            {{-- Subjects Table Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

                {{-- Table header / filters --}}
                <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
                    <div>
                        <h2 class="font-bold text-slate-800 text-m">All Subjects</h2>
                        <p class="text-xs text-gray-400 font-medium" x-text="filteredSubjects.length + (filteredSubjects.length === 1 ? ' Subject' : ' Subjects') + ' found'"></p>
                    </div>
                    <div class="flex gap-2 items-center flex-wrap">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                            <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search subjects..."
                                class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                        </div>

                        {{-- Mentor filter dropdown --}}
                        <div class="relative" id="mentorDropdownWrap">
                            <button id="mentorDropdownBtn"
                                class="table-filter-select flex items-center gap-2 min-w-[120px] justify-between"
                                onclick="toggleMentorDropdown(event)">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-filter text-gray-400"></i> Mentors
                                </span>
                                <span id="mentorBadge" class="hidden bg-red-900 text-white rounded-full px-1.5 text-[10px] font-bold"></span>
                            </button>
                            <div id="mentorDropdown"
                                class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-xl z-20 overflow-hidden py-1"
                                onclick="event.stopPropagation()">
                                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                    <input type="checkbox" id="filterAll" checked onchange="handleAllFilter(this)" class="rounded border-gray-300 w-4 h-4">
                                    <span>All</span>
                                </label>
                                <div class="border-t border-gray-100 my-1"></div>
                                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                    <input type="checkbox" value="with_mentors" onchange="handleMentorFilter()" class="mentor-filter-cb rounded border-gray-300 w-4 h-4"> Has Mentors
                                </label>
                                <label class="flex items-center gap-2 px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-xs text-slate-700 font-medium transition">
                                    <input type="checkbox" value="no_mentors" onchange="handleMentorFilter()" class="mentor-filter-cb rounded border-gray-300 w-4 h-4"> No Mentors
                                </label>
                            </div>
                        </div>

                        <button @click="showSubjectModal = true; $wire.resetSubjectForm()"
                            class="flex items-center gap-2 bg-slate-800 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px]">
                            <i class="fa-solid fa-book-medical text-[11px]"></i> Add Subject
                        </button>
                    </div>
                </div>

                {{-- Table --}}
                <div style="overflow:visible;">
                    <table class="w-full text-left text-sm table-fixed" style="overflow:visible;">

                        <thead class="bg-slate-50 border-b border-gray-100">
                            <tr>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider w-[5%]">#</th>
                                <th @click="setSort('code')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:18%;">
                                    <div class="flex items-center gap-1 hover:text-red-800 transition">
                                        Subject Code
                                        <span x-text="sortColumn === 'code' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                                    </div>
                                </th>
                                <th @click="setSort('name')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none" style="width:32%;">
                                    <div class="flex items-center gap-1 hover:text-red-800 transition">
                                        Subject Name
                                        <span x-text="sortColumn === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                                    </div>
                                </th>
                                <th @click="setSort('mentorCount')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none text-center" style="width:18%;">
                                    <div class="flex items-center justify-center gap-1 hover:text-red-800 transition">
                                        Registered Mentors
                                        <span x-text="sortColumn === 'mentorCount' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                                    </div>
                                </th>
                                <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none text-center" style="width:13%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="(sub, idx) in paginatedSubjects" :key="sub.id">
                                <tr class="subject-row hover:bg-slate-50 transition">
                                    <td class="px-5 py-4 align-middle text-gray-400 text-xs font-medium" style="width:4%;">
                                        <span x-text="(currentPage - 1) * perPage + idx + 1"></span>
                                    </td>
                                    <td class="px-5 py-4 align-middle" style="width:18%;">
                                        <div class="hover-tooltip" :data-full="sub.code">
                                            <p class="font-bold text-slate-800 text-xs truncate" x-text="sub.code"></p>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-middle" style="width:32%;">
                                        <div class="hover-tooltip" :data-full="sub.name">
                                            <p class="text-xs text-slate-600 truncate" x-text="sub.name"></p>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-middle text-center flex-wrap" style="width:18%;">
                                        <span @click="openViewModal(sub)"
                                            class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-100 cursor-pointer hover:bg-blue-100 transition whitespace-nowrap"
                                            x-text="sub.mentorCount + (sub.mentorCount === 1 ? ' Mentor' : ' Mentors')">
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 align-middle text-center" style="width:14%;">
                                        <div class="relative flex items-center justify-center flex-wrap" style="min-height:28px;">
                                            {{-- idle dot --}}
                                            <div class="action-idle absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <span class="w-2 h-2 rounded-full bg-slate-300 inline-block"></span>
                                            </div>

                                            {{-- revealed buttons --}}
                                            <div class="action-buttons flex items-center justify-center flex-wrap gap-1">
                                                <div class="hover-tooltip" data-full="View Mentors">
                                                    <button @click="openViewModal(sub)"
                                                        class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 text-slate-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm"
                                                        style="flex-shrink:0;">
                                                        <i class="fa-solid fa-eye" style="font-size:11px;"></i>
                                                    </button>
                                                </div>

                                                <div class="hover-tooltip" data-full="Edit Subject">
                                                    <button @click="openEditModal(sub)"
                                                        class="w-7 h-7 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm"
                                                        style="flex-shrink:0;">
                                                        <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                                                    </button>
                                                </div>

                                                <div class="hover-tooltip" data-full="Delete Subject">
                                                    <button @click="openDeleteModal(sub)"
                                                        class="w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 flex items-center justify-center transition-all hover:scale-110 hover:shadow-sm"
                                                        style="flex-shrink:0;">
                                                        <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="filteredSubjects.length === 0" x-cloak>
                                <td colspan="5" class="text-center py-16 text-gray-400 text-xs italic">No subjects found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination footer --}}
                <div class="pb-4 pt-3 flex flex-col items-center gap-2" x-show="totalPages > 1" x-cloak>
                    <div class="flex items-center gap-2">
                        <button @click="currentPage--" :disabled="currentPage === 1"
                            class="pagination-btn disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        </button>

                        <template x-for="(page, index) in pages" :key="index">
                            <div class="contents">
                                <button x-show="page !== '...'"
                                    @click="currentPage = page"
                                    :class="currentPage === page
                                        ? 'bg-[#1a3c2f] text-white shadow-sm border border-[#1a3c2f]'
                                        : 'bg-white border border-gray-200 text-slate-500 hover:bg-gray-100'"
                                    class="w-8 h-8 text-xs font-bold rounded-lg transition"
                                    x-text="page">
                                </button>
                                <span x-show="page === '...'"
                                    class="w-7 h-7 flex items-center justify-center text-[11px] font-bold text-gray-400">…</span>
                            </div>
                        </template>

                        <button @click="currentPage++" :disabled="currentPage === totalPages"
                            class="pagination-btn disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                    <span class="text-[11px] text-gray-400 font-medium"
                        x-text="`${(currentPage - 1) * perPage + 1}–${Math.min(currentPage * perPage, filteredSubjects.length)} of ${filteredSubjects.length}`">
                    </span>
                </div>

            </div>
        </main>
    </div>

    {{-- ── VIEW MENTORS MODAL ── --}}
    <template x-teleport="body">
        <div class="modal-overlay" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
                <template x-if="selectedSubject">
                    <div class="contents">
                        <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100 flex-shrink-0">
                            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                                <i class="fa-solid fa-book"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h2 class="text-xl font-black text-slate-800 tracking-tight mb-0.5 truncate" x-text="selectedSubject.code"></h2>
                                <p class="text-xs text-slate-500 font-medium truncate" x-text="selectedSubject.name"></p>
                            </div>
                            <button @click="showViewModal = false" class="text-gray-400 hover:text-red-600 transition flex-shrink-0">
                                <i class="fa-solid fa-xmark text-xl"></i>
                            </button>
                        </div>

                        <div class="overflow-y-auto flex-1 p-6 bg-white">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Registered Peer Mentors</p>
                            <div class="space-y-3">
                                <template x-for="mentor in selectedSubject.mentors" :key="mentor.email">
                                    <div class="flex items-center gap-4 bg-white border border-gray-100 shadow-sm rounded-xl p-3 hover:border-gray-200 transition">
                                        <img :src="mentor.avatar" class="w-12 h-12 rounded-full object-cover shadow-sm bg-gray-100 border border-gray-200">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-bold text-slate-800 truncate" x-text="mentor.name"></p>
                                            <p class="text-xs text-gray-500 truncate" x-text="mentor.email"></p>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="selectedSubject.mentors.length === 0">
                                    <div class="text-center py-8">
                                        <div class="w-12 h-12 bg-gray-50 text-gray-300 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="fa-solid fa-user-slash text-lg"></i>
                                        </div>
                                        <p class="text-sm font-medium text-gray-500">No mentors registered.</p>
                                        <p class="text-xs text-gray-400 mt-1">There are currently no peer mentors assigned to teach this subject.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- ── ADD SUBJECT MODAL ── --}}
    <div x-show="showSubjectModal" x-cloak class="modal-overlay" wire:ignore.self x-data="{ isVerifying: false }">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" @click.stop>
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-book-medical"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Add New Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">This subject will become available for mentor assignments.</p>
                </div>
                <button type="button" @click="showSubjectModal = false; $wire.resetSubjectForm()" x-bind:disabled="isVerifying" class="text-gray-400 hover:text-red-600 transition ml-2 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
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

            <div class="px-6 py-5 border-t border-gray-100">
                <div class="flex gap-3">
                    <button type="button" @click="showSubjectModal = false; $wire.resetSubjectForm()"
                        x-bind:disabled="isVerifying"
                        class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 rounded-xl transition disabled:cursor-not-allowed">
                        Cancel
                    </button>
                    <button type="button"
                        @click="isVerifying = true; $wire.validateSubject().finally(() => isVerifying = false)"
                        x-bind:disabled="isVerifying"
                        class="flex-1 bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-emerald-700 transition disabled:cursor-not-allowed">
                        <span x-show="!isVerifying">Add Subject</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── EDIT SUBJECT MODAL ── --}}
    <div x-show="showEditModal" x-cloak class="modal-overlay" 
        x-data="
        { isVerifying: false, 
        // Check if there are changes to any input
        get hasChanges() {
            if (originalForm.code !== editForm.code) return true;
            if (originalForm.name !== editForm.name) return true;
            
            return false;
            }
        }">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" @click.stop>
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Edit Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">Update the course code or the descriptive name.</p>
                </div>
                <button type="button" @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition ml-2 disabled:cursor-not-allowed" x-bind:disabled="isVerifying">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="form-label">Subject Code <span class="text-red-500">*</span></label>
                    <input type="text" x-model="editForm.code" class="form-input" />
                    @error('editSubjectCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Subject Name <span class="text-red-500">*</span></label>
                    <input type="text" x-model="editForm.name" class="form-input" />
                    @error('editSubjectName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="px-6 py-5 bg-gray-50 border-t border-gray-100">
                <div class="flex gap-3">
                    <button type="button" @click="showEditModal = false; $wire.closeEditModal()"
                        class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 rounded-xl transition disabled:cursor-not-allowed" x-bind:disabled="isVerifying">
                        Cancel
                    </button>
                    <button type="button"
                        @click="if (hasChanges) { isVerifying = true; $wire.validateEditSubject(editingSubject.id, editForm.code, editForm.name).finally(() => isVerifying = false) }"
                        x-bind:disabled="isVerifying || !hasChanges"
                        class="flex-1 bg-blue-500 text-white py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-blue-600 transition"
                        :class="(!hasChanges || isVerifying) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-600'">
                        <span x-show="!isVerifying">Save Changes</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Verifying...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRMATION MODAL ── --}}
    <div id="confirmModal" style="display:none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
            <div class="flex justify-end gap-3 mt-6">
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-300 hover:text-gray-800 rounded-lg transition-colors">Cancel</button>
                <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
            </div>
        </div>
    </div>

</div>

<script>
    /* ── Sidebar & Profile Dropdown (vanilla, outside Alpine scope) ── */
    // document.addEventListener('DOMContentLoaded', () => {
    //     document.getElementById('sidebarToggle').addEventListener('click', () => {
    //         document.getElementById('sidebar').classList.toggle('collapsed');
    //     });

    //     const profileTrigger  = document.getElementById('profileTrigger');
    //     const profileDropdown = document.getElementById('profileDropdown');

    //     profileTrigger.addEventListener('click', e => {
    //         e.stopPropagation();
    //         profileDropdown.classList.toggle('show');
    //     });

    //     window.addEventListener('click', () => {
    //         profileDropdown.classList.remove('show');
    //         document.getElementById('mentorDropdown')?.classList.add('hidden');
    //     });
    // });

    /* ── Mentor filter dropdown (vanilla, syncs with Alpine via custom event) ── */
    let activeMentorFilters = [];

    function toggleMentorDropdown(e) {
        e.stopPropagation();
        document.getElementById('mentorDropdown').classList.toggle('hidden');
    }

    function handleAllFilter(cb) {
        if (cb.checked) {
            document.querySelectorAll('.mentor-filter-cb').forEach(c => c.checked = false);
            activeMentorFilters = [];
        }
        updateMentorBadge();
        window.dispatchEvent(new CustomEvent('mentor-filter-changed', { detail: activeMentorFilters }));
    }

    function handleMentorFilter() {
        activeMentorFilters = [...document.querySelectorAll('.mentor-filter-cb:checked')].map(c => c.value);
        document.getElementById('filterAll').checked = activeMentorFilters.length === 0;
        updateMentorBadge();
        window.dispatchEvent(new CustomEvent('mentor-filter-changed', { detail: activeMentorFilters }));
    }

    function updateMentorBadge() {
        const badge = document.getElementById('mentorBadge');
        if (activeMentorFilters.length > 0) {
            badge.textContent = activeMentorFilters.length;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
/* ── Banners ── */
function showCourseBanner(id, html) {
    const area = document.getElementById('coursesBannerArea');
    if (!area) return;
    let banner = document.getElementById(id);
    if (!banner) {
        banner = document.createElement('div');
        banner.id = id;
        banner.style.cssText = 'border-radius:8px; overflow:hidden; font-size:11px; animation:slideDown 0.2s ease; margin-bottom:4px;';
        area.appendChild(banner);
    }
    banner.innerHTML = html;
    clearTimeout(banner._timer);
    if (id !== 'courseLoadingBanner') {
        banner._timer = setTimeout(() => banner.remove(), 5000);
    }
}

function showCourseLoadingBanner(message) {
    showCourseBanner('courseLoadingBanner', `
        <div style="border:1px solid #bfdbfe; background:#eff6ff; border-radius:8px;">
            <div style="display:flex; align-items:center; gap:8px; padding:10px 12px;">
                <div style="flex-shrink:0;">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;">
                        <circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/>
                        <path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <div style="flex:1; color:#1d4ed8; line-height:1.5; font-size:11px;">
                    <span style="font-weight:600;">${message}</span>
                </div>
            </div>
        </div>
    `);
    const banner = document.getElementById('courseLoadingBanner');
    if (banner) clearTimeout(banner._timer);
}

function hideCourseLoadingBanner() {
    const banner = document.getElementById('courseLoadingBanner');
    if (banner) banner.remove();
}

function showCourseErrorBanner(message) {
    showCourseBanner('courseErrorBanner', `
        <div style="border:1px solid #fca5a5; background:#fef2f2; border-radius:8px;">
            <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                <div style="flex-shrink:0; margin-top:2px;">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                        <circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/>
                        <path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/>
                        <circle cx="8" cy="11" r="0.75" fill="#ef4444"/>
                    </svg>
                </div>
                <div style="flex:1; color:#b91c1c; line-height:1.5;">
                    <span style="font-weight:600;">Error —</span> ${message}
                </div>
                <button onclick="document.getElementById('courseErrorBanner').remove()"
                    style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
            </div>
        </div>
    `);
}
    /* ── Confirmation Modal ── */
    function closeConfirmModal() {
        document.getElementById('confirmModal').style.display = 'none';
        document.getElementById('confirmOkBtn').onclick = null;
    }

    function openConfirmModal({ title, body, meta, variant, confirmText, loadingText, onConfirm }) {
        const confirmModal     = document.getElementById('confirmModal');
        const confirmModalBox  = document.getElementById('confirmModalBox');
        const confirmTitle     = document.getElementById('confirmTitle');
        const confirmBody      = document.getElementById('confirmBody');
        const confirmMeta      = document.getElementById('confirmMeta');
        const confirmOkBtn     = document.getElementById('confirmOkBtn');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmIconWrap  = document.getElementById('confirmIconWrap');

        confirmModal.onclick = (e) => { if (!confirmModalBox.contains(e.target)) closeConfirmModal(); };
        confirmCancelBtn.onclick = closeConfirmModal;

        const variants = {
            accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700' },
            reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700'         },
            neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800'       },
        };
        const v = variants[variant] || variants.neutral;

        confirmIconWrap.style.background = v.iconBg;
        confirmIconWrap.innerHTML        = v.iconHtml;
        confirmTitle.textContent         = title;
        confirmBody.innerHTML            = body;
        confirmMeta.innerHTML            = meta || '';
        confirmMeta.style.display        = meta ? 'block' : 'none';

        confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
        confirmOkBtn.textContent = confirmText || 'Confirm';

confirmOkBtn.onclick = async () => {
    const originalText = confirmOkBtn.textContent;
    confirmOkBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${loadingText || 'Processing...'}`;
    confirmOkBtn.classList.add('opacity-70', 'cursor-not-allowed');
    confirmOkBtn.style.pointerEvents = 'none';
    confirmCancelBtn.disabled = true;
    confirmCancelBtn.classList.add('opacity-50', 'cursor-not-allowed');

    showCourseLoadingBanner(loadingText || 'Processing, please wait...');

    try {
        const result = onConfirm();
        if (result && typeof result.then === 'function') await result;
    } catch (err) {
        hideCourseLoadingBanner();
        showCourseErrorBanner('Something went wrong. Please try again.');
    } finally {
        confirmOkBtn.textContent = originalText;
        confirmOkBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        confirmOkBtn.style.pointerEvents = 'auto';
        confirmCancelBtn.disabled = false;
        confirmCancelBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        closeConfirmModal();
    }
};
        confirmModal.style.display = 'flex';
    }

    function iconCheck(color) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function iconX(color)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function iconInfo(color)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/><path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${color}"/></svg>`; }

    /* ── Alpine.js component ── */
    function courseManagement(initialSubjects) {
        return {
            subjects: initialSubjects,

            // Search & filter
            searchQuery: '',
            mentorFilter: [],   // synced from vanilla checkbox logic above

            // Sort
            sortColumn: 'code',
            sortDirection: 'asc',

            // Pagination
            currentPage: 1,
            perPage: 10,

            // Profile
            profileOpen: false,
            sidebarCollapsed: false,

            // Modals
            showViewModal: false,
            showSubjectModal: false,
            selectedSubject: null,
            showEditModal: false,
            editingSubject: null,
            editForm: { code: '', name: '' },
            originalForm: { code: '', name: '' },

            init() {
                // Listen for mentor filter changes from vanilla checkboxes
                window.addEventListener('mentor-filter-changed', (e) => {
                    this.mentorFilter = e.detail;
                    this.currentPage = 1;
                });

                // To allow sidebar and profile dropdown to keep current states
                window.addEventListener('click', () => {
                    this.profileOpen = false;
                    document.getElementById('mentorDropdown')?.classList.add('hidden');
                });
            },

            setSort(column) {
                if (this.sortColumn === column) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                this.currentPage = 1;
            },

            get filteredSubjects() {
                const term = this.searchQuery.toLowerCase();

                let result = this.subjects.filter(s => {
                    const matchSearch = (s.code + ' ' + s.name).toLowerCase().includes(term);
                    const matchFilter = this.mentorFilter.length === 0
                        || (this.mentorFilter.includes('with_mentors') && s.mentorCount > 0)
                        || (this.mentorFilter.includes('no_mentors')   && s.mentorCount === 0);
                    return matchSearch && matchFilter;
                });

                // Sort
                result = [...result].sort((a, b) => {
                    let valA = a[this.sortColumn];
                    let valB = b[this.sortColumn];
                    if (typeof valA === 'string') { valA = valA.toLowerCase(); valB = valB.toLowerCase(); }
                    if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                    if (valA > valB) return this.sortDirection === 'asc' ?  1 : -1;
                    return 0;
                });

                return result;
            },

            get paginatedSubjects() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.filteredSubjects.slice(start, start + this.perPage);
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredSubjects.length / this.perPage));
            },

            get pages() {
                const total   = this.totalPages;
                const current = this.currentPage;
                if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
                if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
                if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
                return [1, '...', current - 1, current, current + 1, '...', total];
            },

            openViewModal(sub) {
                this.selectedSubject = sub;
                this.showViewModal = true;
            },

            openEditModal(sub) {
                this.editingSubject = sub;
                this.editForm.code  = sub.code;
                this.editForm.name  = sub.name;
                this.originalForm = {
                    code: this.editForm.code,
                    name: this.editForm.name
                };
                this.showEditModal  = true;
            },

            openDeleteModal(sub) {
                openConfirmModal({
                    title: 'Delete Subject?',
                    body:  `Are you sure you want to permanently delete <strong>${sub.code}</strong>? This will also remove the subject from all mentors currently assigned to teach it.`,
                    variant: 'reject',
                    confirmText: 'Delete',
                    loadingText: 'Deleting...',
                    onConfirm: async () => { await this.$wire.deleteSubject(sub.id); }
                });
            },
        };
    }
</script>
