<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'feedback';

    protected $fillable = [
        'booking_id',
        'mentor_id',
        'topic',
        'rating',
        'feedback',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(MentorProfiles::class, 'mentor_id');
    }
}