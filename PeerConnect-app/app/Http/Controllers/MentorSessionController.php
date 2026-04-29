<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MentorProfiles;
use App\Models\Bookings;
use App\Mail\MentorCancelledSession;
use App\Mail\SessionAccepted;
use App\Mail\SessionRejected;
use App\Mail\SessionCompleted;
use Illuminate\Support\Facades\Mail;

class MentorSessionController extends Controller
{
    public function update(Request $request)
    {
        $mentorProfile = MentorProfiles::where('user_id', auth()->id())->first();

        if ($mentorProfile) {
            $booking = Bookings::with('student.user')
                ->where('id', $request->booking_id)
                ->first();

            if ($booking) {
                $requestedStatus = strtolower($request->booking_status);

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

                    // Email Logic
                    if ($booking->student && $booking->student->user->email) {
                        // Email based on session status
                        $studentEmail = $booking->student->user->email;
                        switch ($booking->booking_status) {
                            case 'accepted':
                                Mail::to($studentEmail)->send(new SessionAccepted($booking));
                                break;
                            
                            case 'rejected':
                                Mail::to($studentEmail)->send(new SessionRejected($booking));
                                break;
                            
                            case 'completed':
                                Mail::to($studentEmail)->send(new SessionCompleted($booking));
                                break;

                            case 'cancelled':
                                Mail::to($studentEmail)->send(new MentorCancelledSession($booking));
                                break;
                        }
                    }
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
