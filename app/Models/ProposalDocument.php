<?php
// app/Models/ProposalDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalDocument extends Model
{
    protected $fillable = [
        'proposal_id',
        'document_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}