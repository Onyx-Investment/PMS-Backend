<?php
// app/Http/Controllers/Api/StaffDocumentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffDocumentController extends Controller
{
    public function index(Staff $staff)
    {
        $documents = $staff->documents()->with('uploadedBy')->get();
        return response()->json($documents);
    }

    public function store(Request $request, Staff $staff)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'document' => 'required|file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png', // 5MB max
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $path = $file->store('staff-documents/' . $staff->id, 'public');

        $document = $staff->documents()->create([
            'title' => $request->title,
            'document_path' => Storage::url($path),
            'document_name' => $originalName,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($document->load('uploadedBy'), 201);
    }

    public function destroy(StaffDocument $document)
    {
        // Delete the file from storage
        $path = str_replace('/storage/', '', $document->document_path);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $document->delete();
        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function download(StaffDocument $document)
    {
        $path = str_replace('/storage/', '', $document->document_path);
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($path, $document->document_name);
    }
}