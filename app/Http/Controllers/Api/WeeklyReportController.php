<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeeklyReport;
use Illuminate\Http\Request;

class WeeklyReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = WeeklyReport::with('project', 'consultant', 'reviewedBy')
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->consultant_id, fn ($q) => $q->where('consultant_id', $request->consultant_id))
            ->orderByDesc('week')
            ->paginate(20);

        return response()->json($reports);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'week' => 'required|date',
            'activities' => 'nullable|string',
            'outputs' => 'nullable|string',
            'client_interaction' => 'nullable|string',
            'issues' => 'nullable|string',
            'next_week' => 'nullable|string',
        ]);

        // updateOrCreate on the unique (project, consultant, week) triple —
        // saving again for a week you've already reported on amends it
        // instead of erroring.
        $report = WeeklyReport::updateOrCreate(
            [
                'project_id' => $data['project_id'],
                'consultant_id' => $request->user()->id,
                'week' => $data['week'],
            ],
            $data
        );

        return response()->json($report, 201);
    }

    public function submit(Request $request, WeeklyReport $weeklyReport)
    {
        abort_if($weeklyReport->consultant_id !== $request->user()->id, 403);

        $weeklyReport->update(['submitted_at' => now()]);

        return response()->json($weeklyReport);
    }

    public function review(Request $request, WeeklyReport $weeklyReport)
    {
        $weeklyReport->update(['reviewed_by' => $request->user()->id]);

        return response()->json($weeklyReport);
    }
}
