<?php

namespace App\Models;

use App\Services\InterventionTracker;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A follow-up for a student at risk: what the school did, when to check again,
 * and the attendance rate before and after (to see if it helped).
 */
#[Fillable(['student_id', 'created_by', 'action', 'notes', 'action_date', 'follow_up_date', 'rate_before', 'rate_after', 'outcome', 'status', 'closed_at'])]
class Intervention extends Model
{
    /** The actions a school can take. Stored as the key; shown with __(). */
    public const ACTIONS = [
        'phone_call' => 'Phone call',
        'telegram' => 'Parent message (Telegram)',
        'sms' => 'Parent message (SMS)',
        'meeting' => 'Meeting with parent',
        'home_visit' => 'Home visit',
        'talk' => 'Talk with student',
        'counselling' => 'Counselling',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'follow_up_date' => 'date',
            'closed_at' => 'datetime',
            'rate_before' => 'float',
            'rate_after' => 'float',
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

    public function actionLabel(): string
    {
        return __(self::ACTIONS[$this->action] ?? $this->action);
    }

    /** open, due (the follow-up day has come) or closed. */
    public function state(): string
    {
        if ($this->status === 'closed') {
            return 'closed';
        }

        return $this->follow_up_date && $this->follow_up_date->lte(today()) ? 'due' : 'open';
    }

    /** Points between before and after (closed follow-ups only). */
    public function change(): ?float
    {
        return $this->rate_before === null || $this->rate_after === null ? null : round($this->rate_after - $this->rate_before, 1);
    }

    /** improved, declined or none (null while open). */
    public function result(): ?string
    {
        return $this->status === 'closed' ? InterventionTracker::result($this->rate_before, $this->rate_after) : null;
    }

    public function scopeDue(Builder $query): void
    {
        $query->where('status', 'open')->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', today());
    }

    public function scopeWaiting(Builder $query): void
    {
        $query->where('status', 'open')->where(fn ($q) => $q->whereNull('follow_up_date')->orWhereDate('follow_up_date', '>', today()));
    }
}
