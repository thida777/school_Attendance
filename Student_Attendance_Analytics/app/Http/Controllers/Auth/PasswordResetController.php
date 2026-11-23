<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Website "Forgot password?": 1) ask for a Telegram code, 2) code + new password. Rules in PasswordResetter. */
class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, PasswordResetter $resetter): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255']]);
        $resetter->sendCode($data['login']);

        // Same next page for every account, whether it exists or not
        return redirect()->route('password.reset', ['login' => $data['login']]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->filled('login') && ! old('login')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', [
            'login' => (string) $request->query('login'),
            'minutes' => PasswordResetter::CODE_MINUTES,
        ]);
    }

    public function update(Request $request, PasswordResetter $resetter): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['code' => __('Code from Telegram'), 'password' => __('New password')]);

        $resetter->reset($data['login'], $data['code'], $data['password']);

        return redirect()->route('login')->with('status', __('Your password was changed. Log in with the new password.'));
    }
}
