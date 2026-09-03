<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionItem extends Model
{
    protected $fillable = ['meeting_id', 'assigned_to', 'description', 'due_date', 'status'];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
