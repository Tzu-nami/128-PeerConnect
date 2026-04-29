<x-mail::message>
@if($booking->mentor_id)
# Hello {{ $booking->mentor->user->firstName }},
@else
# Hello Peer Mentor (First Come First Serve),
@endif

You have received a new enrichment session request. **{{ $booking->student->user->firstName }}** needs your help with **{{ $booking->subject->code }}**. 

### Session Details:
* **Student:** {{ strtoupper($booking->student->user->lastName) }}, {{ $booking->student->user->firstName }} - {{ $booking->student->yearLevel->name }} {{ $booking->student->degreeProgram->name }}
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }} to {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}

Please log in to the system to review this request.

<x-mail::button :url="route('mentor.sessions')">
    View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
