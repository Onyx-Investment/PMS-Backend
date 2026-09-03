<?php
// app/Http/Controllers/Api/LeadController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Enums\LeadStatus;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::with('client', 'owner')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->client_id, fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->owner_id, fn ($q) => $q->where('owner_id', $request->owner_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($leads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'source' => 'nullable|string',
            'estimated_value' => 'nullable|numeric',
            'probability' => 'nullable|integer|min:0|max:100',
            'owner_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:' . implode(',', LeadStatus::all()),
        ]);

        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = LeadStatus::PROSPECT;
        }

        $lead = Lead::create($data);

        return response()->json($lead, 201);
    }

    public function show(Lead $lead)
    {
        return response()->json(
            $lead->load('client', 'owner', 'callReports.staff', 'proposals')
        );
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'source' => 'nullable|string',
            'estimated_value' => 'nullable|numeric',
            'probability' => 'nullable|integer|min:0|max:100',
            'status' => 'sometimes|in:' . implode(',', LeadStatus::all()),
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $lead->update($data);

        return response()->json($lead);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(['message' => 'Lead deleted.']);
    }
}