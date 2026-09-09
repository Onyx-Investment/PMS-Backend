<?php
// app/Http/Controllers/Api/ReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Models\Staff;
use App\Models\Project;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function timesheet(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'staff_id' => 'nullable|exists:staff,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $query = TimeEntry::with(['staff.user', 'staff.gradeLevel', 'project', 'task', 'timeCode'])
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        $entries = $query->get();

        return response()->json([
            'entries' => $entries,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_entries' => $entries->count(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:csv,excel,pdf',
            'staff_id' => 'nullable|exists:staff,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        // Fetch data
        $query = TimeEntry::with(['staff.user', 'project', 'task', 'timeCode'])
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if ($request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        $entries = $query->get();

        // Generate export based on format
        switch ($request->format) {
            case 'csv':
                return $this->exportCSV($entries);
            case 'excel':
                return $this->exportExcel($entries);
            case 'pdf':
                return $this->exportPDF($entries);
            default:
                return response()->json(['message' => 'Invalid format'], 422);
        }
    }

    private function exportCSV($entries)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="timesheet-report.csv"',
        ];

        $callback = function() use ($entries) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Staff', 'Project', 'Task', 'Time Code', 'Hours', 'Billable', 'Description']);

            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->date,
                    $entry->staff?->user?->first_name . ' ' . $entry->staff?->user?->last_name,
                    $entry->project?->title,
                    $entry->task?->title,
                    $entry->timeCode?->code,
                    $entry->hours,
                    $entry->billable ? 'Yes' : 'No',
                    $entry->description,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcel($entries)
    {
        // You'll need to install Maatwebsite Excel package
        // return Excel::download(new TimesheetExport($entries), 'timesheet-report.xlsx');
        
        return response()->json(['message' => 'Excel export coming soon'], 501);
    }

    private function exportPDF($entries)
    {
        // You'll need to install a PDF package like DomPDF
        // $pdf = PDF::loadView('exports.timesheet', ['entries' => $entries]);
        // return $pdf->download('timesheet-report.pdf');
        
        return response()->json(['message' => 'PDF export coming soon'], 501);
    }
}