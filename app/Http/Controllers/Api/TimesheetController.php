<?php
// app/Http/Controllers/Api/TimesheetController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewOthers = $user->hasRole('assignment_manager', 'assignment_lead', 'staff_manager', 'admin', 'ceo', 'coo', 'md');

        $timesheets = Timesheet::with('user', 'entries.project', 'entries.task', 'entries.timeCode')
            ->when(
                $request->user_id && $canViewOthers,
                fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when(
                !$request->user_id || !$canViewOthers,
                fn ($q) => $q->where('user_id', $user->id)
            )
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('week_start')
            ->paginate(20);

        return response()->json($timesheets);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($data['week_start'])->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $timesheet = Timesheet::firstOrCreate(
            ['user_id' => $request->user()->id, 'week_start' => $weekStart->toDateString()],
            ['week_end' => $weekEnd->toDateString(), 'status' => 'draft']
        );

        return response()->json($timesheet->load('entries.project', 'entries.task', 'entries.timeCode'));
    }

    public function show(Timesheet $timesheet)
    {
        return response()->json(
            $timesheet->load('user', 'entries.project', 'entries.task', 'entries.timeCode', 'approvedBy')
        );
    }

    public function submit(Request $request, Timesheet $timesheet)
    {
        $this->authorizeOwnTimesheet($request, $timesheet);

        if ($timesheet->entries()->count() === 0) {
            return response()->json(['message' => 'Add at least one time entry before submitting.'], 422);
        }

        $timesheet->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json($timesheet);
    }

    public function approve(Request $request, Timesheet $timesheet)
    {
        $timesheet->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $timesheet->entries()->update(['approved' => true]);

        return response()->json($timesheet->load('entries'));
    }

    public function reject(Request $request, Timesheet $timesheet)
    {
        $timesheet->update(['status' => 'rejected']);

        return response()->json($timesheet);
    }

    public function reopen(Request $request, Timesheet $timesheet)
    {
        $this->authorizeOwnTimesheet($request, $timesheet);

        $timesheet->update(['status' => 'draft']);

        return response()->json($timesheet);
    }

    private function authorizeOwnTimesheet(Request $request, Timesheet $timesheet): void
    {
        abort_if($timesheet->user_id !== $request->user()->id, 403, 'Not your timesheet.');
    }
}