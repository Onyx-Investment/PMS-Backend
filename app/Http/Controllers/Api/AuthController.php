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

    public function login(Request $request)
    {
        // Log the request for debugging
        \Log::info('Login attempt', [
            'email' => $request->email,
            'has_otp' => !empty($request->otp),
            'has_password' => !empty($request->password),
            'otp' => $request->otp
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'nullable|string',
            'otp' => 'nullable|string|size:6',
        ]);

        // Find the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            \Log::warning('User not found', ['email' => $request->email]);
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        // Check if user is active
        if (!$user->is_active) {
            \Log::warning('User account inactive', ['email' => $request->email]);
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // --- OTP LOGIN ---
        if ($request->filled('otp')) {
    \Log::info('OTP login attempt', [
        'email' => $request->email,
        'provided_otp' => $request->otp,
        'stored_otp' => $user->otp,
        'otp_expires_at' => $user->otp_expires_at
    ]);

    if (!$user->verifyOTP($request->otp)) {
        \Log::warning('Invalid OTP', [
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Invalid or expired OTP.'
        ], 401);
    }

    // OTP has served its purpose.
    // Clear it now.
    $user->clearOTP();

    $token = JWTAuth::fromUser($user);

    if ($user->must_change_password || empty($user->password)) {

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'must_change_password' => true,
            'user' => $user->load(
                'staff.role',
                'staff.department',
                'staff.gradeLevel'
            ),
        ]);
    }

    return response()->json([
        'access_token' => $token,
        'token_type' => 'bearer',
        'expires_in' => config('jwt.ttl') * 60,
        'must_change_password' => false,
        'user' => $user->load(
            'staff.role',
            'staff.department',
            'staff.gradeLevel'
        ),
    ]);
}

        // --- PASSWORD LOGIN ---
        if (!$request->password) {
            return response()->json(['message' => 'Password is required.'], 422);
        }

        // Check if user must change password (no password set yet)
        if ($user->must_change_password && empty($user->password)) {
            // Generate new OTP and send it
            $otp = $user->generateOTP();
            
            \Log::info('Sending OTP for first-time login', [
                'email' => $request->email,
                'otp' => $otp
            ]);
            
            try {
                Mail::to($user->email)->send(new OTPMail($user, $otp, false));
            } catch (\Exception $e) {
                \Log::error('Failed to send OTP email: ' . $e->getMessage());
            }
            
            return response()->json([
                'message' => 'Please use OTP to login and set your password. An OTP has been sent to your email.',
                'use_otp' => true,
                'email' => $user->email,
            ], 422);
        }

        // Attempt login with password
        $credentials = $request->only('email', 'password');

        try {
            $token = JWTAuth::attempt($credentials);

            if (!$token) {
                return response()->json([
                    'message' => 'Invalid credentials.'
                ], 401);
            }

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

            \Log::info('Password login successful', ['email' => $request->email]);
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => $user->load('staff.role', 'staff.department', 'staff.gradeLevel'),
            ]);

        } catch (\Throwable $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function requestOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        // Generate OTP
        $otp = $user->generateOTP();

        \Log::info('OTP requested', [
            'email' => $request->email,
            'otp' => $otp
        ]);

        // Send OTP via email
        try {
            Mail::to($user->email)->send(new OTPMail($user, $otp, false));
            
            return response()->json([
                'message' => 'OTP sent to your email.',
                'email' => $user->email,
                // Include OTP in response for development
                'otp' => app()->environment('local') ? $otp : null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP email: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }
    }

 public function changePassword(Request $request)
{
    $request->validate([
        'password' => 'required|string|min:8|confirmed',
        'current_password' => 'nullable|string',
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthenticated.'
        ], 401);
    }

    /*
    |--------------------------------------------------------------------------
    | First-time password setup
    |--------------------------------------------------------------------------
    */

    if ($user->must_change_password || empty($user->password)) {

        // User model automatically hashes the password
        $user->password = $request->password;
        $user->must_change_password = false;
        $user->save();

        \Log::info('First-time password created successfully', [
            'email' => $user->email
        ]);

        return response()->json([
            'message' => 'Password created successfully.',
            'must_change_password' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Existing user changing password
    |--------------------------------------------------------------------------
    */

    if (!$request->current_password) {
        return response()->json([
            'message' => 'Current password is required.'
        ], 422);
    }

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json([
            'message' => 'Current password is incorrect.'
        ], 422);
    }

    // User model automatically hashes the password
    $user->password = $request->password;
    $user->save();

    \Log::info('Password changed successfully', [
        'email' => $user->email
    ]);

    return response()->json([
        'message' => 'Password changed successfully.',
        'must_change_password' => false,
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