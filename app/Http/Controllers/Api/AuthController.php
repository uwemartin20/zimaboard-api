<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login (Web + Mobile)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
            'device'   => ['nullable', 'string'], // mobile device name
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = $request->user();

        // Revoke old tokens if you want single-session behavior
        // $user->tokens()->delete();

        $token = $user->createToken(
            $request->device ?? 'web'
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    /**
     * Logged-in user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Erfolgreich abgemeldet.',
        ]);
    }

    /**
     * Shared user response
     */
    protected function userPayload($user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'is_admin'   => $user->is_admin,
            'department' => [
                'id'    => $user->department->id ?? null,
                'name'  => $user->department->name ?? null,
                'color' => $user->department->color ?? null,
            ],
        ];
    }

    public function users() {

        return response()->json([
            'users' => User::with('department')->get(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword'     => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Aktuelles Passwort ist nicht korrekt.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->newPassword),
        ]);

        // OPTIONAL (recommended): revoke all other tokens
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Passwort erfolgreich geändert.',
        ]);
    }
}
