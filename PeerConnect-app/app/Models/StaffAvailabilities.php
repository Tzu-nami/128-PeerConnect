<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffAvailabilities extends Model {
    use HasUuids;

    protected $table = 'staff_availabilities';
    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time'
    ];

    public function staff() {
        return $this->belongsTo(StaffProfiles::class);
    }
}
