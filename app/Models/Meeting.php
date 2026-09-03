<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'project_id', 'client_id', 'meeting_type', 'agenda',
        'venue', 'meeting_date', 'chair_person_id',
    ];

    protected $casts = [
        'meeting_date' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function chairPerson()
    {
        return $this->belongsTo(User::class, 'chair_person_id');
    }

    public function attendees()
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function minutes()
    {
        return $this->hasOne(MeetingMinutes::class);
    }

    public function actionItems()
    {
        return $this->hasMany(ActionItem::class);
    }
}
