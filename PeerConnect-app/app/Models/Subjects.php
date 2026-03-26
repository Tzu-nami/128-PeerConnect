<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subjects extends Model
{
    use HasFactory;
    public $timestamps = false;
    
    protected $fillable = [
        'code',
        'name',
    ];
}