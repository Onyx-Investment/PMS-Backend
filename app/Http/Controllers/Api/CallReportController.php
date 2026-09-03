<?php
// app/Http/Controllers/Api/CallReportController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallReport;
use App\Models\CallReportAttendee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\CallReportShareMail;


class CallReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = CallReport::with(['lead', 'client', 'staff', 'attendees.staff.user'])
            ->when($request->lead_id, fn ($q) => $q->where('lead_id', $request->lead_id))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('visit_date')
            ->paginate(20);

        return response()->json($reports);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'client_id' => 'required|exists:clients,id',
            'visit_date' => 'required|date',
            'background' => 'nullable|string',
            'meeting_highlights' => 'nullable|string',
            'tasks' => 'nullable|string',
            'action_required' => 'nullable|string',
            'followup_date' => 'nullable|date',
            'attendees' => 'nullable|array',
            'attendees.*.staff_id' => 'nullable|exists:staff,id',
            'attendees.*.name' => 'nullable|string|max:255',
            'attendees.*.email' => 'nullable|email|max:255',
            'attendees.*.phone' => 'nullable|string|max:255',
            'attendees.*.organization' => 'nullable|string|max:255',
            'attendees.*.is_staff' => 'boolean',
            'attendees.*.role' => 'nullable|string|max:255',
        ]);

        // Create the call report
        $report = CallReport::create([
            ...$data,
            'staff_id' => $request->user()->id,
        ]);

        // Create attendees
        if (!empty($data['attendees'])) {
            foreach ($data['attendees'] as $attendeeData) {
                // For staff attendees, if staff_id is provided, auto-fill name, email, phone from staff record
                if (($attendeeData['is_staff'] ?? false) && !empty($attendeeData['staff_id'])) {
                    $staff = \App\Models\Staff::with('user')->find($attendeeData['staff_id']);
                    if ($staff) {
                        $attendeeData['name'] = $attendeeData['name'] ?? ($staff->user?->first_name . ' ' . $staff->user?->last_name);
                        $attendeeData['email'] = $attendeeData['email'] ?? $staff->user?->email;
                        $attendeeData['phone'] = $attendeeData['phone'] ?? $staff->user?->phone;
                        $attendeeData['organization'] = $attendeeData['organization'] ?? $staff->department?->name;
                    }
                }

                // Only create if name is provided or staff_id is provided
                if (!empty($attendeeData['staff_id']) || !empty($attendeeData['name'])) {
                    $report->attendees()->create($attendeeData);
                }
            }
        }

        return response()->json($report->load('lead', 'client', 'staff', 'attendees.staff.user'), 201);
    }

    public function show(CallReport $callReport)
    {
        return response()->json($callReport->load(['lead', 'client', 'staff', 'attendees.staff.user']));
    }

    public function update(Request $request, CallReport $callReport)
    {
        $data = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'client_id' => 'required|exists:clients,id',
            'visit_date' => 'nullable|date',
            'background' => 'nullable|string',
            'meeting_highlights' => 'nullable|string',
            'tasks' => 'nullable|string',
            'action_required' => 'nullable|string',
            'followup_date' => 'nullable|date',
            'status' => 'sometimes|in:open,followed_up,closed',
            'attendees' => 'nullable|array',
            'attendees.*.id' => 'nullable|exists:call_report_attendees,id',
            'attendees.*.staff_id' => 'nullable|exists:staff,id',
            'attendees.*.name' => 'nullable|string|max:255',
            'attendees.*.email' => 'nullable|email|max:255',
            'attendees.*.phone' => 'nullable|string|max:255',
            'attendees.*.organization' => 'nullable|string|max:255',
            'attendees.*.is_staff' => 'boolean',
            'attendees.*.role' => 'nullable|string|max:255',
        ]);

        // Update the call report
        $callReport->update($data);

        // Handle attendees
        if (isset($data['attendees'])) {
            // Get current attendee IDs
            $currentAttendeeIds = $callReport->attendees()->pluck('id')->toArray();
            $updatedAttendeeIds = [];

            foreach ($data['attendees'] as $attendeeData) {
                // For staff attendees, auto-fill data if staff_id is provided
                if (($attendeeData['is_staff'] ?? false) && !empty($attendeeData['staff_id'])) {
                    $staff = \App\Models\Staff::with('user')->find($attendeeData['staff_id']);
                    if ($staff) {
                        $attendeeData['name'] = $attendeeData['name'] ?? ($staff->user?->first_name . ' ' . $staff->user?->last_name);
                        $attendeeData['email'] = $attendeeData['email'] ?? $staff->user?->email;
                        $attendeeData['phone'] = $attendeeData['phone'] ?? $staff->user?->phone;
                        $attendeeData['organization'] = $attendeeData['organization'] ?? $staff->department?->name;
                    }
                }

                if (isset($attendeeData['id']) && in_array($attendeeData['id'], $currentAttendeeIds)) {
                    // Update existing attendee
                    $attendee = CallReportAttendee::find($attendeeData['id']);
                    if ($attendee) {
                        $attendee->update($attendeeData);
                        $updatedAttendeeIds[] = $attendee->id;
                    }
                } else {
                    // Create new attendee
                    if (!empty($attendeeData['staff_id']) || !empty($attendeeData['name'])) {
                        $newAttendee = $callReport->attendees()->create($attendeeData);
                        $updatedAttendeeIds[] = $newAttendee->id;
                    }
                }
            }

            // Delete attendees that were removed
            $toDelete = array_diff($currentAttendeeIds, $updatedAttendeeIds);
            if (!empty($toDelete)) {
                CallReportAttendee::whereIn('id', $toDelete)->delete();
            }
        }

        return response()->json($callReport->load('lead', 'client', 'staff', 'attendees.staff.user'));
    }

    public function destroy(CallReport $callReport)
    {
        // Delete attendees first
        $callReport->attendees()->delete();
        $callReport->delete();

        return response()->json(['message' => 'Call report deleted successfully']);
    }




public function share(Request $request)
{
    $data = $request->validate([
        'report_id' => 'required|exists:call_reports,id',
        'email' => 'required|email',
        'pdf' => 'required|string',
        'subject' => 'nullable|string',
        'message' => 'nullable|string',
    ]);

    $report = CallReport::with(['lead', 'client', 'staff', 'attendees.staff.user'])->find($data['report_id']);

    // Send email with PDF
    Mail::send([], [], function ($message) use ($data, $report) {
        $message->to($data['email'])
            ->subject($data['subject'] ?? 'Call Report: ' . ($report->lead?->title ?? 'Meeting Report'))
            ->html($data['message'] ?? 'Please find attached the call report.');
        
        // Decode base64 PDF and attach
        $pdfContent = base64_decode(str_replace('data:application/pdf;base64,', '', $data['pdf']));
        $message->attachData($pdfContent, 'call-report-' . $report->id . '.pdf', [
            'mime' => 'application/pdf',
        ]);
    });

    return response()->json(['message' => 'Report shared successfully']);
}
}