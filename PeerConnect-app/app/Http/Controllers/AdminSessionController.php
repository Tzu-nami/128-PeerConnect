<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bookings;
use App\Mail\AdminCancelledStudent;
use App\Mail\AdminCancelledMentor;
use App\Mail\SessionAccepted;
use App\Mail\SessionRejected;
use App\Mail\SessionCompleted;
use Illuminate\Support\Facades\Mail;

class AdminSessionController extends Controller
{
    public function update(Request $request)
    {
        $booking = Bookings::with(['student.user', 'mentor.user'])->findOrFail($request->booking_id);
        
        // Update status
        $booking->booking_status = strtolower($request->booking_status);

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
                    // Email the Student
                    Mail::to($booking->student->user->email)
                        ->send(new AdminCancelledStudent($booking));
                    

                    // Email the Mentor
                    if ($booking->mentor_id && $booking->mentor->user->email) {
                        Mail::to($booking->mentor->user->email)
                            ->send(new AdminCancelledMentor($booking));
                    }
                    break;
            }
        }
        return response()->json(['success' => true]);
    }
}
