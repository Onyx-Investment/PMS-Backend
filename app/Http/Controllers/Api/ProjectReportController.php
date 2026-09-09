<?php
// app/Http/Controllers/Api/ProjectReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\ProjectTeam;
use Illuminate\Http\Request;

class ProjectReportController extends Controller
{
    public function generate(Request $request, $projectId)
    {
        $request->validate([
            'report_type' => 'required|in:status,progress,financial,resource,variance',
        ]);

        $project = Project::with(['client', 'assignmentLead', 'assignmentManager', 'members'])->findOrFail($projectId);

        switch ($request->report_type) {
            case 'status':
                return $this->statusReport($project);
            case 'progress':
                return $this->progressReport($project);
            case 'financial':
                return $this->financialReport($project);
            case 'resource':
                return $this->resourceReport($project);
            case 'variance':
                return $this->varianceReport($project);
            default:
                return response()->json(['message' => 'Invalid report type'], 422);
        }
    }

    private function statusReport($project)
    {
        $tasks = $project->tasks()->get();
        $completedTasks = $tasks->where('status', 'done')->count();
        $totalTasks = $tasks->count();
        $completionPercentage = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        // Get blocks (tasks that are blocked)
        $blocks = $tasks->where('status', 'blocked')->map(function($task) {
            return [
                'description' => $task->title,
                'severity' => $task->priority,
            ];
        })->values();

        // Determine health
        $health = 'good';
        if ($blocks->count() > 2) $health = 'critical';
        else if ($blocks->count() > 0 || $completionPercentage < 30) $health = 'warning';

        // Timeline status
        $timelineStatus = 'on_track';
        if ($project->planned_end && now()->gt($project->planned_end) && $completionPercentage < 100) {
            $timelineStatus = 'delayed';
        }

        return response()->json([
            'status' => [
                'health' => $health,
                'timeline_status' => $timelineStatus,
                'completion_percentage' => round($completionPercentage, 1),
                'active_blocks' => $blocks->count(),
                'blocks' => $blocks,
                'recent_activity' => $this->getRecentActivity($project),
            ]
        ]);
    }

    private function progressReport($project)
    {
        $tasks = $project->tasks()->get();
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'done')->count();

        // Calculate overall progress
        $overallProgress = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

        $taskData = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'assignee' => $task->assignee ? $task->assignee->full_name : 'Unassigned',
                'due_date' => $task->planned_end,
                'progress' => $task->status === 'done' ? 100 : 0,
            ];
        });

        return response()->json([
            'progress' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'total_milestones' => 0,
                'completed_milestones' => 0,
                'overall_progress' => round($overallProgress, 1),
                'tasks' => $taskData,
            ]
        ]);
    }

    private function financialReport($project)
    {
        $budget = $project->budget_cost ?? 0;
        $actualCost = $project->budget_cost ?? 0; // In reality, calculate from time entries

        // Calculate actual cost from time entries
        $timeEntries = TimeEntry::where('project_id', $project->id)->get();
        $actualCost = $timeEntries->sum(function($entry) {
            $rate = $entry->staff ? ($entry->staff->cost_per_hour ?? $entry->staff->gradeLevel->cost_per_hour ?? 0) : 0;
            return $entry->hours * $rate;
        });

        $variance = $budget - $actualCost;
        $budgetUsedPercentage = $budget > 0 ? ($actualCost / $budget) * 100 : 0;

        // Cost breakdown by category
        $costBreakdown = $timeEntries->groupBy('time_code_id')->map(function($entries, $codeId) {
            $code = $entries->first()->timeCode;
            return [
                'category' => $code ? $code->code : 'Uncategorized',
                'amount' => $entries->sum(function($e) {
                    $rate = $e->staff ? ($e->staff->cost_per_hour ?? $e->staff->gradeLevel->cost_per_hour ?? 0) : 0;
                    return $e->hours * $rate;
                }),
                'percentage' => 0,
            ];
        })->values();

        // Calculate percentages
        $totalCost = $costBreakdown->sum('amount');
        $costBreakdown->transform(function($item) use ($totalCost) {
            $item['percentage'] = $totalCost > 0 ? round(($item['amount'] / $totalCost) * 100, 1) : 0;
            return $item;
        });

        // Hours breakdown by staff
        $hoursBreakdown = $timeEntries->groupBy('staff_id')->map(function($entries, $staffId) {
            $staff = $entries->first()->staff;
            $budgetHours = 0; // This would come from project team allocation
            $actualHours = $entries->sum('hours');
            return [
                'staff' => $staff ? $staff->full_name : 'Unknown',
                'role' => $staff ? $staff->designation : '—',
                'budget_hours' => $budgetHours,
                'actual_hours' => round($actualHours, 1),
                'variance' => $budgetHours - $actualHours,
            ];
        })->values();

        return response()->json([
            'financial' => [
                'budget' => $budget,
                'actual_cost' => $actualCost,
                'variance' => $variance,
                'budget_used_percentage' => round($budgetUsedPercentage, 1),
                'cost_breakdown' => $costBreakdown,
                'hours_breakdown' => $hoursBreakdown,
            ]
        ]);
    }

    private function resourceReport($project)
    {
        $teamMembers = $project->members()->with('user')->get();
        $totalHours = TimeEntry::where('project_id', $project->id)->sum('hours');

        $workload = $teamMembers->map(function($member) use ($project) {
            $hoursLogged = TimeEntry::where('project_id', $project->id)
                ->where('staff_id', $member->id)
                ->sum('hours');
            
            $allocatedHours = $member->pivot->planned_hours ?? 0;
            $utilization = $allocatedHours > 0 ? ($hoursLogged / $allocatedHours) * 100 : 0;

            return [
                'staff' => $member->full_name,
                'role' => $member->designation,
                'hours_logged' => round($hoursLogged, 1),
                'allocated_hours' => $allocatedHours,
                'utilization' => round($utilization, 1),
            ];
        });

        $avgUtilization = $workload->avg('utilization') ?? 0;

        return response()->json([
            'resource' => [
                'total_staff' => $teamMembers->count(),
                'total_hours' => round($totalHours, 1),
                'avg_utilization' => round($avgUtilization, 1),
                'workload' => $workload,
            ]
        ]);
    }

    private function varianceReport($project)
    {
        $tasks = $project->tasks()->get();
        
        // Schedule variance
        $plannedDuration = $project->planned_start && $project->planned_end 
            ? now()->parse($project->planned_start)->diffInDays(now()->parse($project->planned_end)) 
            : 0;
        $actualDuration = $project->actual_start && $project->actual_end
            ? now()->parse($project->actual_start)->diffInDays(now()->parse($project->actual_end))
            : $plannedDuration;
        $scheduleVariance = $plannedDuration - $actualDuration;

        // Cost variance
        $budget = $project->budget_cost ?? 0;
        $timeEntries = TimeEntry::where('project_id', $project->id)->get();
        $actualCost = $timeEntries->sum(function($entry) {
            $rate = $entry->staff ? ($entry->staff->cost_per_hour ?? $entry->staff->gradeLevel->cost_per_hour ?? 0) : 0;
            return $entry->hours * $rate;
        });
        $costVariance = $budget - $actualCost;

        // Scope variance
        $plannedTasks = $tasks->count();
        $actualTasks = $tasks->count(); // Could track changes over time

        return response()->json([
            'variance' => [
                'schedule_variance' => $scheduleVariance,
                'cost_variance' => $costVariance,
                'scope_variance' => 0,
                'planned_duration' => $plannedDuration,
                'actual_duration' => $actualDuration,
                'planned_cost' => $budget,
                'actual_cost' => $actualCost,
                'baseline_start' => $project->planned_start,
                'actual_start' => $project->actual_start,
                'start_variance' => 0,
                'baseline_end' => $project->planned_end,
                'actual_end' => $project->actual_end,
                'end_variance' => 0,
                'baseline_budget' => $budget,
                'actual_budget' => $actualCost,
                'budget_variance' => $costVariance,
                'baseline_tasks' => $plannedTasks,
                'actual_tasks' => $actualTasks,
                'tasks_variance' => 0,
            ]
        ]);
    }

    private function getRecentActivity($project)
    {
        // Get recent task updates, time entries, etc.
        return [
            [
                'date' => now()->toDateTimeString(),
                'description' => 'Project status updated',
                'user' => 'System',
            ]
        ];
    }
}