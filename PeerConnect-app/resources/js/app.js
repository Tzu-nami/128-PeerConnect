import './bootstrap';
document.addEventListener('DOMContentLoaded', function() {
    // 1. Clock Logic
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true 
        });
        document.getElementById('clock').textContent = timeString;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Calendar Logic
    const calendarGrid = document.getElementById('calendarGrid');
    const monthYearDisplay = document.getElementById('monthYearDisplay');
    let currentDate = new Date();

    function renderCalendar() {
        calendarGrid.innerHTML = '';
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        monthYearDisplay.textContent = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentDate);

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();

        // Padding for empty days
        for (let i = 0; i < firstDay; i++) {
            const emptyDiv = document.createElement('div');
            calendarGrid.appendChild(emptyDiv);
        }

        // Days of the month
        for (let i = 1; i <= daysInMonth; i++) {
            const daySpan = document.createElement('span');
            daySpan.classList.add('cal-day');
            daySpan.textContent = i;

            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                daySpan.classList.add('cal-today');
            }

            calendarGrid.appendChild(daySpan);
        }
    }

    document.getElementById('prevMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
    });

    renderCalendar();
});