<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // --- JWTSubject ---

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->staff?->role?->slug,
            'staff_id' => $this->staff?->id,
        ];
    }

    // --- Relations ---

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    // --- Helpers ---

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function hasRole(string ...$slugs): bool
    {
        if (!$this->staff || !$this->staff->role) {
            return false;
        }

        return in_array($this->staff->role->slug, $slugs, true);
    }


      public function user()
    {
        return $this->belongsTo(User::class);
    }
}