<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Parents page: contacts that receive absence alerts by Telegram or SMS. They do not log in. */
class GuardianController extends Controller
{
    /**
     * List with search and filters (Telegram joined / not yet, class), and how many parents
     * the school can reach on Telegram.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));
        $filters = $request->only(['telegram', 'class']);

        $guardians = $this->filtered($search, $filters)
            ->with('students.schoolClass')
            ->withMax(['messages as last_notified_at' => fn ($q) => $q->where('status', 'sent')], 'sent_at')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $onTelegram = Guardian::whereNotNull('telegram_chat_id')->count();

        return view('parents.index', [
            'guardians' => $guardians,
            // All students for the children search (a child who left school can still be shown on the parent)
            'students' => Student::with('schoolClass')->orderBy('student_code')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
            'search' => $search,
            'filters' => $filters,
            'total' => Guardian::count(),
            'onTelegram' => $onTelegram,
            'telegramLive' => app(Telegram::class)->isLive(),
        ]);
    }

    /**
     * Printable Telegram invites, one card per parent with a QR code: ?parent=5 (one parent),
     * otherwise everyone not on Telegram yet (with ?class=3: only that class). For parents' meetings.
     */
    public function invites(Request $request): View
    {
        $query = $request->filled('parent')
            ? Guardian::whereKey($request->integer('parent'))
            : $this->filtered('', ['telegram' => 'no', 'class' => $request->input('class')]);

        $guardians = $query->with('students.schoolClass')->orderBy('name')->get();

        return view('parents.invites', [
            'cards' => $guardians->map(fn (Guardian $g) => ['guardian' => $g, 'link' => $g->telegramInviteLink()])
                ->filter(fn ($c) => $c['link']),
            'class' => $request->filled('class') ? SchoolClass::find($request->integer('class')) : null,
            'telegramLive' => app(Telegram::class)->isLive(),
        ]);
    }

    /** Search (name, phone or child) and the filters, shared by the list and the invites. */
    private function filtered(string $search, array $filters)
    {
        $digits = preg_replace('/\D/', '', $search);

        return Guardian::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->when($digits !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))
                ->orWhereHas('students', fn ($q) => $q
                    ->where('name_latin', 'like', "%{$search}%")
                    ->orWhere('name_km', 'like', "%{$search}%"))))
            ->when(($filters['telegram'] ?? '') === 'yes', fn ($q) => $q->whereNotNull('telegram_chat_id'))
            ->when(($filters['telegram'] ?? '') === 'no', fn ($q) => $q->whereNull('telegram_chat_id'))
            ->when($filters['class'] ?? null, fn ($q, $class) => $q->whereHas('students', fn ($q) => $q->where('school_class_id', $class)));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $guardian = DB::transaction(function () use ($data) {
            $guardian = Guardian::create(collect($data)->only(['name', 'name_km', 'phone', 'telegram_chat_id'])->all());
            $guardian->students()->attach(collect($data['student_ids'])->mapWithKeys(fn ($id) => [$id => ['relation' => $data['relation']]])->all());

            return $guardian;
        });

        // A new parent without Telegram: the page opens their QR invite at once, so they can scan it now
        return redirect()->route('parents.index')->with('status', __('Parent saved.'))
            ->with('invite_parent', $guardian->telegram_chat_id ? null : $guardian->id);
    }

    /** Saves the contact and links the chosen child (another child is added, the others stay). */
    public function update(Request $request, Guardian $parent): RedirectResponse
    {
        $fullList = $request->has('student_ids'); // the new form sends all children; an older one sends one to add
        $data = $this->validated($request, $parent);
        $children = collect($data['student_ids'])->mapWithKeys(fn ($id) => [$id => ['relation' => $data['relation']]])->all();

        // The form shows all children of the parent: a child taken out is unlinked,
        // but never when this parent is the child's only contact
        if ($fullList) {
            $removed = $parent->students()->whereNotIn('students.id', $data['student_ids'])->withCount('guardians')->get();
            if ($alone = $removed->firstWhere('guardians_count', 1)) {
                return back()->withInput()->with('error', __(':name would have no parent contact. Add another parent for this student first.', ['name' => $alone->name_latin]));
            }
        }

        DB::transaction(function () use ($data, $parent, $children, $fullList) {
            $parent->update(collect($data)->only(['name', 'name_km', 'phone', 'telegram_chat_id'])->all());
            $fullList
                ? $parent->students()->sync($children)
                : $parent->students()->syncWithoutDetaching($children); // one child (older form): added
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
            // The form sends several children (student_ids[]); one student_id is still accepted
            'student_ids' => $request->has('student_ids')
                ? array_values(array_filter((array) $request->input('student_ids')))
                : array_filter([$request->input('student_id')]),
        ]);

        return $request->validate([
            'name_km' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:8,12', Rule::unique('guardians')->ignore($guardian)],
            'telegram_chat_id' => ['nullable', 'string', 'max:32'],
            'relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
            'student_ids' => ['required', 'array', 'min:1', 'max:10'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
        ], [
            'student_ids.required' => __('Choose at least one child.'),
        ], [
            'student_ids' => __('Children'),
            'name_km' => __('Name (Khmer)'),
            'name' => __('Name (Latin)'),
            'phone' => __('Phone'),
            'telegram_chat_id' => __('Telegram chat ID'),
            'relation' => __('Relation'),
            'student_id' => __('Child'),
        ]);
    }
}
