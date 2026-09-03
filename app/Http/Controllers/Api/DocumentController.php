<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $documents = $project->documents()
            ->with('uploadedBy', 'approvedBy')
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($documents);
    }

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'category' => 'required|in:proposal,report,minutes,presentation,spreadsheet,manual',
            // 20MB cap — adjust to taste, but set it deliberately rather
            // than leaving PHP's default upload limits as the only guard.
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');

        // Version = count of existing docs in this project+category, +1.
        // Simple and good enough here; revisit if documents ever need
        // explicit "supersedes" links instead of a flat counter.
        $version = $project->documents()->where('category', $request->category)->count() + 1;

        $path = $file->store("documents/{$project->id}", 'public');

        $document = $project->documents()->create([
            'category' => $request->category,
            'version' => $version,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($document, 201);
    }

    public function approve(Request $request, Project $project, Document $document)
    {
        $document->update(['approved_by' => $request->user()->id]);

        return response()->json($document);
    }

    public function destroy(Project $project, Document $document)
    {
        \Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
