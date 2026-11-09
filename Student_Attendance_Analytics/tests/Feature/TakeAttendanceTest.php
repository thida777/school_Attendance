<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Take Attendance: the four steps and the rules of who may record what. */
class TakeAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // A fixed Monday, so the timetable has lessons "today"
        Carbon::setTestNow('2026-11-09 08:30:00');
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->first();
    }

    /** A lesson on today's timetable, with its teacher. */
    private function slot(): TimetableSlot
    {
        return TimetableSlot::where('weekday', 1)->orderBy('period')->firstOrFail();
    }

    private function lesson(TimetableSlot $slot, array $changes = []): array
    {
        return array_merge([
            'school_class_id' => $slot->school_class_id,
            'subject_id' => $slot->subject_id,
            'date' => today()->toDateString(),
            'period' => $slot->period,
        ], $changes);
    }

    public function test_a_teacher_sees_only_their_own_lessons_today(): void
    {
        $slot = $this->slot();
        $teacher = $slot->teacher;
        $mine = TimetableSlot::where('weekday', 1)->where('teacher_id', $teacher->id)->count();

        $response = $this->actingAs($teacher)->get('/attendance')->assertOk();

        $this->assertSame($mine, substr_count($response->getContent(), 'attendance/mark?slot='));
        $response->assertSee(__(':done of :total classes marked', ['done' => 0, 'total' => $mine]));
    }

    public function test_the_admin_sees_every_lesson_of_the_day(): void
    {
        $all = TimetableSlot::where('weekday', 1)->count();

        $response = $this->actingAs($this->admin())->get('/attendance')->assertOk();
        $this->assertSame($all, substr_count($response->getContent(), 'attendance/mark?slot='));
    }

    public function test_the_marking_page_lists_the_active_students(): void
    {
        $slot = $this->slot();
        $student = Student::where('school_class_id', $slot->school_class_id)->first();

        $this->actingAs($slot->teacher)->get("/attendance/mark?slot={$slot->id}")
            ->assertOk()
            ->assertSee($student->name_latin)
            ->assertSee("statuses[{$student->id}]", false);
    }

    public function test_a_teacher_submits_attendance_with_an_absent_student(): void
    {
        $slot = $this->slot();
        $students = Student::where('school_class_id', $slot->school_class_id)->orderBy('id')->get();
        $absent = $students->first();

        $response = $this->actingAs($slot->teacher)->post('/attendance', $this->lesson($slot, [
            'statuses' => [$absent->id => 'absent'],             // the others are not sent: present
            'reasons' => [$absent->id => 'sick'],
        ]));

        $session = AttendanceSession::firstOrFail();
        $response->assertRedirect(route('attendance.done', $session));
        $this->assertSame($slot->teacher_id, $session->teacher_id);
        $this->assertSame($students->count(), $session->records()->count());
        $this->assertSame(1, $session->records()->where('status', 'absent')->count());
        $this->assertSame('sick', $session->records()->where('student_id', $absent->id)->value('absence_reason'));

        $this->get(route('attendance.done', $session))->assertOk()->assertSee('Attendance submitted')->assertSee($absent->name_latin);
    }

    public function test_a_present_student_has_no_absence_reason(): void
    {
        $slot = $this->slot();
        $student = Student::where('school_class_id', $slot->school_class_id)->first();

        $this->actingAs($slot->teacher)->post('/attendance', $this->lesson($slot, [
            'statuses' => [$student->id => 'present'],
            'reasons' => [$student->id => 'sick'],
        ]));

        $this->assertNull(AttendanceRecord::where('student_id', $student->id)->value('absence_reason'));
    }

    public function test_within_24_hours_a_teacher_changes_it_without_a_reason_and_it_is_logged(): void
    {
        $slot = $this->slot();
        $student = Student::where('school_class_id', $slot->school_class_id)->first();
        $this->actingAs($slot->teacher);

        $this->post('/attendance', $this->lesson($slot, ['statuses' => [$student->id => 'absent']]));
        $this->post('/attendance', $this->lesson($slot, ['statuses' => [$student->id => 'present']]))->assertSessionHasNoErrors();

        $this->assertSame(1, AttendanceSession::count());
        $this->assertSame('present', AttendanceRecord::where('student_id', $student->id)->value('status'));

        $log = AttendanceCorrection::sole(); // only the student who changed is logged
        $this->assertSame(['absent', 'present', 'applied', null], [$log->old_status, $log->new_status, $log->status, $log->reason]);
    }

    public function test_after_24_hours_a_teacher_can_no_longer_change_the_lesson_directly(): void
    {
        $slot = $this->slot();
        $monday = $this->lesson($slot);
        $this->actingAs($slot->teacher)->post('/attendance', $monday);

        $this->travel(25)->hours(); // now Tuesday

        $this->post('/attendance', $monday)->assertSessionHasErrors('date');
    }

    public function test_a_teacher_cannot_record_another_teachers_class(): void
    {
        $slot = $this->slot();
        $other = TeachingAssignment::where('teacher_id', '!=', $slot->teacher_id)
            ->whereNotIn('school_class_id', TeachingAssignment::where('teacher_id', $slot->teacher_id)->select('school_class_id'))
            ->first()
            ?? TeachingAssignment::where('teacher_id', '!=', $slot->teacher_id)->first();
        $mine = TeachingAssignment::where('teacher_id', $slot->teacher_id)
            ->where('school_class_id', $other->school_class_id)->where('subject_id', $other->subject_id)->exists();
        $this->assertFalse($mine);

        $this->actingAs($slot->teacher)
            ->post('/attendance', ['school_class_id' => $other->school_class_id, 'subject_id' => $other->subject_id, 'date' => today()->toDateString(), 'period' => 5])
            ->assertForbidden();
        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_a_teacher_cannot_record_a_past_day(): void
    {
        $slot = $this->slot();

        $this->actingAs($slot->teacher)
            ->post('/attendance', $this->lesson($slot, ['date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('date');
        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_nobody_can_record_a_future_day(): void
    {
        $this->actingAs($this->admin())
            ->post('/attendance', $this->lesson($this->slot(), ['date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    public function test_one_period_of_a_class_holds_one_subject(): void
    {
        $slot = $this->slot();
        $this->actingAs($this->admin())->post('/attendance', $this->lesson($slot));

        $otherSubject = TeachingAssignment::where('school_class_id', $slot->school_class_id)->where('subject_id', '!=', $slot->subject_id)->value('subject_id');
        $this->post('/attendance', $this->lesson($slot, ['subject_id' => $otherSubject]))->assertSessionHasErrors('period');
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_the_admin_corrects_a_past_day_with_a_reason_and_it_is_logged(): void
    {
        $slot = $this->slot();
        $student = Student::where('school_class_id', $slot->school_class_id)->first();
        $lastWeek = $this->lesson($slot, ['date' => today()->subWeek()->toDateString()]);
        $this->actingAs($this->admin());

        // First recording of a past day: nothing to correct yet
        $this->post('/attendance', $lastWeek + ['statuses' => [$student->id => 'absent']])->assertSessionHasNoErrors();
        $this->assertSame(0, AttendanceCorrection::count());

        // More than 24 hours later, changing it needs a reason...
        $this->travel(25)->hours();
        $this->post('/attendance', $lastWeek + ['statuses' => [$student->id => 'present']])->assertSessionHasErrors('correction_reason');
        $this->assertSame('absent', AttendanceRecord::where('student_id', $student->id)->value('status'));

        // ...and is written to the corrections log
        $this->post('/attendance', $lastWeek + ['statuses' => [$student->id => 'present'], 'correction_reason' => 'Medical letter'])->assertSessionHasNoErrors();
        $correction = AttendanceCorrection::sole();
        $this->assertSame(['absent', 'present', 'Medical letter'], [$correction->old_status, $correction->new_status, $correction->reason]);
        $this->assertSame($this->admin()->id, $correction->changed_by);
    }

    public function test_inactive_students_are_not_listed(): void
    {
        $slot = $this->slot();
        $left = Student::where('school_class_id', $slot->school_class_id)->first();
        $left->update(['status' => 'inactive']);

        $this->actingAs($slot->teacher)->post('/attendance', $this->lesson($slot));

        $this->assertFalse(AttendanceRecord::where('student_id', $left->id)->exists());
    }
}
