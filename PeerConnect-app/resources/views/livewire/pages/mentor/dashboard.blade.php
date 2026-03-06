<?php

use function Livewire\Volt\{layout};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user(), 403, 'Unauthorized Access');
});

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LRC PeerConnect Mentor Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

/* COLORS */

:root{
--sidebar:#14532d;
--header:#991b1b;
}

/* BODY */

body{
margin:0;
font-family:Inter, sans-serif;
background:linear-gradient(180deg,#0b1c33,#091628);
}

/* LAYOUT */

.wrapper{
display:flex;
height:100vh;
}

/* SIDEBAR */

.sidebar{
width:260px;
background:var(--sidebar);
color:white;
display:flex;
flex-direction:column;
transition:0.3s;
}

.sidebar.collapsed{
width:70px;
}

.logo{
display:flex;
align-items:center;
gap:12px;
padding:22px 22px;
font-weight:700;
font-size:19px;
}

.nav-item{
display:flex;
align-items:center;
gap:16px;
padding:18px 22px;
color:white;
text-decoration:none;
font-size:16px;
font-weight:500;
transition:0.2s;
}

.nav-item:hover{
background:rgba(255,255,255,0.15);
}

.nav-item i{
width:24px;
text-align:center;
font-size:18px;
}

.sidebar.collapsed .menu-text{
display:none;
}

.sidebar.collapsed .logo-text{
display:none;
}

.sidebar.collapsed .nav-item{
justify-content:center;
}

/* MAIN */

.main{
flex:1;
display:flex;
flex-direction:column;
}

/* HEADER */

.header{
height:70px;
background:var(--header);
color:white;
display:flex;
align-items:center;
justify-content:space-between;
padding:0 25px;
}

.header-left{
display:flex;
align-items:center;
gap:15px;
}

.profile{
width:36px;
height:36px;
border-radius:50%;
background:white;
}

/* CONTENT */

.content{
padding:30px;
}

/* BIG DASHBOARD CONTAINER */

.page-container{
background:white;
padding:35px;
border-radius:14px;
box-shadow:0 10px 30px rgba(0,0,0,0.15);
}

/* WELCOME */

.welcome-section{
margin-bottom:30px;
max-width:700px;
}

.welcome-title{
font-size:32px;
font-weight:700;
margin-bottom:10px;
}

.welcome-subtitle{
font-size:18px;
margin-bottom:15px;
color:#475569;
}

.feature-box{
display:flex;
flex-direction:column;
gap:10px;
}

.feature-item{
display:flex;
align-items:center;
gap:10px;
font-size:17px;
}

.feature-item input{
width:16px;
height:16px;
accent-color:#22c55e;
}

/* DASHBOARD GRID */

.dashboard-grid{
display:grid;
grid-template-columns:minmax(320px,1fr) minmax(500px,2fr);
grid-auto-rows:min-content;
gap:25px;
align-items:stretch;
}

.sidebar-divider{
height:1px;
background:rgba(255,255,255,0.2);
margin:15px 20px;
}

.sidebar-bottom{
margin-top:auto;
padding-bottom:10px;
}

.calendar-card{
grid-column:1;
}

.analytics-card{
grid-column:2;
display:flex;
flex-direction:column;
}

.hours-card{
grid-column:1;
}


/* CARDS */

.card{
background:#f9fafb;
border-radius:12px;
padding:25px;
box-shadow:0 4px 12px rgba(0,0,0,0.08);
}

/* CHART */

.chart-container{
position:relative;
width:100%;
height:260px;
}

.analytics-card canvas{
width:100%!important;
height:100%!important;
}

/* CALENDAR */

#calendarGrid div{
padding:10px;
border-radius:6px;
}

#calendarGrid div:hover{
background:#e5e7eb;
cursor:pointer;
}

/* DARK MODE SUPPORT */

@media (prefers-color-scheme: dark){

.page-container{
background:#0f172a;
color:white;
}

.card{
background:#1e293b;
color:white;
}

.welcome-subtitle{
color:#cbd5f5;
}

.feature-item{
color:#e2e8f0;
}

}

</style>

</head>

<body>

<div class="wrapper">

<!-- SIDEBAR -->

<div class="sidebar" id="sidebar">

<div class="logo">
<i class="fa-solid fa-graduation-cap"></i>
<span class="logo-text">LRC PeerConnect</span>
</div>

<a class="nav-item">
<i class="fa-solid fa-house"></i>
<span class="menu-text">Dashboard</span>
</a>

<a class="nav-item">
<i class="fa-solid fa-calendar-check"></i>
<span class="menu-text">Sessions</span>
</a>

<a class="nav-item">
<i class="fa-solid fa-users"></i>
<span class="menu-text">Boooking</span>
</a>

<!-- <a class="nav-item">
<i class="fa-solid fa-clock-rotate-left"></i>
<span class="menu-text">History</span>
</a> -->

<a class="nav-item">
<i class="fa-solid fa-circle-info"></i>
<span class="menu-text">Feedback</span>
</a>

<div class="sidebar-divider"></div>

<div class="sidebar-bottom">

<a class="nav-item">
<i class="fa-solid fa-gear"></i>
<span class="menu-text">Settings</span>
</a>

<a class="nav-item">
<i class="fa-solid fa-right-from-bracket"></i>
<span class="menu-text">Logout</span>
</a>

</div>

</div>

<!-- MAIN -->

<div class="main">

<!-- HEADER -->

<div class="header">

<div class="header-left">
<i class="fa-solid fa-bars cursor-pointer" onclick="toggleSidebar()"></i>
<span>Welcome, Mentor</span>
</div>

<div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-red-900 font-bold">DDS</div>

</div>

<!-- CONTENT -->

<div class="content">

<div class="page-container">

<!-- WELCOME -->

<div class="welcome-section">

<h1 class="welcome-title">
Welcome, Mentor name, to LRC PeerConnect <!-- < ?php echo $user->name ?? "Student"; ? > -->
</h1>

<p class="welcome-subtitle">
This is the LRC Enrichment Session Booking System. Mentor can:
</p>

<div class="feature-box">

<label class="feature-item">
<input type="checkbox" checked disabled>
<span>Book a tutoring session</span>
</label>

<label class="feature-item">
<input type="checkbox" checked disabled>
<span>View available peer mentors</span>
</label>

<label class="feature-item">
<input type="checkbox" checked disabled>
<span>Choose subject and schedule</span>
</label>

</div>

</div>

<!-- DASHBOARD GRID -->

<div class="dashboard-grid">

<!-- CALENDAR -->

<div class="card calendar-card">

<div class="flex justify-between mb-4">

<button onclick="changeMonth(-1)">
<i class="fa fa-chevron-left"></i>
</button>

<span id="monthDisplay"></span>

<button onclick="changeMonth(1)">
<i class="fa fa-chevron-right"></i>
</button>

</div>

<div class="grid grid-cols-7 text-xs text-gray-500 mb-2 text-center">
<div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
</div>

<div id="calendarGrid" class="grid grid-cols-7 text-center text-sm gap-1"></div>

</div>

<!-- ANALYTICS -->

<div class="card analytics-card">

<h3 class="font-bold mb-4">
Analytics
</h3>

<div class="chart-container">
<canvas id="hoursChart"></canvas>
</div>

</div>

<!-- TOTAL HOURS -->

<div class="card hours-card">

<h4 class="font-bold mb-2">
Total Session Hours
</h4>

<p id="totalHours" class="text-2xl font-bold text-red-800">
0
</p>

</div>

</div>

</div>

</div>

</div>

</div>

<script>

/* SIDEBAR */

function toggleSidebar(){

const sidebar=document.getElementById("sidebar")
sidebar.classList.toggle("collapsed")

setTimeout(()=>{
if(window.hoursChart){
window.hoursChart.resize()
}
},300)

}

/* CALENDAR */

let date=new Date()

function renderCalendar(){

const grid=document.getElementById("calendarGrid")
const monthDisplay=document.getElementById("monthDisplay")

grid.innerHTML=""

monthDisplay.innerText=date.toLocaleString('default',{month:'long',year:'numeric'})

const firstDay=new Date(date.getFullYear(),date.getMonth(),1).getDay()
const lastDay=new Date(date.getFullYear(),date.getMonth()+1,0).getDate()

for(let i=0;i<firstDay;i++) grid.innerHTML+="<div></div>"

for(let i=1;i<=lastDay;i++){

let className="p-2 rounded"

const today=new Date()

if(i===today.getDate() && date.getMonth()===today.getMonth()){
className+=" bg-red-800 text-white"
}

grid.innerHTML+=`<div class="${className}">${i}</div>`

}

}

function changeMonth(dir){
date.setMonth(date.getMonth()+dir)
renderCalendar()
}

/* ANALYTICS */

document.addEventListener("DOMContentLoaded",function(){

renderCalendar()

const hours=[1,2,1,3,2,0,1]
const labels=["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]

const total=hours.reduce((a,b)=>a+b,0)

document.getElementById("totalHours").innerText=total

window.hoursChart = new Chart(document.getElementById("hoursChart"),{

type:"bar",

data:{
labels:labels,
datasets:[{
label:"Session Hours",
data:hours,
backgroundColor:"#991b1b"
}]
},

options:{
responsive:true,
maintainAspectRatio:false,
plugins:{
legend:{
position:"top"
}
},
scales:{
y:{
beginAtZero:true,
ticks:{
stepSize:1
}
}
}
}

})

})

</script>

</body>
</html>
