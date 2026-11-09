<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One lesson of the timetable for a day, with whether attendance is already taken. */
class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->session;

        return [
            'slot_id' => $this->id,
            'period' => $this->period,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'class' => ['id' => $this->schoolClass->id, 'name' => $this->schoolClass->name],
            'subject' => SubjectResource::make($this->subject),
            'students' => $this->size,
            'status' => $session ? 'done' : 'not_marked',
            'session' => $session ? [
                'id' => $session->id,
                'present' => $session->present_count,
                'absent' => $session->records_count - $session->present_count,
            ] : null,
        ];
    }
}
