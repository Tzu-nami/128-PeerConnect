<x-mail::message>
# Hello {{ $booking->mentor->user->firstName ?? 'Mentor' }},

Unfortunately, **{{ $booking->student->user->firstName }}** has cancelled their upcoming enrichment session for **{{ $booking->subject->code }}**.

### Cancelled Session Details:
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}

This timeslot is now open for other students to book.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
