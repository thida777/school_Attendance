<?php

namespace App\Http\Resources;

use App\Support\Risk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student. Optional parts appear only when the controller loaded them:
 *  rate / risk (records_total, records_present counts), parent contact (guardians),
 *  status / absence_reason in one lesson (the "mark" value set by the roster).
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rate = isset($this->records_total) ? Risk::rate((int) $this->records_present, (int) $this->records_total) : null;
        $guardian = $this->relationLoaded('guardians') ? $this->guardians->first() : null;

        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'name_latin' => $this->name_latin,
            'name_km' => $this->name_km,
            'gender' => $this->gender,
            'active' => $this->status === 'active',
            $this->mergeWhen(isset($this->records_total), fn () => [
                'attendance_rate' => $rate,
                'risk' => Risk::level($rate),
            ]),
            $this->mergeWhen($this->relationLoaded('guardians'), fn () => [
                'parent' => $guardian ? [
                    'name' => $guardian->name,
                    'phone' => $guardian->phone,
                    'relation' => $guardian->pivot->relation,
                    'channel' => $guardian->preferredChannel(),
                ] : null,
            ]),
            $this->mergeWhen(isset($this->mark), fn () => [
                'status' => $this->mark['status'],
                'absence_reason' => $this->mark['absence_reason'],
            ]),
        ];
    }
}
