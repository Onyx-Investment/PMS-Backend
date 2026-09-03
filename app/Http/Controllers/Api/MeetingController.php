<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $meetings = Meeting::with('project', 'client', 'chairPerson')
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->orderByDesc('meeting_date')
            ->paginate(20);

        return response()->json($meetings);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'client_id' => 'nullable|exists:clients,id',
            'meeting_type' => 'required|in:formal,informal',
            'agenda' => 'nullable|string',
            'venue' => 'nullable|string',
            'meeting_date' => 'required|date',
            'chair_person_id' => 'nullable|exists:users,id',
        ]);

        $meeting = Meeting::create($data);

        return response()->json($meeting, 201);
    }

    public function show(Meeting $meeting)
    {
        return response()->json(
            $meeting->load('project', 'client', 'chairPerson', 'attendees.user', 'attendees.clientContact', 'minutes', 'actionItems.assignee')
        );
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'meeting_type' => 'sometimes|in:formal,informal',
            'agenda' => 'nullable|string',
            'venue' => 'nullable|string',
            'meeting_date' => 'sometimes|date',
            'chair_person_id' => 'nullable|exists:users,id',
        ]);

        $meeting->update($data);

        return response()->json($meeting);
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted.']);
    }

    // --- Attendees ---
    // Each attendee is EITHER an internal user OR an external client
    // contact — enforced here rather than at the DB layer since MyISAM
    // (used on some of Victor's shared-hosting deployments) doesn't
    // support CHECK constraints reliably.

    public function addAttendee(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id|required_without:client_contact_id',
            'client_contact_id' => 'nullable|exists:client_contacts,id|required_without:user_id',
        ]);

        if ($data['user_id'] ?? null and $data['client_contact_id'] ?? null) {
            return response()->json([
                'message' => 'An attendee is either internal staff or a client contact, not both.',
            ], 422);
        }

        $attendee = $meeting->attendees()->create($data);

        return response()->json($attendee->load('user', 'clientContact'), 201);
    }

    public function removeAttendee(Meeting $meeting, int $attendeeId)
    {
        $meeting->attendees()->where('id', $attendeeId)->delete();

        return response()->json(['message' => 'Attendee removed.']);
    }
}
