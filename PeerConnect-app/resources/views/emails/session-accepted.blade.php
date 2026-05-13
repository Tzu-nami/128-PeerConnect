<x-mail::message>
# Hello {{ $booking->student->user->firstName }},

Your enrichment session request for **{{ $booking->subject->code }}** has been **ACCEPTED** by your peer mentor, **{{ $booking->mentor->user->firstName }}**!

### Confirmed Session Details:
* **Date:** {{ \Carbon\Carbon::parse($booking->date)->format('F j, Y') }}
* **Time:** {{ \Carbon\Carbon::parse($booking->schedule_start)->format('g:i A') }} to {{ \Carbon\Carbon::parse($booking->schedule_end)->format('g:i A') }}
* **Topic:** {{ $booking->topic ?? 'None Specified' }}
* **Mode:** {{ $booking->tutorialMode->mode ?? 'TBA' }}

Please make sure to arrive on time and bring any materials or questions you have prepared. You can view your full session details in your dashboard.

<x-mail::button :url="$dashboardUrl">
View My Session
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
