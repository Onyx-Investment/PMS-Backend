<?php
// app/Models/CallReportAttendee.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallReportAttendee extends Model
{
    protected $fillable = [
        'call_report_id',
        'staff_id',
        'name',
        'email',
        'phone',
        'organization',
        'is_staff',
        'role',
    ];

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    public function callReport()
    {
        return $this->belongsTo(CallReport::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}