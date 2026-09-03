<?php
// app/Models/TimeCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeCode extends Model
{
    protected $fillable = [
        'code',
        'project_id',
        'category',
        'description',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }
}