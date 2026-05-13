<x-mail::message>
# Hello {{ $booking->student->user->firstName }},

Your enrichment session for **{{ $booking->subject->code }}** with **{{ $booking->mentor->user->firstName }}** has been marked as completed. We hope you found the session helpful and that it cleared up any questions you had!

To help us improve our tutoring services, please answer the feedback forms regarding your session. Rest assured, your feedback is completely anonymous and non-mandatory.

<x-mail::button :url="$feedbackUrl">
Leave Feedback
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
