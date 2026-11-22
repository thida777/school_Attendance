<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Models\Student;
use App\Services\InterventionTracker;
use App\Services\RiskAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Interventions (admin): the follow-ups for students at risk.
 * Detect (At-Risk) → Act (add a follow-up) → Measure (close it: rate after) → Evaluate (improved or not).
 */
class InterventionController extends Controller
{
    public function index(Request $request, RiskAnalyzer $analyzer): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'due', 'closed'])],
            'result' => ['nullable', Rule::in(['improved', 'none', 'declined'])],
            'student' => ['nullable', 'integer'],
        ]);
        $points = InterventionTracker::CHANGE_POINTS;

        $interventions = Intervention::query()
            ->with(['student.schoolClass', 'createdBy'])
            ->when($filters['student'] ?? null, fn ($q, $id) => $q->where('student_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->whereHas('student', fn ($q) => $q
                ->where('name_latin', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'due' => $q->due(),
                'open' => $q->waiting(),
                'closed' => $q->where('status', 'closed'),
            })
            ->when($filters['result'] ?? null, fn ($q, $result) => $q->where('status', 'closed')
                ->whereNotNull('rate_before')->whereNotNull('rate_after')
                ->whereRaw(match ($result) {
                    'improved' => 'rate_after - rate_before >= ?',
                    'declined' => 'rate_after - rate_before <= ?',
                    'none' => 'abs(rate_after - rate_before) < ?',
                }, [$result === 'declined' ? -$points : $points]))
            // Due first (they need action now), then open, then closed; newest first in each
            ->orderByRaw("case when status = 'closed' then 2 when follow_up_date <= ? then 0 else 1 end", [today()->toDateString()])
            ->orderByDesc('action_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $closed = Intervention::where('status', 'closed')->whereNotNull('rate_before')->whereNotNull('rate_after')->get();
        $students = Student::with('schoolClass')->where('status', 'active')->get()
            ->sortBy(fn ($s) => $s->schoolClass->name.' '.$s->name_latin);

        return view('analytics.interventions', [
            'interventions' => $interventions,
            'filters' => $filters,
            'counts' => [
                'open' => Intervention::waiting()->count(),
                'due' => Intervention::due()->count(),
                'closed' => $closed->count(),
                'improved' => $closed->filter(fn ($i) => $i->result() === 'improved')->count(),
                'average' => $closed->isEmpty() ? null : round($closed->avg(fn ($i) => $i->change()), 1),
            ],
            'students' => $students,
            'risks' => $analyzer->forStudents($students->pluck('id')),
            'selected' => isset($filters['student']) ? $students->firstWhere('id', $filters['student']) : null,
            'openForm' => $request->boolean('add'),
        ]);
    }

    public function store(Request $request, InterventionTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')],
            'action' => ['required', Rule::in(array_keys(Intervention::ACTIONS))],
            'action_date' => ['required', 'date', 'before_or_equal:today'],
            'follow_up_date' => ['nullable', 'date', 'after:action_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        Intervention::create([
            ...$data,
            'created_by' => $request->user()->id,
            'rate_before' => $tracker->rateBefore($data['student_id'], Carbon::parse($data['action_date'])),
            'status' => 'open',
        ]);

        return redirect()->route('interventions', ['student' => $data['student_id']])
            ->with('status', __('Follow-up saved. Close it on the follow-up day to measure the result.'));
    }

    /** Measure: the rate after the action, then close the follow-up. */
    public function close(Request $request, Intervention $intervention, InterventionTracker $tracker): RedirectResponse
    {
        abort_if($intervention->status === 'closed', 404);
        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:1000']]);

        $after = $tracker->rateAfter($intervention->student_id, $intervention->action_date);
        if ($after === null) {
            return back()->with('error', __('No attendance has been recorded since the action yet, so there is nothing to measure.'));
        }

        $intervention->update([
            'rate_after' => $after,
            'outcome' => $data['outcome'] ?? null,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $change = $intervention->change();

        return back()->with('status', __('Result measured: :before% → :after% (:change).', [
            'before' => $intervention->rate_before ?? '—',
            'after' => $after,
            'change' => $change === null ? '—' : ($change > 0 ? '+' : '').$change,
        ]));
    }

    public function destroy(Intervention $intervention): RedirectResponse
    {
        $intervention->delete();

        return back()->with('status', __('Follow-up deleted.'));
    }
}
