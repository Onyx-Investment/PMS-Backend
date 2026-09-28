<?php
// app/Http/Controllers/Api/DeviceAllocationController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceAllocation;
use App\Models\Staff;
use Illuminate\Http\Request;

class DeviceAllocationController extends Controller
{
    private const HR_ROLES = ['assignment_manager', 'assignment_lead', 'staff_manager', 'admin', 'ceo', 'coo', 'md'];

    /**
     * Full allocation log, filterable — HR's main tracking view.
     */
    public function index(Request $request)
    {
        $this->authorizeHr($request);

        $allocations = DeviceAllocation::with(['device', 'staff.user', 'allocatedBy', 'retrievedBy'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->staff_id, fn ($q) => $q->where('staff_id', $request->staff_id))
            ->when($request->device_id, fn ($q) => $q->where('device_id', $request->device_id))
            ->orderByDesc('allocated_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($allocations);
    }

    /**
     * Devices currently held by one staff member — for their profile /
     * offboarding checklist.
     */
    public function forStaff(Request $request, Staff $staff)
    {
        $this->authorizeHr($request);

        $allocations = DeviceAllocation::with(['device', 'allocatedBy', 'retrievedBy'])
            ->where('staff_id', $staff->id)
            ->orderByDesc('allocated_at')
            ->get();

        return response()->json($allocations);
    }

    public function allocate(Request $request)
    {
        $this->authorizeHr($request);

        $data = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'staff_id' => 'required|exists:staff,id',
            'condition' => 'nullable|in:new,good,fair,poor',
            'notes' => 'nullable|string',
        ]);

        $device = Device::findOrFail($data['device_id']);

        abort_if($device->status !== 'available', 422, "This device is currently '{$device->status}' and can't be allocated.");

        $allocation = DeviceAllocation::create([
            'device_id' => $device->id,
            'staff_id' => $data['staff_id'],
            'allocated_at' => now(),
            'allocated_by' => $request->user()->id,
            'condition_at_allocation' => $data['condition'] ?? null,
            'allocation_notes' => $data['notes'] ?? null,
            'status' => 'allocated',
        ]);

        $device->update(['status' => 'assigned']);

        return response()->json($allocation->load(['device', 'staff.user', 'allocatedBy']), 201);
    }

    public function retrieve(Request $request, DeviceAllocation $deviceAllocation)
    {
        $this->authorizeHr($request);

        $data = $request->validate([
            'condition' => 'nullable|in:new,good,fair,poor,damaged,lost',
            'notes' => 'nullable|string',
        ]);

        abort_unless($deviceAllocation->status === 'allocated', 422, 'This allocation has already been retrieved.');

        $deviceAllocation->update([
            'retrieved_at' => now(),
            'retrieved_by' => $request->user()->id,
            'condition_at_retrieval' => $data['condition'] ?? null,
            'retrieval_notes' => $data['notes'] ?? null,
            'status' => 'retrieved',
        ]);

        // 'lost' or 'damaged' on return -> the device isn't fit for the
        // available pool again without a look, so route it to
        // under_repair rather than silently making it allocatable.
        $newDeviceStatus = in_array($data['condition'] ?? null, ['damaged', 'lost'], true)
            ? 'under_repair'
            : 'available';

        $deviceAllocation->device->update(['status' => $newDeviceStatus]);

        return response()->json($deviceAllocation->load(['device', 'staff.user', 'retrievedBy']));
    }

    private function authorizeHr(Request $request): void
    {
        abort_unless($request->user()->hasRole(...self::HR_ROLES), 403, 'Only HR/admin can manage device allocations.');
    }
}