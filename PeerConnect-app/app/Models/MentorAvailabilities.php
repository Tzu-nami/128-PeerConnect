<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MentorAvailabilities extends Model
{
    use HasUuids;
    
    public $timestamps = false;
    protected $table = 'mentor_availabilities';
    protected $fillable = [
        'mentor_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function mentor(){
        return $this->belongsTo(MentorProfiles::class, 'mentor_id');
    }
}

?>