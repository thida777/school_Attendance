<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * School settings (admin): the school's name in English and Khmer.
 * It appears on the website, in printed reports, in Telegram messages and in the Android app.
 */
class SchoolSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.school', [
            'nameEn' => School::name('en'),
            'nameKm' => School::name('km'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name_en' => ['required', 'string', 'max:100'],
            'school_name_km' => ['required', 'string', 'max:100'],
        ], [], [
            'school_name_en' => __('School name (English)'),
            'school_name_km' => __('School name (Khmer)'),
        ]);

        Setting::put(array_map('trim', $data));

        return back()->with('status', __('School settings saved.'));
    }

    /** GET /api/v1/school (no login): the name the app shows on its login and splash screens. */
    public function show(): JsonResponse
    {
        return response()->json([
            'name' => School::name(),
            'name_en' => School::name('en'),
            'name_km' => School::name('km'),
        ]);
    }
}
