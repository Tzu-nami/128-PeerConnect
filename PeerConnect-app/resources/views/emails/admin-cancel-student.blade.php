<x-mail::message>
# Hello {{ $booking->student->user->firstName }},

Unfortunately, the LRC Admin had to cancel your upcoming enrichment session for **{{ $booking->subject->code }}** with your mentor, **{{ $booking->mentor->user->firstName ?? 'Mentor, TBD' }}**.

### Cancelled Session Details:
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}

If you believe this was an error, please contact the LRC office. Otherwise, you may book a new session at your convenience.

<x-mail::button :url="route('student.bookings')">
Book a New Session
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
