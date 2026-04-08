<?php

use function Livewire\Volt\{layout, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<style>
    .stat-card {
        background: white;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.03em;
    }
    .status-pending   { background: #fef9c3; color: #854d0e; }
    .status-approved  { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-rejected  { background: #fee2e2; color: #991b1b; }
    .status-no-show   { background: #f1f5f9; color: #475569; }
</style>

<div>
    {{-- Page heading --}}
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-800">Session History</h1>
        <p class="text-sm text-gray-400 mt-1">View all your past and current enrichment session bookings.</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                <i class="fa-solid fa-list-check text-slate-600"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase">Total</p>
                <p class="text-xl font-black text-slate-800">8</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                <i class="fa-solid fa-circle-check text-blue-600"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase">Completed</p>
                <p class="text-xl font-black text-slate-800">5</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="w-10 h-10 rounded-lg bg-yellow-50 flex items-center justify-center">
                <i class="fa-solid fa-clock text-yellow-500"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase">Ongoing</p>
                <p class="text-xl font-black text-slate-800">2</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fa-solid fa-ban text-red-500"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold text-gray-400 uppercase">Cancelled</p>
                <p class="text-xl font-black text-slate-800">1</p>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <h2 class="font-bold text-slate-800 text-sm">All Bookings</h2>
            <div class="flex gap-2 flex-wrap">
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input id="historySearch" type="text" placeholder="Search subject or topic..."
                           class="pl-8 pr-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-1 focus:ring-red-800 w-52">
                </div>
                <select id="historyStatusFilter" class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs text-slate-600 outline-none cursor-pointer">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                    <option value="no-show">No Show</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">#</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Subject</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Topic</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mentor</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Date & Time</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Mode</th>
                    <th class="px-5 py-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
                </tr>
                </thead>
                <tbody id="historyTableBody"></tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-[11px] text-gray-400 font-medium" id="historyPageIndicator">Showing 0 results</p>
            <div class="flex gap-2">
                <button id="historyPrevBtn" class="px-3 py-1.5 text-[11px] font-semibold border border-gray-200 rounded-lg text-gray-500 hover:border-red-800 hover:text-red-800 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button id="historyNextBtn" class="px-3 py-1.5 text-[11px] font-semibold border border-gray-200 rounded-lg text-gray-500 hover:border-red-800 hover:text-red-800 disabled:opacity-40 disabled:cursor-not-allowed transition">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const bookings = [
        { subject: 'CMSC 11',  subjectName: 'Introduction to Computing',           topic: 'Binary & Hexadecimal',   mentor: 'Dyoco, Daniel',       date: 'Mar 1, 2026',  time: '9:00 AM – 10:30 AM',  mode: 'Face-to-face', status: 'completed' },
        { subject: 'Math 53',  subjectName: 'Elementary Analysis I',                topic: 'Derivatives',            mentor: 'Lopez, Rhona Shayne', date: 'Mar 3, 2026',  time: '1:00 PM – 2:30 PM',   mode: 'Online',       status: 'completed' },
        { subject: 'CMSC 123', subjectName: 'Data Structures and Algorithms',       topic: 'Linked Lists',           mentor: 'Sinco, Chezka',       date: 'Mar 5, 2026',  time: '10:00 AM – 11:30 AM', mode: 'Face-to-face', status: 'completed' },
        { subject: 'Phys 101', subjectName: 'Physics',                              topic: "Newton's Laws",          mentor: 'Solis, Arielle Mae',  date: 'Mar 7, 2026',  time: '3:00 PM – 4:30 PM',   mode: 'Face-to-face', status: 'completed' },
        { subject: 'CMSC 128', subjectName: 'Introduction to Software Engineering', topic: 'Develop ka website mo', mentor: "Conchada, Ax'l",       date: 'Mar 10, 2026', time: '9:00 AM – 10:30 AM',  mode: 'Online',       status: 'completed' },
        { subject: 'Math 54',  subjectName: 'Calculus I',                           topic: 'Integration by Parts',  mentor: 'Dyoco, Daniel',       date: 'Mar 14, 2026', time: '11:00 AM – 12:30 PM', mode: 'Face-to-face', status: 'approved'  },
        { subject: 'CMSC 123', subjectName: 'Data Structures and Algorithms',       topic: 'Binary Trees',          mentor: 'Lopez, Rhona Shayne', date: 'Mar 18, 2026', time: '2:00 PM – 3:30 PM',   mode: 'Online',       status: 'pending'   },
        { subject: 'CMSC 11',  subjectName: 'Introduction to Computing',            topic: 'Flowcharts',            mentor: 'Sinco, Chezka',       date: 'Feb 20, 2026', time: '10:00 AM – 11:00 AM', mode: 'Face-to-face', status: 'rejected'  },
    ];

    const statusBadge = {
        pending:   '<span class="status-badge status-pending">Pending</span>',
        approved:  '<span class="status-badge status-approved">Approved</span>',
        completed: '<span class="status-badge status-completed">Completed</span>',
        rejected:  '<span class="status-badge status-rejected">Rejected</span>',
        'no-show': '<span class="status-badge status-no-show">No Show</span>',
    };

    const mentorColors = ['bg-emerald-800', 'bg-teal-800', 'bg-cyan-800', 'bg-indigo-800', 'bg-violet-800'];

    function mentorInitials(name) {
        const parts = name.replace(',', '').split(' ');
        return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
    }

    function mentorColor(name) {
        return mentorColors[name.charCodeAt(0) % mentorColors.length];
    }

    let currentPage = 1;
    const perPage = 5;

    function renderTable() {
        const search = document.getElementById('historySearch').value.toLowerCase();
        const status = document.getElementById('historyStatusFilter').value;

        const filtered = bookings.filter(b => {
            const matchSearch = b.subject.toLowerCase().includes(search)
                || b.topic.toLowerCase().includes(search)
                || b.mentor.toLowerCase().includes(search);
            const matchStatus = status === 'all' || b.status === status;
            return matchSearch && matchStatus;
        });

        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;
        const paginated = filtered.slice((currentPage - 1) * perPage, currentPage * perPage);

        const tbody = document.getElementById('historyTableBody');
        if (paginated.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-16 text-center text-gray-400 text-sm italic">No matching records found.</td></tr>`;
        } else {
            tbody.innerHTML = paginated.map((b, i) => `
                <tr class="border-b border-gray-50 hover:bg-slate-50 transition">
                    <td class="px-5 py-4 text-gray-400 text-xs">${(currentPage - 1) * perPage + i + 1}</td>
                    <td class="px-5 py-4">
                        <p class="font-bold text-slate-700 text-xs">${b.subject}</p>
                        <p class="text-gray-400 text-[10px]">${b.subjectName}</p>
                    </td>
                    <td class="px-5 py-4 text-slate-600 text-xs max-w-[160px] truncate">${b.topic}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full ${mentorColor(b.mentor)} text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">${mentorInitials(b.mentor)}</div>
                            <span class="text-xs font-medium text-slate-700">${b.mentor}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-xs font-medium text-slate-700">${b.date}</p>
                        <p class="text-[10px] text-gray-400">${b.time}</p>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500">${b.mode}</td>
                    <td class="px-5 py-4">${statusBadge[b.status] || ''}</td>
                </tr>
            `).join('');
        }

        document.getElementById('historyPageIndicator').innerText = filtered.length === 0
            ? 'No results'
            : `Showing ${Math.min((currentPage - 1) * perPage + 1, filtered.length)}–${Math.min(currentPage * perPage, filtered.length)} of ${filtered.length}`;

        document.getElementById('historyPrevBtn').disabled = currentPage <= 1;
        document.getElementById('historyNextBtn').disabled = currentPage >= totalPages;
    }

    document.getElementById('historySearch').addEventListener('input', () => { currentPage = 1; renderTable(); });
    document.getElementById('historyStatusFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });
    document.getElementById('historyPrevBtn').addEventListener('click', () => { currentPage--; renderTable(); });
    document.getElementById('historyNextBtn').addEventListener('click', () => { currentPage++; renderTable(); });

    renderTable();
</script>
