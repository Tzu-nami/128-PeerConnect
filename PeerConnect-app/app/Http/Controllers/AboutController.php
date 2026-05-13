<?php

namespace App\Http\Controllers;

use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Models\Bookings;
use App\Models\StaffProfiles;

class AboutController extends Controller {
    public function index() {
        $stats = [
            'mentors' => MentorProfiles::count(),
            'subjects' => Subjects::count(),
            'bookings' => Bookings::count(),
        ];

        $staff = StaffProfiles::where('role', 'LRC Head')->first();

        return view('public.about', ['stats' => $stats, 'staff' => $staff]);
    }
}
