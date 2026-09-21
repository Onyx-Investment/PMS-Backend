<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\OTPMail;
use App\Mail\WelcomeOTPMail;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
// use Hash;
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
            // 'password' => Hash::make($request->password),
            'password' => $request->password,
            'is_active' => true,
            'must_change_password' => false,
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

public function verifyOtp(Request $request)
{
    $data = $request->validate([
        'email' => 'required|email',
        'otp'   => 'required|string|size:6',
    ]);

    $user = User::where('email', $data['email'])->first();

    if (!$user || !$user->is_active) {
        return response()->json([
            'message' => 'Invalid email.',
        ], 404);
    }

    if (!$user->verifyOTP($data['otp'])) {
        return response()->json([
            'message' => 'Invalid or expired OTP.',
        ], 422);
    }

    // Make sure this is actually a first-time setup account.
    if (!$user->must_change_password || !empty($user->password)) {
        return response()->json([
            'message' => 'This account has already been set up. Please use normal login or change-password.',
        ], 403);
    }

    // Issue temporary setup token.
    $setupToken = auth('api')
        ->setTTL(30)
        ->login($user);

    // OTP can no longer be reused.
    $user->clearOTP();

    return response()->json([
        'message' => 'OTP verified.',
        'setup_token' => $setupToken,
        'email' => $user->email,
    ]);
}

    /**
     * Step 2 — Staff sets their FIRST password using the setup token.
     * Only allowed if must_change_password is still true.
     */
public function setPassword(Request $request)
{
    $data = $request->validate([
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    /*
     * This endpoint is ONLY for first-time password setup.
     *
     * A user must:
     * - still have must_change_password = true
     * - and should not already have a password
     */
    if (!$user->must_change_password || !empty($user->password)) {
        return response()->json([
            'message' => 'Password already set. Use change-password instead.',
        ], 403);
    }

    // User model mutator automatically hashes the password.
    $user->password = $data['password'];
    $user->must_change_password = false;

    $saved = $user->save();

    if (!$saved || empty($user->fresh()->password)) {
        \Log::error('setPassword failed to persist', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'message' => 'Could not save your password. Please try again.',
        ], 500);
    }

    // Setup token is single-use.
    auth('api')->invalidate();

    return response()->json([
        'message' => 'Password created. You can now sign in.',
    ]);
}

    /* ================================================================== */
    /*  NORMAL LOGIN                                                       */
    /* ================================================================== */

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Block login if setup was never completed.
        if ($user->must_change_password || empty($user->password)) {
            return response()->json([
                'message' => 'Please complete your account setup via the link in your email.',
                'use_setup_flow' => true,
            ], 403);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'user'         => $user->load('staff'),
        ]);
    }

    /* ================================================================== */
    /*  PASSWORD MANAGEMENT (already-authenticated users)                 */
    /* ================================================================== */

    /**
     * Change password — for users who are already signed in.
     * Requires the current password for verification.
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = $data['password']; // mutator hashes it
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /* ================================================================== */
    /*  FORGOT PASSWORD — send OTP to existing user                       */
    /* ================================================================== */

    public function requestOtp(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = User::where('email', $data['email'])->first();

        // Always return 200 so we don't leak which emails exist.
        if ($user && $user->is_active) {
            $otp = $user->generateOTP();
            Mail::to($user->email)->send(new WelcomeOTPMail($user, $otp));
        }

        return response()->json([
            'message' => 'If your email is registered, an OTP has been sent.',
        ]);
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
            'must_change_password' => $user->must_change_password ?? false,
            'user' => $user->load('staff.role', 'staff.department', 'staff.gradeLevel'),
        ]);
    }
}