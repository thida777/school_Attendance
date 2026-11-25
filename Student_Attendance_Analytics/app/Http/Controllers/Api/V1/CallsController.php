<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\ParentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Parents asking for a call" in the teacher app.
 * When a parent taps 📞 under an absence alert in Telegram, the homeroom teacher gets an open
 * follow-up "Phone call" (ParentReplies). The app lists them; after calling, the teacher taps Done.
 */
class CallsController extends Controller
{
    /** GET /api/v1/calls → my open call requests, oldest first. */
    public function index(Request $request): JsonResponse
    {
        $calls = $this->mine($request)
            ->with('student.schoolClass', 'student.guardians')
            ->orderBy('created_at')
            ->get()
            ->map(function (Intervention $call) {
                $student = $call->student;
                // The parent who asked (from the answer in Telegram), otherwise the first parent
                $asked = ParentMessage::with('guardian')->where('student_id', $student->id)
                    ->where('reply', 'call_me')->latest('replied_at')->first();
                $parent = $asked?->guardian ?? $student->guardians->first();

                return [
                    'id' => $call->id,
                    'asked_at' => ($asked?->replied_at ?? $call->created_at)->toIso8601String(),
                    'student' => [
                        'id' => $student->id,
                        'name_latin' => $student->name_latin,
                        'name_km' => $student->name_km,
                        'class' => $student->schoolClass->name,
                    ],
                    'parent' => $parent ? [
                        'name' => $parent->name,
                        'name_km' => $parent->name_km,
                        'phone' => $parent->phone,
                        'relation' => $student->guardians->firstWhere('id', $parent->id)?->pivot->relation,
                    ] : null,
                ];
            });

        return response()->json(['calls' => $calls]);
    }

    /**
     * POST /api/v1/calls/{id}/done → the teacher called. The note is saved as the outcome, so the
     * call leaves the list; the follow-up stays open on the website to measure attendance later.
     */
    public function done(Request $request, int $id): JsonResponse
    {
        $call = $this->mine($request)->findOrFail($id);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $call->update([
            'outcome' => trim(__('Called the parent on :date (teacher app).', ['date' => today()->toDateString()]).' '.($data['note'] ?? '')),
        ]);

        return response()->json(['message' => __('Saved. The follow-up stays open on the website to measure attendance.')]);
    }

    /** Open "Phone call" follow-ups that belong to me and have not been called yet. */
    private function mine(Request $request)
    {
        return Intervention::where('created_by', $request->user()->id)
            ->where('action', 'phone_call')
            ->where('status', 'open')
            ->whereNull('outcome');
    }
}
