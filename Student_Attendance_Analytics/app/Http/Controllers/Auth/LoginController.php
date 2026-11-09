<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Show the login form. */
    public function create(): View
    {
        return view('auth.login');
    }

    /** Log in with email or phone number. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Stop password guessing: 5 wrong tries per minute for the same account and computer.
        $throttleKey = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => __('Too many attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        // "012 345 678" and "012345678" are the same phone number.
        $isEmail = filter_var($data['login'], FILTER_VALIDATE_EMAIL) !== false;
        $credentials = $isEmail
            ? ['email' => Str::lower($data['login'])]
            : ['phone' => preg_replace('/\D/', '', $data['login'])];
        $credentials['password'] = $data['password'];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => __('The email, phone or password is not correct.'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('locale', $request->user()->locale);

        return redirect()->intended(route('home'));
    }

    /** Log out and forget the session. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
