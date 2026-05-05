@php
use App\Models\Bookings;

$notifications = collect();

/*
|--------------------------------------------------------------------------
| ADMIN NOTIFICATIONS
|--------------------------------------------------------------------------
| Admin receives ALL session-related notifications across the whole system:
| - all incoming booking requests
| - accepted / rejected / cancelled / completed / no-show
| - open sessions waiting for claiming
|--------------------------------------------------------------------------
*/

$bookings = Bookings::with([
    'student.user',
    'mentor.user',
    'subject',
])
->whereIn('booking_status', [
    'pending',
    'accepted',
    'rejected',
    'cancelled',
    'completed',
    'no_show',
])
->orderBy('updated_at', 'desc')
->take(100)
->get();

foreach ($bookings as $b) {
    $student = optional(optional($b->student)->user);
    $mentor  = optional(optional($b->mentor)->user);

    $studentName = $student->firstName
        ? $student->firstName . ' ' . $student->lastName
        : 'Unknown Student';

    $mentorName = $mentor->firstName
        ? $mentor->firstName . ' ' . $mentor->lastName
        : ($b->mentor_id ? 'Assigned Mentor' : 'Open Session');

    if (mb_strlen($studentName) > 30) {
        $studentName = mb_substr($studentName, 0, 30) . '...';
    }

    if (mb_strlen($mentorName) > 30) {
        $mentorName = mb_substr($mentorName, 0, 30) . '...';
    }

    $subject   = optional($b->subject)->code ?? 'N/A';
    $date      = $b->date
        ? \Carbon\Carbon::parse($b->date)->format('M j, Y')
        : '—';

    $updatedAt = \Carbon\Carbon::parse($b->updated_at);

    switch ($b->booking_status) {
        case 'pending':
            if (is_null($b->mentor_id)) {
                $icon = 'fa-hand-pointer';
                $iconBg = 'bg-purple-100';
                $iconColor = 'text-purple-700';

                $message = "<strong>{$studentName}</strong> created an open session request for <strong>{$subject}</strong>";
                $badge = 'bg-purple-100 text-purple-700';
                $badgeText = 'Open Session';
            } else {
                $icon = 'fa-hourglass-half';
                $iconBg = 'bg-yellow-100';
                $iconColor = 'text-yellow-600';

                $message = "<strong>{$studentName}</strong> requested a session with <strong>{$mentorName}</strong> for <strong>{$subject}</strong>";
                $badge = 'bg-yellow-100 text-yellow-700';
                $badgeText = 'Pending Request';
            }
            break;

        case 'accepted':
            $icon = 'fa-circle-check';
            $iconBg = 'bg-green-100';
            $iconColor = 'text-green-600';

            $message = "<strong>{$mentorName}</strong> accepted <strong>{$studentName}</strong>'s <strong>{$subject}</strong> session";
            $badge = 'bg-green-100 text-green-700';
            $badgeText = 'Accepted';
            break;

        case 'rejected':
            $icon = 'fa-circle-xmark';
            $iconBg = 'bg-red-100';
            $iconColor = 'text-red-500';

            $message = "<strong>{$mentorName}</strong> rejected <strong>{$studentName}</strong>'s <strong>{$subject}</strong> request";
            $badge = 'bg-red-100 text-red-600';
            $badgeText = 'Rejected';
            break;

        case 'cancelled':
            $icon = 'fa-ban';
            $iconBg = 'bg-red-100';
            $iconColor = 'text-red-500';

            $message = "<strong>{$studentName}</strong> cancelled their <strong>{$subject}</strong> session";
            $badge = 'bg-red-100 text-red-600';
            $badgeText = 'Cancelled';
            break;

        case 'completed':
            $icon = 'fa-flag-checkered';
            $iconBg = 'bg-slate-100';
            $iconColor = 'text-slate-500';

            $message = "<strong>{$subject}</strong> session between <strong>{$studentName}</strong> and <strong>{$mentorName}</strong> was completed";
            $badge = 'bg-slate-100 text-slate-600';
            $badgeText = 'Completed';
            break;

        case 'no_show':
            $icon = 'fa-user-slash';
            $iconBg = 'bg-orange-100';
            $iconColor = 'text-orange-500';

            $message = "<strong>{$studentName}</strong> was marked no-show for <strong>{$subject}</strong>";
            $badge = 'bg-orange-100 text-orange-600';
            $badgeText = 'No Show';
            break;

        default:
            continue 2;
    }

    $notifications->push([
        'id'        => 'admin-' . $b->id,
        'icon'      => $icon,
        'iconBg'    => $iconBg,
        'iconColor' => $iconColor,
        'message'   => $message,
        'badge'     => $badge,
        'badgeText' => $badgeText,
        'date'      => $date,
        'timeAgo'   => $updatedAt->diffForHumans(),
        'timestamp' => $b->updated_at->timestamp,
    ]);
}

$notifications = $notifications
    ->sortByDesc('timestamp')
    ->take(50)
    ->values();

$latestTimestamp = $notifications->max('timestamp') ?? 0;
@endphp


<div class="relative" x-data="{
    open: false,
    showAll: false,
    hasNew: false,
    lastSeen: parseInt(localStorage.getItem('mentor_notif_last_seen') || '0'),
    latestTs: {{ $latestTimestamp }},

    init() {
        this.hasNew = this.latestTs > this.lastSeen;
    },

    toggle() {
        this.open = !this.open;
        if (this.open && this.hasNew) {
            this.hasNew = false;
            this.lastSeen = this.latestTs;
            localStorage.setItem('mentor_notif_last_seen', this.latestTs);
        }
        this.showAll = false;
    }
}" @click.outside="open = false; showAll = false">

    {{-- Bell Button --}}
    <button @click="toggle()"
        class="relative p-2 rounded-full text-white/70 hover:bg-red-800 hover:text-white transition-all duration-200 focus:outline-none">
        <i class="fa-solid fa-bell text-xl"></i>
        <span x-show="hasNew" x-cloak class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-white"></span>
        </span>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak x-transition
        class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
        style="top: calc(100% + 4px);">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-gray-100 bg-slate-50 flex items-center justify-between">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Notifications</p>
            @if($notifications->count() > 3)
                <button @click="showAll = !showAll"
                    class="text-[10px] font-bold text-red-800 hover:text-red-600 transition">
                    <span x-text="showAll ? 'Show Less' : 'Show All ({{ $notifications->count() }})'"></span>
                </button>
            @endif
        </div>

        {{-- Notification List --}}
        <div :class="showAll ? 'max-h-[420px] overflow-y-auto' : ''">

            @forelse($notifications->take(3) as $notif)
                <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition cursor-default
                    {{ !$loop->last ? '' : 'border-b-0' }}"
                    x-show="true">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full {{ $notif['iconBg'] }} flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fa-solid {{ $notif['icon'] }} {{ $notif['iconColor'] }} text-xs"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-700 leading-snug">{!! $notif['message'] !!}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $notif['badge'] }}">
                                    {{ $notif['badgeText'] }}
                                </span>
                                <span class="text-[10px] text-gray-400">{{ $notif['timeAgo'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <i class="fa-solid fa-bell-slash text-gray-300 text-2xl mb-2"></i>
                    <p class="text-xs text-gray-400 font-medium">No notifications yet.</p>
                </div>
            @endforelse

{{-- Remaining notifications (shown when showAll = true) --}}
        <template x-if="showAll">
            <div>
                @foreach($notifications->skip(3) as $notif)
                    <div class="px-4 py-3 border-b border-gray-50 hover:bg-gray-50 transition cursor-default">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full {{ $notif['iconBg'] }} flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i class="fa-solid {{ $notif['icon'] }} {{ $notif['iconColor'] }} text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-gray-700 leading-snug">{!! $notif['message'] !!}</p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $notif['badge'] }}">
                                        {{ $notif['badgeText'] }}
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ $notif['timeAgo'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </template>

        </div>{{-- end Notification List --}}

        {{-- Footer --}}
        @if($notifications->count() > 3)
            <div class="px-4 py-2.5 border-t border-gray-100 bg-slate-50 text-center">
                <button @click="showAll = !showAll"
                        class="text-xs font-bold text-red-900 hover:text-red-700 transition">
                    <span x-text="showAll ? '↑ Show Less' : '↓ View All ' + {{ $notifications->count() }} + ' Notifications'"></span>
                </button>
            </div>
        @endif

    </div>{{-- end Dropdown --}}
</div>{{-- end relative wrapper --}}
