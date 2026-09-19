<?php
// app/Models/Proposal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $fillable = [
        'proposal_code',
        'lead_id', 
        'title',
        'proposal_no',
        'submission_date',
        'status',
        'prepared_by',
        'reviewed_by',
        'review_notes',
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

    public function documents()
    {
        return $this->hasMany(ProposalDocument::class);
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where('proposal_code', 'like', "%{$search}%")
            ->orWhere('title', 'like', "%{$search}%")
            ->orWhere('proposal_no', 'like', "%{$search}%");
    }
}