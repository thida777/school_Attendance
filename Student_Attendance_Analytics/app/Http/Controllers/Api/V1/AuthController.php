<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Login for the Android app. The app keeps the token and sends it as "Authorization: Bearer <token>".
 * Only teachers use the app; the admin works on the website.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],       // email, or phone with or without spaces
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $isEmail = filter_var($data['login'], FILTER_VALIDATE_EMAIL) !== false;
        $user = $isEmail
            ? User::where('email', Str::lower($data['login']))->first()
            : User::where('phone', preg_replace('/\D/', '', $data['login']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => __('The email, phone or password is not correct.')]);
        }
        if (! $user->isTeacher()) {
            return response()->json(['message' => __('The app is for teachers. Please use the website.')], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'] ?? 'android')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user->load('homeroomClasses')),
        ]);
    }

    /** Log out this phone only (other phones of the same teacher stay logged in). */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('homeroomClasses'));
    }

    /** Personal info screen: language. */
    public function update(Request $request): UserResource
    {
        $data = $request->validate(['locale' => ['required', Rule::in(SetLocale::LOCALES)]]);
        $request->user()->update($data);

        return UserResource::make($request->user()->load('homeroomClasses'));
    }

    /** Change password screen. Other phones are logged out; this one stays. */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['current_password' => __('Current password'), 'password' => __('New password')]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => __('Password changed.')]);
    }
}
