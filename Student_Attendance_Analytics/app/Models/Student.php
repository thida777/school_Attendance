<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_class_id', 'student_code', 'name_km', 'name_latin', 'gender', 'date_of_birth', 'status'])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** Parents and guardians who receive messages about this student. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot('relation')
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function parentMessages(): HasMany
    {
        return $this->hasMany(ParentMessage::class);
    }

    /** The values that fill the Edit student form (Students page and Student details page). */
    public function formValues(): array
    {
        $guardian = $this->guardians->first();

        return [
            'name_km' => $this->name_km,
            'name_latin' => $this->name_latin,
            'student_code' => $this->student_code,
            'gender' => $this->gender,
            'school_class_id' => $this->school_class_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'guardian_name' => $guardian?->name,
            'guardian_phone' => $guardian?->phone,
            'guardian_relation' => $guardian?->pivot->relation ?? 'guardian',
        ];
    }
}
