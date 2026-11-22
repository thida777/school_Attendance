<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ParentMessenger;
use App\Services\RiskAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    /** List students with search, filters and each student's attendance rate. */
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'class', 'gender', 'status']);

        $students = Student::query()
            ->with(['schoolClass', 'guardians'])
            ->withCount([
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('status', 'present'),
            ])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('name_latin', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")))
            ->when($filters['class'] ?? null, fn ($q, $classId) => $q->where('school_class_id', $classId))
            ->when($filters['gender'] ?? null, fn ($q, $gender) => $q->where('gender', $gender))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderBy('student_code')
            ->paginate(15)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            // Risk with the full rules (rate, days in a row, trend) for the students on this page
            'risks' => app(RiskAnalyzer::class)->forStudents($students->getCollection()->pluck('id')),
            'classes' => SchoolClass::orderBy('name')->get(),
            'filters' => $filters,
            'total' => Student::count(),
        ]);
    }

    /**
     * Student details: profile, risk with the reason, weekly trend (8 weeks), when absences happen,
     * absence reasons, follow-ups, and recent records.
     */
    public function show(Student $student, RiskAnalyzer $analyzer): View
    {
        $student->load(['schoolClass', 'guardians']);
        $risk = $analyzer->forStudent($student->id);

        $records = $student->attendanceRecords()
            ->with(['session.subject', 'session.teacher'])
            ->get()
            ->sortByDesc(fn ($r) => $r->session->date->toDateString().sprintf('%02d', $r->session->period))
            ->values();
        $absences = $records->where('status', 'absent');

        // Weekly rate for the last 8 weeks (Monday to Saturday); null = no lessons that week
        $thisMonday = today()->startOfWeek();
        $weeks = collect(range(7, 0))->map(function (int $back) use ($records, $thisMonday) {
            $start = $thisMonday->copy()->subWeeks($back);
            $week = $records->filter(fn ($r) => $r->session->date->between($start, $start->copy()->addDays(6)));

            return [
                'label' => $start->translatedFormat('j M'),
                'rate' => $week->isEmpty() ? null : round(100 * $week->where('status', 'present')->count() / $week->count(), 1),
            ];
        });

        $byDay = collect(range(1, 6))->mapWithKeys(fn ($d) => [$d => $absences->filter(fn ($r) => $r->session->date->dayOfWeekIso === $d)->count()]);
        $bySubject = $absences->groupBy(fn ($r) => $r->session->subject_id)->sortByDesc(fn ($g) => $g->count());
        $topSubject = $bySubject->first()?->first()->session->subject;

        return view('students.show', [
            'student' => $student,
            'risk' => $risk,
            'weeks' => $weeks,
            'byDay' => $byDay,
            'when' => [
                'day' => $risk->patternDay ? [$risk->patternDay, $byDay[$risk->patternDay]] : null,
                'period' => $risk->patternPeriod ? [$risk->patternPeriod, $absences->where('session.period', $risk->patternPeriod)->count()] : null,
                'subject' => $topSubject && $bySubject->first()->count() >= 2 ? [$topSubject, $bySubject->first()->count()] : null,
            ],
            'reasons' => collect(\App\Models\AttendanceRecord::ABSENCE_REASONS)
                ->mapWithKeys(fn ($code) => [$code => $absences->filter(fn ($r) => ($r->absence_reason ?? 'not_given') === $code)->count()]),
            'recent' => $records->take(8),
            'interventions' => $student->interventions()->with('createdBy')->latest('action_date')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
            // "Message parent": the starting text (attendance this month), and the last messages
            'messageText' => ($guardian = $student->guardians->first())
                ? app(ParentMessenger::class)->summaryText($student->loadMissing('schoolClass.homeroomTeacher'), $guardian)
                : null,
            'messages' => $student->parentMessages()->latest('id')->take(3)->get(),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $student = Student::create($request->safe()->except(['guardian_name', 'guardian_phone', 'guardian_relation']));
            $this->saveGuardian($student, $request);
        });

        return redirect()->route('students.index')->with('status', __('Student saved.'));
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        DB::transaction(function () use ($request, $student) {
            $student->update($request->safe()->except(['guardian_name', 'guardian_phone', 'guardian_relation']));
            $this->saveGuardian($student, $request);
        });

        return back()->with('status', __('Student saved.'));
    }

    /** A student with attendance records is never deleted: the history would be lost. */
    public function destroy(Student $student): RedirectResponse
    {
        if ($student->attendanceRecords()->exists()) {
            return back()->with('error', __(':name has attendance records and cannot be deleted. Set the student to Inactive instead.', ['name' => $student->name_latin]));
        }

        $student->delete();

        return back()->with('status', __('Student deleted.'));
    }

    /**
     * The form has one parent. A parent is found by phone number, so two brothers share one contact.
     * The parent shown in the form is replaced; other parents linked on the Parents page stay.
     */
    private function saveGuardian(Student $student, StudentRequest $request): void
    {
        $guardian = Guardian::updateOrCreate(
            ['phone' => $request->validated('guardian_phone')],
            ['name' => $request->validated('guardian_name')],
        );

        $current = $student->guardians()->first();
        if ($current && $current->id !== $guardian->id) {
            $student->guardians()->detach($current->id);
        }

        $student->guardians()->syncWithoutDetaching([
            $guardian->id => ['relation' => $request->validated('guardian_relation')],
        ]);
    }
}
