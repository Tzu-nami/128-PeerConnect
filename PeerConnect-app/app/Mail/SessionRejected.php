<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Bookings;

class SessionRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $bookingUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Bookings $booking)
    {
        $this->booking = $booking;
        // Check what role the mentee is
        $user = $booking->student->user;
        if ($user && $user->user_roles === 'mentor') {
            $this->bookingUrl = route('mentor.bookings');
        } else {
            $this->bookingUrl = route('student.bookings');
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Session Rejected - LRC PeerConnect',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.session-rejected',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
