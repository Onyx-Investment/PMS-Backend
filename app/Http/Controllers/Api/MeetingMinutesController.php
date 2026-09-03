<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingMinutesController extends Controller
{
    public function store(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'summary' => 'nullable|string',
            'decisions' => 'nullable|string',
            'next_meeting' => 'nullable|date',
            'prepared_by' => 'nullable|exists:users,id',
        ]);

        // updateOrCreate since minutes are 1:1 with a meeting — resubmitting
        // amends the same record rather than erroring on the unique constraint.
        $minutes = $meeting->minutes()->updateOrCreate([], $data);

        return response()->json($minutes, 201);
    }

    public function approve(Request $request, Meeting $meeting)
    {
        $minutes = $meeting->minutes;

        if (! $minutes) {
            return response()->json(['message' => 'No minutes recorded for this meeting yet.'], 404);
        }

        $minutes->update(['approved_by' => $request->user()->id]);

        return response()->json($minutes);
    }
}
