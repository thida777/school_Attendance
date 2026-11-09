<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One attendance record (Attendance History screen). */
class RecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->session;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'absence_reason' => $this->absence_reason,
            'date' => $session->date->toDateString(),
            'period' => $session->period,
            'class' => ['id' => $session->schoolClass->id, 'name' => $session->schoolClass->name],
            'subject' => SubjectResource::make($session->subject),
            'student' => StudentResource::make($this->student),
            'can_change_directly' => (bool) $this->can_change_directly,
            'has_pending_request' => (bool) $this->has_pending_request,
        ];
    }
}
