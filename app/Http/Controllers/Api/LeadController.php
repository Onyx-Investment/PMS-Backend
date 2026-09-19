<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::with(['client', 'owner'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn($q) => $q->where('client_id', $request->client_id))
            ->when($request->owner_id, fn($q) => $q->where('owner_id', $request->owner_id))
            ->when($request->search, fn($q) => $q->search($request->search))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($leads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_code' => 'nullable|string|unique:leads,lead_code',
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'nullable|string|max:255',
            'estimated_value' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:qualified,prospect,proposal,negotiation,closed,disqualified,won,abandoned',
        ]);

        // Auto-generate lead code if not provided
        if (empty($data['lead_code'])) {
            $client = Client::find($data['client_id']);
            $data['lead_code'] = $this->generateLeadCode($client->client_code, $data['title']);
        }

        $lead = Lead::create($data);

        return response()->json($lead->load(['client', 'owner']), 201);
    }

    public function show(Lead $lead)
    {
        return response()->json($lead->load(['client', 'owner', 'proposals', 'callReports.staff.user']));
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'lead_code' => ['sometimes', 'string', Rule::unique('leads')->ignore($lead->id)],
            'client_id' => 'sometimes|exists:clients,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'source' => 'nullable|string|max:255',
            'estimated_value' => 'nullable|numeric|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:qualified,prospect,proposal,negotiation,closed,disqualified,won,abandoned',
        ]);

        $lead->update($data);

        return response()->json($lead->load(['client', 'owner']));
    }

    public function destroy(Lead $lead)
    {
        // Check if lead has proposals
        if ($lead->proposals()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete lead as it has proposals associated with it.'
            ], 422);
        }

        // Check if lead has call reports
        if ($lead->callReports()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete lead as it has call reports associated with it.'
            ], 422);
        }

        $lead->delete();

        return response()->json(['message' => 'Lead deleted successfully.']);
    }

    /**
     * Generate a unique lead code
     * Format: L-{client_code_prefix}-{title_acronym}-{sequence}
     * Example: L-CLT2026-00001-WRP-001
     */
    private function generateLeadCode(string $clientCode, string $title): string
    {
        // Extract acronym from title (first letter of each word)
        $words = preg_split('/[\s\-_]+/', $title);
        $acronym = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $acronym .= strtoupper(substr($word, 0, 1));
            }
        }
        // Limit acronym to 5 characters
        $acronym = substr($acronym, 0, 5);
        
        // If acronym is empty, use 'L'
        if (empty($acronym)) {
            $acronym = 'LD';
        }

        // Extract client code prefix (remove hyphens and limit to 8 chars)
        $clientPrefix = str_replace('-', '', $clientCode);
        $clientPrefix = substr($clientPrefix, 0, 8);

        // Escape special characters for LIKE query
        $likePattern = 'LD-' . $clientPrefix . '-' . $acronym . '-%';
        $likePattern = addcslashes($likePattern, '%_');

        // Get the last lead code for this client and acronym
        $lastLead = Lead::where('lead_code', 'like', $likePattern)
            ->orderBy('lead_code', 'desc')
            ->first();

        if ($lastLead) {
            // Extract the sequence number from the last code
            $parts = explode('-', $lastLead->lead_code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        $code = "LD-{$clientPrefix}-{$acronym}-{$sequence}";

        // Ensure uniqueness (just in case)
        while (Lead::where('lead_code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 3, '0', STR_PAD_LEFT);
            $code = "LD-{$clientPrefix}-{$acronym}-{$sequence}";
        }

        return $code;
    }

    /**
     * Endpoint to preview a generated lead code
     */
    public function previewCode(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
        ]);

        $client = Client::find($request->client_id);
        $code = $this->generateLeadCode($client->client_code, $request->title);

        return response()->json(['lead_code' => $code]);
    }

    /**
     * Get leads by client
     */
    public function getByClient(Request $request, $clientId)
    {
        $leads = Lead::with(['client', 'owner'])
            ->where('client_id', $clientId)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($leads);
    }

    /**
     * Convert lead to project (when won)
     */
    public function convertToProject(Request $request, Lead $lead)
    {
        if ($lead->status !== 'won') {
            return response()->json([
                'message' => 'Only won leads can be converted to projects.'
            ], 422);
        }

        // Check if already converted
        if ($lead->project_id) {
            return response()->json([
                'message' => 'This lead has already been converted to a project.'
            ], 422);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'assignment_type' => 'required|in:m&e,transaction,advisory,investment,training,research,internal,consulting',
            'assignment_lead_id' => 'nullable|exists:staff,id',
            'assignment_manager_id' => 'nullable|exists:staff,id',
            'budget_hours' => 'nullable|numeric',
            'budget_cost' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
        ]);

        // Create project
        $project = Project::create([
            'client_id' => $lead->client_id,
            'title' => $data['title'] ?? $lead->title,
            'assignment_type' => $data['assignment_type'],
            'assignment_lead_id' => $data['assignment_lead_id'] ?? null,
            'assignment_manager_id' => $data['assignment_manager_id'] ?? null,
            'budget_hours' => $data['budget_hours'] ?? null,
            'budget_cost' => $data['budget_cost'] ?? $lead->estimated_value,
            'planned_start' => $data['planned_start'] ?? null,
            'planned_end' => $data['planned_end'] ?? null,
            'status' => 'planned',
        ]);

        // Update lead with project reference
        $lead->update(['project_id' => $project->id]);

        return response()->json([
            'message' => 'Lead converted to project successfully.',
            'project' => $project->load('client')
        ]);
    }
}