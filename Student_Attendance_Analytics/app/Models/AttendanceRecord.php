<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attendance_session_id', 'student_id', 'status', 'absence_reason', 'note'])]
class AttendanceRecord extends Model
{
    // Only two statuses. An absent student may have an optional reason.
    public const STATUSES = ['present', 'absent'];
    public const ABSENCE_REASONS = ['not_given', 'sick', 'family_matter', 'school_activity', 'other'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Every change made to this record after it was first saved. */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
