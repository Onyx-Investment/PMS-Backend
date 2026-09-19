<?php
// app/Http/Controllers/Api/ProjectController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->proposal_id, fn ($q) => $q->where('proposal_id', $request->proposal_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_code' => 'nullable|string|unique:projects,project_code',
            'proposal_id' => 'required|exists:proposals,id',
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'assignment_type' => 'required|in:m&e,transaction,advisory,investment,training,research,internal,consulting',
            'assignment_lead_id' => 'nullable|exists:staff,id',
            'assignment_manager_id' => 'nullable|exists:staff,id',
            'client_relationship_partner_id' => 'nullable|exists:staff,id',
            'project_value' => 'nullable|numeric|min:0',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'auto_generate_code' => 'boolean',
        ]);

        // Auto-generate code if requested or if no code provided
        if (($request->auto_generate_code ?? false) || empty($data['project_code'])) {
            $data['project_code'] = $this->generateProjectCodeFromProposal($request->proposal_id);
        }

        // Get the proposal
        $proposal = Proposal::with('lead')->find($data['proposal_id']);

        // Create project
        $project = Project::create($data);

        // Copy staff assignments from proposal's lead to project
        if ($proposal && $proposal->lead) {
            // If the lead has an owner, add them as a team member
            if ($proposal->lead->owner_id) {
                // Check if the owner is a staff member
                $staff = \App\Models\Staff::where('user_id', $proposal->lead->owner_id)->first();
                if ($staff) {
                    ProjectTeam::create([
                        'project_id' => $project->id,
                        'staff_id' => $staff->id,
                        'role' => 'lead',
                        'planned_hours' => 0,
                        'billable_rate' => 0,
                    ]);
                }
            }
        }

        // Update proposal status to converted
        $proposal->update(['status' => 'converted']);

        return response()->json($project->load(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal']), 201);
    }

    public function show(Project $project)
    {
        return response()->json(
            $project->load(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal', 'members.user'])
        );
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'project_code' => 'sometimes|string|unique:projects,project_code,' . $project->id,
            'proposal_id' => 'sometimes|exists:proposals,id',
            'client_id' => 'sometimes|exists:clients,id',
            'title' => 'sometimes|string|max:255',
            'assignment_type' => 'sometimes|in:m&e,transaction,advisory,investment,training,research,internal,consulting',
            'assignment_lead_id' => 'nullable|exists:staff,id',
            'assignment_manager_id' => 'nullable|exists:staff,id',
            'client_relationship_partner_id' => 'nullable|exists:staff,id',
            'project_value' => 'nullable|numeric|min:0',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'actual_start' => 'nullable|date',
            'actual_end' => 'nullable|date',
            'status' => 'sometimes|in:planned,active,on_hold,completed,cancelled',
        ]);

        $project->update($data);

        return response()->json($project->load(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal']));
    }

    public function destroy(Project $project)
    {
        // Check if project has any time entries
        if ($project->timeEntries()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete project as it has time entries associated with it.'
            ], 422);
        }

        // Delete team members first
        $project->team()->delete();
        
        // Delete related data (stages, tasks, meetings, documents)
        $project->stages()->delete();
        $project->tasks()->delete();
        $project->meetings()->delete();
        $project->documents()->delete();
        
        // Delete the project
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    /**
     * Generate a unique project code from proposal
     * Format: PRJ-{proposal_code}-{sequence}
     * Example: PRJ-P-CLT202600001-WRP-001-001
     */
    private function generateProjectCodeFromProposal(int $proposalId): string
    {
        $proposal = Proposal::findOrFail($proposalId);
        $prefix = 'PRJ';
        $proposalCode = $proposal->proposal_code ?? 'P';
        
        // Escape special characters for LIKE query
        $likePattern = $prefix . '-' . $proposalCode . '-%';
        $likePattern = addcslashes($likePattern, '%_');

        // Get the last project code for this proposal
        $lastProject = Project::where('project_code', 'like', $likePattern)
            ->orderBy('project_code', 'desc')
            ->first();

        if ($lastProject) {
            // Extract the sequence number from the last code
            $parts = explode('-', $lastProject->project_code);
            $lastSequence = intval(end($parts));
            $sequence = str_pad($lastSequence + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sequence = '001';
        }

        $code = "{$prefix}-{$proposalCode}-{$sequence}";

        // Ensure uniqueness (just in case)
        while (Project::where('project_code', $code)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 3, '0', STR_PAD_LEFT);
            $code = "{$prefix}-{$proposalCode}-{$sequence}";
        }

        return $code;
    }

    /**
     * Endpoint to preview a generated project code from proposal
     */
    public function previewCodeFromProposal(Request $request)
    {
        $request->validate([
            'proposal_id' => 'required|exists:proposals,id',
        ]);

        $code = $this->generateProjectCodeFromProposal($request->proposal_id);
        
        return response()->json(['project_code' => $code]);
    }

    /**
     * Get team members for a project
     */
    public function getTeamMembers(Project $project)
    {
        $teamMembers = $project->team()->with('staff.user', 'staff.gradeLevel')->get();
        
        $members = $teamMembers->map(function ($member) {
            return [
                'id' => $member->id,
                'staff_id' => $member->staff_id,
                'role' => $member->role,
                'planned_hours' => $member->planned_hours,
                'billable_rate' => $member->billable_rate,
                'staff' => $member->staff ? [
                    'id' => $member->staff->id,
                    'employee_no' => $member->staff->employee_no,
                    'user' => $member->staff->user ? [
                        'first_name' => $member->staff->user->first_name,
                        'last_name' => $member->staff->user->last_name,
                        'email' => $member->staff->user->email,
                    ] : null,
                    'cost_per_hour' => $member->staff->cost_per_hour,
                    'gradeLevel' => $member->staff->gradeLevel ? [
                        'name' => $member->staff->gradeLevel->name,
                        'cost_per_hour' => $member->staff->gradeLevel->cost_per_hour,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json($members);
    }

    /**
     * Add team member to project
     */
    public function addTeamMember(Request $request, Project $project)
    {
        $data = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'role' => 'required|in:lead,am,consultant,reviewer',
            'planned_hours' => 'nullable|numeric|min:0',
            'billable_rate' => 'nullable|numeric|min:0',
        ]);

        // Check if staff already assigned
        if ($project->team()->where('staff_id', $data['staff_id'])->exists()) {
            return response()->json([
                'message' => 'This staff member is already assigned to the project.'
            ], 422);
        }

        $member = $project->team()->create($data);

        return response()->json($member, 201);
    }

    /**
     * Remove team member from project
     */
    public function removeTeamMember(Project $project, int $staffId)
    {
        $project->team()->where('staff_id', $staffId)->delete();

        return response()->json(['message' => 'Removed from project team.']);
    }

    /**
     * Update team member
     */
    public function updateTeamMember(Request $request, Project $project, int $staffId)
    {
        $data = $request->validate([
            'role' => 'sometimes|in:lead,am,consultant,reviewer',
            'planned_hours' => 'nullable|numeric|min:0',
            'billable_rate' => 'nullable|numeric|min:0',
        ]);

        $member = $project->team()->where('staff_id', $staffId)->firstOrFail();
        $member->update($data);

        return response()->json($member);
    }

    /**
     * Get projects assigned to the authenticated user
     */
    public function getAssignedProjects(Request $request)
    {
        $user = $request->user();
        $staff = \App\Models\Staff::where('user_id', $user->id)->first();
        
        if (!$staff) {
            return response()->json([]);
        }
        
        $projects = Project::whereHas('team', function($q) use ($staff) {
            $q->where('staff_id', $staff->id);
        })
        ->with(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal'])
        ->orderBy('title')
        ->get();
        
        return response()->json($projects);
    }

    /**
     * Get projects by client
     */
    public function getByClient(Request $request, $clientId)
    {
        $projects = Project::with(['client', 'assignmentLead.user', 'assignmentManager.user', 'proposal'])
            ->where('client_id', $clientId)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($projects);
    }
}