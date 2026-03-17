<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MentorSubjects extends Model
{
    use HasUuids;
    
    protected $table = 'mentor_subjects';
    protected $fillable = [
        'mentor_id',
        'subject_id'
    ];

    public function mentor(){
        return $this->belongsTo(MentorProfiles::class, 'mentor_id');
    }

    public function subject(){
        return $this->belongsTo(Subjects::class, 'subject_id');
    }
}

?>