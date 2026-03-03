// Sidebar Toggle
document.getElementById('toggleMenu').onclick = () => {
    document.body.classList.toggle('sidebar-closed');
};

// Interactive Calendar
let date = new Date();
function renderCalendar() {
    const monthDisplay = document.getElementById('monthYearDisplay');
    const grid = document.getElementById('calendarGrid');
    if(!grid) return; // Only run on dashboard

    const year = date.getFullYear();
    const month = date.getMonth();
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    
    monthDisplay.innerText = `${monthNames[month]} ${year}`;
    grid.innerHTML = `<small>Su</small><small>Mo</small><small>Tu</small><small>We</small><small>Th</small><small>Fr</small><small>Sa</small>`;
    
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    
    for(let x=0; x<firstDay; x++) grid.innerHTML += `<span></span>`;
    for(let i=1; i<=lastDate; i++) {
        let isToday = (i === new Date().getDate() && month === new Date().getMonth()) ? 'class="today"' : '';
        grid.innerHTML += `<span ${isToday}>${i}</span>`;
    }
}

document.getElementById('prevMonth').onclick = () => { date.setMonth(date.getMonth()-1); renderCalendar(); };
document.getElementById('nextMonth').onclick = () => { date.setMonth(date.getMonth()+1); renderCalendar(); };
renderCalendar();

// Clock
setInterval(() => {
    const clock = document.getElementById('clock');
    if(clock) clock.innerText = new Date().toLocaleTimeString();
}, 1000);