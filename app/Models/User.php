<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Hash;

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
        'must_change_password',
        'otp',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'otp_expires_at' => 'datetime',
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

    public function generateOTP()
    {
        $this->otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otp_expires_at = now()->addMinutes(15);
        $this->save();
        
        return $this->otp;
    }

   // app/Models/User.php

public function verifyOTP($otp)
{
    // Check if OTP exists and not expired
    if (!$this->otp || !$this->otp_expires_at) {
        return false;
    }

    // Check if OTP is expired
    if (now()->gt($this->otp_expires_at)) {
        return false;
    }

    // Check if OTP matches (trim and compare)
    $provided = trim($otp);
    $stored = trim($this->otp);
    
    return $provided === $stored;
}

    public function clearOTP()
    {
        $this->otp = null;
        $this->otp_expires_at = null;
        $this->save();
    }

      public function user()
    {
        return $this->belongsTo(User::class);
    }

//     public function setPasswordAttribute($value)
// {
//     if ($value) {
//         $this->attributes['password'] = Hash::make($value);
//         // If password is set, user no longer needs to change it
//         $this->attributes['must_change_password'] = false;
//     }
// }


public function setPasswordAttribute($value)
{
    // Only hash real values. null / '' leaves the existing password alone.
    if ($value === null || $value === '') {
        return;
    }

    $this->attributes['password'] = Hash::make($value);
}

public function clearPassword(): void
{
    $this->attributes['password'] = null;
    $this->attributes['must_change_password'] = true;
    $this->save();
}
}