<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The API used by the teacher Android app, called the same way the app calls it. */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private TimetableSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 08:30:00'); // a Monday
        $this->slot = TimetableSlot::where('weekday', 1)->orderBy('period')->firstOrFail();
    }

    /** Log in like the app does and return the headers for the next calls. */
    private function loginAs(User $teacher): array
    {
        $token = $this->postJson('/api/v1/login', ['login' => $teacher->phone, 'password' => 'password', 'device_name' => 'test phone'])
            ->assertOk()
            ->json('token');

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_a_teacher_logs_in_with_the_phone_and_gets_a_token(): void
    {
        $this->postJson('/api/v1/login', ['login' => '012 345 678', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'name_km', 'phone', 'role', 'locale', 'homeroom_class']])
            ->assertJsonPath('user.name', 'Pen Sovann');
    }

    public function test_wrong_password_and_admin_login_are_refused(): void
    {
        $this->postJson('/api/v1/login', ['login' => '012345678', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->postJson('/api/v1/login', ['login' => 'admin@school.edu.kh', 'password' => 'password'])->assertForbidden();
    }

    public function test_without_a_token_the_api_answers_401(): void
    {
        $this->getJson('/api/v1/today')->assertUnauthorized();
    }

    public function test_messages_can_be_in_khmer(): void
    {
        $this->postJson('/api/v1/login', ['login' => '012345678', 'password' => 'wrong'], ['Accept-Language' => 'km'])
            ->assertJsonPath('errors.login.0', __('The email, phone or password is not correct.', [], 'km'));
    }

    public function test_today_lists_the_teachers_lessons(): void
    {
        $headers = $this->loginAs($this->slot->teacher);
        $mine = TimetableSlot::where('weekday', 1)->where('teacher_id', $this->slot->teacher_id)->count();

        $this->getJson('/api/v1/today', $headers)
            ->assertOk()
            ->assertJsonPath('total', $mine)
            ->assertJsonPath('done', 0)
            ->assertJsonPath('lessons.0.status', 'not_marked')
            ->assertJsonStructure(['lessons' => [['slot_id', 'period', 'start_time', 'end_time', 'class' => ['id', 'name'], 'subject' => ['id', 'name_en', 'name_km'], 'students']]]);
    }

    public function test_classes_and_class_students(): void
    {
        $headers = $this->loginAs($this->slot->teacher);

        $this->getJson('/api/v1/classes', $headers)->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'is_homeroom', 'students', 'attendance_rate', 'subjects']]]);

        $this->getJson("/api/v1/classes/{$this->slot->school_class_id}/students", $headers)->assertOk()
            ->assertJsonStructure(['data' => [['id', 'student_code', 'name_latin', 'name_km', 'attendance_rate', 'risk', 'parent' => ['name', 'phone', 'channel']]]]);
    }

    public function test_the_full_attendance_flow_roster_submit_history_correct(): void
    {
        $headers = $this->loginAs($this->slot->teacher);

        // 1. Roster: everyone starts present
        $roster = $this->getJson("/api/v1/attendance/roster?slot_id={$this->slot->id}", $headers)->assertOk()
            ->assertJsonPath('already_taken', false)
            ->assertJsonPath('students.0.status', 'present');
        $absentId = $roster->json('students.0.id');

        // 2. Submit with one absent student
        $this->postJson('/api/v1/attendance', [
            'school_class_id' => $this->slot->school_class_id,
            'subject_id' => $this->slot->subject_id,
            'date' => '2026-11-09',
            'period' => $this->slot->period,
            'records' => [['student_id' => $absentId, 'status' => 'absent', 'absence_reason' => 'sick']],
        ], $headers)->assertCreated()->assertJsonPath('absent', 1)->assertJsonPath('absent_students.0.absence_reason', 'sick');

        // Today now shows it as done, and the roster shows the saved status
        $this->getJson('/api/v1/today', $headers)->assertJsonPath('done', 1)->assertJsonPath('lessons.0.status', 'done');
        $this->getJson("/api/v1/attendance/roster?slot_id={$this->slot->id}", $headers)->assertJsonPath('already_taken', true);

        // 3. History, and a change within 24 hours
        $record = AttendanceRecord::where('student_id', $absentId)->firstOrFail();
        $this->getJson('/api/v1/history?status=absent', $headers)->assertOk()
            ->assertJsonPath('data.0.id', $record->id)
            ->assertJsonPath('data.0.can_change_directly', true);
        $this->postJson("/api/v1/records/{$record->id}/correct", ['new_status' => 'present'], $headers)
            ->assertOk()->assertJsonPath('correction.status', 'applied');

        // 4. After 24 hours, a change becomes a request for the admin
        $this->travel(25)->hours();
        $this->postJson("/api/v1/records/{$record->id}/correct", ['new_status' => 'absent', 'reason' => 'Left early'], $headers)
            ->assertStatus(202)->assertJsonPath('correction.status', 'pending');
        $this->assertSame('present', $record->fresh()->status);
    }

    public function test_the_app_follows_the_same_rules_as_the_website(): void
    {
        $headers = $this->loginAs($this->slot->teacher);
        $lesson = ['school_class_id' => $this->slot->school_class_id, 'subject_id' => $this->slot->subject_id, 'period' => $this->slot->period];

        $this->postJson('/api/v1/attendance', $lesson + ['date' => '2026-11-08'], $headers)->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson('/api/v1/attendance', $lesson + ['date' => '2026-11-10'], $headers)->assertUnprocessable();

        // A class and subject this teacher does not teach
        $other = TimetableSlot::where('teacher_id', '!=', $this->slot->teacher_id)->get()
            ->first(fn ($s) => ! $this->slot->teacher->teachingAssignments()->where('school_class_id', $s->school_class_id)->where('subject_id', $s->subject_id)->exists());
        $this->getJson("/api/v1/attendance/roster?slot_id={$other->id}", $headers)->assertForbidden();
        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_change_password_and_logout(): void
    {
        $teacher = $this->slot->teacher;
        $headers = $this->loginAs($teacher);

        $this->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'], $headers)
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'], $headers)
            ->assertOk();

        $this->postJson('/api/v1/logout', [], $headers)->assertOk();
        $this->assertSame(0, $teacher->tokens()->count());

        $this->postJson('/api/v1/login', ['login' => $teacher->phone, 'password' => 'newpass123'])->assertOk();
    }

    public function test_a_teacher_cannot_see_students_of_a_class_they_do_not_teach(): void
    {
        // A teacher who is new at the school teaches no class yet
        $teacher = User::create(['name' => 'New Teacher', 'name_km' => 'គ្រូថ្មី', 'phone' => '099000222', 'password' => 'password', 'role' => 'teacher']);
        $headers = $this->loginAs($teacher);

        $this->getJson("/api/v1/classes/{$this->slot->school_class_id}/students", $headers)->assertForbidden();
        $this->getJson('/api/v1/classes', $headers)->assertOk()->assertJsonCount(0, 'data');
    }
}
