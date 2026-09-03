<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingMinutes extends Model
{
    protected $table = 'meeting_minutes';

    protected $fillable = [
        'meeting_id', 'summary', 'decisions', 'next_meeting',
        'prepared_by', 'approved_by',
    ];

    protected $casts = [
        'next_meeting' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
