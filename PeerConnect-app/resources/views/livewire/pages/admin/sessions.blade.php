<?php

use function Livewire\Volt\{layout, state, mount, computed, action};
use App\Models\Bookings;
use App\Models\MentorProfiles;

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized Access');
});

$sessions = computed(function () {

    // AUTO-COMPLETE: mark all accepted bookings as completed if their date has passed
    Bookings::where('booking_status', 'accepted')
        ->whereDate('date', '<', today())
        ->update([
            'booking_status' => 'completed',
            'completed_at'   => now(),
        ]);

    // Fetch ALL bookings (no mentor_id filter — admin sees everything)
    return Bookings::with([
        'student.user',
        'subject',
        'mentor.user',
    ])
    ->get()
    ->map(function ($b) {
        $start = \Carbon\Carbon::parse($b->schedule_start);
        $end   = \Carbon\Carbon::parse($b->schedule_end);

        $durationMinutes = $start->diffInMinutes($end);
        $durationHours   = $durationMinutes / 60;

        $durationText = $durationHours == 1
            ? '1 hr'
            : rtrim(rtrim(number_format($durationHours, 2), '0'), '.') . ' hrs';

        return [
            'id'       => $b->id,
            'student'  => optional(optional($b->student)->user)->firstName
                        ? $b->student->user->firstName . ' ' . $b->student->user->lastName
                        : 'Unknown',
            'subject'  => optional($b->subject)->code ?? 'N/A',
            'topic'    => $b->topic ?? '—',
            'date'     => $b->date ? \Carbon\Carbon::parse($b->date)->format('M d, Y') : '—',
            'start'    => $start->format('H:i'),
            'end'       => $end->format('H:i'),
            'duration' => $start->format('h:i A') . ' - ' . $end->format('h:i A') . ' (' . $durationText . ')',
            'status'   => $b->booking_status,
            'is_open'  => is_null($b->mentor_id),
            'mentor'   => optional(optional($b->mentor)->user)->firstName
                        ? $b->mentor->user->firstName . ' ' . $b->mentor->user->lastName
                        : null,
        ];
    })
    ->values()
    ->toArray();
});

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LRC PeerConnect – Session Management</title>

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
        .sidebar.collapsed .nav-item i { margin: 0; width: auto; text-align: center; flex-shrink: 0; }
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
        .scroll-container { flex-grow: 1; overflow-y: auto; padding: 32px; width: 100%; }

        .profile-dropdown {
            position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none;
            flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden;
        }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        .table-filter-select { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; background: white; cursor: pointer; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }

        .topic-text { word-break: break-word; overflow-wrap: anywhere; white-space: normal; }
        .topic-text.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; white-space: normal; word-break: break-all; }
        .tabular-nums { font-variant-numeric: tabular-nums; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        #confirmMeta { max-height: 220px; overflow-y: auto; }

        /* Stat cards */
        .stat-card {
            background: white; padding: 20px 24px; border-radius: 16px;
            border-left-width: 4px; border-left-style: solid;
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .hover-tooltip-wrap {
    position: relative;
    display: inline-block;
    max-width: 100%;
}
.hover-tooltip-wrap .tooltip-full {
    visibility: hidden;
    opacity: 0;
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    background: #1e293b;
    color: #f8fafc;
    font-size: 11px;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: normal;
    word-break: break-word;
    width: max-content;
    max-width: 260px;
    z-index: 100;
    transition: opacity 0.15s ease;
    line-height: 1.5;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.hover-tooltip-wrap:hover .tooltip-full {
    visibility: visible;
    opacity: 1;
}
.hover-tooltip-wrap .truncated-label {
    display: block;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    max-width: 100%;
    cursor: default;
}
    </style>
</head>

<body>
<div class="app-wrapper">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo-container">
            <div class="logo-content">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>
        </div>

        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon"><i class="fa-solid fa-chevron-right"></i></span>
        </button>

        <nav class="flex-grow">
            <a href="{{ route('admin.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('admin.mentors') }}" class="nav-item" data-tooltip="Mentor Management">
                <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentor Management</span>
            </a>
            <a href="{{ route('admin.courses') }}" class="nav-item" data-tooltip="Course Management">
                <i class="fa-solid fa-book-open w-5"></i><span>Course Management</span>
            </a>
            <a href="{{ route('admin.sessions') }}" class="nav-item active" data-tooltip="Session Management">
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

    <!-- ── MAIN CONTENT ── -->
    <div class="main-content">
        <header class="top-header relative">
            <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>

            <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"></i>
            </button>

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
            <div class="space-y-6">

                <!-- ── STAT CARDS ── -->
                @php
                    $allSess    = collect($this->sessions);
                    $total      = $allSess->count();
                    $pending    = $allSess->where('status', 'pending')->count();
                    $accepted   = $allSess->where('status', 'accepted')->count();
                    $completed  = $allSess->where('status', 'completed')->count();
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="stat-card border-green-600">
                        <div class="text-2xl text-green-600"><i class="fa-solid fa-layer-group"></i></div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Total Sessions</p>
                            <p class="text-xl font-black text-slate-800">{{ $total }}</p>
                        </div>
                    </div>
                    <div class="stat-card border-blue-500">
                        <div class="text-2xl text-blue-500"><i class="fa-solid fa-clock"></i></div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Pending Approval</p>
                            <p class="text-xl font-black text-slate-800">{{ $pending }}</p>
                        </div>
                    </div>
                    <div class="stat-card border-yellow-500">
                        <div class="text-2xl text-yellow-500"><i class="fa-solid fa-check-circle"></i></div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Upcoming (Accepted)</p>
                            <p class="text-xl font-black text-slate-800">{{ $accepted }}</p>
                        </div>
                    </div>
                    <div class="stat-card border-red-700">
                        <div class="text-2xl text-red-700"><i class="fa-solid fa-chart-line"></i></div>
                        <div>
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1">Completed</p>
                            <p class="text-xl font-black text-slate-800">{{ $completed }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── SESSION TABLE ── -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">

                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">All Sessions</h2>
                            <p class="text-xs text-gray-400">All student-selected mentor sessions</p>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" id="searchInput" placeholder="Search..."
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-red-900/10">
                            <select id="statusFilter" class="table-filter-select">
                                <option value="All">All</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="completed">Completed</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex border border-gray-200 rounded-lg overflow-hidden text-xs font-medium">
                            <button id="filter-all"   onclick="setDateFilter('all')"   class="date-range-btn active px-3 py-2 bg-red-900 text-white hover:bg-red-800 transition-colors">All time</button>
                            <button id="filter-week"  onclick="setDateFilter('week')"  class="date-range-btn px-3 py-2 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors border-l border-gray-200">This week</button>
                            <button id="filter-month" onclick="setDateFilter('month')" class="date-range-btn px-3 py-2 text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors border-l border-gray-200">This month</button>
                        </div>
                    </div>

                    <!-- Banner area -->
                    <div id="sessionsBannerArea" class="flex flex-col gap-2 mb-4"></div>

                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="text-gray-400 border-b">
                            <tr>
                                <th onclick="setSort('student')"  class="cursor-pointer pb-3 text-[13px] select-none" style="width:13%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Student<span id="sort-student"  class="text-[10px]"></span></div></th>
                                <th onclick="setSort('subject')"  class="cursor-pointer pb-3 text-[13px] select-none" style="width:8%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Subject<span id="sort-subject"  class="text-[10px]"></span></div></th>
                                <th onclick="setSort('topic')"    class="cursor-pointer pb-3 text-[13px] select-none" style="width:17%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Topic<span id="sort-topic"    class="text-[10px]"></span></div></th>
                                <th onclick="setSort('mentor')"   class="cursor-pointer pb-3 text-[13px] select-none" style="width:13%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Mentor<span id="sort-mentor"   class="text-[10px]"></span></div></th>
                                <th onclick="setSort('date')"     class="cursor-pointer pb-3 text-[13px] select-none" style="width:10%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Date<span id="sort-date"     class="text-[10px]"></span></div></th>
                                <th onclick="setSort('duration')" class="cursor-pointer pb-3 text-[13px] select-none" style="width:14%;"><div class="flex items-center gap-1 hover:text-red-800 transition">Duration<span id="sort-duration" class="text-[10px]"></span></div></th>
                                <th onclick="setSort('status')"   class="cursor-pointer pb-3 text-[13px] select-none" style="width:8%;"><div class="flex justify-center gap-1 hover:text-red-800 transition">Status<span id="sort-status"   class="text-[10px]"></span></div></th>
                                <th class="pb-3 text-[13px] select-none" style="width:17%;"><div class="flex justify-end">Actions</div></th>
                            </tr>
                        </thead>
                        <tbody id="sessionsTable">
                            <tr>
                                <td colspan="8" class="text-center py-10 text-gray-400 text-xs">Loading sessions…</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div id="sessionsPaginationFooter" style="display:none;"
                        class="mt-5 pt-4 border-t border-gray-50 flex items-center justify-between">
                        <span id="sessionsPageInfo" class="text-[11px] text-gray-400 font-medium"></span>
                        <div class="flex gap-2">
                            <button id="sessionsPrevBtn" class="pagination-btn opacity-30 cursor-not-allowed" disabled>
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </button>
                            <button id="sessionsNextBtn" class="pagination-btn">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- ── CONFIRM MODAL ── -->
    <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0"></div>
                <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
            </div>
            <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>
            <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>
            <div class="flex justify-end gap-3">
                <button id="confirmCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Cancel</button>
                <button id="confirmOkBtn"     class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">Confirm</button>
            </div>
        </div>
    </div>

</div><!-- /.app-wrapper -->

<script>
    /* ── SESSION DATA FROM DB ── */
    const allSessions = @json($this->sessions);

    /* ── SIDEBAR & PROFILE DROPDOWN ── */
    document.getElementById('sidebarToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    const profileTrigger  = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    profileTrigger.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
    window.addEventListener('click', () => profileDropdown.classList.remove('show'));

    /* ── MODAL REFS ── */
    const confirmModal     = document.getElementById('confirmModal');
    const confirmModalBox  = document.getElementById('confirmModalBox');
    const confirmTitle     = document.getElementById('confirmTitle');
    const confirmBody      = document.getElementById('confirmBody');
    const confirmMeta      = document.getElementById('confirmMeta');
    const confirmOkBtn     = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmIconWrap  = document.getElementById('confirmIconWrap');

    confirmModal.addEventListener('click', e => { if (!confirmModalBox.contains(e.target)) closeConfirmModal(); });
    confirmCancelBtn.addEventListener('click', closeConfirmModal);

    function closeConfirmModal() { confirmModal.style.display = 'none'; confirmOkBtn.onclick = null; }

    /* ── CONFLICT HELPERS ── */
    function toMin(t) {
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

    function hasConflict(newReq) {
        return allSessions.some(s => {
            if (s.id === newReq.id || s.status !== 'accepted' || s.date !== newReq.date) return false;
            return toMin(newReq.start) < toMin(s.end) && toMin(newReq.end) > toMin(s.start);
        });
    }

    function getConflictingPendingIds(accepted) {
        const aStart = toMin(accepted.start), aEnd = toMin(accepted.end);
        return allSessions
            .filter(s => s.id !== accepted.id && s.status === 'pending' && s.date === accepted.date
                && aStart < toMin(s.end) && aEnd > toMin(s.start))
            .map(s => s.id);
    }

    /* ── BANNERS ── */
    function showBanner(id, html) {
        const area = document.getElementById('sessionsBannerArea');
        let banner = document.getElementById(id);
        if (!banner) { banner = document.createElement('div'); banner.id = id; banner.style.cssText = 'border-radius:8px;overflow:hidden;font-size:11px;animation:slideDown 0.2s ease;'; area.appendChild(banner); }
        banner.innerHTML = html;
        clearTimeout(banner._timer);
        banner._timer = setTimeout(() => banner.remove(), 6000);
    }

    function showConflictBanner(message) {
        showBanner('conflictBanner', `<div style="border:1px solid #fca5a5;background:#fef2f2;border-radius:8px;"><div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;"><div style="flex-shrink:0;margin-top:2px;"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7.5" stroke="#ef4444" stroke-width="1"/><path d="M8 4.5v4" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="11" r="0.75" fill="#ef4444"/></svg></div><div style="flex:1;color:#b91c1c;line-height:1.5;"><span style="font-weight:600;">Cannot approve —</span> ${message}</div><button onclick="document.getElementById('conflictBanner').remove()" style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#b91c1c;font-size:14px;line-height:1;padding:0;">&times;</button></div></div>`);
    }

    function showAutoRejectBanner(count) {
        showBanner('autoRejectBanner', `<div style="border:1px solid #fcd34d;background:#fffbeb;border-radius:8px;"><div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;"><div style="flex-shrink:0;margin-top:2px;"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 1.5L14.5 13H1.5L8 1.5Z" stroke="#d97706" stroke-width="1" stroke-linejoin="round"/><path d="M8 6v3.5" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="11.5" r="0.75" fill="#d97706"/></svg></div><div style="flex:1;color:#92400e;line-height:1.5;"><span style="font-weight:600;">${count} conflicting ${count === 1 ? 'request' : 'requests'} auto-rejected</span> — overlapping bookings were declined automatically.</div><button onclick="document.getElementById('autoRejectBanner').remove()" style="flex-shrink:0;background:none;border:none;cursor:pointer;color:#92400e;font-size:14px;line-height:1;padding:0;">&times;</button></div></div>`);
    }

    function showLoadingBanner() {
        showBanner('loadingBanner', `<div style="border:1px solid #bfdbfe;background:#eff6ff;border-radius:8px;"><div style="display:flex;align-items:center;gap:8px;padding:10px 12px;"><div style="flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;"><circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/><path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/></svg></div><div style="flex:1;color:#1d4ed8;line-height:1.5;font-size:11px;"><span style="font-weight:600;">Updating session status</span> — please wait...</div></div></div>`);
        const b = document.getElementById('loadingBanner'); if (b) clearTimeout(b._timer);
    }
    function hideLoadingBanner() { const b = document.getElementById('loadingBanner'); if (b) b.remove(); }

    /* ── ROUTE & TOKEN ── */
    const csrfToken   = '{{ csrf_token() }}';
    const sessionsUrl = '{{ route('mentor.sessions.update') }}';

    /* ── STATUS HELPERS ── */
    function getStatusColor(status) {
        const map = { accepted:'text-green-900 bg-green-100 border-green-400', completed:'text-gray-600 bg-gray-100 border-gray-300', pending:'text-yellow-700 bg-yellow-100 border-yellow-300', rejected:'text-red-700 bg-red-100 border-red-300', cancelled:'text-red-700 bg-red-100 border-red-300', no_show:'text-orange-700 bg-orange-100 border-orange-300' };
        return map[status] ?? 'text-gray-500 bg-gray-50 border-gray-200';
    }

    function getStatusLabel(status) {
        const map = { accepted:'Accepted', completed:'Completed', pending:'Pending', rejected:'Rejected', cancelled:'Cancelled', no_show:'No Show' };
        return map[status] ?? (status.charAt(0).toUpperCase() + status.slice(1));
    }

    /* ── RENDER ACTIONS (admin sees all transitions + open-session claim) ── */
    function renderActions(s) {
        const btn = (label, status, color) =>
            `<button onclick="updateStatus('${s.id}','${status}')"
                class="text-[10px] px-2 py-1 ${color} rounded font-semibold whitespace-nowrap">
                ${label}
            </button>`;

        if (s.status === 'pending') {
            if (s.is_open) {
                return btn('<i class="fa-solid fa-triangle-exclamation mr-1"></i>Claim', 'accepted', 'bg-purple-100 text-purple-700 hover:bg-purple-200')
                    + btn('Reject', 'rejected', 'bg-red-100 text-red-700 hover:bg-red-200');
            }
            return btn('Accept', 'accepted', 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200')
                + btn('Reject', 'rejected', 'bg-red-100 text-red-700 hover:bg-red-200');
        }
        if (s.status === 'accepted') {
            return btn('Complete', 'completed', 'bg-gray-100 text-gray-700 hover:bg-gray-200')
                + btn('No-show', 'no_show', 'bg-orange-100 text-orange-700 hover:bg-orange-200')
                + btn('Cancel', 'cancelled', 'bg-red-100 text-red-700 hover:bg-red-200');
        }
        if (s.status === 'completed') {
            return btn('Uncomplete', 'accepted', 'bg-gray-100 text-gray-500 hover:bg-gray-200');
        }
        return '<span class="text-gray-300 text-[10px]">—</span>';
    }

    /* ── TIME FORMATTERS ── */
    function formatTimeRange(s) { return s.duration.split(' (')[0]; }
    function formatHours(s) { const m = s.duration.match(/\((.*?)\)/); return m ? `(${m[1]})` : ''; }

    /* ── TOGGLE HELPERS ── */
    function toggleText(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const collapsed = el.classList.toggle('line-clamp-1');
        const more = document.getElementById('more-' + id);
        const less = document.getElementById('less-' + id);
        if (more) more.classList.toggle('hidden', !collapsed);
        if (less) less.classList.toggle('hidden', collapsed);
    }

    function toggleModalText(id) {
        const el = document.getElementById('modal-text-' + id);
        if (!el) return;
        const collapsed = el.classList.toggle('line-clamp-1');
        const more = document.getElementById('modal-more-' + id);
        const less = document.getElementById('modal-less-' + id);
        if (more) more.classList.toggle('hidden', !collapsed);
        if (less) less.classList.toggle('hidden', collapsed);
    }

    /* ── SORT ── */
    let sortColumn = null, sortDirection = 'asc';

    function setSort(col) {
        sortDirection = sortColumn === col && sortDirection === 'asc' ? 'desc' : 'asc';
        sortColumn = col;
        sessionsPage = 0;
        renderSessions();
    }

    function updateSortIcons() {
        document.querySelectorAll('[id^="sort-"]').forEach(el => el.innerHTML = '');
        if (sortColumn) { const el = document.getElementById('sort-' + sortColumn); if (el) el.innerHTML = sortDirection === 'asc' ? '↑' : '↓'; }
    }

    function sortSessions(data) {
        if (!sortColumn) return data;
        const statusOrder = { accepted:1, pending:2, completed:3, no_show:4, cancelled:5, rejected:6 };
        return [...data].sort((a, b) => {
            let vA = a[sortColumn], vB = b[sortColumn];
            if (sortColumn === 'status') { vA = statusOrder[vA] ?? 999; vB = statusOrder[vB] ?? 999; }
            else if (sortColumn === 'date') { vA = new Date(vA); vB = new Date(vB); }
            else if (sortColumn === 'duration') { vA = a.start; vB = b.start; }
            if (typeof vA === 'string') vA = vA.toLowerCase();
            if (typeof vB === 'string') vB = vB.toLowerCase();
            return vA < vB ? (sortDirection === 'asc' ? -1 : 1) : vA > vB ? (sortDirection === 'asc' ? 1 : -1) : 0;
        });
    }

    /* ── PAGINATION ── */
    let sessionsPage = 0;

    let activeDateFilter = 'all';

function setDateFilter(range) {
    activeDateFilter = range;
    sessionsPage = 0;
    document.querySelectorAll('.date-range-btn').forEach(btn => {
        btn.classList.remove('bg-red-900', 'text-white', 'hover:bg-red-800');
        btn.classList.add('text-gray-500', 'hover:bg-gray-50', 'hover:text-gray-700');
    });
    const active = document.getElementById('filter-' + range);
        active.classList.add('bg-red-900', 'text-white', 'hover:bg-red-800');
        active.classList.remove('text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
    renderSessions();
}
    const SESSIONS_PER_PAGE = 10;

    function updateSessionsPagination(total, maxPage) {
        const footer = document.getElementById('sessionsPaginationFooter');
        if (!footer) return;
        if (total === 0) { footer.style.display = 'none'; return; }
        footer.style.display = 'flex';
        const start = sessionsPage * SESSIONS_PER_PAGE;
        document.getElementById('sessionsPageInfo').innerText = `${start + 1}–${Math.min(start + SESSIONS_PER_PAGE, total)} of ${total}`;
        const prev = document.getElementById('sessionsPrevBtn'), next = document.getElementById('sessionsNextBtn');
        prev.disabled = sessionsPage === 0;
        next.disabled = sessionsPage >= maxPage;
        prev.classList.toggle('opacity-30', sessionsPage === 0);
        prev.classList.toggle('cursor-not-allowed', sessionsPage === 0);
        next.classList.toggle('opacity-30', sessionsPage >= maxPage);
        next.classList.toggle('cursor-not-allowed', sessionsPage >= maxPage);
    }

    /* ── RENDER TABLE ── */
    function renderSessions() {
        updateSortIcons();
        const tbody  = document.getElementById('sessionsTable');
        const search = document.getElementById('searchInput').value.toLowerCase();
        const filter = document.getElementById('statusFilter').value;

        let filtered = allSessions.filter(s => {
            const hay = [s.student, s.subject, s.topic, s.date, s.duration, s.status, s.mentor ?? ''].join(' ').toLowerCase();
            return hay.includes(search) && (filter === 'All' || s.status === filter);
        });
        // Date range filter
if (activeDateFilter !== 'all') {
    const now = new Date();
    const startOfWeek = new Date(now);
    startOfWeek.setDate(now.getDate() - now.getDay());
    startOfWeek.setHours(0, 0, 0, 0);
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);

    filtered = filtered.filter(s => {
        if (!s.date) return false;
        const d = new Date(s.date);
        if (activeDateFilter === 'week')  return d >= startOfWeek;
        if (activeDateFilter === 'month') return d >= startOfMonth;
        return true;
    });
}

        filtered = sortSessions(filtered);

        if (!filtered.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10 text-gray-400 text-xs">No sessions found.</td></tr>`;
            updateSessionsPagination(0, 0);
            return;
        }

        const total   = filtered.length;
        const maxPage = Math.max(0, Math.ceil(total / SESSIONS_PER_PAGE) - 1);
        if (sessionsPage > maxPage) sessionsPage = 0;

        const visible = filtered.slice(sessionsPage * SESSIONS_PER_PAGE, sessionsPage * SESSIONS_PER_PAGE + SESSIONS_PER_PAGE);
        updateSessionsPagination(total, maxPage);

 tbody.innerHTML = visible.map(s => {
            const mentorBadge = s.is_open
                ? `<span class="text-[10px] px-2 py-0.5 bg-purple-100 text-purple-700 border border-purple-200 rounded font-semibold">Open</span>`
                : `<span class="text-slate-600 text-sm">${s.mentor ?? '—'}</span>`;

            return `
            <tr class="border-b hover:bg-slate-50">
                <td class="py-3 text-sm align-middle pr-2" style="max-width:0;">
                    <div class="hover-tooltip-wrap" style="max-width:100%;">
                        <span class="truncated-label font-bold text-slate-700 leading-snug">${s.student}</span>
                        ${s.student.length > 20 ? `<span class="tooltip-full">${s.student}</span>` : ''}
                    </div>
                </td>
                <td class="py-3 text-sm text-slate-600">${s.subject}</td>
                <td class="py-3 text-sm pr-2 align-middle" style="max-width:0;">
                    <div class="hover-tooltip-wrap" style="max-width:100%;">
                        <span class="truncated-label text-slate-600 leading-snug">${s.topic}</span>
                        ${s.topic.length > 40 ? `<span class="tooltip-full">${s.topic}</span>` : ''}
                    </div>
                </td>
                <td class="py-3 align-middle">${mentorBadge}</td>
                <td class="py-3 text-sm text-slate-600 pr-0">${s.date}</td>
                <td class="py-3 text-sm pl-0">
                    <div class="flex gap-1 font-medium tabular-nums flex-wrap">
                        <span>${formatTimeRange(s)}</span>
                        <span class="text-gray-400">${formatHours(s)}</span>
                    </div>
                </td>
                <td class="py-3 text-center">
                    <span class="${getStatusColor(s.status)} text-[10px] px-2 py-1 rounded border font-bold whitespace-nowrap">
                        ${getStatusLabel(s.status)}
                    </span>
                </td>
                <td class="py-3 align-middle">
                    <div class="flex gap-1 items-center justify-end pr-1 flex-wrap">
                        ${renderActions(s)}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    /* ── UPDATE STATUS ── */
    function updateStatus(id, status) {
        const req = allSessions.find(s => s.id == id);
        if (!req) return;

        if (status === 'accepted' && req.status !== 'completed' && hasConflict(req)) {
            const conflict = allSessions.find(s => s.id !== req.id && s.status === 'accepted' && s.date === req.date);
            showConflictBanner(conflict
                ? `Conflicts with <strong>${conflict.student}</strong> (${formatTimeRange(conflict)}) on ${conflict.date}.`
                : 'This session overlaps with an already-accepted booking.');
            return;
        }

        const isUncomplete = status === 'accepted' && req.status === 'completed';
        const isClaiming   = status === 'accepted' && req.status === 'pending' && req.is_open;

        const cfgMap = {
            accepted:  isUncomplete ? { title:'Revert to accepted?',    body:'This will mark the session as accepted again, reversing the completed status.', variant:'neutral' }
                     : isClaiming   ? { title:'Claim open session?',    body:'This session will be permanently assigned.',                                    variant:'accept'  }
                     :                { title:'Accept booking?',         body:'The student will be notified that their session has been approved.',            variant:'accept'  },
            rejected:  { title:'Reject booking?',    body:'The student will be notified that their session request was declined.', variant:'reject'  },
            completed: { title:'Mark as completed?', body:'This will mark the session as done.',                                   variant:'neutral' },
            no_show:   { title:'Mark as no-show?',   body:'This will record that the student did not attend the session.',         variant:'reject'  },
            cancelled: { title:'Cancel session?',    body:'This will cancel the accepted session.',                                variant:'reject'  },
        };

        const cfg = cfgMap[status] ?? { title:'Confirm action', body:'Are you sure?', variant:'neutral' };

 const metaHtml = `
            <div class="flex justify-between items-start gap-2">
                <span class="text-gray-400">Student</span>
                <span class="font-medium text-gray-700 text-right" style="max-width:160px;word-break:break-word;">${req.student}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Mentor</span>
                <span class="font-medium text-gray-700 text-right">${req.mentor ?? '<em class="text-purple-600">Open / Unassigned</em>'}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Subject</span>
                <span class="font-medium text-gray-700 text-right truncate max-w-[140px]">${req.subject}</span>
            </div>
            <div class="flex justify-between items-start gap-2">
                <span class="text-gray-400">Topic</span>
                <span class="font-medium text-gray-700 text-right" style="max-width:180px;word-break:break-word;">${req.topic}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Date</span>
                <span class="font-medium text-gray-700">${req.date}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-gray-400">Time</span>
                <span class="font-medium text-gray-700">${formatTimeRange(req)}</span>
            </div>
        `;
        openConfirmModal({ title: cfg.title, body: cfg.body, meta: metaHtml, variant: cfg.variant, onConfirm: () => commitStatus(id, status, req) });
    }

    function commitStatus(id, status, target) {
        showLoadingBanner();
        const fd = new FormData();
        fd.append('_token', csrfToken);
        fd.append('booking_id', id);
        fd.append('booking_status', status);

        fetch(sessionsUrl, { method: 'POST', body: fd })
            .then(res => {
                if (!res.ok) throw new Error('Request failed');
                target.status = status;

                if (status === 'accepted') {
                    const conflictIds = getConflictingPendingIds(target);
                    if (conflictIds.length > 0) {
                        let done = 0;
                        conflictIds.forEach(cid => {
                            const cs = allSessions.find(s => s.id == cid);
                            if (cs) cs.status = 'rejected';
                            const cfd = new FormData();
                            cfd.append('_token', csrfToken); cfd.append('booking_id', cid); cfd.append('booking_status', 'rejected');
                            fetch(sessionsUrl, { method: 'POST', body: cfd })
                                .then(() => { if (++done === conflictIds.length) { hideLoadingBanner(); renderSessions(); showAutoRejectBanner(conflictIds.length); } })
                                .catch(err => { hideLoadingBanner(); console.error('Auto-reject failed:', err); });
                        });
                        renderSessions();
                        return;
                    }
                }
                hideLoadingBanner();
                renderSessions();
            })
            .catch(err => {
                hideLoadingBanner();
                showBanner('errorBanner', `<div style="border:1px solid #fca5a5;background:#fef2f2;border-radius:8px;padding:10px 12px;color:#b91c1c;font-size:11px;"><strong>Update failed —</strong> please check your connection and try again.</div>`);
                console.error(err);
            });
    }

    /* ── MODAL HELPERS ── */
    function openConfirmModal({ title, body, meta, variant, onConfirm }) {
        const v = { accept:{ iconFn:iconCheck, iconBg:'bg-emerald-100', iconColor:'#059669', btnBg:'bg-emerald-600 hover:bg-emerald-700', label:'Confirm' }, reject:{ iconFn:iconX, iconBg:'bg-red-100', iconColor:'#dc2626', btnBg:'bg-red-600 hover:bg-red-700', label:'Reject' }, neutral:{ iconFn:iconInfo, iconBg:'bg-gray-100', iconColor:'#64748b', btnBg:'bg-gray-700 hover:bg-gray-800', label:'Confirm' } }[variant] ?? { iconFn:iconInfo, iconBg:'bg-gray-100', iconColor:'#64748b', btnBg:'bg-gray-700 hover:bg-gray-800', label:'Confirm' };
        confirmIconWrap.className = `w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 ${v.iconBg}`;
        confirmIconWrap.innerHTML = v.iconFn(v.iconColor);
        confirmTitle.textContent  = title;
        confirmBody.innerHTML     = body;
        confirmMeta.innerHTML     = meta ?? '';
        confirmMeta.style.display = meta ? 'block' : 'none';
        confirmOkBtn.className    = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnBg}`;
        confirmOkBtn.textContent  = v.label;
        confirmOkBtn.onclick = () => { closeConfirmModal(); onConfirm(); };
        confirmModal.style.display = 'flex';
    }

    function iconCheck(c) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${c}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
    function iconX(c)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${c}" stroke-width="2" stroke-linecap="round"/></svg>`; }
    function iconInfo(c)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${c}" stroke-width="1.5"/><path d="M10 9v5" stroke="${c}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${c}"/></svg>`; }

    /* ── BOOT ── */
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('searchInput').addEventListener('input',  () => { sessionsPage = 0; renderSessions(); });
        document.getElementById('statusFilter').addEventListener('change', () => { sessionsPage = 0; renderSessions(); });
        document.getElementById('sessionsPrevBtn').addEventListener('click', () => { sessionsPage--; renderSessions(); });
        document.getElementById('sessionsNextBtn').addEventListener('click', () => { sessionsPage++; renderSessions(); });
        renderSessions();
    });
</script>
</body>