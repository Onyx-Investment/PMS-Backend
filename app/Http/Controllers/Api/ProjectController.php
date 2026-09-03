<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTeam;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with(['client', 'assignmentLead.user', 'assignmentManager.user'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_code' => 'nullable|string|unique:projects,project_code',
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'assignment_type' => 'required|in:m&e,transaction,advisory,investment,training,research,internal,consulting',
            'assignment_lead_id' => 'nullable|exists:staff,id', // Changed to staff
            'assignment_manager_id' => 'nullable|exists:staff,id', // Changed to staff
            'client_relationship_partner_id' => 'nullable|exists:staff,id', // Changed to staff
            'budget_hours' => 'nullable|numeric',
            'budget_cost' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'auto_generate_code' => 'boolean',
            'staff_assignments' => 'nullable|array',
            'staff_assignments.*.staff_id' => 'required|exists:staff,id',
            'staff_assignments.*.hours' => 'nullable|numeric',
            'staff_assignments.*.cost_per_hour' => 'nullable|numeric',
        ]);

        // Auto-generate code if requested or if no code provided
        if (($request->auto_generate_code ?? false) || empty($data['project_code'])) {
            $data['project_code'] = $this->generateProjectCode($request->client_id);
        }

        // Remove staff_assignments from project data
        $staffAssignments = $data['staff_assignments'] ?? [];
        unset($data['staff_assignments']);

        // Create project
        $project = Project::create($data);

        // Save staff assignments to project_team table
        if (!empty($staffAssignments)) {
            foreach ($staffAssignments as $assignment) {
                // Skip if no staff_id
                if (empty($assignment['staff_id'])) continue;

                ProjectTeam::create([
                    'project_id' => $project->id,
                    'staff_id' => $assignment['staff_id'],
                    'role' => 'consultant', // Default role
                    'planned_hours' => $assignment['hours'] ?? 0,
                    'billable_rate' => $assignment['cost_per_hour'] ?? 0,
                ]);
            }
        }

        return response()->json($project->load('client', 'assignmentLead.user', 'assignmentManager.user'), 201);
    }

    public function show(Project $project)
    {
        return response()->json(
            $project->load('client', 'assignmentLead.user', 'assignmentManager.user', 'members')
        );
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'project_code' => 'sometimes|string|unique:projects,project_code,' . $project->id,
            'title' => 'sometimes|string|max:255',
            'assignment_type' => 'sometimes|in:m&e,transaction,advisory,investment,training,research,internal,consulting',
            'assignment_lead_id' => 'nullable|exists:staff,id', // Changed to staff
            'assignment_manager_id' => 'nullable|exists:staff,id', // Changed to staff
            'client_relationship_partner_id' => 'nullable|exists:staff,id', // Changed to staff
            'budget_hours' => 'nullable|numeric',
            'budget_cost' => 'nullable|numeric',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'actual_start' => 'nullable|date',
            'actual_end' => 'nullable|date',
            'status' => 'sometimes|in:planned,active,on_hold,completed,cancelled',
            'staff_assignments' => 'nullable|array',
            'staff_assignments.*.staff_id' => 'required|exists:staff,id',
            'staff_assignments.*.hours' => 'nullable|numeric',
            'staff_assignments.*.cost_per_hour' => 'nullable|numeric',
        ]);

        // Remove staff_assignments from project data
        $staffAssignments = $data['staff_assignments'] ?? [];
        unset($data['staff_assignments']);

        // Update project
        $project->update($data);

        // Update staff assignments in project_team table
        if (isset($request->staff_assignments)) {
            // Get current team members
            $currentTeamIds = $project->team()->pluck('staff_id')->toArray();
            $newTeamIds = [];

            foreach ($staffAssignments as $assignment) {
                if (empty($assignment['staff_id'])) continue;

                $newTeamIds[] = $assignment['staff_id'];

                // Update or create
                ProjectTeam::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'staff_id' => $assignment['staff_id'],
                    ],
                    [
                        'role' => 'consultant', // Default role
                        'planned_hours' => $assignment['hours'] ?? 0,
                        'billable_rate' => $assignment['cost_per_hour'] ?? 0,
                    ]
                );
            }

            // Remove team members that are no longer assigned
            $toRemove = array_diff($currentTeamIds, $newTeamIds);
            if (!empty($toRemove)) {
                $project->team()->whereIn('staff_id', $toRemove)->delete();
            }
        }

        return response()->json($project->load('client', 'assignmentLead.user', 'assignmentManager.user'));
    }

    public function destroy(Project $project)
    {
        // Delete team members first
        $project->team()->delete();
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    // --- Team assignment ---

    // public function addTeamMember(Request $request, Project $project)
    // {
    //     $data = $request->validate([
    //         'user_id' => 'required|exists:users,id',
    //         'role' => 'required|in:lead,am,consultant,reviewer',
    //         'planned_hours' => 'nullable|numeric',
    //         'billable_rate' => 'nullable|numeric',
    //     ]);

    //     $member = $project->team()->updateOrCreate(
    //         ['user_id' => $data['user_id']],
    //         $data
    //     );

    //     return response()->json($member, 201);
    // }

    // public function removeTeamMember(Project $project, int $userId)
    // {
    //     $project->team()->where('user_id', $userId)->delete();

    //     return response()->json(['message' => 'Removed from project team.']);
    // }

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

    /**
     * Endpoint to preview a generated project code
     */
    public function previewCode(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
        ]);

        $code = $this->generateProjectCode($request->client_id);
        
        return response()->json(['project_code' => $code]);
    }


     public function getTeamMembers(Project $project)
    {
        $teamMembers = $project->team()->with('staff.user', 'staff.gradeLevel')->get();
        
        // Transform to include staff details
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
            'planned_hours' => 'nullable|numeric',
            'billable_rate' => 'nullable|numeric',
        ]);

        $member = $project->team()->updateOrCreate(
            ['staff_id' => $data['staff_id']],
            $data
        );

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


    public function updateTeamMember(Request $request, Project $project, int $staffId)
{
    $data = $request->validate([
        'role' => 'sometimes|in:lead,am,consultant,reviewer',
        'planned_hours' => 'nullable|numeric',
        'billable_rate' => 'nullable|numeric',
    ]);

    $member = $project->team()->where('staff_id', $staffId)->firstOrFail();
    $member->update($data);

    return response()->json($member);
}


public function getAssignedProjects(Request $request)
{
    $user = $request->user();
    $staff = \App\Models\Staff::where('user_id', $user->id)->first();
    
    if (!$staff) {
        return response()->json([]);
    }
    
    // Get projects where this staff is a team member
    $projects = Project::whereHas('team', function($q) use ($staff) {
        $q->where('staff_id', $staff->id);
    })
    ->with(['client', 'assignmentLead.user', 'assignmentManager.user'])
    ->orderBy('title')
    ->get();
    
    return response()->json($projects);
}

}