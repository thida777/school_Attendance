<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to one attendance record: the audit log and the correction requests.
 * Rows are never edited or deleted, except a pending request that the admin decides.
 */
#[Fillable(['attendance_record_id', 'changed_by', 'old_status', 'new_status', 'status', 'reason', 'decided_by', 'decided_at', 'decision_note'])]
class AttendanceCorrection extends Model
{
    public const APPLIED = 'applied';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    /** Who made the change, or who asked for it. */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** The admin who approved or rejected the request. */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
