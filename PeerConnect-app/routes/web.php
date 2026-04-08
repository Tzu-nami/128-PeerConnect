<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Public Route
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard Redirect
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        return match(auth()->user()->user_roles) {
            'admin' => redirect()->route('admin.dashboard'),
            'mentor' => redirect()->route('mentor.dashboard'),
            'student' => redirect()->route('student.dashboard'),
        };
    })->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->prefix('admin')->group(function () {

        Volt::route('/dashboard', 'pages.admin.dashboard')
            ->name('admin.dashboard');

        Volt::route('/mentors', 'pages.admin.mentors')
            ->name('admin.mentors');

        Volt::route('/courses', 'pages.admin.courses')
            ->name('admin.courses');

        Volt::route('/sessions', 'pages.admin.sessions')
            ->name('admin.sessions');

        Volt::route('/feedbacks', 'pages.admin.feedbacks')
            ->name('admin.feedbacks');

    });


    /*
    |--------------------------------------------------------------------------
    | MENTOR ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:mentor')->prefix('mentor')->group(function () {

        Volt::route('/dashboard', 'pages.mentor.dashboard')
            ->name('mentor.dashboard');

        Route::post('/dashboard/update', function () {
            $mentorProfile = \App\Models\MentorProfiles::where('user_id', auth()->id())->first();

        if ($mentorProfile) {
            $booking = \App\Models\Bookings::where('id', request('id'))
            ->where('mentor_id', $mentorProfile->id)
            ->first();

            if ($booking) {
            $booking->booking_status = request('status');
            if (request('status') === 'completed') {
                $booking->completed_at = now();
            }
            $booking->save();
            }
            }

        return response()->json(['success' => true]);
        })->name('mentor.dashboard.update');

        Volt::route('/bookings', 'pages.mentor.bookings')
            ->name('mentor.bookings');

        Volt::route('/sessions', 'pages.mentor.sessions')
            ->name('mentor.sessions');

        Volt::route('/feedbacks', 'pages.mentor.feedbacks')
            ->name('mentor.feedbacks');

        Route::post('/sessions/update', function () {

        $mentorProfile = \App\Models\MentorProfiles::where('user_id', auth()->id())->first();

        if ($mentorProfile) {
            $booking = \App\Models\Bookings::where('id', request('booking_id'))
                ->first();

            if ($booking) {
                $requestedStatus = strtolower(request('booking_status'));

                if(is_null($booking->mentor_id) && $booking->booking_status === 'pending' && $requestedStatus === 'accepted') {
                    $booking->mentor_id = $mentorProfile->id;
                    $booking->booking_status = 'accepted';
                    $booking->save();
                }
                elseif($booking->mentor_id === $mentorProfile->id) { 
                $booking->booking_status = $requestedStatus;

                if ($booking->booking_status === 'completed') {
                    $booking->completed_at = now();
                }

                $booking->save();
                }
            }
        }

        return redirect()->route('mentor.sessions');
        })->name('mentor.sessions.update');

    });


    /*
    |--------------------------------------------------------------------------
    | STUDENT ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:student')->prefix('student')->group(function () {

        Volt::route('/dashboard', 'pages.student.dashboard')
            ->name('student.dashboard');

        Volt::route('/bookings', 'pages.student.bookings')
            ->name('student.bookings');

        Volt::route('/history', 'pages.student.history')
            ->name('student.history');

        Volt::route('/mentors', 'pages.student.mentors')
            ->name('student.mentors');

        Volt::route('/about', 'pages.student.about')
            ->name('student.about');

    });

});


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');


require __DIR__.'/auth.php';
