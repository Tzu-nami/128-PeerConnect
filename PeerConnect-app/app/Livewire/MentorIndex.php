<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\MentorProfiles;
use App\Models\Subjects;
use App\Services\Avatar;

class MentorIndex extends Component
{
    public function render()
    {
        $mentors = MentorProfiles::with([
            'user.studentProfile.college',
            'user.studentProfile.degreeProgram',
            'user.studentProfile.yearLevel',
            'subjects',
            'availabilities',
        ])->get()->map(function ($mp) {
            $dayOrder   = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
            $activeDays = $mp->availabilities->pluck('day_of_week')->unique()
                ->sortBy(fn($day) => $dayOrder[strtolower($day)] ?? 99)
                ->map(fn($day) => ucfirst(substr($day, 0, 3)))
                ->values()->toArray();

            $schedule = $mp->availabilities
                ->groupBy(fn($item) => strtolower($item->day_of_week))
                ->map(fn($slots) => [
                    'slots' => $slots->sortBy(fn($t) => \Carbon\Carbon::parse($t->start_time)->timestamp)
                        ->map(fn($t) => [
                            'start' => \Carbon\Carbon::parse($t->start_time)->format('g:i A'),
                            'end'   => \Carbon\Carbon::parse($t->end_time)->format('g:i A'),
                        ])->values()->toArray(),
                ])->toArray();

            if (empty($schedule)) $schedule = new \stdClass();

            return [
                'id'            => $mp->id,
                'user_id'       => $mp->user_id,
                'lastName'      => strtoupper($mp->user->lastName),
                'firstName'     => $mp->user->firstName,
                'middleInitial' => $mp->user->middleInitial ? $mp->user->middleInitial . '.' : '',
                'email'         => $mp->user->email,
                'avatar'        => $mp->user->avatar ?? app(Avatar::class)->placeholder($mp->user->firstName . ' ' . $mp->user->lastName),
                'subjects'      => $mp->subjects->unique('id')->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->sortBy('code')->values()->toArray(),
                'days'          => $activeDays,
                'schedule'      => $schedule,
                'yearLevel'     => $mp->user->studentProfile->yearLevel->name,
                'degreeProgram' => $mp->user->studentProfile->degreeProgram->name,
                'college'       => $mp->user->studentProfile->college->name,
                'bookingUrl' => auth()->check() && auth()->user()->isMentor()
                    ? route('mentor.bookings', ['mentor' => $mp->id])
                    : route('student.bookings', ['mentor' => $mp->id]),
            ];
        })->sortBy('lastName')->values();

        $subjects = Subjects::orderBy('code')->get();

        return view('livewire.mentor-index-landing', compact('mentors', 'subjects'));
    }
}
