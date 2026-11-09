<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Services\AttendanceRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Corrections & Audit Log (admin): requests to decide, and the read-only history of every change. */
class CorrectionController extends Controller
{
    public const ACTIONS = ['submitted', 'corrected', 'approved', 'rejected'];

    public function __construct(private AttendanceRecorder $recorder) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(self::ACTIONS)],
        ]);

        $requests = AttendanceCorrection::where('status', AttendanceCorrection::PENDING)
            ->with(['record.student', 'record.session.schoolClass', 'record.session.subject', 'changedBy'])
            ->oldest()
            ->get();

        $events = $this->auditLog($filters['q'] ?? null, $filters['action'] ?? null);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $log = new LengthAwarePaginator($events->forPage($page, 20)->values(), $events->count(), 20, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('attendance.corrections', compact('requests', 'log', 'filters'));
    }

    public function approve(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->recorder->approve($request->user(), $correction);

        return back()->with('status', __('Request approved. The attendance record was changed.'));
    }

    public function reject(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:255']]);
        $this->recorder->reject($request->user(), $correction, $data['decision_note'] ?? null);

        return back()->with('status', __('Request rejected. The record was kept as it was.'));
    }

    /**
     * Every event, newest first: lessons submitted, records corrected, requests approved or rejected.
     * Each event: time, user, action, record (text), change (old → new) and reason.
     */
    private function auditLog(?string $search, ?string $action): Collection
    {
        $events = collect();

        if (! $action || $action === 'submitted') {
            AttendanceSession::with(['schoolClass', 'subject', 'teacher', 'recordedBy'])
                ->withCount('records')
                ->when($search, fn ($q) => $q->whereHas('schoolClass', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                ->get()
                ->each(fn ($s) => $events->push((object) [
                    'time' => $s->created_at,
                    'user' => ($s->recordedBy ?? $s->teacher)?->name,
                    'action' => 'submitted',
                    'record' => "{$s->schoolClass->name} · {$s->subject->name_en} · P{$s->period}",
                    'detail' => __(':count students', ['count' => $s->records_count]).' · '.$s->date->translatedFormat('D j M'),
                    'old' => null,
                    'new' => null,
                    'reason' => null,
                ]));
        }

        if ($action !== 'submitted') {
            $statuses = match ($action) {
                'corrected' => [AttendanceCorrection::APPLIED],
                'approved' => [AttendanceCorrection::APPROVED],
                'rejected' => [AttendanceCorrection::REJECTED],
                default => [AttendanceCorrection::APPLIED, AttendanceCorrection::APPROVED, AttendanceCorrection::REJECTED],
            };

            AttendanceCorrection::whereIn('status', $statuses)
                ->with(['record.student', 'record.session.schoolClass', 'record.session.subject', 'changedBy', 'decidedBy'])
                ->when($search, fn ($q) => $q->whereHas('record.student', fn ($q) => $q
                    ->where('name_latin', 'like', "%{$search}%")
                    ->orWhere('name_km', 'like', "%{$search}%")
                    ->orWhere('student_code', 'like', "%{$search}%")))
                ->get()
                ->each(function ($c) use ($events) {
                    $session = $c->record->session;
                    $decided = $c->status !== AttendanceCorrection::APPLIED;
                    $events->push((object) [
                        'time' => $decided ? $c->decided_at : $c->created_at,
                        'user' => ($decided ? $c->decidedBy : $c->changedBy)?->name,
                        'action' => $c->status === AttendanceCorrection::APPLIED ? 'corrected' : $c->status,
                        'record' => "{$c->record->student->name_latin} · {$session->schoolClass->name} · {$session->subject->name_en} · P{$session->period}",
                        'detail' => $session->date->translatedFormat('D j M').($decided ? ' · '.__('asked by :name', ['name' => $c->changedBy?->name]) : ''),
                        'old' => $c->old_status,
                        'new' => $c->new_status,
                        'reason' => $c->status === AttendanceCorrection::REJECTED && $c->decision_note ? $c->decision_note : $c->reason,
                    ]);
                });
        }

        return $events->sortByDesc('time')->values();
    }
}
