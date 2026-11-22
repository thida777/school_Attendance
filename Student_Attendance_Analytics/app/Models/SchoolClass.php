<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Services\RiskAnalyzer;
use Illuminate\Support\Collection;

#[Fillable(['academic_year_id', 'homeroom_teacher_id', 'name', 'grade', 'room'])]
class SchoolClass extends Model
{
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** Every attendance record of every lesson in this class. */
    public function attendanceRecords(): HasManyThrough
    {
        return $this->hasManyThrough(AttendanceRecord::class, AttendanceSession::class);
    }

    /**
     * How many active students of each class are at risk (level High or Critical).
     *
     * @param  iterable<int>  $classIds
     * @return Collection<int, int> class id => number of students at risk
     */
    public static function atRiskCounts(iterable $classIds): Collection
    {
        $students = Student::query()
            ->whereIn('school_class_id', collect($classIds))
            ->where('status', 'active')
            ->get(['id', 'school_class_id']);
        // Full rules (rate, days in a row, trend), the same as the At-Risk page
        $risks = app(RiskAnalyzer::class)->forStudents($students->pluck('id'));

        return $students
            ->filter(fn ($s) => $risks->get($s->id)?->isAtRisk())
            ->countBy('school_class_id');
    }

    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
