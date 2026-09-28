<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LeaveRequest extends Model
{
   // app/Models/LeaveRequest.php
protected $fillable = [
    'staff_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'pay_amount', 'reason', 'status',
    'manager_id', 'manager_action', 'manager_acted_by', 'manager_acted_at', 'manager_notes',
    'hr_action', 'hr_acted_by', 'hr_acted_at', 'hr_notes',
    'cancelled_at',
    'original_end_date', 'original_days', 'curtailed_at', 'curtailed_by', 'curtailment_notes',
    'payment_status', 'paid_at', 'paid_by', 'payment_notes',
];

protected $casts = [
    'start_date' => 'date',
    'end_date' => 'date',
    'original_end_date' => 'date',
    'days' => 'decimal:2',
    'original_days' => 'decimal:2',
    'pay_amount' => 'decimal:2',
    'manager_acted_at' => 'datetime',
    'hr_acted_at' => 'datetime',
    'cancelled_at' => 'datetime',
    'curtailed_at' => 'datetime',
    'paid_at' => 'datetime',
];

public function curtailedBy()
{
    return $this->belongsTo(User::class, 'curtailed_by');
}

public function paidBy()
{
    return $this->belongsTo(User::class, 'paid_by');
}

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function manager()
    {
        return $this->belongsTo(Staff::class, 'manager_id');
    }

    public function managerActedBy()
    {
        return $this->belongsTo(User::class, 'manager_acted_by');
    }

    public function hrActedBy()
    {
        return $this->belongsTo(User::class, 'hr_acted_by');
    }

    /**
     * Weekday count between two dates, inclusive. Doesn't account for
     * public holidays — plug a holiday calendar in here later if needed.
     */
    public static function countBusinessDays(string $start, string $end): float
    {
        $date = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        $count = 0;

        while ($date->lte($endDate)) {
            if (!$date->isWeekend()) {
                $count++;
            }
            $date->addDay();
        }

        return (float) $count;
    }
}