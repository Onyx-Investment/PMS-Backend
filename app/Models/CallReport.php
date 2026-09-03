<?php
// app/Models/CallReport.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallReport extends Model
{
    protected $fillable = [
        'lead_id', 'client_id', 'staff_id', 'visit_date', 'background',
        'meeting_highlights', 'tasks', 'action_required', 'followup_date', 'status',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'followup_date' => 'date',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function attendees()
    {
        return $this->hasMany(CallReportAttendee::class);
    }
}