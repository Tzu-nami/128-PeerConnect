<?php

    use function Livewire\Volt\{layout, state, mount, computed, action};
    use App\Models\Bookings;
    use App\Models\MentorProfiles;

    layout('layouts.app');

    mount(function () {
        abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
    });

    $sessions = computed(function () {

        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

        if (!$mentorProfile) return [];

        // AUTO-COMPLETE: mark accepted bookings as completed if their date has passed
        Bookings::where('mentor_id', $mentorProfile->id)
            ->where('booking_status', 'accepted')
            ->whereDate('date', '<', today())
            ->update([
                'booking_status' => 'completed',
                'completed_at'   => now(),
            ]);

        return Bookings::with([
            'student.user',
            'subject'
        ])
        ->where('mentor_id', $mentorProfile->id)
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

            // ✅ KEEP THESE (important!)
    'start' => $start->format('H:i'),
    'end'   => $end->format('H:i'),

    'duration' => $start->format('h:i A') . ' - ' . $end->format('h:i A') . ' (' . $durationText . ')',

            'status'   => $b->booking_status,
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
        <title>LRC PeerConnect – Sessions</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.tailwindcss.com?plugins=line-clamp"></script>

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
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 20px;
                gap: 12px;
                flex-shrink: 0;
                overflow: hidden;
                transition: padding 0.3s, justify-content 0.3s;
            }
            .sidebar:not(.collapsed) .sidebar-logo-container { justify-content: flex-start; }

            .logo-icon { flex-shrink: 0; font-size: 1.25rem; width: 32px; text-align: center; }

            .logo-text {
                font-size: 1rem;
                font-weight: 700;
                white-space: nowrap;
                overflow: hidden;
                opacity: 1;
                max-width: 200px;
                transition: opacity 0.2s, max-width 0.3s;
            }
            .sidebar.collapsed .logo-text { opacity: 0; max-width: 0; pointer-events: none; }

            /* ── Nav items ── */
            .nav-item {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 20px;
                color: rgba(255,255,255,0.7);
                text-decoration: none;
                transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.3s;
                white-space: nowrap;
                position: relative;
                text-align: left;
                background: transparent;
                border: none;
                width: 100%;
                cursor: pointer;
                font-size: 0.875rem;
                justify-content: flex-start;
            }
            .sidebar.collapsed .nav-item { justify-content: center; padding: 14px 0; }

            .nav-item i { width: 32px; text-align: center; flex-shrink: 0; font-size: 18px; transition: width 0.3s; }
            .sidebar.collapsed .nav-item i { width: 32px; margin: 0; }

            .nav-item span {
                overflow: hidden; opacity: 1; max-width: 200px;
                transition: opacity 0.2s, max-width 0.3s;
            }
            .sidebar.collapsed .nav-item span { opacity: 0; max-width: 0; pointer-events: none; }

            .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
            .nav-item.active {
                background: var(--bg-light);
                color: var(--header-maroon);
                font-weight: 700;
                border-radius: 0;
                width: calc(100% + 1px);
                z-index: 10;
            }

            /* Tooltips */
            .nav-item::after {
                content: attr(data-tooltip);
                position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
                margin-left: 14px; background: rgba(0,0,0,0.85); color: white;
                padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500;
                white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s;
                pointer-events: none; z-index: 100;
            }
            .sidebar.collapsed .nav-item:hover::after { opacity: 1; visibility: visible; }

            /* Logout section */
            .sidebar-footer { padding: 12px 0; border-top: 1px solid rgba(255,255,255,0.1); }

            /* ── TOGGLE BUTTON ── */
            .sidebar-toggle-btn {
                position: absolute;
                right: -16px;
                top: 3%;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #7b1d1d;
                font-size: 13px;
                z-index: 50;
                box-shadow: 0 2px 8px rgba(0,0,0,0.25);
                transition: background 0.2s;
                flex-shrink: 0;
            }
            .sidebar-toggle-btn:hover { background: #dfcece; }
            .sidebar-toggle-btn .toggle-icon {
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex; align-items: center; justify-content: center;
            }
            .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon { transform: rotate(180deg); }
            /* lahat ng may sidebar copy paste*/
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
            .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: #7b1d1d; border-color: #7b1d1d; }
            .tabular-nums {font-variant-numeric: tabular-nums;}
            .topic-text {word-break: break-word;overflow-wrap: anywhere;white-space: normal;}
            .topic-text.line-clamp-1 {display: -webkit-box;-webkit-line-clamp: 1;-webkit-box-orient: vertical;overflow: hidden;white-space: normal;word-break: break-all;}
            @keyframes slideDown {from { opacity: 0; transform: translateY(-6px); }to   { opacity: 1; transform: translateY(0); }}
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            #confirmMeta {max-height: 200px;overflow-y: auto;}

        </style>
    </head>

    <body>

    <div class="app-wrapper">

        <!-- ito navbar copy paste mo -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo-container">
                <i class="fa-solid fa-graduation-cap logo-icon"></i>
                <span class="logo-text">LRC PeerConnect</span>
            </div>

            <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
                <span class="toggle-icon">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            </button>

            <nav class="flex-grow">
                <a href="{{ route('mentor.dashboard') }}" class="nav-item {{ request()->routeIs('mentor.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('mentor.bookings') }}" class="nav-item {{ request()->routeIs('mentor.bookings') ? 'active' : '' }}" data-tooltip="Booking Form">
                    <i class="fa-solid fa-calendar-check"></i><span>Booking Form</span>
                </a>
                <a href="{{ route('mentor.sessions') }}" class="nav-item {{ request()->routeIs('mentor.sessions') ? 'active' : '' }}" data-tooltip="Tutorial Sessions">
                    <i class="fa-solid fa-clock"></i><span>Tutorial Sessions</span>
                </a>
                <a href="{{ route('mentor.feedbacks') }}" class="nav-item {{ request()->routeIs('mentor.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedbacks">
                    <i class="fa-solid fa-comment-dots"></i><span>Student Feedbacks</span>
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
        <!-- hanggang dito navbar copy paste mo -->

        <div class="main-content">
            <header class="top-header relative">
                <div class="text-lg">Welcome, <span class="font-bold">{{ auth()->user()->name }}</span></div>
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                    <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200" id="dropdownArrow"></i>
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
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">

                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800">All Sessions</h2>
                            <p class="text-xs text-gray-400">All student-selected mentor sessions</p>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" id="searchInput" placeholder="Search..."
                                class="px-3 py-2 text-xs border border-gray-200 rounded-lg">
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

                    <div id="sessionsBannerArea" class="flex flex-col gap-2 mb-4"></div>
                    
                    <table class="w-full text-left text-sm table-fixed">
                        <thead class="text-gray-400 border-b">
                            <tr>
<th onclick="setSort('student')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex items-center gap-1 hover:text-red-800 transition">Student<span id="sort-student" class="text-[10px]"></span></div></th>
<th onclick="setSort('subject')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex items-center gap-1 hover:text-red-800 transition">Subject<span id="sort-subject" class="text-[10px]"></span></div></th>
<th onclick="setSort('topic')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex items-center gap-1 hover:text-red-800 transition">Topic<span id="sort-topic" class="text-[10px]"></span></div></th>
<th onclick="setSort('date')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex items-center gap-1 hover:text-red-800 transition">Date<span id="sort-date" class="text-[10px]"></span></div></th>
<th onclick="setSort('duration')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex items-center gap-1 hover:text-red-800 transition">Duration<span id="sort-duration" class="text-[10px]"></span></div></th>
<th onclick="setSort('status')" class="cursor-pointer pb-3 text-[13px] select-none"><div class="flex justify-center gap-1 hover:text-red-800 transition">Status<span id="sort-status" class="text-[10px]"></span></div></th>
<th class="pb-3 text-[13px] select-none"><div class="flex justify-end gap-1">Actions&nbsp;&nbsp;&nbsp;</div></th>
                            </tr>
                            </tr>
                        </thead>
                        <tbody id="sessionsTable">
                            <tr>
                                <td colspan="7" class="text-center py-10 text-gray-400 text-xs">Loading sessions…</td>
                            </tr>
                        </tbody>
    </table>

                    <!-- SESSIONS PAGINATION -->
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
            </main>
        </div>

        <!-- CONFIRMATION MODAL — inside app-wrapper, single root satisfied -->
        <div id="confirmModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-[#fffffa] rounded-xl p-6 max-w-sm w-full mx-4 shadow-2xl" id="confirmModalBox">

                <div class="flex items-center gap-3 mb-3">
                    <div id="confirmIconWrap" class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0">
                        <!-- icon injected by JS -->
                    </div>
                    <h3 id="confirmTitle" class="text-base font-bold text-gray-900"></h3>
                </div>

                <p id="confirmBody" class="text-sm text-gray-600 mb-1 leading-relaxed"></p>

                <div id="confirmMeta" class="mt-3 mb-5 bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-xs text-gray-600 space-y-1"></div>

                <div class="flex justify-end gap-3">
                    <button id="confirmCancelBtn"
                        class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button id="confirmOkBtn"
                        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                        Confirm
                    </button>
                </div>

            </div>
        </div>

    </div>

    <script>
        const allSessions = @json($this->sessions);

        /* =========================
        CONFLICT HELPERS
        ========================= */
        function toMin(t) {
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        }

        function hasConflict(newReq) {
            return allSessions.some(s => {
                if (s.id === newReq.id) return false;
                if (s.status !== 'accepted') return false;
                if (s.date !== newReq.date) return false;
                const sStart = toMin(s.start), sEnd = toMin(s.end);
                const rStart = toMin(newReq.start), rEnd = toMin(newReq.end);
                return rStart < sEnd && rEnd > sStart;
            });
        }

        function getConflictingPendingIds(acceptedSession) {
            const aStart = toMin(acceptedSession.start);
            const aEnd   = toMin(acceptedSession.end);
            return allSessions
                .filter(s => {
                    if (s.id === acceptedSession.id) return false;
                    if (s.status !== 'pending') return false;
                    if (s.date !== acceptedSession.date) return false;
                    const sStart = toMin(s.start), sEnd = toMin(s.end);
                    return aStart < sEnd && aEnd > sStart;
                })
                .map(s => s.id);
        }

        /* =========================
        BANNERS
        ========================= */
        function showBanner(id, html) {
            const area = document.getElementById('sessionsBannerArea');
            let banner = document.getElementById(id);
            if (!banner) {
                banner = document.createElement('div');
                banner.id = id;
                banner.style.cssText = 'border-radius:8px; overflow:hidden; font-size:11px; animation:slideDown 0.2s ease;';
                area.appendChild(banner);
            }
            banner.innerHTML = html;
            clearTimeout(banner._timer);
            banner._timer = setTimeout(() => banner.remove(), 6000);
        }

        function showConflictBanner(message) {
            showBanner('conflictBanner', `
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
                            <span style="font-weight:600;">Cannot approve —</span> ${message}
                        </div>
                        <button onclick="document.getElementById('conflictBanner').remove()"
                            style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                    </div>
                </div>
            `);
        }

        function showAutoRejectBanner(count) {
            showBanner('autoRejectBanner', `
                <div style="border:1px solid #fcd34d; background:#fffbeb; border-radius:8px;">
                    <div style="display:flex; align-items:flex-start; gap:8px; padding:10px 12px;">
                        <div style="flex-shrink:0; margin-top:2px;">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1.5L14.5 13H1.5L8 1.5Z" stroke="#d97706" stroke-width="1" stroke-linejoin="round"/>
                                <path d="M8 6v3.5" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="8" cy="11.5" r="0.75" fill="#d97706"/>
                            </svg>
                        </div>
                        <div style="flex:1; color:#92400e; line-height:1.5;">
                            <span style="font-weight:600;">${count} conflicting ${count === 1 ? 'request' : 'requests'} auto-rejected</span>
                            — overlapping bookings were declined automatically.
                        </div>
                        <button onclick="document.getElementById('autoRejectBanner').remove()"
                            style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#92400e; font-size:14px; line-height:1; padding:0;">&times;</button>
                    </div>
                </div>
            `);
        }
    function showLoadingBanner() {
            showBanner('loadingBanner', `
                <div style="border:1px solid #bfdbfe; background:#eff6ff; border-radius:8px;">
                    <div style="display:flex; align-items:center; gap:8px; padding:10px 12px;">
                        <div style="flex-shrink:0;">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="animation:spin 1s linear infinite;">
                                <circle cx="7" cy="7" r="6" stroke="#93c5fd" stroke-width="1.5"/>
                                <path d="M7 1a6 6 0 0 1 6 6" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div style="flex:1; color:#1d4ed8; line-height:1.5; font-size:11px;">
                            <span style="font-weight:600;">Updating session status</span> — please wait...
                        </div>
                    </div>
                </div>
            `);
            // Prevent auto-dismiss while loading
            const banner = document.getElementById('loadingBanner');
            if (banner) clearTimeout(banner._timer);
        }

        function hideLoadingBanner() {
            const banner = document.getElementById('loadingBanner');
            if (banner) banner.remove();
        }

        const csrfToken   = '{{ csrf_token() }}';
        const sessionsUrl = '{{ route('mentor.sessions.update') }}';

        /* =========================
        UTILS
        ========================= */
        function calculateHours(start, end) {
            const [sh, sm] = start.split(':').map(Number);
            const [eh, em] = end.split(':').map(Number);
            const diff = ((eh * 60 + em) - (sh * 60 + sm)) / 60;
            return parseFloat(diff.toFixed(2));
        }

        function getStatusColor(status) {
            switch (status) {
                case 'accepted':  return 'text-blue-700 bg-blue-100 border-blue-300';
                case 'completed': return 'text-gray-600 bg-gray-100 border-gray-300';
                case 'pending':   return 'text-yellow-700 bg-yellow-100 border-yellow-300';
                case 'rejected':  return 'text-red-700 bg-red-100 border-red-300';
                case 'cancelled': return 'text-red-700 bg-red-100 border-red-300';
                case 'no_show':   return 'text-orange-700 bg-orange-100 border-orange-300';
                default:          return 'text-gray-500 bg-gray-50 border-gray-200';
            }
        }

        function getStatusLabel(status) {
            switch (status) {
                case 'no_show':   return 'No Show';
                case 'accepted':  return 'Upcoming';
                case 'completed': return 'Completed';
                case 'rejected':  return 'Rejected';
                case 'cancelled': return 'Cancelled';
                case 'pending':   return 'Pending';
                default:          return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }

    function renderActions(s) {
            const btn = (action, status, color) =>
                `<button onclick="updateStatus('${s.id}','${status}')"
                    class="text-[10px] px-2 py-1 ${color} rounded font-semibold whitespace-nowrap">
                    ${action}
                </button>`;

            if (s.status === 'pending') {
                return btn('Accept', 'accepted', 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200')
                    + btn('Reject', 'rejected', 'bg-red-100 text-red-700 hover:bg-red-200');
            }
            if (s.status === 'accepted') {
                return btn('Complete', 'completed', 'bg-gray-100 text-gray-700 hover:bg-gray-200')
                    + btn('No-show',  'no_show',   'bg-orange-100 text-orange-700 hover:bg-orange-200')
                    + btn('Cancel',   'cancelled',  'bg-red-100 text-red-700 hover:bg-red-200');
            }
            if (s.status === 'completed') {
                return btn('Uncomplete', 'accepted', 'bg-gray-100 text-gray-500 hover:bg-gray-200');
            }
            return '<span class="text-gray-300 text-[10px]">—</span>';
        }

        function formatTimeRange(s) {
            const [start, end] = s.duration.split(' (')[0].split(' - ');
            return `${start} - ${end}`;
        }

        function formatHours(s) {
            const match = s.duration.match(/\((.*?)\)/);
            return match ? `(${match[1]})` : '';
        }

        function setSort(column) {
    if (sortColumn === column) {
        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn = column;
        sortDirection = 'asc';
    }

    sessionsPage = 0;
    renderSessions();
}

function updateSortIcons() {
    document.querySelectorAll('[id^="sort-"]').forEach(el => el.innerHTML = '');

    if (sortColumn) {
        const icon = sortDirection === 'asc' ? '↑' : '↓';
        const el = document.getElementById(`sort-${sortColumn}`);
        if (el) el.innerHTML = icon;
    }
}
        let sessionsPage = 0;
        const SESSIONS_PER_PAGE = 10;

        let sortColumn = null;
        let sortDirection = 'asc';

        function renderSessions() {
            updateSortIcons();
            const tbody  = document.getElementById('sessionsTable');
            const search = document.getElementById('searchInput').value.toLowerCase();
            const filter = document.getElementById('statusFilter').value;

            let filtered = allSessions.filter(s => {
                const searchable = [s.student, s.subject, s.topic, s.date, s.duration, s.status].join(' ').toLowerCase();
                const matchSearch = searchable.includes(search);
                const matchStatus = filter === 'All' || s.status === filter;
                return matchSearch && matchStatus;
            });

            filtered = sortSessions(filtered);

            if (!filtered.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-10 text-gray-400 text-xs">No sessions found.</td></tr>`;
                updateSessionsPagination(0, 0);
                return;
            }

            const total   = filtered.length;
            const maxPage = Math.max(0, Math.ceil(total / SESSIONS_PER_PAGE) - 1);
            if (sessionsPage > maxPage) sessionsPage = 0;

            const start   = sessionsPage * SESSIONS_PER_PAGE;
            const visible = filtered.slice(start, start + SESSIONS_PER_PAGE);

            updateSessionsPagination(total, maxPage);

            tbody.innerHTML = visible.map(s => `
                <tr class="border-b hover:bg-slate-50">
                    <td class="py-4 text-sm align-middle pr-4" style="max-width:0; width:17%;">
        <div class="flex flex-col justify-center" style="min-width:0;">
            <div class="flex items-start justify-between gap-2" style="min-width:0;">
                <span class="topic-text line-clamp-1 flex-1 leading-snug font-bold text-slate-700"
                    id="student-${s.id}">
                    ${s.student}
                </span>

                ${s.student.length > 20 ? `
                    <button onclick="toggleStudent('${s.id}')"
                        id="more-student-${s.id}"
                        class="text-[10px] text-gray-400 hover:text-gray-600 whitespace-nowrap">
                        see more
                    </button>
                ` : ''}
            </div>

            <div class="flex justify-end mt-1">
                <button onclick="toggleStudent('${s.id}')"
                    id="less-student-${s.id}"
                    class="hidden text-[10px] text-gray-400 hover:text-gray-600">
                    view less
                </button>
            </div>
        </div>
    </td>
                    <td class="text-sm">${s.subject}</td>
                    <td class="text-sm pr-4 align-middle" style="max-width:0; width:22%;">
                        <div class="py-3 flex flex-col justify-center" style="min-width:0;">
                            <div class="flex items-start justify-between gap-2" style="min-width:0;">
                                <span class="topic-text line-clamp-1 flex-1 leading-snug" style="min-width:0; max-width:100%;" id="topic-${s.id}">${s.topic}</span>
                                    ${s.topic.length > 40 ? `
                                        <button onclick="toggleTopic('${s.id}')" id=" more-${s.id}" class="text-[10px] text-gray-400 hover:text-gray-600 whitespace-nowrap self-start flex-shrink-0">see more</button>
                                ` : ''}
                            </div>
                            <div class="flex justify-end mt-1">
                                <button onclick="toggleTopic('${s.id}')" id="less-${s.id}" class="hidden text-[10px] text-gray-400 hover:text-gray-600 flex-shrink-0">view less</button>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm pr-0">${s.date}</td>
                    <td class="text-sm pl-0">
                        <div class="flex gap-2 font-medium tabular-nums">
                            <span>${formatTimeRange(s)}</span>
                            <span class="text-gray-400">${formatHours(s)}</span>
                        </div>
                    </td>
    <td class="text-center">
                        <span class="${getStatusColor(s.status)} text-[10px] px-2 py-1 rounded border font-bold">
                            ${getStatusLabel(s.status)}
                        </span>
                    </td>
    <td class="py-4 align-middle">
                        <div class="flex gap-1 items-center justify-end pr-2">
                            ${renderActions(s)}
                        </div>
                    </td>
                </tr>
    `).join('');
        }

function sortSessions(data) {
    if (!sortColumn) return data;

    const statusOrder = {
        accepted: 1,
        pending: 2,
        completed: 3,
        no_show: 4,
        cancelled: 5,
        rejected: 6
    };

    return [...data].sort((a, b) => {
        let valA = a[sortColumn];
        let valB = b[sortColumn];

        if (sortColumn === 'status') {
            valA = statusOrder[valA] ?? 999;
            valB = statusOrder[valB] ?? 999;
        }

        if (sortColumn === 'date') {
            valA = new Date(valA);
            valB = new Date(valB);
        }

        if (sortColumn === 'duration') {
            valA = a.start;
            valB = b.start;
        }

        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();

        if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
        if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
        return 0;
    });
}

    function toggleStudent(id) {
        const textEl = document.getElementById(`student-${id}`);
        const moreBtn = document.getElementById(`more-student-${id}`);
        const lessBtn = document.getElementById(`less-student-${id}`);

        const isCollapsed = textEl.classList.contains('line-clamp-1');

        if (isCollapsed) {
            textEl.classList.remove('line-clamp-1');
            textEl.classList.add('line-clamp-none');
            moreBtn?.classList.add('hidden');
            lessBtn?.classList.remove('hidden');
        } else {
            textEl.classList.add('line-clamp-1');
            textEl.classList.remove('line-clamp-none');
            lessBtn?.classList.add('hidden');
            moreBtn?.classList.remove('hidden');
        }
    }

        function updateSessionsPagination(total, maxPage) {
            const info    = document.getElementById('sessionsPageInfo');
            const prevBtn = document.getElementById('sessionsPrevBtn');
            const nextBtn = document.getElementById('sessionsNextBtn');
            const footer  = document.getElementById('sessionsPaginationFooter');

            if (!footer) return;

            if (total === 0) {
                footer.style.display = 'none';
                return;
            }

            footer.style.display = 'flex';
            const start = sessionsPage * SESSIONS_PER_PAGE;
            info.innerText = `${start + 1}–${Math.min(start + SESSIONS_PER_PAGE, total)} of ${total}`;

            prevBtn.disabled = sessionsPage === 0;
            nextBtn.disabled = sessionsPage >= maxPage;
            prevBtn.classList.toggle('opacity-30', sessionsPage === 0);
            prevBtn.classList.toggle('cursor-not-allowed', sessionsPage === 0);
            nextBtn.classList.toggle('opacity-30', sessionsPage >= maxPage);
            nextBtn.classList.toggle('cursor-not-allowed', sessionsPage >= maxPage);
        }

        const confirmModal    = document.getElementById('confirmModal');
        const confirmModalBox = document.getElementById('confirmModalBox');
        const confirmTitle    = document.getElementById('confirmTitle');
        const confirmBody     = document.getElementById('confirmBody');
        const confirmMeta     = document.getElementById('confirmMeta');
        const confirmOkBtn    = document.getElementById('confirmOkBtn');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const confirmIconWrap = document.getElementById('confirmIconWrap');

        confirmModal.addEventListener('click', (e) => {
            if (!confirmModalBox.contains(e.target)) closeConfirmModal();
        });
        confirmCancelBtn.addEventListener('click', closeConfirmModal);

        function closeConfirmModal() {
            confirmModal.style.display = 'none';
            confirmOkBtn.onclick = null;
        }

        function openConfirmModal({ title, body, meta, variant, onConfirm }) {
            const variants = {
                accept:  { icon: iconCheck(),   iconBg: 'bg-emerald-100', iconColor: '#059669', btnBg: 'bg-emerald-600 hover:bg-emerald-700', label: 'Confirm' },
                reject:  { icon: iconX(),       iconBg: 'bg-red-100',     iconColor: '#dc2626', btnBg: 'bg-red-600 hover:bg-red-700',         label: 'Reject'  },
                neutral: { icon: iconInfo(),    iconBg: 'bg-gray-100',    iconColor: '#64748b', btnBg: 'bg-gray-700 hover:bg-gray-800',        label: 'Confirm' },
            };

            const v = variants[variant] || variants.neutral;

            confirmIconWrap.className = `w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 ${v.iconBg}`;
            confirmIconWrap.innerHTML = v.icon(v.iconColor);
            confirmTitle.textContent  = title;
            confirmBody.innerHTML     = body;
            confirmMeta.innerHTML     = meta || '';
            confirmMeta.style.display = meta ? 'block' : 'none';

            confirmOkBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnBg}`;
            confirmOkBtn.textContent = v.label;
            confirmOkBtn.onclick = () => { closeConfirmModal(); onConfirm(); };

            confirmModal.style.display = 'flex';
        }

        function iconCheck() {
            return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
        }
        function iconX() {
            return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/>
            </svg>`;
        }
        function iconInfo() {
            return (color) => `<svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                <circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/>
                <path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="10" cy="6.5" r="0.8" fill="${color}"/>
            </svg>`;
        }

function truncateText(text, maxLength = 25) {
    if (!text) return '—';
    return text.length > maxLength
        ? text.substring(0, maxLength) + '...'
        : text;
}

function toggleModalText(id) {
    const textEl = document.getElementById(`modal-text-${id}`);
    const moreBtn = document.getElementById(`modal-more-${id}`);
    const lessBtn = document.getElementById(`modal-less-${id}`);

    const isCollapsed = textEl.classList.contains('line-clamp-1');

    if (isCollapsed) {
        textEl.classList.remove('line-clamp-1');
        textEl.classList.add('line-clamp-none');
        moreBtn?.classList.add('hidden');
        lessBtn?.classList.remove('hidden');
    } else {
        textEl.classList.add('line-clamp-1');
        textEl.classList.remove('line-clamp-none');
        lessBtn?.classList.add('hidden');
        moreBtn?.classList.remove('hidden');
    }
}

        function updateStatus(id, status) {

            const req = allSessions.find(s => s.id == id);
            if (!req) return;

            if (status === 'accepted' && req.status !== 'completed' && hasConflict(req)) {
                const conflict = allSessions.find(s =>
                    s.id !== req.id &&
                    s.status === 'accepted' &&
                    s.date === req.date
                );
                const conflictInfo = conflict
                    ? `Conflicts with <strong>${conflict.student}</strong> (${conflict.duration.split(' (')[0]}) on ${conflict.date}.`
                    : 'This session overlaps with an already-accepted booking.';
                showConflictBanner(conflictInfo);
                return;
            }

            const isUncomplete = status === 'accepted' && req.status === 'completed';

            const dialogConfig = {
                accepted: isUncomplete ? {
                    title:   'Revert to accepted?',
                    body:    'This will mark the session as accepted again, reversing the completed status.',
                    variant: 'neutral',
                } : {
                    title:   'Accept booking?',
                    body:    'The student will be notified that their session has been approved.',
                    variant: 'accept',
                },
                rejected: {
                    title:   'Reject booking?',
                    body:    'The student will be notified that their session request was declined.',
                    variant: 'reject',
                },
                completed: {
                    title:   'Mark as completed?',
                    body:    'This will mark the session as done.',
                    variant: 'neutral',
                },
                no_show: {
                    title:   'Mark as no-show?',
                    body:    'This will record that the student did not attend the session.',
                    variant: 'reject',
                },
                cancelled: {
                    title:   'Cancel session?',
                    body:    'This will cancel the accepted session.',
                    variant: 'reject',
                },
            };

            const cfg = dialogConfig[status] || {
                title: 'Confirm action',
                body: 'Are you sure you want to proceed?',
                variant: 'neutral',
            };

const metaHtml = `
    <!-- STUDENT -->
    <div>
        <div class="flex justify-between items-start gap-2">
            <span class="text-gray-400">Student</span>

            <div class="flex flex-col items-end max-w-[160px]">
                <span id="modal-text-student-${req.id}"
                    class="font-medium text-gray-700 text-right topic-text line-clamp-1">
                    ${req.student}
                </span>

                ${req.student.length > 25 ? `
                    <button onclick="toggleModalText('student-${req.id}')"
                        id="modal-more-student-${req.id}"
                        class="text-[10px] text-gray-400 hover:text-gray-600">
                        see more
                    </button>

                    <button onclick="toggleModalText('student-${req.id}')"
                        id="modal-less-student-${req.id}"
                        class="hidden text-[10px] text-gray-400 hover:text-gray-600">
                        view less
                    </button>
                ` : ''}
            </div>
        </div>
    </div>

    <!-- SUBJECT -->
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">Subject</span>
        <span class="font-medium text-gray-700 text-right truncate max-w-[140px]">
            ${req.subject}
        </span>
    </div>

    <!-- TOPIC -->
    <div>
        <div class="flex justify-between items-start gap-2">
            <span class="text-gray-400">Topic</span>

            <div class="flex flex-col items-end max-w-[180px]">
                <span id="modal-text-topic-${req.id}"
                    class="font-medium text-gray-700 text-right topic-text line-clamp-1">
                    ${req.topic}
                </span>

                ${req.topic.length > 40 ? `
                    <button onclick="toggleModalText('topic-${req.id}')"
                        id="modal-more-topic-${req.id}"
                        class="text-[10px] text-gray-400 hover:text-gray-600">
                        see more
                    </button>

                    <button onclick="toggleModalText('topic-${req.id}')"
                        id="modal-less-topic-${req.id}"
                        class="hidden text-[10px] text-gray-400 hover:text-gray-600">
                        view less
                    </button>
                ` : ''}
            </div>
        </div>
    </div>

    <!-- DATE -->
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">Date</span>
        <span class="font-medium text-gray-700 text-right">
            ${req.date}
        </span>
    </div>

    <!-- TIME -->
    <div class="flex justify-between gap-2">
        <span class="text-gray-400">Time</span>
        <span class="font-medium text-gray-700 text-right">
            ${formatTimeRange(req)}
        </span>
    </div>
`;



            openConfirmModal({
                title:     cfg.title,
                body:      cfg.body,
                meta:      metaHtml,
                variant:   cfg.variant,
                onConfirm: () => commitStatus(id, status, req),
            });
        }

    function commitStatus(id, status, target) {
            showLoadingBanner();

            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('booking_id', id);
            formData.append('booking_status', status);

            fetch(sessionsUrl, { method: 'POST', body: formData })
                .then(res => {
                    if (!res.ok) throw new Error('Request failed');

                    target.status = status;

                    if (status === 'accepted') {
                        const conflictingIds = getConflictingPendingIds(target);

                        if (conflictingIds.length > 0) {
                            let completed = 0;
                            conflictingIds.forEach(conflictId => {
                                const conflictSession = allSessions.find(s => s.id == conflictId);
                                if (conflictSession) conflictSession.status = 'rejected';

                                const fd = new FormData();
                                fd.append('_token', csrfToken);
                                fd.append('booking_id', conflictId);
                                fd.append('booking_status', 'rejected');

                                fetch(sessionsUrl, { method: 'POST', body: fd })
                                    .then(() => {
                                        completed++;
                                        if (completed === conflictingIds.length) {
                                            hideLoadingBanner();
                                            renderSessions();
                                            showAutoRejectBanner(conflictingIds.length);
                                        }
                                    })
                                    .catch(err => {
                                        hideLoadingBanner();
                                        console.error('Auto-reject failed for id', conflictId, err);
                                    });
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
                    showBanner('errorBanner', `
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
                                    <span style="font-weight:600;">Update failed —</span> please check your connection and try again.
                                </div>
                                <button onclick="document.getElementById('errorBanner').remove()"
                                    style="flex-shrink:0; background:none; border:none; cursor:pointer; color:#b91c1c; font-size:14px; line-height:1; padding:0;">&times;</button>
                            </div>
                        </div>
                    `);
                    console.error('commitStatus failed:', err);
                });
        }

    function toggleTopic(id) {
        const textEl = document.getElementById(`topic-${id}`);
        const moreBtn = document.getElementById(`more-${id}`);
        const lessBtn = document.getElementById(`less-${id}`);

        const isCollapsed = textEl.classList.contains('line-clamp-1');

        if (isCollapsed) {
            textEl.classList.remove('line-clamp-1');
            textEl.classList.add('line-clamp-none');
            moreBtn.classList.add('hidden');
            lessBtn.classList.remove('hidden');
        } else {
            textEl.classList.add('line-clamp-1');
            textEl.classList.remove('line-clamp-none');
            lessBtn.classList.add('hidden');
            moreBtn.classList.remove('hidden');
        }
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
            window.addEventListener('click', () => profileDropdown.classList.remove('show'));

            document.getElementById('searchInput').addEventListener('input', () => { sessionsPage = 0; renderSessions(); });
            document.getElementById('statusFilter').addEventListener('change', () => { sessionsPage = 0; renderSessions(); });

            document.getElementById('sessionsPrevBtn').addEventListener('click', () => { sessionsPage--; renderSessions(); });
            document.getElementById('sessionsNextBtn').addEventListener('click', () => { sessionsPage++; renderSessions(); });

            renderSessions();
        });
    </script>
    </body>
