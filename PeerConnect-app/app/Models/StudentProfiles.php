<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class studentProfiles extends Model
{
    use HasUuids;

    protected $table = 'student_profiles';

    protected $fillable = [
        'user_id',
        'student_num',
        'college_id',
        'degreeProgram_id',
        'yearLevel_id',
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function college(){
        return $this->belongsTo(Colleges::class, 'college_id');
    }

    public function degreeProgram(){
        return $this->belongsTo(DegreePrograms::class, 'degreeProgram_id');
    }

    public function yearLevel(){
        return $this->belongsTo(YearLevels::class, 'yearLevel_id');
    }
}