<?php

use function Livewire\Volt\{layout, state, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isMentor(), 403, 'Unauthorized Access');
});

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --sidebar-green: #1a3c2f; --header-maroon: #7b1d1d; --bg-light: #f4f7f6; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); }
        .app-wrapper { display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background: var(--sidebar-green); flex-shrink: 0; display: flex; flex-direction: column; color: white; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 15px; padding: 15px 25px; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .nav-item.active { border-left: 4px solid white; }
        .top-header { background: var(--header-maroon); height: 80px; padding: 0 40px; display: flex; align-items: center; justify-content: space-between; color: white; flex-shrink: 0; }
        
        /* Stat Cards with Hover Effects */
        .stat-card { background: white; padding: 25px; border-radius: 12px; transition: all 0.3s ease; border: 1px solid transparent; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--header-maroon); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .stat-card i { font-size: 24px; color: var(--sidebar-green); transition: 0.3s; }
        .stat-card:hover i { transform: scale(1.2); color: var(--header-maroon); }
        
        /* Calendar Styling */
        .cal-day { padding: 8px; border-radius: 8px; cursor: pointer; font-size: 13px; }
        .cal-day:hover { background: #f0f0f0; }
        .cal-today { background: var(--header-maroon) !important; color: white !important; font-weight: bold; }
        
        .btn-maroon { background: var(--header-maroon); color: white; font-weight: 700; width: 100%; padding: 12px; border-radius: 8px; margin-bottom: 10px; transition: 0.2s; }
        .btn-maroon:hover { filter: brightness(1.2); transform: scale(1.02); }
    </style>
</head>
<body>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="p-8 text-xl font-bold flex items-center gap-3"><i class="fa-solid fa-graduation-cap"></i> <span>LRC PeerConnect</span></div>
        <nav class="flex-grow">
            <a href="#" class="nav-item active"><i class="fa-solid fa-gauge w-5"></i> Dashboard</a>
            <a href="#" class="nav-item"><i class="fa-solid fa-user-tie w-5"></i> Mentor Management</a>
            <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check w-5"></i> Session Management</a>
            <a href="#" class="nav-item"><i class="fa-solid fa-comment-dots w-5"></i> Student Feedback</a>
        </nav>
<div class="p-4 border-t border-white/10">

    <!-- Logout -->
    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="nav-item w-full text-left">
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </button>
    </form>

</div>
    </aside>

    <div class="main-content">
<header class="top-header">
    <div class="text-lg">
        Welcome, <span class="font-bold">{{ auth()->user()->name }}</span>
    </div>

    <!-- Profile Dropdown -->
    <div class="relative">
        <button onclick="toggleProfileMenu()" 
            class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-red-900 font-bold">
            
            {{ strtoupper(substr(auth()->user()->name,0,2)) }}
        </button>

        <!-- Dropdown -->
    <div id="profileMenu"
     class="hidden absolute right-0 mt-3 w-56 bg-white rounded-lg shadow-lg border overflow-hidden">

    <!-- User Info -->
    <div class="px-4 py-3 border-b text-sm text-gray-600">
        <div class="font-semibold">{{ auth()->user()->name }}</div>
        <div class="text-xs text-gray-400">{{ auth()->user()->email }}</div>
    </div>

    <!-- Logout -->
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
            class="flex items-center gap-2 w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
            <i class="fa-solid fa-right-from-bracket w-4"></i>
            Logout
        </button>
    </form>

</div>
    </div>
</header>



        <main class="p-8">
            <div class="grid grid-cols-5 gap-4 mb-8">
                <div class="stat-card flex items-center gap-4">
                    <i class="fa-solid fa-users"></i>
                    <div><h3>Total Mentors</h3><p class="text-2xl font-black">40</p></div>
                </div>
                <div class="stat-card flex items-center gap-4">
                    <i class="fa-solid fa-calendar-day"></i>
                    <div><h3>Sessions Today</h3><p class="text-2xl font-black">18</p></div>
                </div>
                <div class="stat-card flex items-center gap-4">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><h3>Pending</h3><p class="text-2xl font-black">5</p></div>
                </div>
                <div class="stat-card flex items-center gap-4">
                    <i class="fa-solid fa-star"></i>
                    <div><h3>Ratings</h3><p class="text-2xl font-black">4.9</p></div>
                </div>
                <div class="stat-card flex items-center gap-4">
                    <i class="fa-solid fa-user-graduate"></i>
                    <div><h3>Total Mentees</h3><p class="text-2xl font-black">75</p></div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-8">
                <div class="col-span-2 space-y-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold">Today's Sessions</h2>
                            <a href="#" class="text-red-800 font-bold text-sm">View All ></a>
                        </div>
                        <table class="w-full text-left">
                            <thead class="text-gray-400 text-xs uppercase border-b">
                                <tr><th class="pb-4">Mentor</th><th class="pb-4">Student</th><th class="pb-4">Time</th><th class="pb-4">Status</th></tr>
                            </thead>
                            <tbody>
                                <tr class="border-b"><td class="py-4 font-bold">Dyoco, Daniel Joco</td><td>Nabo, Frian Karl</td><td>10:00 AM</td><td><span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold">Upcoming</span></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-6">Statistics Overview</h2>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <h3 class="text-gray-500 text-sm font-bold mb-4 uppercase">Sessions Overview</h3>
                                <canvas id="sessionsChart"></canvas>
                            </div>
                            <div>
                                <h3 class="text-gray-500 text-sm font-bold mb-4 uppercase">Feedback Summary</h3>
                                <canvas id="feedbackChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="flex justify-between items-center mb-4 font-bold">
                            <button onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                            <span id="monthDisplay">March 2026</span>
                            <button onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="grid grid-cols-7 text-center text-xs font-bold text-gray-400 mb-2">
                            <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                        </div>
                        <div id="calendarGrid" class="grid grid-cols-7 text-center text-sm gap-1"></div>
                        <div class="mt-4 pt-4 border-t text-center font-mono text-gray-400" id="liveClock">00:00:00 PM</div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <h3 class="font-bold mb-4">Quick Actions</h3>
                        <button class="btn-maroon">Add Mentor</button>
                        <button class="btn-maroon">Create Session Slot</button>
                        <button class="btn-maroon">Manage Subjects</button>
                        <button class="btn-maroon">Generate Report</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // 1. CALENDAR LOGIC
    let date = new Date();
    function renderCalendar() {
        const grid = document.getElementById('calendarGrid');
        const monthDisp = document.getElementById('monthDisplay');
        grid.innerHTML = '';
        monthDisp.innerText = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        
        const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
        const startDay = new Date(date.getFullYear(), date.getMonth(), 1).getDay();
        
        for(let i=0; i<startDay; i++) grid.innerHTML += '<div></div>';
        for(let i=1; i<=lastDay; i++) {
            const isToday = i === new Date().getDate() && date.getMonth() === new Date().getMonth();
            grid.innerHTML += `<div class="cal-day ${isToday?'cal-today':''}">${i}</div>`;
        }
    }
    function changeMonth(dir) { date.setMonth(date.getMonth() + dir); renderCalendar(); }
    function updateClock() { document.getElementById('liveClock').innerText = new Date().toLocaleTimeString(); }
    setInterval(updateClock, 1000);
    renderCalendar();

    // 2. ANALYTICS CHARTS (Dummy Data)
    new Chart(document.getElementById('sessionsChart'), {
        type: 'line',
        data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], datasets: [{ label: 'Sessions', data: [12, 19, 15, 25, 18], borderColor: '#7b1d1d', tension: 0.4 }] }
    });
    new Chart(document.getElementById('feedbackChart'), {
        type: 'doughnut',
        data: { labels: ['Excellent', 'Good', 'Average'], datasets: [{ data: [60, 30, 10], backgroundColor: ['#1a3c2f', '#7b1d1d', '#ccc'] }] }
    });

    function toggleProfileMenu() {
    document.getElementById("profileMenu").classList.toggle("hidden");
}

    window.addEventListener('click', function(e){
        const menu = document.getElementById("profileMenu");
        const button = e.target.closest("button");

        if(!button && !e.target.closest("#profileMenu")){
            menu.classList.add("hidden");
}
});

</script>
</body>
