<x-mail::message>
# Hello {{ $booking->student->user->firstName }},

Unfortunately, **{{ $booking->mentor->user->firstName }}**, had to cancel your upcoming enrichment session for **{{ $booking->subject->code }}**.

### Cancelled Session Details:
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}

Please log back into the system to book a new session.

<x-mail::button :url="route('student.bookings')">
Book a New Session
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
