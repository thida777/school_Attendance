<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Send each role to its own start page after login. */
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            User::ROLE_ADMIN => redirect()->route('dashboard'),
            default => redirect()->route('teacher.dashboard'),
        };
    }
}
