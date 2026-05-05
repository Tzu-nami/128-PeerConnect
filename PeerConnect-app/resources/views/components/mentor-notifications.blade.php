@php
    use App\Models\Bookings;
    use App\Models\MentorProfiles;
    use App\Models\StudentProfiles;

    $mentorProfile  = MentorProfiles::where('user_id', auth()->id())->first();
    $studentProfile = StudentProfiles::where('user_id', auth()->id())->first();
    $notifications  = collect();

    // ── 1. NOTIFICATIONS WHERE THIS USER IS THE MENTOR (receiving requests) ──
    if ($mentorProfile) {
        $bookings = Bookings::with(['student.user', 'subject'])
            ->where(function($q) use ($mentorProfile) {
                $q->where('mentor_id', $mentorProfile->id)
                  ->orWhere(function($q2) use ($mentorProfile) {
                      $subjectIds = \App\Models\MentorSubjects::where('mentor_id', $mentorProfile->id)
                          ->pluck('subject_id');
                      $q2->whereNull('mentor_id')
                         ->whereIn('subject_id', $subjectIds)
                         ->where('booking_status', 'pending');
                  });
            })
            ->whereIn('booking_status', ['pending', 'cancelled', 'accepted', 'rejected', 'no_show', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get();

        foreach ($bookings as $b) {
            $student = optional(optional($b->student)->user);
            $name    = $student->firstName
                ? $student->firstName . ' ' . $student->lastName
                : 'Unknown Student';
            if (mb_strlen($name) > 30) $name = mb_substr($name, 0, 30) . '...';

            $subject   = optional($b->subject)->code ?? 'N/A';
            $date      = $b->date ? \Carbon\Carbon::parse($b->date)->format('M j, Y') : '—';
            $updatedAt = \Carbon\Carbon::parse($b->updated_at);

            switch ($b->booking_status) {
                case 'pending':
                    $icon = 'fa-hourglass-half'; $iconBg = 'bg-yellow-100'; $iconColor = 'text-yellow-600';
                    $message = "<strong>{$name}</strong> requested a session for <strong>{$subject}</strong>";
                    $badge = 'bg-yellow-100 text-yellow-700'; $badgeText = 'New Request';
                    break;
                case 'cancelled':
                    $icon = 'fa-ban'; $iconBg = 'bg-red-100'; $iconColor = 'text-red-500';
                    $message = "<strong>{$name}</strong> cancelled their <strong>{$subject}</strong> session";
                    $badge = 'bg-red-100 text-red-600'; $badgeText = 'Cancelled';
                    break;
                case 'accepted':
                    $icon = 'fa-circle-check'; $iconBg = 'bg-green-100'; $iconColor = 'text-green-600';
                    $message = "You accepted <strong>{$name}</strong>'s <strong>{$subject}</strong> session";
                    $badge = 'bg-green-100 text-green-700'; $badgeText = 'Accepted';
                    break;
                case 'rejected':
                    $icon = 'fa-circle-xmark'; $iconBg = 'bg-red-100'; $iconColor = 'text-red-500';
                    $message = "You rejected <strong>{$name}</strong>'s <strong>{$subject}</strong> session";
                    $badge = 'bg-red-100 text-red-600'; $badgeText = 'Rejected';
                    break;
                case 'no_show':
                    $icon = 'fa-user-slash'; $iconBg = 'bg-orange-100'; $iconColor = 'text-orange-500';
                    $message = "<strong>{$name}</strong> did not show for <strong>{$subject}</strong>";
                    $badge = 'bg-orange-100 text-orange-600'; $badgeText = 'No Show';
                    break;
                case 'completed':
                    $icon = 'fa-flag-checkered'; $iconBg = 'bg-slate-100'; $iconColor = 'text-slate-500';
                    $message = "Session with <strong>{$name}</strong> for <strong>{$subject}</strong> was completed";
                    $badge = 'bg-slate-100 text-slate-600'; $badgeText = 'Completed';
                    break;
                default:
                    continue 2;
            }

            $notifications->push([
                'id'        => 'mentor-' . $b->id,
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
    }

    // ── 2. NOTIFICATIONS WHERE THIS USER IS THE STUDENT (their own bookings) ──
    if ($studentProfile) {
        $myBookings = Bookings::with(['mentor.user', 'subject'])
            ->where('student_id', $studentProfile->id)
            ->whereIn('booking_status', ['pending', 'accepted', 'rejected', 'completed', 'no_show', 'cancelled'])
            ->orderBy('updated_at', 'desc')
            ->take(50)
            ->get();

        foreach ($myBookings as $b) {
            $mentor     = optional(optional($b->mentor)->user);
            $mentorName = $mentor->firstName
                ? $mentor->firstName . ' ' . $mentor->lastName
                : 'Your mentor';
            if (mb_strlen($mentorName) > 30) $mentorName = mb_substr($mentorName, 0, 30) . '...';

            $subject   = optional($b->subject)->code ?? 'N/A';
            $date      = $b->date ? \Carbon\Carbon::parse($b->date)->format('M j, Y') : '—';
            $updatedAt = \Carbon\Carbon::parse($b->updated_at);

            switch ($b->booking_status) {
                case 'pending':
                    $icon = 'fa-paper-plane'; $iconBg = 'bg-blue-100'; $iconColor = 'text-blue-500';
                    $message = "You requested a <strong>{$subject}</strong> session on <strong>{$date}</strong>";
                    $badge = 'bg-blue-100 text-blue-700'; $badgeText = 'Booking Sent';
                    break;
                case 'accepted':
                    $icon = 'fa-circle-check'; $iconBg = 'bg-green-100'; $iconColor = 'text-green-600';
                    $message = "<strong>{$mentorName}</strong> accepted your <strong>{$subject}</strong> session";
                    $badge = 'bg-green-100 text-green-700'; $badgeText = 'Session Confirmed';
                    break;
                case 'rejected':
                    $icon = 'fa-circle-xmark'; $iconBg = 'bg-red-100'; $iconColor = 'text-red-500';
                    $message = "<strong>{$mentorName}</strong> declined your <strong>{$subject}</strong> request";
                    $badge = 'bg-red-100 text-red-600'; $badgeText = 'Declined';
                    break;
                case 'completed':
                    $icon = 'fa-flag-checkered'; $iconBg = 'bg-slate-100'; $iconColor = 'text-slate-500';
                    $message = "Your <strong>{$subject}</strong> session with <strong>{$mentorName}</strong> was completed";
                    $badge = 'bg-slate-100 text-slate-600'; $badgeText = 'Completed';
                    break;
                case 'no_show':
                    $icon = 'fa-user-slash'; $iconBg = 'bg-orange-100'; $iconColor = 'text-orange-500';
                    $message = "You were marked no-show for your <strong>{$subject}</strong> session";
                    $badge = 'bg-orange-100 text-orange-600'; $badgeText = 'No Show';
                    break;
                case 'cancelled':
                    $icon = 'fa-ban'; $iconBg = 'bg-red-100'; $iconColor = 'text-red-500';
                    $message = "Your <strong>{$subject}</strong> session on <strong>{$date}</strong> was cancelled";
                    $badge = 'bg-red-100 text-red-600'; $badgeText = 'Cancelled';
                    break;
                default:
                    continue 2;
            }

            $notifications->push([
                'id'        => 'student-' . $b->id,
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
    }

    // ── 3. MERGE & DEDUPLICATE by booking ID, sort newest first ──
    $notifications = $notifications
        ->groupBy(fn($n) => preg_replace('/^(mentor|student)-/', '', $n['id']))
        ->map(fn($group) => $group->sortByDesc('timestamp')->first())
        ->values()
        ->sortByDesc('timestamp')
        ->take(50)
        ->values();

    $latestTimestamp = $notifications->max('timestamp') ?? 0;
@endphp

<div class="relative"
     x-data="{
         showAll: false,
         hasNew: false,
         lastSeen: parseInt(localStorage.getItem('mentor_notif_last_seen') || '0'),
         latestTs: {{ $latestTimestamp }},
         get open() { return $store.dropdowns.active === 'notifications' },

         init() {
             this.hasNew = this.latestTs > this.lastSeen;
         },

         openBell() {
             const willOpen = $store.dropdowns.active !== 'notifications';
             $store.dropdowns.toggle('notifications');

             if (willOpen && this.hasNew) {
                 this.hasNew = false;
                 this.lastSeen = this.latestTs;
                 localStorage.setItem('mentor_notif_last_seen', this.latestTs);
             }

             if (!willOpen) this.showAll = false;
         }
     }"
     @click.outside="$store.dropdowns.active === 'notifications' && ($store.dropdowns.close(), showAll = false)">

    {{-- Bell Button --}}
    <button @click.stop="openBell()"
            class="relative p-2 rounded-full text-white/70 hover:bg-red-800 hover:text-white transition-all duration-200 focus:outline-none">
        <i class="fa-solid fa-bell text-xl"></i>
        <span x-show="hasNew" x-cloak class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-white"></span>
        </span>
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-cloak
         x-transition
         @click.stop
         class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">

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
                    {{ $loop->last ? 'border-b-0' : '' }}"
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
