<?php

use function Livewire\Volt\{layout, state, mount, computed, action, uses};
use App\Models\Subjects;
use App\Models\MentorSubjects;
use Illuminate\Validation\Rule;
use App\Services\Avatar;

state([
    'newSubjectCode' => '',
    'newSubjectName' => '',
    'showEditModal' => false,
    'editSubjectId' => null,
    'editSubjectCode' => '',
    'editSubjectName' => '',
]);

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

$resetSubjectForm = action(function () {
    $this->reset(['newSubjectCode', 'newSubjectName']);
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

$saveSubject = action(function () {
    Subjects::create([
        'code' => trim($this->newSubjectCode),
        'name' => trim($this->newSubjectName),
    ]);
    session()->flash('successMessage', "{$this->newSubjectCode} has been successfully added.");
    $this->redirect(route('admin.courses'), navigate: true);
});

$closeEditModal = action(function () {
    $this->showEditModal = false;
    $this->reset(['editSubjectId', 'editSubjectCode', 'editSubjectName', 'showEditModal']);
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

$updateSubject = action(function () {
    $subject = Subjects::findOrFail($this->editSubjectId);
    $subject->update([
        'code' => trim($this->editSubjectCode),
        'name' => trim($this->editSubjectName),
    ]);
    session()->flash('successMessage', "{$this->editSubjectCode} has been successfully updated.");
    $this->redirect(route('admin.courses'), navigate: true);
});

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

<div x-data="courseManagement(@js($this->allSubjects), $wire)"
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
         variant: 'edit',
         confirmText: 'Save Changes',
         loadingText: 'Saving...',
         onConfirm: async () => {
             showEditModal = false;
             await $wire.updateSubject();
         }
     })">

    {{-- Page heading --}}
    <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                Course Management
            </h1>
            <p class="text-sm font-medium text-slate-500 mt-1">LRC Registry of Subjects</p>
        </div>
    </div>

    {{-- SUBJECTS TABLE --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-visible">

        {{-- Table header / filters --}}
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-m">All Subjects</h2>
                <p class="text-xs text-gray-400 font-medium" x-text="filteredSubjects.length + (filteredSubjects.length === 1 ? ' Subject' : ' Subjects') 
                    + ' found'"></p>
            </div>
            <div class="flex gap-2 items-center flex-wrap">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" x-model="searchQuery" @input="currentPage = 1" placeholder="Search subjects..."
                        class="pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white 
                            outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                </div>

                <button x-data="{ isOpening: false }" 
                    @click="isOpening = true; $wire.resetSubjectForm().then(() => { showSubjectModal = true; isOpening = false; })"
                    x-bind:disabled="isOpening"
                    class="flex items-center justify-center bg-slate-800 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-black transition shadow-sm h-[34px] w-[125px] disabled:opacity-75 disabled:cursor-not-allowed">
                    
                    <span x-show="!isOpening" class="flex items-center gap-2">
                        <i class="fa-solid fa-book-medical text-[11px]"></i> Add Subject
                    </span>
                    
                    <span x-show="isOpening" style="display: none;" class="flex items-center gap-1.5">
                        <i class="fa-solid fa-spinner fa-spin text-[11px]"></i> Opening...
                    </span>
                    
                </button>
            </div>
        </div>

        {{-- Success Message --}}
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

        {{-- SUBJECTS CONTENT --}}
        <div style="overflow:visible;">
            <table class="w-full text-left text-sm table-fixed" style="overflow:visible;">
                <thead class="bg-slate-50 border-b border-gray-100">
                    <tr>
                        <th @click="setSort('code')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-
                            none" style="width:20;">
                            <div class="flex items-center gap-1 hover:text-red-800 transition">
                                Subject Code
                                <span x-text="sortColumn === 'code' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                            </div>
                        </th>
                        <th @click="setSort('name')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-
                            none" style="width:32%;">
                            <div class="flex items-center gap-1 hover:text-red-800 transition">
                                Subject Name
                                <span x-text="sortColumn === 'name' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                            </div>
                        </th>
                        <th @click="setSort('mentorCount')" class="cursor-pointer px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider 
                            select-none text-center" style="width:28%;">
                            <div class="flex items-center justify-center gap-1 hover:text-red-800 transition">
                                Registered Mentors
                                <span x-text="sortColumn === 'mentorCount' ? (sortDirection === 'asc' ? '↑' : '↓') : ''" class="text-[10px]"></span>
                            </div>
                        </th>
                        <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider select-none text-center" 
                            style="width:13%;">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                        <template x-for="sub in paginatedSubjects" :key="sub.id">
                            <tr class="courses-row hover:bg-slate-50 transition">
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
                                    class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-100 cursor-pointer 
                                    hover:bg-blue-100 transition whitespace-nowrap"
                                    x-text="sub.mentorCount + (sub.mentorCount === 1 ? ' Mentor' : ' Mentors')">
                                </span>
                            </td>
                            <td class="px-5 py-4 align-middle text-center" style="width:14%;">
                                <div class="relative flex items-center justify-center flex-wrap" style="min-height:28px;">
                                    
                                    <div class="action-idle absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <span class="w-2 h-2 rounded-full bg-slate-300 inline-block"></span>
                                    </div>

                                    <div class="action-buttons flex items-center justify-center flex-wrap gap-1">
                                        
                                        <div class="hover-tooltip" data-full="View Mentors">
                                            <button @click="openViewModal(sub)" class="icon-btn icon-btn-view" style="flex-shrink:0;">
                                                <i class="fa-solid fa-eye" style="font-size:11px;"></i>
                                            </button>
                                        </div>

                                        <div class="hover-tooltip" data-full="Edit Subject">
                                            <button @click="openEditModal(sub)" class="icon-btn icon-btn-edit" style="flex-shrink:0;">
                                                <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                                            </button>
                                        </div>

                                        <div class="hover-tooltip" data-full="Delete Subject">
                                            <button @click="openDeleteModal(sub)" class="icon-btn icon-btn-delete" style="flex-shrink:0;">
                                                <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                                            </button>
                                        </div>
                                        
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredSubjects.length === 0" x-cloak>
                        <td colspan="4" class="text-center py-16 text-gray-400 text-xs italic">No subjects found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
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
                x-text="filteredSubjects.length === 0 ? '' : pageStart + '–' + pageEnd + ' of ' + filteredSubjects.length">
            </span>
        </div>
    </div>

    {{-- ── VIEW MENTORS MODAL ── --}}
    <template x-teleport="body">
        <div class="modal-overlay" style="display: none;" x-show="showViewModal" @click.self="showViewModal = false" x-cloak>
            <div class="modal-box-crud max-w-md flex flex-col" style="max-height: 90vh;">
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
                                    <div class="flex items-center gap-4 bg-white border border-gray-100 shadow-sm rounded-xl p-3 hover:border-gray-200 
                                        transition">
                                        <img :src="mentor.avatar" class="w-12 h-12 rounded-full object-cover shadow-sm bg-gray-100 border 
                                        border-gray-200">
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
<div x-cloak class="modal-overlay" style="display: none;" x-show="showSubjectModal" wire:ignore.self
    x-data="{
        isVerifying: false,
        subjectCode: '',
        subjectName: '',
        get canSubmit() {
            return this.subjectCode.trim() !== '' && this.subjectName.trim() !== '';
        },
        init() {
            this.$watch('$wire.newSubjectCode', v => this.subjectCode = v ?? '');
            this.$watch('$wire.newSubjectName', v => this.subjectName = v ?? '');
        }
    }">        <div class="modal-box-crud max-w-md" @click.stop>
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-book-medical"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Add New Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">This subject will become available for mentor assignments.</p>
                </div>
                <button type="button" @click="showSubjectModal = false; $wire.resetSubjectForm()" x-bind:disabled="isVerifying" class="text-gray-400 
                    hover:text-red-600 transition ml-2 disabled:cursor-not-allowed">
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
                        class="btn-modal btn-modal-cancel">
                        Cancel
                    </button>
                    <button type="button"
                        @click="isVerifying = true; $wire.validateSubject().finally(() => isVerifying = false)"
                        x-bind:disabled="isVerifying || !canSubmit"
                        :class="(isVerifying || !canSubmit) ? 'opacity-50 cursor-not-allowed' : ''"
                        class="btn-modal btn-modal-green">
                        <span x-show="!isVerifying">Add Subject</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Validating...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── EDIT SUBJECT MODAL ── --}}
    <div x-cloak class="modal-overlay" style="display: none;" x-show="showEditModal"
         x-data="{ isVerifying: false }" wire:ignore.self>
        <div class="modal-box-crud max-w-md" @click.stop>
            <div class="flex items-center gap-4 px-6 py-5 bg-white border-b border-gray-100">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xl flex-shrink-0">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight mb-0.5">Edit Subject</h2>
                    <p class="text-xs text-slate-500 leading-snug">Update the course code or the descriptive name.</p>
                </div>
                <button type="button" @click="showEditModal = false; $wire.closeEditModal()" class="text-gray-400 hover:text-red-600 transition ml-2 
                    disabled:cursor-not-allowed" x-bind:disabled="isVerifying">
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
                        class="btn-modal btn-modal-cancel" x-bind:disabled="isVerifying">
                        Cancel
                    </button>
                    <button type="button"
                        @click="isVerifying = true; $wire.validateEditSubject(editingSubject.id, editForm.code, editForm.name).finally(() => isVerifying = false)"
                        x-bind:disabled="isVerifying || (originalForm.code === editForm.code && originalForm.name === editForm.name)"
                        class="btn-modal btn-modal-blue"
                        :class="(isVerifying || (originalForm.code === editForm.code && originalForm.name === editForm.name)) ? 'opacity-50 cursor-not-allowed' : ''">
                        <span x-show="!isVerifying">Save Changes</span>
                        <span x-show="isVerifying" style="display: none;"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Verifying...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CONFIRMATION MODAL ── --}}
    <div id="confirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-box-crud max-w-sm p-6 mx-4" id="confirmModalBox">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
            <div class="flex justify-end gap-3 mt-6">
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-300 hover:text-gray-800 
                rounded-lg transition-colors">Cancel</button>
                <button id="confirmOkBtn" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
            </div>
        </div>
    </div>
</div>