<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LRC PeerConnect Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Flex:wght@100..1000&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="sidebar-open">

<aside class="sidebar">
    <div class="sidebar-brand-area">
        <button id="toggleMenu" class="hamburger-btn"><i class="fa-solid fa-bars"></i></button>
        <div class="brand-name">
            <img src="logo.jpg" alt="LRC" onerror="this.src='https://via.placeholder.com/30';">
            <span>LRC PeerConnect</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="top-links">
            <a href="#" class="nav-item active"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-users"></i> <span>Mentor Management</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-calendar-check"></i> <span>Session Management</span></a>
            <a href="#" class="nav-item"><i class="fa-solid fa-comments"></i> <span>Student Feedback</span></a>
        </div>
        <div class="bottom-links">
            <a href="#" class="nav-item"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
            <a href="#" class="nav-item logout"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
        </div>
    </nav>
</aside>

<div class="main-layout">
    <header class="top-header">
        <span class="welcome-label">Welcome, <strong>Admin Name</strong></span>
        <div class="user-circle">AN</div>
    </header>

    <main class="dashboard-body">
        <div class="stats-row">
            <div class="stat-card clickable"><i class="fa-solid fa-user-group"></i><div class="txt"><h3>Total Mentors</h3><p>40</p></div></div>
            <div class="stat-card clickable"><i class="fa-solid fa-chalkboard-user"></i><div class="txt"><h3>Sessions Today</h3><p>18</p></div></div>
            <div class="stat-card clickable"><i class="fa-solid fa-triangle-exclamation"></i><div class="txt"><h3>Pending Requests</h3><p>5</p></div></div>
            <div class="stat-card clickable"><i class="fa-solid fa-star"></i><div class="txt"><h3>Average Ratings</h3><p>4.9</p></div></div>
            <div class="stat-card clickable"><i class="fa-solid fa-user-graduate"></i><div class="txt"><h3>Total Mentees</h3><p>75</p></div></div>
        </div>

        <div class="grid-container">
            <div class="table-section card">
                <div class="card-header">
                    <h2>Today's Sessions</h2>
                    <a href="#" class="view-all">View All ></a>
                </div>
                <div class="table-wrapper">
                    <table class="session-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Mentor</th>
                                <th style="width: 30%;">Student</th>
                                <th style="width: 15%;">Time</th>
                                <th style="width: 15%;" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Dyoco, Daniel Joco</td>
                                <td>Nabo, Frian Karl</td>
                                <td>10:00 AM</td>
                                <td class="text-center"><span class="status-badge">Upcoming</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="widget-col">
                <div class="calendar-card card">
                    <div class="cal-nav">
                        <i class="fa-solid fa-chevron-left" id="prevMonth" style="cursor:pointer;"></i>
                        <span id="monthYearDisplay"></span>
                        <i class="fa-solid fa-chevron-right" id="nextMonth" style="cursor:pointer;"></i>
                    </div>
                    <div class="cal-grid" id="calendarGrid">
                        </div>
                    <div class="clock-display">
                        <i class="fa-regular fa-clock"></i> <span id="clock">00:00:00 PM</span>
                    </div>
                </div>

                <div class="quick-actions-card card">
                    <h3>Quick Actions</h3>
                    <div class="action-buttons">
                        <button class="btn-action">Add Mentor</button>
                        <button class="btn-action">Create Session Slot</button>
                        <button class="btn-action">Manage Subjects</button>
                        <button class="btn-action">Generate Report</button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="script.js"></script>
</body>
</html>