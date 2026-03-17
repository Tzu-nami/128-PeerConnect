<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookings extends Model
{
    use HasUuids;

    const DAYS_OF_WEEK = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    const STATUS = [
        'pending',
        'accepted',
        'rejected',
        'completed',
        'no-show',
    ];

    protected $fillable = [
        'student_id',
        'mentor_id',
        'subject_id',
        'topic',
        'tutorialMode_id',
        'schedule_start',
        'schedule_end',
        'booking_status',
        'completed_at',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'schedule_start' => 'datetime',
        'schedule_end' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfiles::class, 'student_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(MentorProfiles::class, 'mentor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subject_id');
    }

    public function tutorialMode(): BelongsTo
    {
        return $this->belongsTo(TutorialMode::class, 'tutorialMode_id');
    }
}