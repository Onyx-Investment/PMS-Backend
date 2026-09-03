<?php
// app/Http/Controllers/Api/ProposalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Enums\LeadStatus;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $proposals = Proposal::with('lead.client', 'preparedBy', 'reviewedBy')
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
            'proposal_no' => 'required|string',
            'submission_date' => 'nullable|date',
        ]);

        // Get the lead
        $lead = Lead::find($data['lead_id']);
        
        // Update lead status to proposal if it's not already further along
        if (in_array($lead->status, ['qualified', 'prospect'])) {
            $lead->update(['status' => 'proposal']);
        }

        $proposal = Proposal::create([
            ...$data,
            'version' => 1,
            'status' => 'draft',
            'prepared_by' => $request->user()->id,
        ]);

        return response()->json($proposal, 201);
    }

    public function show(Proposal $proposal)
    {
        return response()->json($proposal->load('lead.client', 'preparedBy', 'reviewedBy'));
    }

    public function update(Request $request, Proposal $proposal)
    {
        $data = $request->validate([
            'status' => 'sometimes|in:draft,submitted,approved,rejected,converted',
            'proposal_no' => 'sometimes|string',
            'submission_date' => 'nullable|date',
        ]);

        $proposal->update($data);

        return response()->json($proposal->load('lead.client', 'preparedBy', 'reviewedBy'));
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
            'decision' => 'required|in:approved,rejected',
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

    public function newVersion(Request $request, Proposal $proposal)
    {
        abort_if($proposal->status !== 'rejected', 422, 'Only a rejected proposal can get a new version.');

        $next = Proposal::create([
            'lead_id' => $proposal->lead_id,
            'proposal_no' => $proposal->proposal_no,
            'version' => $proposal->version + 1,
            'status' => 'draft',
            'prepared_by' => $request->user()->id,
        ]);

        // Move lead back to proposal
        $proposal->lead->update(['status' => 'proposal']);

        return response()->json($next, 201);
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

    /**
     * Generate a unique project code
     * Format: PRJ-YYYY-XXXXX (e.g., PRJ-2026-00001)
     */
    private function generateProjectCode(int $clientId): string
    {
        $prefix = 'PRJ';
        $year = date('Y');
        
        // Get the last project code for this year
        $lastProject = Project::where('project_code', 'like', "{$prefix}-{$year}-%")
            ->orderBy('project_code', 'desc')
            ->first();
        
        if ($lastProject) {
            // Extract the sequence number from the last code
            $parts = explode('-', $lastProject->project_code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $sequence = '00001';
        }
        
        $code = "{$prefix}-{$year}-{$sequence}";
        
        // Ensure uniqueness (just in case)
        while (Project::where('project_code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 5, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$year}-{$sequence}";
        }
        
        return $code;
    }

    public function destroy(Proposal $proposal)
    {
        $proposal->delete();
        return response()->json(['message' => 'Proposal deleted.']);
    }
}