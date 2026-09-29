<?php
// app/Http/Controllers/Api/DepartmentController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Returns every department, each with its FULL sub-department tree
     * (any depth — department1 -> sub1 -> sub1a -> ... -> as deep as it
     * goes) embedded under `sub_departments`, and its immediate parents
     * under `parent_departments`.
     *
     * A department can have several parents, so this is really a DAG, not
     * a strict tree — the same department can legitimately appear nested
     * under more than one branch. serializeDepartment() below is
     * cycle-safe regardless: if a department is ever encountered again
     * along its own ancestry path (which addParents()/update() should
     * always prevent from happening, but this is a hard backstop against
     * it anyway), recursion stops there instead of looping forever.
     *
     * Adjacency is pulled once as a flat list rather than letting Eloquent
     * lazy-load subDepartments/parentDepartments once per node while we
     * recurse — keeps this to a fixed, small number of queries regardless
     * of how deep or wide the org chart is.
     */
    public function index(Request $request)
    {
        $departments = Department::with(['headOfDepartment.user', 'staff.user'])
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $links = DB::table('department_parents')
            ->select('child_department_id', 'parent_department_id')
            ->get();

        $childrenOf = [];
        $parentsOf = [];

        foreach ($links as $link) {
            $childrenOf[$link->parent_department_id][] = $link->child_department_id;
            $parentsOf[$link->child_department_id][] = $link->parent_department_id;
        }

        $tree = $departments
            ->map(fn (Department $department) => $this->serializeDepartment(
                $department,
                $departments,
                $childrenOf,
                $parentsOf
            ))
            ->values();

        return response()->json($tree);
    }

    private function serializeDepartment(
        Department $department,
        \Illuminate\Support\Collection $allDepartments,
        array $childrenOf,
        array $parentsOf,
        array $ancestryIds = []
    ): array {
        $data = [
            'id' => $department->id,
            'name' => $department->name,
            'description' => $department->description,
            'head_of_department_id' => $department->head_of_department_id,
            'head_of_department' => $department->headOfDepartment,
            'staff' => $department->staff,
            'created_at' => $department->created_at,
            'updated_at' => $department->updated_at,
            'parent_departments' => collect($parentsOf[$department->id] ?? [])
                ->map(fn ($id) => $allDepartments->get($id))
                ->filter()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
                ->values(),
        ];

        // Cycle guard — see doc comment on index() above.
        if (in_array($department->id, $ancestryIds, true)) {
            $data['sub_departments'] = [];
            return $data;
        }

        $childIds = $childrenOf[$department->id] ?? [];
        $nextAncestry = [...$ancestryIds, $department->id];

        $data['sub_departments'] = collect($childIds)
            ->map(fn ($id) => $allDepartments->get($id))
            ->filter()
            ->map(fn (Department $child) => $this->serializeDepartment(
                $child,
                $allDepartments,
                $childrenOf,
                $parentsOf,
                $nextAncestry
            ))
            ->values();

        return $data;
    }

    /**
     * True if $ancestor is already an ancestor of the department with id
     * $descendantId — i.e. walking down from $ancestor through its
     * sub-departments (any depth) reaches $descendantId.
     *
     * Used to block a parent assignment that would create a cycle: making
     * $descendantId a parent of $ancestor when $ancestor is already
     * upstream of it would close a loop.
     */
    private function isAncestorOf(Department $ancestor, int $descendantId): bool
    {
        $visited = [];
        $queue = [$ancestor->id];

        while (!empty($queue)) {
            $currentId = array_shift($queue);

            if (in_array($currentId, $visited, true)) {
                continue;
            }
            $visited[] = $currentId;

            if ($currentId === $descendantId) {
                return true;
            }

            $childIds = Department::find($currentId)
                ?->subDepartments()
                ->pluck('departments.id')
                ->toArray() ?? [];

            foreach ($childIds as $childId) {
                if (!in_array($childId, $visited, true)) {
                    $queue[] = $childId;
                }
            }
        }

        return false;
    }

    /**
     * Validates a set of proposed parent ids for $department, rejecting
     * self-parenting and anything that would create a cycle. Returns an
     * error response to short-circuit the caller with, or null if every
     * id is safe to attach/sync.
     */
    private function rejectCyclicParents(Department $department, array $parentIds)
    {
        foreach ($parentIds as $parentId) {
            $parentId = (int) $parentId;

            if ($parentId === $department->id) {
                return response()->json([
                    'message' => 'A department cannot be its own parent.',
                ], 422);
            }

            if ($this->isAncestorOf($department, $parentId)) {
                $conflicting = Department::find($parentId);

                return response()->json([
                    'message' => "Can't add \"{$conflicting?->name}\" as a parent of \"{$department->name}\" — it is already one of its sub-departments, so this would create a loop.",
                ], 422);
            }
        }

        return null;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
            'parent_department_ids' => 'nullable|array',
            'parent_department_ids.*' => 'exists:departments,id',
        ]);

        // No cycle check needed here: a brand-new department has no
        // sub-departments yet, so it can't already be an ancestor of
        // anything — every id in parent_department_ids is safe.

        $department = Department::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'head_of_department_id' => $data['head_of_department_id'] ?? null,
        ]);

        if (!empty($data['parent_department_ids'])) {
            $department->parentDepartments()->attach($data['parent_department_ids']);
        }

        return response()->json($department->load(['headOfDepartment.user', 'subDepartments', 'parentDepartments']), 201);
    }

    public function show(Department $department)
    {
        return response()->json($department->load([
            'headOfDepartment.user',
            'staff.user',
            'subDepartments',
            'parentDepartments'
        ]));
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)],
            'description' => 'nullable|string',
            'head_of_department_id' => 'nullable|exists:staff,id',
            'parent_department_ids' => 'nullable|array',
            'parent_department_ids.*' => 'exists:departments,id',
        ]);

        if (isset($data['parent_department_ids'])) {
            if ($error = $this->rejectCyclicParents($department, $data['parent_department_ids'])) {
                return $error;
            }
        }

        $department->update([
            'name' => $data['name'] ?? $department->name,
            'description' => $data['description'] ?? $department->description,
            'head_of_department_id' => $data['head_of_department_id'] ?? $department->head_of_department_id,
        ]);

        if (isset($data['parent_department_ids'])) {
            $department->parentDepartments()->sync($data['parent_department_ids']);
        }

        return response()->json($department->load(['headOfDepartment.user', 'subDepartments', 'parentDepartments']));
    }

    public function destroy(Department $department)
    {
        // Check if any staff belong to this department
        if ($department->staff()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department as it has staff members assigned to it'
            ], 422);
        }

        // Check if it has sub-departments
        if ($department->subDepartments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete department as it has sub-departments'
            ], 422);
        }

        // Detach parent relationships
        $department->parentDepartments()->detach();
        $department->subDepartments()->detach();

        $department->delete();
        return response()->json(['message' => 'Department deleted successfully']);
    }

    // Add staff to department
    public function addStaff(Request $request, Department $department)
    {
        $data = $request->validate([
            'staff_ids' => 'required|array',
            'staff_ids.*' => 'exists:staff,id',
        ]);

        $department->staff()->syncWithoutDetaching($data['staff_ids']);

        return response()->json(['message' => 'Staff added to department successfully']);
    }

    // Remove staff from department
    public function removeStaff(Department $department, $staffId)
    {
        $department->staff()->detach($staffId);
        return response()->json(['message' => 'Staff removed from department successfully']);
    }

    // Add parent departments
    public function addParents(Request $request, Department $department)
    {
        $data = $request->validate([
            'parent_ids' => 'required|array',
            'parent_ids.*' => 'exists:departments,id',
        ]);

        if ($error = $this->rejectCyclicParents($department, $data['parent_ids'])) {
            return $error;
        }

        $department->parentDepartments()->syncWithoutDetaching($data['parent_ids']);

        return response()->json(['message' => 'Parent departments added successfully']);
    }

    // Remove parent department
    public function removeParent(Department $department, $parentId)
    {
        $department->parentDepartments()->detach($parentId);
        return response()->json(['message' => 'Parent department removed successfully']);
    }
}