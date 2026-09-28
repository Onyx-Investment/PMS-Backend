<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = [
        'name', 'code', 'annual_days', 'paid', 'description', 'is_active',
        'pay_basis', 'flat_allowance', 'salary_percentage',
    ];

    protected $casts = [
        'annual_days' => 'decimal:2',
        'paid' => 'boolean',
        'is_active' => 'boolean',
        'flat_allowance' => 'decimal:2',
        'salary_percentage' => 'decimal:2',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    // Grade-level overrides of this type's default annual_days /
    // flat_allowance / salary_percentage. A grade level with no row here
    // just uses this type's own values as-is.
    public function entitlements()
    {
        return $this->hasMany(LeaveEntitlement::class);
    }
}