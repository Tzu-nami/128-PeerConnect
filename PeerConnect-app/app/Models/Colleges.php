<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colleges extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    public function degreePrograms(){
        return $this->hasMany(DegreePrograms::class);
    }
}

?>