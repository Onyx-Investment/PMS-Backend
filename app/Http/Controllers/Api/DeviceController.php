<?php
// app/Http/Controllers/Api/DeviceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    // Same HR-style gate used elsewhere (TimesheetController@approve,
    // LeaveRequestController::HR_ROLES). Device tracking is an HR/admin
    // function, so every action here requires one of these.
    private const HR_ROLES = ['assignment_manager', 'assignment_lead', 'staff_manager', 'admin', 'ceo', 'coo', 'md'];

    public function index(Request $request)
    {
        $this->authorizeHr($request);

        $devices = Device::with(['currentAllocation.staff.user'])
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('asset_tag', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($devices);
    }

    public function store(Request $request)
    {
        $this->authorizeHr($request);

        $data = $request->validate([
            'asset_tag' => 'nullable|string|max:50|unique:devices,asset_tag',
            'category' => 'required|in:laptop,phone,monitor,accessory,other',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:devices,serial_number',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'auto_generate_tag' => 'boolean',
        ]);

        if (($request->boolean('auto_generate_tag')) || empty($data['asset_tag'])) {
            $data['asset_tag'] = $this->generateAssetTag();
        }
        unset($data['auto_generate_tag']);

        $data['status'] = 'available';

        $device = Device::create($data);

        return response()->json($device, 201);
    }

    public function show(Request $request, Device $device)
    {
        $this->authorizeHr($request);

        return response()->json(
            $device->load(['allocations' => fn ($q) => $q->with(['staff.user', 'allocatedBy', 'retrievedBy'])->orderByDesc('allocated_at')])
        );
    }

    public function update(Request $request, Device $device)
    {
        $this->authorizeHr($request);

        $data = $request->validate([
            'category' => 'sometimes|in:laptop,phone,monitor,accessory,other',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('devices', 'serial_number')->ignore($device->id)],
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Only allows moving in/out of retired or under_repair directly.
            // 'available' <-> 'assigned' should go through allocate/retrieve
            // so there's always a device_allocations row backing the change.
            'status' => 'nullable|in:under_repair,retired,available',
        ]);

        if (isset($data['status']) && $data['status'] === 'available' && $device->currentAllocation()->exists()) {
            return response()->json([
                'message' => 'This device is currently allocated. Retrieve it first before marking it available.',
            ], 422);
        }

        $device->update($data);

        return response()->json($device);
    }

    public function destroy(Request $request, Device $device)
    {
        $this->authorizeHr($request);

        if ($device->currentAllocation()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a device that is currently allocated. Retrieve it first.',
            ], 422);
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully']);
    }

    public function previewAssetTag(Request $request)
    {
        $this->authorizeHr($request);

        return response()->json(['asset_tag' => $this->generateAssetTag()]);
    }

    /**
     * Devices with no active allocation — the pickable list for the
     * "Allocate" action.
     */
    public function available(Request $request)
    {
        $this->authorizeHr($request);

        $devices = Device::where('status', 'available')
            ->orderBy('category')
            ->orderBy('asset_tag')
            ->get();

        return response()->json($devices);
    }

    private function authorizeHr(Request $request): void
    {
        abort_unless($request->user()->hasRole(...self::HR_ROLES), 403, 'Only HR/admin can manage devices.');
    }

    /**
     * Format: DEV-YYYY-XXXXX, same shape as Staff::generateEmployeeNumber.
     */
    private function generateAssetTag(): string
    {
        $prefix = 'DEV';
        $year = date('Y');

        $last = Device::where('asset_tag', 'like', "{$prefix}-{$year}-%")
            ->orderBy('asset_tag', 'desc')
            ->first();

        if ($last) {
            $parts = explode('-', $last->asset_tag);
            $sequence = str_pad(intval(end($parts)) + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $sequence = '00001';
        }

        $tag = "{$prefix}-{$year}-{$sequence}";

        while (Device::where('asset_tag', $tag)->exists()) {
            $sequence = str_pad(intval($sequence) + 1, 5, '0', STR_PAD_LEFT);
            $tag = "{$prefix}-{$year}-{$sequence}";
        }

        return $tag;
    }
}