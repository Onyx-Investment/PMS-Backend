<?php
// app/Http/Controllers/Api/ProposalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $proposals = Proposal::with(['lead.client', 'preparedBy', 'reviewedBy', 'documents'])
            ->when($request->lead_id, fn ($q) => $q->where('lead_id', $request->lead_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($proposals);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'title' => 'required|string|max:255',
            'proposal_no' => 'nullable|string',
            'submission_date' => 'nullable|date',
        ]);

        // Get the lead
        $lead = Lead::with('client')->find($data['lead_id']);
        
        // Generate proposal code
        $data['proposal_code'] = $this->generateProposalCode($lead);
        
        // Auto-generate proposal number if not provided
        if (empty($data['proposal_no'])) {
            $data['proposal_no'] = 'PRO-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        // Update lead status to proposal if it's not already further along
        if (in_array($lead->status, ['qualified', 'prospect'])) {
            $lead->update(['status' => 'proposal']);
        }

        $proposal = Proposal::create([
            ...$data,
            'status' => 'draft',
            'prepared_by' => $request->user()->id,
        ]);

        return response()->json($proposal->load(['lead.client', 'preparedBy', 'documents']), 201);
    }

    public function show(Proposal $proposal)
    {
        return response()->json($proposal->load(['lead.client', 'preparedBy', 'reviewedBy', 'documents.uploadedBy']));
    }

    public function update(Request $request, Proposal $proposal)
    {
        $data = $request->validate([
            'status' => 'sometimes|in:draft,submitted,reviewed,approved,converted,lost',
            'title' => 'sometimes|string|max:255',
            'proposal_no' => 'sometimes|string',
            'submission_date' => 'nullable|date',
        ]);

        $proposal->update($data);

        return response()->json($proposal->load(['lead.client', 'preparedBy', 'reviewedBy', 'documents']));
    }

    public function submit(Request $request, Proposal $proposal)
    {
        abort_if($proposal->status !== 'draft', 422, 'Only a draft proposal can be submitted.');

        $proposal->update([
            'status' => 'submitted',
            'submission_date' => now()
        ]);

        // Update lead status
        if (in_array($proposal->lead->status, ['qualified', 'prospect'])) {
            $proposal->lead->update(['status' => 'proposal']);
        }

        return response()->json($proposal);
    }

    public function review(Request $request, Proposal $proposal)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,lost',
            'review_notes' => 'nullable|string',
        ]);

        abort_if($proposal->status !== 'submitted', 422, 'Only a submitted proposal can be reviewed.');

        $proposal->update([
            'status' => $data['decision'],
            'reviewed_by' => $request->user()->id,
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        // If rejected, move lead to negotiation
        if ($data['decision'] === 'rejected') {
            $proposal->lead->update(['status' => 'negotiation']);
        }

        return response()->json($proposal);
    }

    public function convertToProject(Request $request, Proposal $proposal)
    {
        abort_if($proposal->status !== 'approved', 422, 'Only an approved proposal can be converted to a project.');

        $data = $request->validate([
            'project_code' => 'nullable|string|unique:projects,project_code',
            'title' => 'required|string|max:255',
            'assignment_type' => 'required|in:consulting,training,research,internal,m&e,transaction,advisory,investment',
            'assignment_lead_id' => 'nullable|exists:staff,id',
            'assignment_manager_id' => 'nullable|exists:staff,id',
            'budget_hours' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'auto_generate_code' => 'boolean',
        ]);

        // Auto-generate code if requested or if no code provided
        if (($request->auto_generate_code ?? false) || empty($data['project_code'])) {
            $data['project_code'] = $this->generateProjectCode($proposal->lead->client_id);
        }

        $project = Project::create([
            'project_code' => $data['project_code'],
            'title' => $data['title'],
            'assignment_type' => $data['assignment_type'],
            'assignment_lead_id' => $data['assignment_lead_id'] ?? null,
            'assignment_manager_id' => $data['assignment_manager_id'] ?? null,
            'budget_hours' => $data['budget_hours'] ?? null,
            'planned_start' => $data['planned_start'] ?? null,
            'planned_end' => $data['planned_end'] ?? null,
            'proposal_id' => $proposal->id,
            'client_id' => $proposal->lead->client_id,
            'status' => 'planned',
        ]);

        // Update proposal status to converted
        $proposal->update(['status' => 'converted']);
        
        // Update lead status to won
        $proposal->lead->update(['status' => 'won']);

        return response()->json($project, 201);
    }

    public function destroy(Proposal $proposal)
    {
        $proposal->delete();
        return response()->json(['message' => 'Proposal deleted.']);
    }

    /**
     * Generate a unique proposal code
     * Format: P-{lead_code}-{sequence}
     * Example: P-L-CLT202600001-WRP-001
     */
    private function generateProposalCode(Lead $lead): string
    {
        $prefix = 'PP';
        $leadCode = $lead->lead_code ?? 'LD';
        
        // Get the last proposal code for this lead
        $lastProposal = Proposal::where('proposal_code', 'like', "{$prefix}-{$leadCode}-%")
            ->orderBy('proposal_code', 'desc')
            ->first();

        if ($lastProposal) {
            // Extract the sequence number from the last code
            $parts = explode('-', $lastProposal->proposal_code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        $code = "{$prefix}-{$leadCode}-{$sequence}";

        // Ensure uniqueness (just in case)
        while (Proposal::where('proposal_code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 3, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$leadCode}-{$sequence}";
        }

        return $code;
    }

    /**
     * Generate a unique project code
     */
    private function generateProjectCode(int $clientId): string
    {
        $prefix = 'PRJ';
        $year = date('Y');
        
        $lastProject = Project::where('project_code', 'like', "{$prefix}-{$year}-%")
            ->orderBy('project_code', 'desc')
            ->first();
        
        if ($lastProject) {
            $parts = explode('-', $lastProject->project_code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $sequence = '00001';
        }
        
        $code = "{$prefix}-{$year}-{$sequence}";
        
        while (Project::where('project_code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 5, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$year}-{$sequence}";
        }
        
        return $code;
    }

    /**
     * Get proposal code preview
     */
    public function previewCode(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
        ]);

        $lead = Lead::find($request->lead_id);
        $code = $this->generateProposalCode($lead);

        return response()->json(['proposal_code' => $code]);
    }

    /**
     * Upload document for proposal
     */
    public function uploadDocument(Request $request, Proposal $proposal)
    {
        $data = $request->validate([
            'document' => 'required|file|max:20480', // 20MB max
            'description' => 'nullable|string',
        ]);

        $file = $request->file('document');
        $path = $file->store('proposals/' . $proposal->id, 'public');

        $document = ProposalDocument::create([
            'proposal_id' => $proposal->id,
            'document_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($document->load('uploadedBy'), 201);
    }

    /**
     * Delete document
     */
    public function deleteDocument(Proposal $proposal, $documentId)
    {
        $document = ProposalDocument::where('proposal_id', $proposal->id)
            ->findOrFail($documentId);

        // Delete file from storage
        Storage::disk('public')->delete($document->file_path);

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    /**
     * Download document
     */
    public function downloadDocument(Proposal $proposal, $documentId)
    {
        $document = ProposalDocument::where('proposal_id', $proposal->id)
            ->findOrFail($documentId);

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->document_name
        );
    }
}