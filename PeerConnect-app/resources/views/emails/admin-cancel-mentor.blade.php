<x-mail::message>
# Hello {{ $booking->mentor->user->firstName ?? 'Mentor' }},

Unfortunately, the LRC Admin had to cancel your upcoming session for **{{ $booking->subject->code }}** with your mentee, **{{ $booking->student->user->firstName ?? 'Student' }}**. 

### Cancelled Session Details:
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}

The session has been removed from your active schedule and this timeslot is now open for other students to book.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
