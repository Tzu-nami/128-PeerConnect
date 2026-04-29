<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MentorProfiles;
use App\Models\Bookings;

class MentorDashboardController extends Controller
{
    public function update(Request $request)
    {
        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

        if ($mentorProfile) {
            $booking = Bookings::where('id', $request->id)
                ->where('mentor_id', $mentorProfile->id)
                ->first();

            if ($booking) {
                $booking->booking_status = $request->status;
                
                if ($request->status === 'completed') {
                    $booking->completed_at = now();
                }
                
                $booking->save();
            }
        }

        return response()->json(['success' => true]);
    }
}
