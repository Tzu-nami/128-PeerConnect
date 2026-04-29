<?php

use function Livewire\Volt\{layout, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect | About Us</title>
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

        .cal-header-day { font-size: 11px; font-weight: 800; color: #94a3b8; text-align: center; padding-bottom: 10px; text-transform: uppercase; }
        .cal-day { aspect-ratio: 1/1; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; border-radius: 8px; transition: all 0.2s; cursor: pointer; font-size: 13px; font-weight: 500; }
        .cal-today { background: #fee2e2 !important; color: var(--header-maroon) !important; font-weight: 800; }
        .cal-selected { border: 2px solid var(--header-maroon); background: #f8fafc; }

        .stats-overview-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); border: 1px solid #e5e7eb; width: 100%; }
        .stats-header { padding: 12px 24px; background: #f8fafc; font-weight: 700; font-size: 0.9rem; color: #1e293b; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
        .stats-body { display: grid; grid-template-columns: repeat(2, 1fr); background: white; width: 100%; }
        .stats-column { padding: 24px; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; min-width: 0; }
        .stats-column:nth-child(2n) { border-right: none; }
        .stats-column-title { font-weight: 600; margin-bottom: 15px; font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em; }

        .stat-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s ease; border: 1px solid transparent; cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: var(--sidebar-green); }
        .stat-card i { font-size: 24px; color: var(--sidebar-green); }

        .pagination-btn { padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .pagination-btn:hover:not(:disabled) { background: #f1f5f9; color: var(--header-maroon); border-color: var(--header-maroon); }
        .table-filter-select, .header-filter { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 0.75rem; color: #475569; outline: none; cursor: pointer; }
        .weekly-table{table-layout:fixed;width:100%;}
        .weekly-table th, .weekly-table td{width:16%;}
        .schedule-block{
font-size:9px;
line-height:1.2;
padding:2px 4px;
margin-bottom:2px;
border-radius:4px;
background:#d1fae5;
color:#065f46;
}

.notif-dot {
    width: 6px;
    height: 6px;
    background: #3b82f6; /* blue */
    border-radius: 50%;
    position: top;
    bottom: 6px;
}
    </style>
</head>

<body>
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
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            </button>

            <nav class="flex-grow">
                <a href="{{ route('student.dashboard') }}" class="nav-item" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge w-5"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.mentors') }}" class="nav-item" data-tooltip="Mentors">
                    <i class="fa-solid fa-chalkboard-user w-5"></i><span>Mentors</span>
                </a>
                <a href="{{ route('student.bookings') }}" class="nav-item" data-tooltip="Bookings">
                    <i class="fa-solid fa-calendar-check w-5"></i><span>Bookings</span>
                </a>
                <a href="{{ route('student.history') }}" class="nav-item" data-tooltip="History">
                    <i class="fa-solid fa-clock-rotate-left w-5"></i><span>History</span>
                </a>
                <a href="{{ route('student.about') }}" class="nav-item active" data-tooltip="About Us">
                    <i class="fa-solid fa-circle-info w-5"></i><span>About Us</span>
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
                <x-student-notifications />
                
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


    {{-- Hero --}}
    <section class="grid grid-cols-2 px-6 md:px-20 py-10">
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow-dark"></span>
                About Us
            </div>
            <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider text-3xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-[#7b1d1d] to-[#b91c1c] flex items-center gap-3">
                What is PeerConnect?
            </h1>
        </div>

        <div class="text-text-brown leading-7 border-l border-up-yellow-dark pl-5 self-center animate-fade-up">
            LRC PeerConnect connects UPB students with trained peer mentors for enrichment sessions and academic support — simple, organized, and easy to book.
        </div>
    </section>

    {{-- Main Content --}}
    <section class="animate-fade-up [animation-delay:1ms]">

            {{-- Stats --}}
            <div class="grid grid-cols-3 border border-cream-border">
                <div class="border-r border-cream-border text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">12</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Mentors</div>
                </div>
                <div class="border-r border-cream-border text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">84</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Sessions Held</div>
                </div>
                <div class="text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">10</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Subjects Covered</div>
                </div>
            </div>

            <br><br>

            {{-- Mission --}}
            <div class="flex flex-col gap-3">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase text-center" style="font-size:1.3rem">Our Mission</div>
                <p class="text-text-brown leading-7 text-center">
                    The Learning Resource Center exists to empower every UPB student with the academic tools, guidance, and peer support they need to succeed — making quality learning assistance accessible to all.
                </p>
            </div>

            <br>

            {{-- Quote --}}
            <div class="bg-cream-dark border border-cream-border px-7 py-5">
                <i class="fa-solid fa-quote-left text-3xl mb-3 text-cream-border"></i>
                <p class="italic text-text-brown mb-3 leading-7">
                    "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </p>
                <p class="text-text-brown-light text-xs text-right tracking-widest uppercase">— LRC Head</p>
            </div>

            <br><br>

            {{-- Common FAQs --}}
            <div class="flex flex-col gap-3">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase">Common Questions</div>

                <div class="border border-cream-border divide-y divide-cream-border">
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">Who can use PeerConnect?</div>
                        <div class="text-sm text-text-brown-light leading-6">Any currently enrolled UPB student can book a mentoring session through PeerConnect.</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">Is it free?</div>
                        <div class="text-sm text-text-brown-light leading-6">Yes, all sessions are completely free for UPB students.</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">How long is a session?</div>
                        <div class="text-sm text-text-brown-light leading-6">Sessions typically run for one hour, depending on the subject and mentor availability.</div>
                    </div>
                </div>

                <a href="{{ route('public.services') }}#faqs" class="text-xs text-up-maroon font-bold tracking-widest uppercase self-end hover:underline">
                    See all FAQs →
                </a>
            </div>

            {{-- How it Works --}}
            <div>
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase mb-4">How it Works</div>

                <div class="border border-cream-border divide-y divide-cream-border">
                    <div class="py-4 px-5 flex items-start gap-5">
                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">1</div>
                        <div>
                            <div class="font-bold mb-1">Log in</div>
                            <div class="text-text-brown-light text-sm leading-6">Sign in using your UP email account.</div>
                        </div>
                    </div>
                    <div class="py-4 px-5 flex items-start gap-5">
                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">2</div>
                        <div>
                            <div class="font-bold mb-1">Book a session</div>
                            <div class="text-text-brown-light text-sm leading-6">Pick a mentor, subject, date, and time that works for you.</div>
                        </div>
                    </div>
                    <div class="py-4 px-5 flex items-start gap-5">
                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">3</div>
                        <div>
                            <div class="font-bold mb-1">Wait for approval</div>
                            <div class="text-text-brown-light text-sm leading-6">Your booking is reviewed and confirmed by the LRC staff.</div>
                        </div>
                    </div>
                    <div class="py-4 px-5 flex items-start gap-5">
                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">4</div>
                        <div>
                            <div class="font-bold mb-1">Attend your session</div>
                            <div class="text-text-brown-light text-sm leading-6">Show up, ask questions, and learn actively.</div>
                        </div>
                    </div>
                    <div class="py-4 px-5 flex items-start gap-5">
                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">5</div>
                        <div>
                            <div class="font-bold mb-1">Leave a review</div>
                            <div class="text-text-brown-light text-sm leading-6">Rate your session to help improve the program for everyone.</div>
                        </div>
                    </div>
                </div>
            </div>

            <br>

            {{-- Get in Touch --}}
            <div class="border border-cream-border">
                <div class="text-cream font-bold tracking-widest uppercase border-b border-cream-border bg-up-maroon py-2 px-4">
                    Get in Touch
                </div>
                <div class="divide-y divide-cream-border">
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">location_on</span>
                        <div class="flex flex-col text-sm leading-6">
                            <div>Learning Resource Center, University of the Philippines Baguio</div>
                            <div class="text-text-brown-light">2nd Floor, University Library</div>
                        </div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">schedule</span>
                        <div class="flex flex-col text-sm leading-6">
                            <div>Monday to Friday</div>
                            <div class="text-text-brown-light">8:00 AM – 5:00 PM</div>
                        </div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">mail</span>
                        <div class="text-sm">lrc.upbaguio@up.edu.ph</div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">call</span>
                        <div class="text-sm">(074) 444 8720</div>
                    </div>
                </div>
            </div>

            {{-- Developers --}}
            <div class="border-t border-cream-border pt-7">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase mb-4">Developed By</div>

                <div class="border border-cream-border">
                    <div class="grid grid-cols-2 divide-x divide-cream-border">
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Ax'l Jhone David P. Conchada</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Daniel Joco B. Dyoco</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                    </div>
                    <div class="border-t border-cream-border grid grid-cols-2 divide-x divide-cream-border">
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Rhona Shayne B. Lopez</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Frian Karl C. Nabo</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-text-brown-light tracking-wide mt-2">
                    University of the Philippines Baguio | 2025 – 2026
                </div>
            </div>



    </section>

</main>


        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
document.getElementById('sidebarToggle').addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    const icon = document.getElementById('toggleIcon');
    icon.classList.toggle('fa-chevron-left');
    icon.classList.toggle('fa-chevron-right');
});        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');
        profileTrigger.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        window.addEventListener('click', () => profileDropdown.classList.remove('show'));
    </script>
</body>
