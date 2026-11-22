<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
use App\Models\ParentMessage;
use App\Models\Student;
use App\Services\InterventionTracker;
use App\Services\ParentMessenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parent Messages (admin): every message sent to parents, automatic or written by hand,
 * with its delivery status. Also: "Message parent" from Student details, and Resend for a failed message.
 */
class ParentMessageController extends Controller
{
    public function index(Request $request, ParentMessenger $messenger): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['delivered', 'simulated', 'failed'])],
            'type' => ['nullable', Rule::in(['absence_alert', 'manual'])],
        ]);

        $messages = ParentMessage::query()
            ->with(['student.schoolClass', 'guardian', 'sentBy'])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->whereHas('student', fn ($q) => $q
                ->where('name_latin', 'like', "%{$search}%")
                ->orWhere('name_km', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'delivered' => $q->where('status', 'sent')->where('simulated', false),
                'simulated' => $q->where('status', 'sent')->where('simulated', true),
                'failed' => $q->where('status', 'failed'),
            })
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $month = ParentMessage::where('created_at', '>=', today()->startOfMonth());

        return view('messages.index', [
            'messages' => $messages,
            'filters' => $filters,
            'live' => $messenger->isLive(),
            'counts' => [
                'month' => (clone $month)->count(),
                'alerts' => (clone $month)->where('type', 'absence_alert')->count(),
                'delivered' => (clone $month)->where('status', 'sent')->where('simulated', false)->count(),
                'simulated' => (clone $month)->where('status', 'sent')->where('simulated', true)->count(),
                'failed' => ParentMessage::where('status', 'failed')->count(),
            ],
            'streakDays' => ParentMessenger::STREAK_DAYS,
        ]);
    }

    /** "Message parent" on Student details: send to every parent of the student, and (optionally) add a follow-up. */
    public function store(Request $request, Student $student, ParentMessenger $messenger, InterventionTracker $tracker): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'follow_up' => ['nullable', 'boolean'],
        ]);
        $student->load('guardians');
        if ($student->guardians->isEmpty()) {
            return back()->with('error', __('This student has no parent yet. Add a parent first.'));
        }

        $sent = $student->guardians->map(fn ($guardian) => $messenger->send($student, $guardian, 'manual', $data['body'], $request->user()));

        // Sending a message is an action: record it as a follow-up, so the result can be measured later
        if ($request->boolean('follow_up')) {
            $channel = $sent->first()->channel;
            Intervention::create([
                'student_id' => $student->id,
                'created_by' => $request->user()->id,
                'action' => $channel === 'telegram' ? 'telegram' : 'sms',
                'notes' => __('Message sent from the system.'),
                'action_date' => today(),
                'follow_up_date' => ($d = today()->addDays(14))->isSunday() ? $d->addDay() : $d,
                'rate_before' => $tracker->rateBefore($student->id, today()),
                'status' => 'open',
            ]);
        }

        return back()->with($sent->contains('status', 'failed') ? 'error' : 'status', $this->result($sent->first()));
    }

    public function resend(ParentMessage $message, ParentMessenger $messenger): RedirectResponse
    {
        abort_unless($message->status === 'failed', 404);

        return back()->with('status', $this->result($messenger->deliver($message)));
    }

    private function result(ParentMessage $message): string
    {
        return match (true) {
            $message->status === 'failed' => __('The message was saved but could not be delivered: :error', ['error' => $message->error]),
            $message->simulated => __('Message saved (demo: not really sent).'),
            default => __('Message delivered by Telegram.'),
        };
    }
}
