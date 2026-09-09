<?php
// app/Models/StaffDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDocument extends Model
{
    protected $fillable = [
        'staff_id',
        'title',
        'document_path',
        'document_name',
        'uploaded_by',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}