<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use App\Models\Subjects;
use App\Models\MentorSubjects;
use Illuminate\Validation\Rule;
use App\Services\Avatar;

layout('layouts.app');

state([
    // Form States
    'showSubjectModal' => false,
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


// Add subject
$openSubjectModal = action(function () {
    $this->reset(['newSubjectCode', 'newSubjectName']);
    $this->showSubjectModal = true;
});

$closeSubjectModal = action(function () {
    $this->showSubjectModal = false;
    $this->reset(['newSubjectCode', 'newSubjectName', 'showSubjectModal']);
});

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

$saveSubject = action(function () {

    Subjects::create([
        'code' => trim($this->newSubjectCode),
        'name' => trim($this->newSubjectName),
    ]);
    session()->flash('successMessage', "{$this->newSubjectCode} has been successfully added.");
    $this->closeSubjectModal();
    $this->redirect(route('admin.courses'), navigate: true);
});


// Edit subject
$closeEditModal = action(function () {
    $this->showEditModal = false;
    $this->reset(['editSubjectId', 'editSubjectCode', 'editSubjectName', 'showEditModal']);
});

$updateSubject = action(function ($id, $code, $name) {

    $subject = Subjects::findOrFail($this->editSubjectId);
    $subject->update([
        'code' => trim($this->editSubjectCode),
        'name' => trim($this->editSubjectName),
    ]);
    session()->flash('successMessage', "{$this->editSubjectCode} has been successfully updated.");
    $this->redirect(route('admin.courses'), navigate: true);
});


// Delete subject
$deleteSubject = action(function ($id) {
    $subject = Subjects::findOrFail($id);
    $code = $subject->code;

    // Clear pivot table relationships before deleting
    MentorSubjects::where('subject_id', $subject->id)->delete();
    $subject->delete();

    session()->flash('successMessage', "{$code} has been successfully removed.");
    $this->redirect(route('admin.courses'), navigate: true);
});

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

?>

<div class="livewire-root-scope" 
     x-data="courseManagement(@js($this->allSubjects), $wire)"
     @validation-passed.window="openConfirmModal({
        title: 'Confirm New Subject',
        body: 'Are you sure you want to add this subject? This will become a teachable subject.',
        variant: 'accept',
        confirmText: 'Save Subject',
        loadingText: 'Saving...',
        onConfirm: async () => { 
            await $wire.saveSubject(); 
            $wire.showSubjectModal = false; 
        }
     })"
     @edit-validation-passed.window="openConfirmModal({
        title: 'Update Subject?',
        body: 'Are you sure you want to save the changes made to this subject?',
        variant: 'accept',
        confirmText: 'Save Changes',
        loadingText: 'Saving...',
        onConfirm: async () => { 
            await $wire.updateSubject(editingSubject.id, editForm.code, editForm.name);
            showEditModal = false; 
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
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }

        /* ── SIDEBAR ── */
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-green); flex-shrink: 0; display: flex; flex-direction: column; color: white; height: 100vh; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 30; position: relative; overflow: visible; }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        .sidebar-logo-container { height: var(--header-height); display: flex; align-items: center; justify-content: center; padding: 0 20px; gap: 12px; flex-shrink: 0; overflow: hidden; transition: padding 0.3s, justify-content 0.3s; }
        .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }
        .logo-icon { flex-shrink: 0; font-size: 27px; width: auto; text-align: center; }
        .logo-text { font-size: 1.24rem; font-weight: 700; white-space: nowrap; overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }
        .sidebar.collapsed .sidebar-logo-container { justify-content: center; padding: 0; width: 100%; }
        .sidebar.collapsed .logo-content { gap: 0; justify-content: center; width: 100%; }

        .nav-item { display: flex; align-items: center; gap: 14px; padding: 16px 20px; color: rgba(255,255,255,0.7); text-decoration: none; transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s; white-space: nowrap; position: relative; text-align: left; background: transparent; border: none; width: 100%; cursor: pointer; font-size: 0.95rem; justify-content: flex-start; }
        .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 22px; transition: width 0.3s; }
        .nav-item span { overflow: hidden; opacity: 1; max-width: 200px; transition: opacity 0.2s, max-width 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { background: var(--bg-light); color: var(--header-maroon); font-weight: 700; border-radius: 0; width: calc(100% + 1px); z-index: 10; }

        .sidebar.collapsed .nav-item { display: flex; align-items: center; justify-content: center; padding: 16px 0; width: 100%; gap: 0; }
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; text-align: center; flex-shrink: 0; font-size: 22px;}
        .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

        .nav-item::after { content: attr(data-tooltip); position: absolute; left: 100%; top: 50%; transform: translateY(-50%); margin-left: 14px; background: rgba(0,0,0,0.85); color: white; padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500; white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s; pointer-events: none; z-index: 100; }
        .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

        .sidebar-footer { padding: 0; border-top: 1px solid rgba(255,255,255,0.1); }

        .sidebar-toggle-btn { position: absolute; right: -16px; top: 50%; width: 32px; height: 32px; border-radius: 50%; background: var(--header-maroon); border: 2px solid white; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; z-index: 50; box-shadow: 0 2px 8px rgba(0,0,0,0.25); transition: background 0.2s; flex-shrink: 0; }
        .sidebar-toggle-btn:hover { background: #dfcece; }
        .sidebar-toggle-btn .toggle-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; justify-content: center; }
        .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; position: relative; }
        
        .profile-dropdown { position: absolute; top: 75px; right: 40px; background: white; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none; flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden; }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; text-decoration: none; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
        .form-input:focus { border-color: var(--header-maroon); }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
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
            <header class="top-header">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group text-slate-800">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500" id="dropdownArrow"></i>
                </button>

                <div id="profileDropdown" class="profile-dropdown">
                    <div class="p-4 border-b border-gray-100 bg-slate-50 text-slate-800">
                        <p class="text-[11px] font-bold text-gray-400 uppercase mb-1">Signed in as</p>
                        <p class="text-sm font-bold truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>

                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0 border-t border-gray-50">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-red-600 font-semibold bg-transparent border-none cursor-pointer">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="scroll-container">
                
                {{-- Success Message --}}
                @if(session('successMessage'))
                    <div class="mb-6 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-xl">
                        {{ session('successMessage') }}
                    </div>
                @endif

                <div class="flex justify-between items-end mb-8">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">Course Management</h1>
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">LRC Registry of Subjects</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="relative">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="text" x-model="searchQuery" @input="currentPage = 1"  placeholder="Search subjects..." class="pl-8 pr-3 py-2 h-12 text-xs border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-64">
                        </div>
                        
                        <button wire:click="openSubjectModal" @click="$wire.showSubjectModal = true" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-black transition shadow-lg">
                            <i class="fa-solid fa-book-medical"></i> Add New Subject
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <table class="w-full text-left table-fixed">
                        <thead class="bg-white border-b uppercase text-[10px] font-bold text-gray-400 tracking-widest">
                            <tr>
                                <th class="px-6 py-5 w-[30%]">Subject Code</th>
                                <th class="px-6 py-5 w-[40%]">Subject Name</th>
                                <th class="px-6 py-5 w-[20%] text-center">Registered Mentors</th>
                                <th class="px-6 py-5 w-[10%] text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="sub in paginatedSubjects" :key="sub.id">
                                <tr class="hover:bg-gray-50 transition group">
                                    <td class="px-6 py-5">
                                        <span class="font-bold text-slate-800 text-sm truncate block" x-text="sub.code"></span>
                                    </td>
                                    
                                    <td class="px-6 py-5 text-slate-600 text-sm">
                                        <span class="truncate block" :title="sub.name" x-text="sub.name"></span>
                                    </td>
                                    
                                    <td class="px-6 py-5 text-center">
                                        <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-100" x-text="sub.mentorCount + ' Mentors'"></span>
                                    </td>
                                    
                                    <td class="px-6 py-5">
                                        <div class="flex gap-2 justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <button class="w-8 h-8 rounded-lg text-slate-600 hover:text-slate-800 transition" title="View Mentors" @click="openViewModal(sub)"><i class="fa-solid fa-eye text-[10px]"></i></button>
                                            <button class="w-8 h-8 rounded-lg text-slate-600 hover:text-blue-600 transition flex-shrink-0" title="Edit Subject" @click="openEditModal(sub)"><i class="fa-solid fa-pen text-[10px]"></i></button>
                                            <button class="w-8 h-8 rounded-lg text-slate-600 hover:text-red-600 transition" title="Remove Subject" @click="openDeleteModal(sub)"><i class="fa-solid fa-trash text-[10px]"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredSubjects.length === 0" x-cloak>
                                <td colspan="5" class="px-6 py-10 text-center text-sm italic text-gray-500">
                                    No subjects match your search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-4 flex justify-center items-center gap-2" x-show="totalPages >= 1" x-cloak>
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


                {{-- List of mentors --}}
                <template x-teleport="body">
                    <div class="modal-overlay" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
                        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="max-height: 90vh;">
                            <template x-if="selectedSubject">
                                <div class="contents">
                                    {{-- Header --}}
                                    <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100 flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                                            <i class="fa-solid fa-book"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h2 class="text-xl font-black text-slate-800 tracking-tight mb-0.5 truncate" x-text="selectedSubject.code"></h2>
                                            <p class="text-xs text-slate-500 font-medium truncate" x-text="selectedSubject.name"></p>
                                        </div>
                                        <button @click="showViewModal = false" class="text-white/50 hover:text-white transition flex-shrink-0 mt-1">
                                            <i class="fa-solid fa-xmark text-xl"></i>
                                        </button>
                                    </div>

                                    {{-- Mentors List --}}
                                    <div class="overflow-y-auto flex-1 p-6 bg-white">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Registered Peer Mentors</p>
                                        
                                        <div class="space-y-3">
                                            <template x-for="mentor in selectedSubject.mentors" :key="mentor.email">
                                                <div class="flex items-center gap-4 bg-white border border-gray-100 shadow-sm rounded-xl p-3 hover:border-gray-200 transition">
                                                    <img :src="mentor.avatar" class="w-12 h-12 rounded-full object-cover shadow-sm bg-gray-100 border border-gray-200">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-bold text-slate-800 truncate" :title="mentor.name" x-text="mentor.name"></p>
                                                        <p class="text-xs text-gray-500 truncate" :title="mentor.email" x-text="mentor.email"></p>
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

            </main>
        </div>
    </div>

    {{-- Subject Modal --}}
    <div x-show="$wire.showSubjectModal" x-cloak class="modal-overlay">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-book-medical"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Add New Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">This subject will become available for mentor assignments.</p>
                </div>
                <button type="button" @click="$wire.showSubjectModal = false; $wire.closeSubjectModal()" class="text-gray-400 hover:text-red-600 transition ml-2">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
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

            {{-- Save button --}}
            <div class="px-6 py-5 bg-gray-50 border-t border-gray-100">
                <div class="flex gap-3">
                    <button type="button" @click="$wire.showSubjectModal = false; $wire.closeSubjectModal()" class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="button" 
                            @click="$wire.validateSubject"
                            wire:loading.attr="disabled"
                            wire:target="validateSubject"
                            class="flex-1 bg-slate-800 text-white py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-black transition">
                        <span wire:loading.remove wire:target="validateSubject">Add Subject</span>
                        <span wire:loading wire:target="validateSubject"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Verifying...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- Edit Subject Modal --}}
    <div x-show="showEditModal" x-cloak class="modal-overlay">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Edit Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">Update the course code or the descriptive name.</p>
                </div>
                <button type="button" @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition ml-2">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            {{-- Body --}}
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

            {{-- Save button --}}
            <div class="px-6 py-5 bg-gray-50 border-t border-gray-100">
                <div class="flex gap-3">
                    <button type="button" @click="showEditModal = false; $wire.closeEditModal()" class="flex-1 py-2.5 text-sm font-bold text-gray-600 bg-white border border-gray-200 hover:bg-gray-100 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="button" 
                            @click="$wire.validateEditSubject(editingSubject.id, editForm.code, editForm.name)"
                            wire:loading.attr="disabled"
                            wire:target="validateEditSubject"
                            class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center">
                        
                        <span wire:loading.remove wire:target="validateEditSubject">Save Changes</span>
                        <span wire:loading wire:target="validateEditSubject" class="items-center justify-center gap-1">
                            <i class="fa-solid fa-spinner fa-spin mr-1"></i> Verifying...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>


    <div id="confirmModal" style="display:none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-[#ffffff] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
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


    <script>
        window.onclick = (e) => {
            const profileDropdown = document.getElementById('profileDropdown');
            const dropdownArrow = document.getElementById('dropdownArrow');
            
            // Handle Profile Dropdown Trigger
            const trigger = e.target.closest('#profileTrigger');
            if (trigger) {
                e.stopPropagation();
                if (profileDropdown) {
                    const isShown = profileDropdown.classList.toggle('show');
                    if (dropdownArrow) dropdownArrow.style.transform = isShown ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                return;
            }

            // Clicked outside profile dropdown
            if (profileDropdown && profileDropdown.classList.contains('show')) {
                profileDropdown.classList.remove('show');
                if (dropdownArrow) dropdownArrow.style.transform = 'rotate(0deg)';
            }
            
            // Handle Sidebar Toggle
            const sidebarToggle = e.target.closest('#sidebarToggle');
            if (sidebarToggle) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.toggle('collapsed');
            }
        };

        /* ── CONFIRMATION MODAL ── */
        function closeConfirmModal() { 
            const confirmModal = document.getElementById('confirmModal');
            const confirmOkBtn = document.getElementById('confirmOkBtn');
            
            if (confirmModal) confirmModal.style.display = 'none'; 
            if (confirmOkBtn) confirmOkBtn.onclick = null; 
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
            confirmOkBtn.textContent = confirmText || v.label;
            
            confirmOkBtn.onclick = async () => { 
                const originalText = confirmOkBtn.textContent;
                confirmOkBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${loadingText || 'Processing...'}`;
                confirmOkBtn.classList.add('opacity-70', 'cursor-not-allowed');
                confirmOkBtn.style.pointerEvents = 'none';
                
                confirmCancelBtn.disabled = true;
                confirmCancelBtn.classList.add('opacity-50', 'cursor-not-allowed');

                try {
                    const result = onConfirm();
                    if (result && typeof result.then === 'function') {
                        await result;
                    }
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

        function courseManagement(initialSubjects) {
            return {
                subjects: initialSubjects,
                searchQuery: '',
                currentPage: 1,
                perPage: 5,
                showViewModal: false,
                selectedSubject: null,
                showEditModal: false,
                editingSubject: null,
                initialEditState: '',

                editForm: {
                    code: '',
                    name: ''
                },

                get filteredSubjects() {
                    const term = this.searchQuery.toLowerCase();
                    return this.subjects.filter(sub => {
                        return sub.code.toLowerCase().includes(term) || sub.name.toLowerCase().includes(term);
                    });
                },

                get paginatedSubjects() {
                    const start = (this.currentPage - 1) * this.perPage;
                    return this.filteredSubjects.slice(start, start + this.perPage);
                },

                get totalPages() {
                    return Math.ceil(this.filteredSubjects.length / this.perPage) || 1;
                },

                get pages() {
                    const total = this.totalPages;
                    const current = this.currentPage;

                    if(total <= 8) {
                        return Array.from({ length: total }, (_, i) => i + 1);
                    }
                    if(current <= 4) {
                        return [1, 2, 3, 4, 5, '...', total];
                    }
                    if(current >= total - 3) {
                        return [1, '...', total - 3, total - 2, total - 1, total];
                    }
                    return [1, '...', current - 1, current, current + 1, '...', total];
                },

                openViewModal(sub) {
                    this.selectedSubject = sub;
                    this.showViewModal = true;
                },

                openEditModal(sub) {
                    this.editingSubject = sub;
                    this.editForm.code = sub.code;
                    this.editForm.name = sub.name;
                    
                    this.initialEditState = JSON.stringify(this.editForm);
                    this.showEditModal = true;
                },

                openDeleteModal(sub) {
                    openConfirmModal({
                        title: 'Delete Subject?',
                        body: `Are you sure you want to permanently delete <strong>${sub.code}</strong>? This will also remove the subject from all mentors currently assigned to teach it.`,
                        variant: 'reject',
                        confirmText: 'Delete',
                        loadingText: 'Deleting...',
                        onConfirm: async () => { await this.$wire.deleteSubject(sub.id); }
                    });
                }
            };
        }
    </script>