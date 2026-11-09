<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'created_by', 'action', 'notes', 'action_date', 'follow_up_date', 'rate_before', 'rate_after', 'status', 'closed_at'])]
class Intervention extends Model
{
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'follow_up_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
