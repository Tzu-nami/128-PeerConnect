<?php

namespace App\Http\Controllers;

use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Models\Bookings;

class AboutController extends Controller {
    public function index() {
        $stats = [
            'mentors' => MentorProfiles::count(),
            'subjects' => Subjects::count(),
            'bookings' => Bookings::count(),
        ];
        return view('public.about', ['stats' => $stats]);
    }
}
