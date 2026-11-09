<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Profile (admin and teacher): my details, language and theme, and my password. */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge([
            'phone' => preg_replace('/\D/', '', (string) $request->input('phone')),
            'email' => Str::lower(trim((string) $request->input('email'))) ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_km' => ['required', 'string', 'max:255'],
            // The admin logs in with the email, so the admin must keep one
            'email' => [$user->isAdmin() ? 'required' : 'nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['required', 'digits_between:8,12', Rule::unique('users')->ignore($user)],
        ], [], [
            'name' => __('Name (Latin)'), 'name_km' => __('Name (Khmer)'), 'email' => __('Email'), 'phone' => __('Phone'),
        ]);

        $user->update($data);

        return back()->with('status', __('Your details were saved.'));
    }

    /** New password. The Android app must log in again with it. */
    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'current_password' => __('Current password'), 'password' => __('New password'),
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $user->tokens()->delete(); // the Android app must log in again with the new password

        return back()->with('status', __('Password changed.'));
    }
}
