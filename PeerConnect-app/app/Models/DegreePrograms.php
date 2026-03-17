<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DegreePrograms extends Model
{
    protected $fillable = [
        'college_id',
        'code',
        'name',
    ];

    public function college(){
        return $this->belongsTo(Colleges::class);
    }
}

?>