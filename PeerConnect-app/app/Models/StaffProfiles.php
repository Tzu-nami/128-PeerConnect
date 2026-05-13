<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffProfiles extends Model {
    use HasUuids;

    protected $table = 'staff_profiles';
    protected $fillable = [
        'firstName',
        'lastName',
        'middleInitial',
        'role',
        'email',
        'avatar'
    ];

    public function availabilities() {
        return $this->hasMany(StaffAvailabilities::class, 'staff_id');
    }

    public function getFullNameAttribute() {
        $mi = $this->middleInitial ? "{$this->middleInitial}." : '';
        return "{$this->firstName}{$mi}{$this->lastName}";
    }
}
