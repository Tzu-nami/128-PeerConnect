<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public View Route
Route::get('/', function() {
    return view('welcome');
});

// Authentication Routes
Route::middleware(['auth', 'verified']) -> group(function () {

    // Redirect to dashboards
    Route::get('dashboard', function () {
        return match(auth()->user()->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'mentor' => redirect()->route('mentor.dashboard'),
            'student' => redirect()->route('student.dashboard'),
        };
    })->name('dashboard');

    // Admin routes
    Route::middleware('role:admin')->group(function () {
        Volt::route('/admin/dashboard', 'pages.admin.dashboard')->name('admin.dashboard');
        Volt::route('/admin/users', 'pages.admin.mentor-management')->name('admin.users');
    });

    // Mentor routes
    Route::middleware('role:mentor')->group(function () {
        Volt::route('/mentor/dashboard', 'pages.mentor.dashboard')->name('mentor.dashboard');
    });

    Route::middleware('role:student')->group(function () {
        Volt::route('/student/dashboard', 'pages.student.dashboard')->name('student.dashboard');
    });

});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
