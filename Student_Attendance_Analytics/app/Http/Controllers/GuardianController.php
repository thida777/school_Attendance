<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Parents page: contacts that receive absence alerts by Telegram or SMS. They do not log in. */
class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));
        $digits = preg_replace('/\D/', '', $search);

        $guardians = Guardian::query()
            ->with('students.schoolClass')
            ->withMax(['messages as last_notified_at' => fn ($q) => $q->where('status', 'sent')], 'sent_at')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))
                ->orWhereHas('students', fn ($q) => $q
                    ->where('name_latin', 'like', "%{$search}%")
                    ->orWhere('name_km', 'like', "%{$search}%"))))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('parents.index', [
            'guardians' => $guardians,
            'students' => Student::with('schoolClass')->where('status', 'active')->orderBy('name_latin')->get(),
            'search' => $search,
            'total' => Guardian::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $guardian = Guardian::create(collect($data)->only(['name', 'name_km', 'phone', 'telegram_chat_id'])->all());
            $guardian->students()->attach($data['student_id'], ['relation' => $data['relation']]);
        });

        return redirect()->route('parents.index')->with('status', __('Parent saved.'));
    }

    /** Saves the contact and links the chosen child (another child is added, the others stay). */
    public function update(Request $request, Guardian $parent): RedirectResponse
    {
        $data = $this->validated($request, $parent);

        DB::transaction(function () use ($data, $parent) {
            $parent->update(collect($data)->only(['name', 'name_km', 'phone', 'telegram_chat_id'])->all());
            $parent->students()->syncWithoutDetaching([$data['student_id'] => ['relation' => $data['relation']]]);
        });

        return back()->with('status', __('Parent saved.'));
    }

    /** Every student keeps at least one parent contact, or absence alerts would reach nobody. */
    public function destroy(Guardian $parent): RedirectResponse
    {
        $alone = $parent->students()->withCount('guardians')->get()->firstWhere('guardians_count', 1);
        if ($alone) {
            return back()->with('error', __(':name would have no parent contact. Add another parent for this student first.', ['name' => $alone->name_latin]));
        }

        $parent->delete();

        return back()->with('status', __('Parent deleted.'));
    }

    /** Remove one child from a parent. */
    public function unlink(Guardian $parent, Student $student): RedirectResponse
    {
        if ($student->guardians()->count() <= 1) {
            return back()->with('error', __(':name would have no parent contact. Add another parent for this student first.', ['name' => $student->name_latin]));
        }

        $parent->students()->detach($student->id);

        return back()->with('status', __('Child removed from this parent.'));
    }

    private function validated(Request $request, ?Guardian $guardian = null): array
    {
        $request->merge([
            'phone' => preg_replace('/\D/', '', (string) $request->input('phone')),
            'telegram_chat_id' => trim((string) $request->input('telegram_chat_id')) ?: null,
        ]);

        return $request->validate([
            'name_km' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:8,12', Rule::unique('guardians')->ignore($guardian)],
            'telegram_chat_id' => ['nullable', 'string', 'max:32'],
            'relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
            'student_id' => ['required', 'exists:students,id'],
        ], [], [
            'name_km' => __('Name (Khmer)'),
            'name' => __('Name (Latin)'),
            'phone' => __('Phone'),
            'telegram_chat_id' => __('Telegram chat ID'),
            'relation' => __('Relation'),
            'student_id' => __('Child'),
        ]);
    }
}
