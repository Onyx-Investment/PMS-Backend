<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Only Admin/HR should be creating staff accounts — this is not a
    // public self-signup endpoint. Gate it with the 'role' middleware
    // in routes/api.php.
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_no' => 'required|string|unique:staff,employee_no',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'role_id' => 'required|exists:roles,id',
            'grade' => 'nullable|string',
            'designation' => 'nullable|string',
            'staff_manager_id' => 'nullable|exists:staff,id',
            'joined_date' => 'nullable|date',
            'grade_level_id' => 'nullable|exists:grade_levels,id',
            'cost_per_hour' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create user account
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        // Create staff record
        $staff = Staff::create([
            'user_id' => $user->id,
            'employee_no' => $request->employee_no,
            'grade' => $request->grade,
            'designation' => $request->designation,
            'department_id' => $request->department_id,
            'role_id' => $request->role_id,
            'grade_level_id' => $request->grade_level_id,
            'staff_manager_id' => $request->staff_manager_id,
            'joined_date' => $request->joined_date,
            'cost_per_hour' => $request->cost_per_hour,
            'status' => 'active',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Staff account created successfully.',
            'user' => $user->load('staff.role', 'staff.department', 'staff.gradeLevel'),
        ], 201);
    }

    public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    $credentials = $request->only('email', 'password');

    try {
        $token = JWTAuth::attempt($credentials);

        if (!$token) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Use the token we just generated instead of parsing the request
        $user = JWTAuth::setToken($token)->toUser();

        if (!$user) {
            JWTAuth::invalidate($token);

            return response()->json([
                'message' => 'Unable to retrieve authenticated user.'
            ], 401);
        }

        if (!$user->staff) {
            JWTAuth::invalidate($token);

            return response()->json([
                'message' => 'User account not linked to staff record.'
            ], 403);
        }

        if ($user->staff->status !== 'active') {
            JWTAuth::invalidate($token);

            return response()->json([
                'message' => 'This account is not active.'
            ], 403);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load(
                'staff.role',
                'staff.department',
                'staff.gradeLevel'
            ),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
}

    public function me(Request $request)
    {
        return response()->json(
            $request->user()->load('staff.role', 'staff.department', 'staff.gradeLevel')
        );
    }

    public function logout(Request $request)
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh());
    }

   protected function respondWithToken(string $token)
{
    $user = JWTAuth::setToken($token)->toUser();

    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => config('jwt.ttl') * 60,
        'user' => $user->load('staff.role', 'staff.department', 'staff.gradeLevel'),
    ]);
}
}