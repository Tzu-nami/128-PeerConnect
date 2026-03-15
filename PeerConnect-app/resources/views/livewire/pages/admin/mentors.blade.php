<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

state([
    'editing_index' => null,
    'surname' => '',
    'first_name' => '',
    'middle_name' => '',
    'subjects' => '',
    'up_mail' => '',
    'student_number' => ''
]);

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

$saveMentor = function () {
    $this->validate([
        'surname' => 'required|min:2',
        'first_name' => 'required|min:2',
        'up_mail' => 'required|email|ends_with:up.edu.ph',
        'student_number' => 'required|regex:/^\d{4}-\d{5}$/',
        'subjects' => 'required',
    ], [
        'up_mail.ends_with' => 'Please use a valid @up.edu.ph email.',
        'student_number.regex' => 'Format must be 20XX-XXXXX.',
    ]);

    $this->dispatch('mentor-saved-success', isEdit: $this->editing_index !== null);
    $this->reset(['surname', 'first_name', 'middle_name', 'subjects', 'up_mail', 'student_number', 'editing_index']);
};

?>

<div class="app-wrapper">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root { 
            --sidebar-green: #1a3c2f; 
            --header-maroon: #7b1d1d; 
            --bg-light: #f4f7f6; 
            --header-height: 80px; 
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
        }
        
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); overflow: hidden; }
        .app-wrapper { display: flex; height: 100vh; width: 100vw; overflow: hidden; }
        
        /* SIDEBAR (PROTECTED) */
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-green); flex-shrink: 0; display: flex; flex-direction: column; color: white; height: 100vh; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 30; position: relative; }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-logo-container { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; gap: 15px; flex-shrink: 0; overflow: hidden; }
        #sidebarToggle { background: transparent; border: none; color: white; font-size: 1.4rem; cursor: pointer; }
        .logo-content { display: flex; align-items: center; gap: 12px; white-space: nowrap; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; white-space: nowrap; position: relative; }
        .nav-item i { width: 30px; text-align: center; flex-shrink: 0; font-size: 20px; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { border-left: 4px solid white; }

        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        /* TABLE UI UPDATES */
        .mentor-card { background: white; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .table-label { font-size: 12px; font-weight: 700; color: #64748b; letter-spacing: 0.05em; text-transform: uppercase; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; overflow: hidden; transform: scale(0.95); transition: 0.2s; }
        .modal-overlay.active .modal-content { transform: scale(1); }
        
        .form-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; font-size: 13px; outline: none; }
        .form-input:focus { border-color: var(--header-maroon); box-shadow: 0 0 0 3px rgba(123, 29, 29, 0.1); }
    </style>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo-container">
                <button id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <div class="logo-content">
                    <i class="fa-solid fa-graduation-cap text-xl"></i>
                    <span class="logo-text">LRC PeerConnect</span>
                </div>
        </div>
        <nav class="flex-grow">
            <a href="{{ route('admin.dashboard') }}" class="nav-item" data-tooltip="Dashboard"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
            <a href="{{ route('admin.mentors') }}" class="nav-item active" data-tooltip="Mentors"><i class="fa-solid fa-chalkboard-user"></i><span>Mentor Management</span></a>
            <a href="{{ route('admin.sessions') }}" class="nav-item" data-tooltip="Sessions"><i class="fa-solid fa-calendar-days"></i><span>Session Management</span></a>
            <a href="{{ route('admin.feedbacks') }}" class="nav-item" data-tooltip="Feedback"><i class="fa-solid fa-comments"></i><span>Student Feedback</span></a>
        </nav>
        <div class="p-4 border-t border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-item w-full bg-transparent border-none text-left" data-tooltip="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                    </button>
                </form>
            </div>
    </aside>

    <div class="main-content">
        <header class="top-header">
    <div class="text-lg">
        Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
    </div>

    <!-- PROFILE BUTTON -->
    <button id="profileTrigger"
class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">

    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
        {{ strtoupper(substr(auth()->user()->name,0,2)) }}
    </div>

    <!-- DROPDOWN ARROW -->
    <i class="fa-solid fa-chevron-down text-gray-600 text-xs"></i>

</button>
</header>

        <main class="scroll-container">
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="text-2xl font-black text-slate-800">Mentor Management</h1>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">LRC Registry of Peer Mentors</p>
                </div>
                <button id="openModalBtn" class="bg-slate-800 text-white px-6 py-3 rounded-xl text-xs font-bold flex items-center gap-2 hover:bg-black transition shadow-lg">
                    <i class="fa-solid fa-user-plus"></i> Add New Mentor
                </button>
            </div>

            <div class="mentor-card overflow-hidden">
                <div class="p-6 bg-gray-50 border-b flex flex-wrap gap-4 justify-between items-center">
                    <div class="flex gap-3 items-center">
                        <div class="relative w-80">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" id="mentorSearch" placeholder="Search mentors..." class="w-full pl-9 pr-3 py-2.5 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800">
                        </div>
                        
                        <select id="specFilter" class="px-4 py-2.5 text-xs border border-gray-200 rounded-lg outline-none bg-white font-bold text-slate-600 focus:ring-1 focus:ring-red-800">
                            <option value="all">All Departments</option>
                            <option value="CMSC">CMSC (Computer Science)</option>
                            <option value="MATH">MATH (Mathematics)</option>
                            <option value="HIST">HIST (History)</option>
                            <option value="CHEM">CHEM (Chemistry)</option>
                            <option value="POSC">POSC (Political Science)</option>
                        </select>
                    </div>
                    <div id="rowCountDisplay" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest"></div>
                </div>

                <table class="w-full text-left text-sm">
                    <thead class="bg-white border-b">
                        <tr>
                            <th class="px-6 py-5 table-label">Mentor Information</th>
                            <th class="px-6 py-5 table-label">Specialization</th>
                            <th class="px-6 py-5 table-label text-center">Sessions</th>
                            <th class="px-6 py-5 table-label text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="mentorTableBody" class="divide-y divide-gray-50 bg-white"></tbody>
                </table>

                <div class="p-4 bg-white border-t flex justify-between items-center">
                    <div class="flex gap-1">
                        <button id="prevBtn" class="px-3 py-1 border rounded text-[10px] font-bold text-slate-700 hover:bg-gray-50 disabled:opacity-30">PREV</button>
                        <button id="nextBtn" class="px-3 py-1 border rounded text-[10px] font-bold text-slate-700 hover:bg-gray-50 disabled:opacity-30">NEXT</button>
                    </div>
                    <p id="pageIndicator" class="text-[10px] font-bold text-gray-400 uppercase"></p>
                </div>
            </div>
        </main>
    </div>

    <div id="viewMentorModal" class="modal-overlay">
        <div class="modal-content w-[750px] shadow-2xl">
            <div class="bg-slate-800 p-10 text-white relative">
                <button onclick="closeViewModal()" class="absolute top-6 right-6 text-white/50 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
                <div class="flex items-center gap-6">
                    <div id="viewInitials" class="w-20 h-20 rounded-2xl bg-white text-slate-800 flex items-center justify-center text-3xl font-black"></div>
                    <div><h2 id="viewName" class="text-3xl font-black"></h2><p id="viewEmail" class="text-white/60 font-medium"></p></div>
                </div>
            </div>
            <div class="p-10 grid grid-cols-2 gap-8 bg-white">
                <div><h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Student Number</h4><p id="viewStudentNo" class="text-slate-800 font-bold text-lg"></p></div>
                <div><h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">UP Official Mail</h4><p id="viewFullEmail" class="text-slate-800 font-bold text-lg"></p></div>
                <div class="col-span-2 p-6 bg-gray-50 rounded-2xl border border-gray-100">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Academic Subjects</h4>
                    <div id="viewSubjects" class="flex flex-wrap gap-2"></div>
                </div>
            </div>
            <div class="px-10 py-6 border-t border-gray-100 flex justify-end"><button onclick="closeViewModal()" class="bg-slate-800 text-white px-8 py-3 rounded-xl text-xs font-bold hover:bg-black transition">Close Profile</button></div>
        </div>
    </div>

    <div id="addMentorModal" class="modal-overlay" wire:ignore.self>
        <div class="modal-content w-[600px] shadow-2xl">
            <div class="bg-gray-50 px-8 py-6 border-b flex justify-between items-center">
                <h2 id="modalTitle" class="text-xl font-black text-slate-800">Add New Mentor</h2>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-red-600"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <div class="p-8">
                <form wire:submit.prevent="saveMentor" class="space-y-5">
                    <div class="grid grid-cols-3 gap-4">
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Surname</label><input type="text" wire:model="surname" class="form-input"></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">First Name</label><input type="text" wire:model="first_name" class="form-input"></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Middle Name</label><input type="text" wire:model="middle_name" class="form-input"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">UP Mail</label><input type="email" wire:model="up_mail" class="form-input"></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Student No.</label><input type="text" wire:model="student_number" class="form-input"></div>
                    </div>
                    <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Subjects</label><input type="text" wire:model="subjects" class="form-input"></div>
                    <div class="mt-8 flex gap-3 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeAddModal()" class="flex-1 px-6 py-3 rounded-xl border text-xs font-bold text-slate-600">Cancel</button>
                        <button type="submit" class="flex-2 bg-red-900 text-white px-10 py-3 rounded-xl text-xs font-bold shadow-lg hover:bg-red-950">
                            {{ $editing_index !== null ? 'Update Profile' : 'Save Profile' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @script
    <script>
        const addModal = document.getElementById('addMentorModal');
        const viewModal = document.getElementById('viewMentorModal');

        document.getElementById('sidebarToggle').onclick = () => document.getElementById('sidebar').classList.toggle('collapsed');
        
        // Data generation (35 items)
        const baseData = [
            { name: "Sarmiento, Maria Clara", email: "mcsarmiento@up.edu.ph", studentNo: "2021-00123", initials: "MS", specialization: "CMSC 11, CMSC 12", sessions: 24 },
            { name: "Rizal, Jose P.", email: "jprizal@up.edu.ph", studentNo: "1892-00001", initials: "JR", specialization: "HIST 1, PI 10", sessions: 42 },
            { name: "Luna, Antonio K.", email: "akluna@up.edu.ph", studentNo: "2019-45678", initials: "AL", specialization: "CHEM 16, CHEM 17", sessions: 15 },
            { name: "Mabini, Apolinario M.", email: "ammabini@up.edu.ph", studentNo: "2020-11223", initials: "AM", specialization: "POSC 1, POSC 10", sessions: 30 },
            { name: "Silang, Gabriela F.", email: "gfsilang@up.edu.ph", studentNo: "2022-99887", initials: "GS", specialization: "MATH 53, MATH 54", sessions: 12 }
        ];

        const departments = ["CMSC", "MATH", "HIST", "CHEM", "POSC"];
        for(let i=0; i<30; i++) {
            const dept = departments[i % 5];
            baseData.push({
                name: `User${i}, Demo ${String.fromCharCode(65 + (i%26))}.`,
                email: `demo${i}@up.edu.ph`,
                studentNo: `202${i%5}-100${i}`,
                initials: "D"+i,
                specialization: `${dept} ${10+i}, General`,
                sessions: Math.floor(Math.random() * 50)
            });
        }

        let filteredData = [...baseData];
        let currentPage = 1; const rowsPerPage = 7;

        window.renderTable = () => {
            const tbody = document.getElementById('mentorTableBody');
            tbody.innerHTML = '';
            const start = (currentPage - 1) * rowsPerPage;
            const pageData = filteredData.slice(start, start + rowsPerPage);

            pageData.forEach((m) => {
                const globalIdx = baseData.indexOf(m);
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-red-100 text-red-800 flex items-center justify-center font-black text-xs">${m.initials}</div>
                                <div><p class="font-bold text-slate-700 text-sm">${m.name}</p><p class="text-xs text-gray-400">${m.email}</p></div>
                            </div>
                        </td>
                        <td class="px-6 py-5"><span class="bg-gray-100 px-3 py-1 rounded-md text-[11px] font-bold text-gray-600">${m.specialization}</span></td>
                        <td class="px-6 py-5 font-bold text-slate-700 text-center text-sm">${m.sessions}</td>
                        <td class="px-6 py-5 flex justify-center gap-2">
                            <button onclick="openViewModal(${globalIdx})" class="w-9 h-9 rounded bg-gray-100 text-gray-600 flex items-center justify-center hover:bg-gray-200 transition"><i class="fa-solid fa-eye"></i></button>
                            <button onclick="openEditModal(${globalIdx})" class="w-9 h-9 rounded bg-blue-50 text-blue-800 flex items-center justify-center hover:bg-blue-100 transition"><i class="fa-solid fa-pen"></i></button>
                            <button onclick="confirm('Delete?') && alert('Deleted')" class="w-9 h-9 rounded bg-red-50 text-red-800 flex items-center justify-center hover:bg-red-100 transition"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>`;
            });
            document.getElementById('rowCountDisplay').innerText = `Mentors: ${filteredData.length}`;
            document.getElementById('pageIndicator').innerText = `Page ${currentPage} of ${Math.ceil(filteredData.length / rowsPerPage) || 1}`;
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage >= Math.ceil(filteredData.length / rowsPerPage);
        };

        const applyFilters = () => {
            const searchTerm = document.getElementById('mentorSearch').value.toLowerCase();
            const filterDept = document.getElementById('specFilter').value;

            filteredData = baseData.filter(m => {
                const matchesSearch = m.name.toLowerCase().includes(searchTerm) || m.email.toLowerCase().includes(searchTerm);
                const matchesDept = filterDept === 'all' || m.specialization.includes(filterDept);
                return matchesSearch && matchesDept;
            });

            currentPage = 1;
            renderTable();
        };

        document.getElementById('mentorSearch').oninput = applyFilters;
        document.getElementById('specFilter').onchange = applyFilters;

        window.openEditModal = (idx) => {
            const m = baseData[idx];
            const names = m.name.split(', ');
            $wire.set('editing_index', idx);
            $wire.set('surname', names[0]);
            $wire.set('first_name', names[1].split(' ')[0]);
            $wire.set('up_mail', m.email);
            $wire.set('student_number', m.studentNo);
            $wire.set('subjects', m.specialization);
            document.getElementById('modalTitle').innerText = "Edit Mentor Profile";
            addModal.classList.add('active');
        };

        window.closeAddModal = () => { addModal.classList.remove('active'); $wire.set('editing_index', null); };
        window.openViewModal = (idx) => {
            const m = baseData[idx];
            document.getElementById('viewName').innerText = m.name;
            document.getElementById('viewInitials').innerText = m.initials;
            document.getElementById('viewEmail').innerText = m.email;
            document.getElementById('viewFullEmail').innerText = m.email;
            document.getElementById('viewStudentNo').innerText = m.studentNo;
            document.getElementById('viewSubjects').innerHTML = m.specialization.split(',').map(s => `<span class="bg-white border px-3 py-1.5 rounded-lg text-[11px] font-bold text-slate-600 shadow-sm">${s.trim()}</span>`).join('');
            viewModal.classList.add('active');
        };
        window.closeViewModal = () => viewModal.classList.remove('active');
        document.getElementById('openModalBtn').onclick = () => { document.getElementById('modalTitle').innerText = "Add New Mentor"; addModal.classList.add('active'); };
        document.getElementById('prevBtn').onclick = () => { if(currentPage > 1) { currentPage--; renderTable(); } };
        document.getElementById('nextBtn').onclick = () => { if(currentPage < Math.ceil(filteredData.length / rowsPerPage)) { currentPage++; renderTable(); } };

        renderTable();
    </script>
    @endscript
</div>