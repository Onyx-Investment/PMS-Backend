<?php
// app/Models/Proposal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $fillable = [
        'lead_id', 'proposal_no', 'version', 'submission_date', 'status',
        'prepared_by', 'reviewed_by', 'review_notes',
    ];

    protected $casts = [
        'submission_date' => 'date',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }
}