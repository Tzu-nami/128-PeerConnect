<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorProfiles extends Model
{
    use HasUuids;

    protected $table = 'mentor_profiles';

    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(
            Subjects::class,
            'mentor_subjects',
            'mentor_id',
            'subject_id',
        );
    }

    public function availabilities()
    {
        return $this->hasMany(MentorAvailabilities::class, 'mentor_id');
    }
}