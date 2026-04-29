<?php

use function Livewire\Volt\{layout, state, mount, computed};
use App\Models\MentorProfiles;

layout('layouts.app');

state([
    'search'        => '',
    'subjectFilter' => '',
    'page'          => 1,
    'perPage'       => 10,
]);

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

$feedbacks = computed(function () {
    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
    if (!$mentorProfile) return collect();

    $query = \DB::table('feedback')
        ->join('bookings', 'feedback.booking_id', '=', 'bookings.id')
        ->where('bookings.mentor_id', $mentorProfile->id)
        ->select(
            'feedback.id',
            'feedback.feedback',
            'feedback.subject',
            'feedback.topic',
            'feedback.date_submitted',
            'feedback.q1', 'feedback.q2', 'feedback.q3', 'feedback.q4', 'feedback.q5',
            'feedback.q6', 'feedback.q7', 'feedback.q8', 'feedback.q9', 'feedback.q10',
            'bookings.student_id',
            'bookings.date as session_date',
            'bookings.schedule_start',
            'bookings.schedule_end',
        );

    if ($this->search) {
        $search = '%' . $this->search . '%';
        $query->where(function ($q) use ($search) {
            $q->where('feedback.feedback', 'ilike', $search)
              ->orWhere('feedback.subject',  'ilike', $search)
              ->orWhere('feedback.topic',    'ilike', $search);
        });
    }

    if ($this->subjectFilter) {
        $query->where('feedback.subject', $this->subjectFilter);
    }

    return $query->orderByDesc('feedback.date_submitted')->get();
});

$subjects = computed(function () {
    $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();
    if (!$mentorProfile) return collect();

    return \DB::table('feedback')
        ->join('bookings', 'feedback.booking_id', '=', 'bookings.id')
        ->where('bookings.mentor_id', $mentorProfile->id)
        ->whereNotNull('feedback.subject')
        ->distinct()
        ->orderBy('feedback.subject')
        ->pluck('feedback.subject');
});

$paginatedFeedbacks = computed(function () {
    return collect($this->feedbacks)->forPage($this->page, $this->perPage)->values();
});

$totalPages = computed(function () {
    return max(1, (int) ceil(count($this->feedbacks) / $this->perPage));
});

?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect – Student Feedbacks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; --header-height: 80px; --sidebar-width: 260px; --sidebar-collapsed-width: 72px; }
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

        /* LAYOUT */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .top-header { background: var(--header-maroon); height: var(--header-height); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        .scroll-container { flex-grow: 1; overflow-y: scroll; padding: 32px; width: 100%; }

        /* PROFILE DROPDOWN */
        .profile-dropdown { position: absolute; top: 70px; right: 40px; background: white; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); width: 220px; display: none; flex-direction: column; z-index: 50; border: 1px solid #e2e8f0; overflow: hidden; }
        .profile-dropdown.show { display: flex; }
        .dropdown-item { padding: 12px 20px; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .dropdown-item:hover { background: #f8fafc; color: var(--header-maroon); }

        /* TABLE */
        .table-filter-select { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; cursor: pointer; background: white; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        /* Fixed table layout — equal column distribution */
        table { table-layout: fixed; width: 100%; border-collapse: collapse; }
        td, th { min-width: 0; overflow: hidden; }

        /* Column widths — all 5 columns equal share */
        .col-date     { width: 11%; }
        .col-subject  { width: 9%; }
        .col-topic    { width: 15%; }
        .col-feedback { width: 30%; }
        .col-rating   { width: 13%; }

        /* Text truncation helpers */
        .cell-text {
            display: block;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .cell-text-wrap {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* FEEDBACK ROW */
        .feedback-row { transition: background 0.15s; } 
        .feedback-row:hover { background: #f1f5f9; cursor: pointer; }

.hover-tooltip {
    position: relative;
    cursor: pointer;
}
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
.hover-tooltip:hover::after {
    opacity: 1;
    transform: translateY(0);
}

        /* RATING BADGE */
        .rating-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.15s; white-space: nowrap; border: 1.5px solid transparent; }
        .rating-pill:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(0,0,0,0.12); }
        .rating-excellent { background: #dcfce7; color: #15803d; border-color: #86efac; }
        .rating-good      { background: #dbeafe; color: #1d4ed8; border-color: #93c5fd; }
        .rating-average   { background: #fef9c3; color: #92400e; border-color: #fde68a; }
        .rating-poor      { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .ontime-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 10px; margin-top: 5px; }
        .ontime-yes { background: #d1fae5; color: #065f46; }
        .ontime-no  { background: #fee2e2; color: #991b1b; }

        /* EMPTY STATE */
        .empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px 32px; color: #9ca3af; text-align: center; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; color: #d1d5db; }

        /* ── DETAIL MODAL (full rating details) ── */
        .modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 520px;
            /* Fixed height so it never grows beyond viewport */
            height: 80vh;
            max-height: 640px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0,0,0,0.2);
            margin: 16px;
            animation: modalIn 0.18s ease;
        }
        @keyframes modalIn { from { opacity:0; transform:scale(0.96) translateY(8px); } to { opacity:1; transform:scale(1) translateY(0); } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: flex-start; justify-content: space-between; flex-shrink: 0; }
        .modal-close-btn { width: 30px; height: 30px; border-radius: 50%; border: none; background: #f1f5f9; cursor: pointer; font-size: 14px; color: #64748b; display: flex; align-items: center; justify-content: center; transition: background 0.15s; flex-shrink: 0; margin-left: 12px; }
        .modal-close-btn:hover { background: #e2e8f0; color: #1e293b; }
        /* Modal body scrolls, header stays fixed */
        .modal-body { overflow-y: auto; padding: 20px 24px 24px; flex: 1; min-height: 0; }
        .modal-body::-webkit-scrollbar { width: 5px; }
        .modal-body::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
        .modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .modal-body::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Modal header meta — truncated topic */
        .modal-meta-subject {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            display: block;
        }
        .modal-meta-topic {
            display: block;
            font-size: 11px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 380px; /* constrained so it never overflows header */
            margin-top: 1px;
        }
        .modal-meta-date {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 2px;
        }

        /* QUESTION ROWS in modal */
        .q-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .q-row:last-of-type { border-bottom: none; }
        .q-number { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; background: #f1f5f9; font-size: 10px; font-weight: 800; color: #64748b; display: flex; align-items: center; justify-content: center; }
        .q-text { flex: 1; font-size: 12px; color: #374151; line-height: 1.5; min-width: 0; }
        .q-score { flex-shrink: 0; display: flex; align-items: center; gap: 3px; }
        .q-dot { width: 9px; height: 9px; border-radius: 50%; background: #e2e8f0; }
        .q-dot.c5 { background: #16a34a; } .q-dot.c4 { background: #3b82f6; }
        .q-dot.c3 { background: #eab308; } .q-dot.c2 { background: #f97316; }
        .q-dot.c1 { background: #ef4444; }
        .q-num { font-size: 13px; font-weight: 800; margin-left: 5px; min-width: 16px; text-align: center; }
        .s5{color:#16a34a;} .s4{color:#3b82f6;} .s3{color:#eab308;} .s2{color:#f97316;} .s1{color:#ef4444;}
        .bool-answer { flex-shrink: 0; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .bool-yes { background: #dcfce7; color: #15803d; }
        .bool-no  { background: #fee2e2; color: #b91c1c; }
        .avg-bar-track { height: 8px; background: #e2e8f0; border-radius: 4px; flex: 1; overflow: hidden; }
        .avg-bar-fill { height: 100%; border-radius: 4px; }

        /* Remarks box in detail modal */
        .remarks-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 14px 14px 28px;
            margin-top: 8px;
            font-size: 13px;
            color: #374151;
            line-height: 1.6;
            position: relative;
            /* Allow long words to break */
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .remarks-box::before { content: '\201C'; font-size: 2rem; color: #d1d5db; position: absolute; top: -4px; left: 8px; line-height: 1; }

        /* ── FEEDBACK POPUP (small, text-only) ── */
        .feedback-popup-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
        }
        .feedback-popup-overlay.open { display: flex; }
        .feedback-popup-box {
            background: white;
            border-radius: 14px;
            width: 100%;
            max-width: 400px;
            /* Fixed height with scroll */
            max-height: 340px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 16px 40px rgba(0,0,0,0.18);
            margin: 16px;
            animation: modalIn 0.15s ease;
            overflow: hidden;
        }
        .feedback-popup-header {
            padding: 14px 18px 12px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .feedback-popup-header span {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .feedback-popup-body {
            padding: 16px 18px 18px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }
        .feedback-popup-body::-webkit-scrollbar { width: 4px; }
        .feedback-popup-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .feedback-popup-text {
            font-size: 13px;
            color: #374151;
            line-height: 1.7;
            /* Break long words to prevent UI overflow */
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .feedback-popup-empty {
            font-size: 12px;
            color: #94a3b8;
            font-style: italic;
        }
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
            <span class="toggle-icon"><i class="fa-solid fa-chevron-right"></i></span>
        </button>
        <nav class="flex-grow">
            <a href="{{ route('mentor.dashboard') }}" class="nav-item {{ request()->routeIs('mentor.dashboard') ? 'active' : '' }}" data-tooltip="Dashboard"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
            <a href="{{ route('mentor.bookings') }}"  class="nav-item {{ request()->routeIs('mentor.bookings')  ? 'active' : '' }}" data-tooltip="Booking Form"><i class="fa-solid fa-calendar-check"></i><span>Booking Form</span></a>
                <a href="{{ route('mentor.history') }}" class="nav-item {{ request()->routeIs('mentor.history') ? 'active' : '' }}" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i></i><span>History</span>
                </a>
            <a href="{{ route('mentor.sessions') }}"  class="nav-item {{ request()->routeIs('mentor.sessions')  ? 'active' : '' }}" data-tooltip="Tutorial Sessions"><i class="fa-solid fa-clock"></i><span>Tutorial Sessions</span></a>
            <a href="{{ route('mentor.feedbacks') }}" class="nav-item {{ request()->routeIs('mentor.feedbacks') ? 'active' : '' }}" data-tooltip="Student Feedbacks"><i class="fa-solid fa-comment-dots"></i><span>Student Feedbacks</span></a>
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
                <x-mentor-notifications />
                
                <button id="profileTrigger" class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                    <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                        {{ strtoupper(substr(auth()->user()->name,0,2)) }}
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
                <div class="mb-6 pb-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-up-maroon flex items-center gap-3">
                            Student Feedbacks
                        </h1>
                        <p class="text-sm font-medium text-slate-500 mt-1">All student feedbacks collected</p>
                    </div>
                </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">

                    {{-- Header bar --}}
                    <div class="p-6 border-b border-gray-100 flex flex-wrap gap-4 justify-between items-center bg-white">
                        <div>
                            <h2 class="font-bold text-slate-800 text-sm">
                                All Feedbacks
                            </h2>
                            <p class="text-xs text-gray-400 font-medium">
                                {{ count($this->feedbacks) }} {{ count($this->feedbacks) === 1 ? 'feedback' : 'feedbacks' }} found
                            </p>
                        </div>
                        <div class="flex gap-3 flex-wrap items-center">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                <input type="text" wire:model.live.debounce.300ms="search"
                                    placeholder="Search..."
                                    class="w-full pl-8 pr-3 py-1.5 text-xs font-medium text-slate-700 placeholder-gray-400 border border-gray-200 rounded-lg bg-white outline-none focus:ring-1 focus:border-up-maroon focus:ring-up-maroon w-56 h-[34px] transition-shadow">
                            </div>
                            <select wire:model.live="subjectFilter" class="table-filter-select">
                                <option value="">All Subjects</option>
                                @foreach($this->subjects as $sub)
                                    <option value="{{ $sub }}">{{ $sub }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Table --}}
                    @if(count($this->paginatedFeedbacks) > 0)
                    <div class="w-full overflow-x-auto">
                        <table>
                            <thead class="bg-slate-50 text-gray-400 text-[10px] uppercase tracking-wider">
                                <tr>
                                    <th class="px-5 py-4 font-semibold text-left col-date">Date</th>
                                    <th class="px-5 py-4 font-semibold text-left col-subject">Subject</th>
                                    <th class="px-5 py-4 font-semibold text-left col-topic">Topic</th>
                                    <th class="px-5 py-4 font-semibold text-left col-feedback">Feedback</th>
                                    <th class="px-5 py-4 font-semibold text-right col-rating">Rating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">

                            @foreach($this->paginatedFeedbacks as $fb)
                            @php
                                $scores = array_filter([
                                    $fb->q1, $fb->q2, $fb->q3, $fb->q4, $fb->q5,
                                    $fb->q6, $fb->q7, $fb->q8, $fb->q9,
                                ], fn($v) => !is_null($v));

                                $avg = count($scores) > 0
                                    ? round(array_sum($scores) / count($scores), 1)
                                    : null;

                                $avgClass = match(true) {
                                    $avg === null => '',
                                    $avg >= 4.5   => 'rating-excellent',
                                    $avg >= 3.5   => 'rating-good',
                                    $avg >= 2.5   => 'rating-average',
                                    default       => 'rating-poor',
                                };

                                $avgLabel = match(true) {
                                    $avg === null => 'N/A',
                                    $avg >= 4.5   => 'Excellent',
                                    $avg >= 3.5   => 'Good',
                                    $avg >= 2.5   => 'Average',
                                    default       => 'Poor',
                                };

                                // Data for the full detail modal (opened via Rating click)
                                $modalData = json_encode([
                                    'subject'  => $fb->subject ?? '—',
                                    'topic'    => $fb->topic   ?? '—',
                                    'date'     => $fb->date_submitted
                                        ? \Carbon\Carbon::parse($fb->date_submitted)->format('M j, Y')
                                        : '—',
                                    'avg'      => $avg,
                                    'avgLabel' => $avgLabel,
                                    'q1'  => $fb->q1,  'q2'  => $fb->q2,
                                    'q3'  => $fb->q3,  'q4'  => $fb->q4,
                                    'q5'  => $fb->q5,  'q6'  => $fb->q6,
                                    'q7'  => $fb->q7,  'q8'  => $fb->q8,
                                    'q9'  => $fb->q9,  'q10' => $fb->q10,
                                    'feedback' => $fb->feedback ?? null,
                                ]);

                                // Data for the simple feedback popup (opened via Feedback text click)
                                $popupData = json_encode([
                                    'feedback' => $fb->feedback ?? null,
                                ]);
                            @endphp

                            <tr class="feedback-row cursor-pointer" @click="openDetailModal({ 
    mentor: fb.mentor_name, 
    subject: fb.subject, 
    topic: fb.topic, 
    date: fb.date_formatted, 
    avg: fb.avg, 
    avgLabel: fb.avgLabel, 
    q1: fb.q1, q2: fb.q2, q3: fb.q3, q4: fb.q4, q5: fb.q5, q6: fb.q6, q7: fb.q7, q8: fb.q8, q9: fb.q9, q10: fb.q10, 
    feedback: fb.has_feedback ? fb.feedback : null 
})">

                                {{-- Date --}}
                                <td class="px-5 py-5 align-middle col-date">
                                    <span class="cell-text text-slate-700 text-[13px] font-semibold">
                                        {{ $fb->date_submitted ? \Carbon\Carbon::parse($fb->date_submitted)->format('M j, Y') : '—' }}
                                    </span>
                                </td>

                                {{-- Subject --}}
                                <td class="px-5 py-5 align-middle col-subject">
                                    <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold border border-red-100 whitespace-nowrap">
                                        {{ $fb->subject ?? '—' }}
                                    </span>
                                </td>

                                {{-- Topic — 2-line clamp, break long words --}}
                                <td class="px-5 py-5 align-middle col-topic" style = "overflow:visible; position:relative;">
                                    <div class="hover-tooltip" data-full="{{ $fb->topic ?? '—' }} ">
                                    <span class="cell-text-wrap text-xs text-slate-600">
                                        {{ $fb->topic ?? '—' }}
                                    </span>
                                    </div>
                                </td>

                                {{-- Feedback — click opens simple text popup only --}}
                                <td class="px-5 py-5 align-middle col-feedback">
                                    <button type="button"
                                        onclick='openFeedbackPopup({{ $popupData }})'
                                        class="text-left w-full group">
                                        <span class="cell-text-wrap text-[11px] bg-slate-100 px-2 py-1 rounded text-slate-700 font-semibold block group-hover:bg-slate-200 transition-colors">
                                            {{ $fb->feedback ?? '—' }}
                                        </span>
                                    </button>
                                </td>

                                {{-- Rating — click opens full detail modal --}}
<td class="px-5 py-5 align-middle col-rating text-right">


                            </tr>
                            @endforeach

                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="p-4 border-t border-gray-100 flex justify-between items-center bg-slate-50 text-xs text-gray-500">
                        <span>
                            Showing {{ (($this->page - 1) * $this->perPage) + 1 }}–{{ min($this->page * $this->perPage, count($this->feedbacks)) }}
                            of {{ count($this->feedbacks) }}
                        </span>
                        <div class="flex gap-2 items-center">
                            <button wire:click="$set('page', {{ max(1, $this->page - 1) }})"
                                @if($this->page <= 1) disabled @endif class="pagination-btn">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </button>
                            @for($p = 1; $p <= $this->totalPages; $p++)
                                <button wire:click="$set('page', {{ $p }})"
                                    class="pagination-btn {{ $this->page === $p ? 'bg-red-800 text-white border-red-800' : '' }}">
                                    {{ $p }}
                                </button>
                            @endfor
                            <button wire:click="$set('page', {{ min($this->totalPages, $this->page + 1) }})"
                                @if($this->page >= $this->totalPages) disabled @endif class="pagination-btn">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        </div>
                        <span></span>
                    </div>

                    @else
                    <div class="empty-state">
                        <i class="fa-regular fa-comment-dots"></i>
                        <p class="text-sm font-semibold text-gray-400 mb-1">No feedback found</p>
                        <p class="text-xs text-gray-400">
                            @if($this->search || $this->subjectFilter)
                                No results match your current filters. Try adjusting your search.
                            @else
                                Student feedback will appear here once sessions are marked complete and reviewed.
                            @endif
                        </p>
                    </div>
                    @endif

                </div>
            </div>
        </main>
    </div>


{{-- ═══════════ SIMPLE FEEDBACK POPUP (text only) ═══════════ --}}
<div class="feedback-popup-overlay" id="feedbackPopup" onclick="if(event.target===this) closeFeedbackPopup()">
    <div class="feedback-popup-box">
        <div class="feedback-popup-header">
            <span>
                <i class="fa-regular fa-comment-dots" style="color:#94a3b8;"></i>
                Student Remark
            </span>
            <button class="modal-close-btn" onclick="closeFeedbackPopup()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="feedback-popup-body" id="feedbackPopupBody"></div>
    </div>
</div>

{{-- ═══════════ DETAIL MODAL (full rating details) ═══════════ --}}
<div class="modal-overlay" id="feedbackModal" onclick="if(event.target===this) closeDetailModal()">
    <div class="modal-box">
        <div class="modal-header">
            <div style="min-width:0;flex:1;">
                <h3 class="text-base font-bold text-slate-800">Session Feedback Details</h3>
                <div id="modalMeta" style="margin-top:4px;"></div>
            </div>
            <button class="modal-close-btn" onclick="closeDetailModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

</div>{{-- end app-wrapper --}}

<script>
    /* ── SIDEBAR / DROPDOWN ── */
    document.getElementById('sidebarToggle').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('collapsed'));
    document.getElementById('profileTrigger').addEventListener('click', (e) => { e.stopPropagation(); document.getElementById('profileDropdown').classList.toggle('show'); });
    window.addEventListener('click', () => { document.getElementById('profileDropdown').classList.remove('show'); });

    /* ── ESC key closes any open modal/popup ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeFeedbackPopup();
            closeDetailModal();
        }
    });

    /* ════════════════════════════════════
       SIMPLE FEEDBACK POPUP
       Opens when clicking the feedback text cell.
       Shows only the remark text, nothing else.
    ════════════════════════════════════ */
    function openFeedbackPopup(data) {
        const body = document.getElementById('feedbackPopupBody');

        if (data.feedback) {
            body.innerHTML = `<p class="feedback-popup-text">${escapeHtml(data.feedback)}</p>`;
        } else {
            body.innerHTML = `<p class="feedback-popup-empty">No additional remarks provided.</p>`;
        }

        document.getElementById('feedbackPopup').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFeedbackPopup() {
        document.getElementById('feedbackPopup').classList.remove('open');
        // Only restore scroll if detail modal is also closed
        if (!document.getElementById('feedbackModal').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }

    /* ════════════════════════════════════
       FULL DETAIL MODAL
       Opens when clicking the Rating cell.
       Shows all Likert scores, Q10, and remarks.
    ════════════════════════════════════ */
    const QUESTIONS = [
        'The topics have been discussed very well.',
        'I have learned a lot from the Tutorial Session.',
        'The mentor is good enough in doing his/her tasks.',
        'The mentor was able to clearly explain the topics I do not understand.',
        'There were adequate exercises given.',
        'The mentor has mastery of the subject matter.',
        'The mentor introduces new techniques or simpler approach to the subject.',
        'I will recommend the Tutorial Sessions to my classmates.',
        'I am coming back to attend more Tutorial Sessions.',
    ];

    function dotClass(score) { return ['','c1','c2','c3','c4','c5'][score] ?? ''; }
    function numClass(score) { return ['','s1','s2','s3','s4','s5'][score] ?? ''; }
    function barColor(avg) {
        if (avg >= 4.5) return '#16a34a';
        if (avg >= 3.5) return '#3b82f6';
        if (avg >= 2.5) return '#eab308';
        return '#ef4444';
    }
    function buildDots(score) {
        return [1,2,3,4,5].map(i =>
            `<div class="q-dot ${i <= score ? dotClass(score) : ''}"></div>`
        ).join('');
    }
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openDetailModal(data) {
        /* ── Modal header meta ── */
        const metaEl = document.getElementById('modalMeta');
        metaEl.innerHTML = `
            <span class="modal-meta-subject">${escapeHtml(data.subject)}</span>
            <span class="modal-meta-topic" title="${escapeHtml(data.topic)}">${escapeHtml(data.topic)}</span>
            <span class="modal-meta-date">${escapeHtml(data.date)}</span>
        `;

        const avg    = data.avg;
        const avgPct = avg ? ((avg / 5) * 100).toFixed(1) : 0;
        const bc     = avg ? barColor(avg) : '#e2e8f0';

        let html = '';

        /* Average summary card */
        if (avg !== null && avg !== undefined) {
            html += `
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">
                        Average Score &mdash; Q1 to Q9
                    </span>
                    <span style="font-size:20px;font-weight:800;color:${bc};">
                        ${avg} <span style="font-size:12px;color:#94a3b8;">/ 5</span>
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="avg-bar-track">
                        <div class="avg-bar-fill" style="width:${avgPct}%;background:${bc};"></div>
                    </div>
                    <span style="font-size:11px;font-weight:700;color:${bc};white-space:nowrap;">${data.avgLabel}</span>
                </div>
            </div>`;
        }

        /* Q1–Q9 */
        html += `<p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">
                    Likert Scale (1 = Strongly Disagree &nbsp;·&nbsp; 5 = Strongly Agree)
                 </p>`;

        ['q1','q2','q3','q4','q5','q6','q7','q8','q9'].forEach((key, idx) => {
            const s = data[key];
            const valid = s !== null && s !== undefined;
            html += `
            <div class="q-row">
                <div class="q-number">${idx + 1}</div>
                <div class="q-text">${QUESTIONS[idx]}</div>
                <div class="q-score">
                    ${valid ? buildDots(s) : ''}
                    <span class="q-num ${valid ? numClass(s) : ''}">${valid ? s : '—'}</span>
                </div>
            </div>`;
        });

        /* Q10 */
        const q10 = data.q10;
        const q10Html = (q10 === null || q10 === undefined)
            ? `<span style="font-size:11px;color:#94a3b8;">—</span>`
            : q10
                ? `<span class="bool-answer bool-yes"><i class="fa-solid fa-check" style="font-size:9px;margin-right:3px;"></i>Yes &mdash; On time</span>`
                : `<span class="bool-answer bool-no"><i class="fa-solid fa-xmark" style="font-size:9px;margin-right:3px;"></i>No &mdash; Late</span>`;

        html += `
        <div class="q-row" style="border-bottom:none;">
            <div class="q-number">10</div>
            <div class="q-text">The peer mentor started the session on time.</div>
            <div class="q-score">${q10Html}</div>
        </div>`;

        /* Remarks */
        html += `
        <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;margin-top:20px;margin-bottom:6px;">
            Additional Remarks
        </p>`;

        if (data.feedback) {
            html += `
            <div class="remarks-box">
                ${escapeHtml(data.feedback)}
            </div>`;
        } else {
            html += `<p style="font-size:12px;color:#d1d5db;font-style:italic;padding:6px 0;">No additional remarks provided.</p>`;
        }

        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('feedbackModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailModal() {
        document.getElementById('feedbackModal').classList.remove('open');
        if (!document.getElementById('feedbackPopup').classList.contains('open')) {
            document.body.style.overflow = '';
        }
    }
</script>
