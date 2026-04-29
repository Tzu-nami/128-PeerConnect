<x-mail::message>
# Hello {{ $booking->student->user->firstName }},

Your enrichment session request for **{{ $booking->subject->code }}** on {{ \Carbon\Carbon::parse($booking->date)->format('M j') }} could not be accepted at this time due to schedule conflicts. 

Please select another mentor to help you with this subject.

<x-mail::button :url="route('student.bookings')">
Book a New Session
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
