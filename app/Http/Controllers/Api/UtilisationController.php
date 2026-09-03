<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtilisationController extends Controller
{
    // Returns booked hours per user for a date range, split by
    // billable/non-billable/internal. Deliberately simple — a "true"
    // utilisation % needs a working-hours-per-week assumption per user,
    // which the manual doesn't specify yet. Revisit once that's defined.
    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $rows = TimeEntry::query()
            ->join('timesheets', 'timesheets.id', '=', 'time_entries.timesheet_id')
            ->join('time_codes', 'time_codes.id', '=', 'time_entries.time_code_id')
            ->whereBetween('time_entries.date', [$data['from'], $data['to']])
            ->select(
                'timesheets.user_id',
                'time_codes.category',
                DB::raw('SUM(time_entries.hours) as total_hours')
            )
            ->groupBy('timesheets.user_id', 'time_codes.category')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows) {
                return $rows->pluck('total_hours', 'category');
            });

        return response()->json($rows);
    }
}
