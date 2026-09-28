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

    // app/Models/User.php

    // 'roles' / 'role' are appended so every response that returns a User
    // (login, /auth/me, refresh) carries role info at the top level —
    // this is the exact shape DashboardRouter.tsx already expects
    // (`user?.role` as a string and/or `user?.roles` as an array), so the
    // frontend needed no changes to consume multiple roles once these
    // exist.
    protected $appends = ['has_password', 'roles', 'role'];

    public function getHasPasswordAttribute(): bool
    {
        return !empty($this->attributes['password'] ?? null);
    }

    /**
     * All role slugs assigned to this user's staff record, via the
     * staff_role pivot (Staff::roles()). Empty array if the user has no
     * staff record or no roles assigned yet.
     */
    public function getRolesAttribute(): array
    {
        return $this->staff?->roles?->pluck('slug')->filter()->values()->all() ?? [];
    }

    /**
     * The "primary" role — first of the assigned roles. Kept only for
     * places (older frontend code, JWT consumers) that still expect a
     * single role string. Prefer `roles` (the full list) for anything new.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles[0] ?? null;
    }

    // --- JWTSubject ---

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            // 'role' kept for backward compatibility with anything decoding
            // the token and expecting a single role string.
            'role' => $this->role,
            'roles' => $this->roles,
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

    /**
     * True if this user's staff record has ANY of the given role slugs
     * assigned (not just one specific role). e.g. $user->hasRole('admin', 'hr')
     */
    public function hasRole(string ...$slugs): bool
    {
        if (!$this->staff) {
            return false;
        }

        $assigned = $this->staff->relationLoaded('roles')
            ? $this->staff->roles
            : $this->staff->roles()->get();

        return $assigned->pluck('slug')->intersect($slugs)->isNotEmpty();
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