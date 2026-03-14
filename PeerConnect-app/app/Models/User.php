<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUuids, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $table = 'user_profiles';

    protected $fillable = [
        'google_id',
        'firstName',
        'lastName',
        'middleInitial',
        'avatar',
        'last_login_at',
        'email',
        'user_roles',
        'password',
        'remember_token',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getNameAttribute(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    //Role Checking
    public function isStudent(): bool {
        return strtolower(trim($this->user_roles)) === 'student';
    }

    public function isMentor(): bool {
        return strtolower(trim($this->user_roles)) === 'mentor';
    }

    public function isAdmin(): bool {
        return strtolower(trim($this->user_roles)) === 'admin';
    }

    // Relationships of tables (naol relationships)
    public function studentBookings() {
        return $this->hasMany(Booking::class, 'student_id');
    }

    public function mentorBookings() {
        return $this->hasMany(Booking::class, 'mentor_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
}
