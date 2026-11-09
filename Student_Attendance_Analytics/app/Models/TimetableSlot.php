<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['school_class_id', 'subject_id', 'teacher_id', 'weekday', 'period', 'start_time', 'end_time'])]
class TimetableSlot extends Model
{
    /** Lesson times used by the school. */
    public const PERIODS = [
        1 => ['07:00', '08:00'],
        2 => ['08:00', '09:00'],
        3 => ['09:15', '10:15'],
        4 => ['10:15', '11:15'],
        5 => ['13:00', '14:00'],
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
