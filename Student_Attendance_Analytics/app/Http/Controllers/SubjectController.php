<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        $subjects = Subject::query()
            ->with('teachingAssignments:id,subject_id,teacher_id')
            ->orderBy('code')
            ->get();

        return view('subjects.index', ['subjects' => $subjects]);
    }

    public function store(Request $request): RedirectResponse
    {
        Subject::create($this->validated($request));

        return redirect()->route('subjects.index')->with('status', __('Subject saved.'));
    }

    public function update(Request $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->validated($request, $subject));

        return back()->with('status', __('Subject saved.'));
    }

    /** A subject that is taught or has attendance stays, so timetables and history keep their meaning. */
    public function destroy(Subject $subject): RedirectResponse
    {
        if ($subject->teachingAssignments()->exists() || $subject->attendanceSessions()->exists()) {
            return back()->with('error', __(':name is still taught or has attendance records. Remove its teaching assignments first.', ['name' => $subject->name_en]));
        }

        $subject->delete();

        return back()->with('status', __('Subject deleted.'));
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'alpha_num', 'max:10', Rule::unique('subjects')->ignore($subject)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_km' => ['required', 'string', 'max:255'],
            'hours_per_week' => ['required', 'integer', 'between:1,12'],
        ], [], [
            'code' => __('Code'),
            'name_en' => __('Name (English)'),
            'name_km' => __('Name (Khmer)'),
            'hours_per_week' => __('Hours per week'),
        ]);
    }
}
