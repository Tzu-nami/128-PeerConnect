<?php use function Livewire\Volt\{layout, state, mount}; layout('layouts.app'); mount(function () { abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access'); }); ?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LRC PeerConnect Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: var(--bg-light);
        overflow: hidden;
    }

    .app-wrapper {
        display: flex;
        height: 100vh;
        width: 100vw;
        overflow: hidden;
    }

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
        overflow: visible;        /* allow toggle button to poke out */
    }

    .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

    /* ── Logo row ── */
    .sidebar-logo-container {
        height: var(--header-height);
        display: flex;
        align-items: center;
        justify-content: center;    /* center when collapsed */
        padding: 0 20px;
        gap: 12px;
        flex-shrink: 0;
        overflow: hidden;
        transition: padding 0.3s, justify-content 0.3s;
    }

    .sidebar:not(.collapsed) .sidebar-logo-container {
        justify-content: flex-start;
    }

    .logo-icon {
        flex-shrink: 0;
        font-size: 1.25rem;
        width: 32px;
        text-align: center;
    }

    .logo-text {
        font-size: 1rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        opacity: 1;
        max-width: 200px;
        transition: opacity 0.2s, max-width 0.3s;
    }

    .sidebar.collapsed .logo-text {
        opacity: 0;
        max-width: 0;
        pointer-events: none;
    }

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

    .sidebar.collapsed .nav-item {
        justify-content: center;
        padding: 14px 0;
    }

    .nav-item i {
        width: 32px;
        text-align: center;
        flex-shrink: 0;
        font-size: 18px;
        transition: width 0.3s;
    }

    .sidebar.collapsed .nav-item i {
        width: 32px;
        margin: 0;
    }

    .nav-item span {
        overflow: hidden;
        opacity: 1;
        max-width: 200px;
        transition: opacity 0.2s, max-width 0.3s;
    }

    .sidebar.collapsed .nav-item span {
        opacity: 0;
        max-width: 0;
        pointer-events: none;
    }

    .nav-item:hover, .nav-item.active {
        background: rgba(255,255,255,0.1);
        color: white;
    }

    .nav-item.active {
        background: var(--bg-light);
        color: var(--header-maroon);
        font-weight: 700;
        border-radius: 0;
        width: calc(100% + 1px);
        z-index: 10;
    }

    /* Tooltips (collapsed only) */
    .nav-item::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 14px;
        background: rgba(0,0,0,0.85);
        color: white;
        padding: 5px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s;
        pointer-events: none;
        z-index: 100;
    }

    .sidebar.collapsed .nav-item:hover::after {
        opacity: 1;
        visibility: visible;
    }

    /* Logout section */
    .sidebar-footer {
        padding: 12px 0;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* ── TOGGLE BUTTON ── */
    /* Sits exactly at the intersection of sidebar right edge + header bottom */
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
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* When sidebar is open, chevron points LEFT (close); collapsed → points RIGHT (open) */
    .sidebar:not(.collapsed) .sidebar-toggle-btn .toggle-icon {
        transform: rotate(180deg);
    }

    /* ── MAIN CONTENT ── */
    .main-content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
    }

    .top-header {
        background: var(--header-maroon);
        height: var(--header-height);
        padding: 0 40px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: white;
        flex-shrink: 0;
    }

    .scroll-container {
        flex-grow: 1;
        overflow-y: auto;
        padding: 32px;
        width: 100%;
    }

    /* ── PROFILE DROPDOWN ── */
    .profile-dropdown {
        position: absolute;
        top: 70px;
        right: 40px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
        width: 220px;
        display: none;
        flex-direction: column;
        z-index: 50;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .profile-dropdown.show { display: flex; }

    .dropdown-item {
        padding: 12px 20px;
        font-size: 13px;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.2s;
    }

    .dropdown-item:hover {
        background: #f8fafc;
        color: var(--header-maroon);
    }

    /* ── TABLE HELPERS ── */
    .table-filter-select, .header-filter {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.75rem;
        color: #475569;
        outline: none;
        cursor: pointer;
    }

    .pagination-btn {
        padding: 4px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        transition: all 0.2s;
        cursor: pointer;
        background: white;
    }

    .pagination-btn:hover:not(:disabled) {
        background: #f1f5f9;
        color: var(--header-maroon);
        border-color: var(--header-maroon);
    }
</style>
</head>
<body>
<div class="app-wrapper">

    <!-- ═══════════════ SIDEBAR ═══════════════ -->
    <aside class="sidebar" id="sidebar">

        <!-- Logo -->
        <div class="sidebar-logo-container">
            <i class="fa-solid fa-graduation-cap logo-icon"></i>
            <span class="logo-text">LRC PeerConnect</span>
        </div>

        <!-- Toggle button – sits at intersection of sidebar / header / main -->
        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">
                <i class="fa-solid fa-chevron-right"></i>
            </span>
        </button>

        <!-- Nav -->
        <nav class="flex-grow">
            <a href="{{ route('mentor.dashboard') }}"
               class="nav-item {{ request()->routeIs('mentor.dashboard') ? 'active' : '' }}"
               data-tooltip="Dashboard">
                <i class="fa-solid fa-gauge"></i><span>Dashboard</span>
            </a>
            <a href="{{ route('mentor.bookings') }}"
               class="nav-item {{ request()->routeIs('mentor.bookings') ? 'active' : '' }}"
               data-tooltip="Booking Form">
                <i class="fa-solid fa-calendar-check"></i><span>Booking Form</span>
            </a>
            <a href="{{ route('mentor.sessions') }}"
               class="nav-item {{ request()->routeIs('mentor.sessions') ? 'active' : '' }}"
               data-tooltip="Tutorial Sessions">
                <i class="fa-solid fa-clock"></i><span>Tutorial Sessions</span>
            </a>
            <a href="{{ route('mentor.feedbacks') }}"
               class="nav-item {{ request()->routeIs('mentor.feedbacks') ? 'active' : '' }}"
               data-tooltip="Student Feedbacks">
                <i class="fa-solid fa-comment-dots"></i><span>Student Feedbacks</span>
            </a>
        </nav>

        <!-- Logout -->
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item" data-tooltip="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- ═══════════════ MAIN ═══════════════ -->
    <div class="main-content">
        <header class="top-header relative">
            <div class="text-lg">
                Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
            </div>
            <button id="profileTrigger"
                    class="flex items-center gap-2 px-3 py-1 bg-white rounded-full hover:bg-gray-100 transition shadow-sm border-2 border-white/20 group">
                <div class="w-8 h-8 bg-red-900 text-white rounded-full flex items-center justify-center text-xs font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 group-hover:text-red-900 transition-transform duration-200"
                   id="dropdownArrow"></i>
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
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-white">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                                <i class="fa-solid fa-user-secret text-gray-400"></i>
                                Anonymous Student Feedbacks
                            </h2>
                            <p class="text-xs text-gray-500">Student identities are hidden to encourage honest reporting.</p>
                        </div>
                        <div class="flex gap-3">
                            <select class="table-filter-select">
                                <option>All Ratings</option>
                                <option>5 Stars</option>
                                <option>4 Stars</option>
                                <option>Below 3</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-gray-400 text-xs uppercase">
                                <tr>
                                    <th class="px-6 py-4">Participant Ref</th>
                                    <th class="px-6 py-4">Course/Year</th>
                                    <th class="px-6 py-4">Comment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-8821</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Computer Science - 2nd Year</td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-slate-600 italic">"The peer mentor was very patient with the coding exercises."</p>
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-600">USR-4509</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700 font-medium">BS Information Tech - 1st Year</td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-slate-600 italic">"Hard to hear the audio during the session."</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-gray-100 flex justify-between items-center bg-slate-50 text-xs text-gray-500">
                        <span></span>
                        <div class="flex gap-2">
                            <button class="pagination-btn">Previous</button>
                            <button class="pagination-btn bg-red-800 text-white border-red-800">1</button>
                            <button class="pagination-btn">Next</button>
                        </div>
                        <span></span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    const sidebar   = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const profileTrigger  = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });

    profileTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });

    window.addEventListener('click', () => {
        if (profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show');
        }
    });
</script>
</body>
