<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin manages classes, subjects, teachers, parents and teaching assignments. */
class SchoolDataTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('role', 'admin')->first());
    }

    private function teacher(string $name): User
    {
        return User::where('name', $name)->firstOrFail();
    }

    // ---------- Pages open ----------

    public function test_every_school_data_page_opens_with_the_seeded_data(): void
    {
        $this->get('/classes')->assertOk()->assertSee('10A')->assertSee('Pen Sovann');
        $this->get('/subjects')->assertOk()->assertSee('Mathematics')->assertSee('គណិតវិទ្យា');
        $this->get('/teachers')->assertOk()->assertSee('Pen Sovann')->assertSee('Not logged in yet');
        $this->get('/parents')->assertOk()->assertSee('Telegram')->assertSee('SMS');
        $this->get('/assignments')->assertOk()->assertSee('Mathematics');
    }

    public function test_teachers_cannot_open_school_data_pages(): void
    {
        $this->actingAs($this->teacher('Pen Sovann'));

        foreach (['/classes', '/subjects', '/teachers', '/parents', '/assignments'] as $page) {
            $this->get($page)->assertForbidden();
        }
    }

    // ---------- Classes ----------

    public function test_admin_adds_and_edits_a_class(): void
    {
        $this->post('/classes', ['name' => '10c', 'grade' => 10, 'room' => '103', 'homeroom_teacher_id' => ''])
            ->assertSessionHas('status');
        $class = SchoolClass::where('name', '10C')->firstOrFail(); // saved in capitals
        $this->assertNull($class->homeroom_teacher_id);

        $this->put("/classes/{$class->id}", ['name' => '10C', 'grade' => 10, 'room' => '104', 'homeroom_teacher_id' => $this->teacher('Tep Mony')->id]);
        $this->assertSame('104', $class->fresh()->room);
    }

    public function test_two_classes_cannot_have_the_same_name_in_one_year(): void
    {
        $this->post('/classes', ['name' => '10A', 'grade' => 10])->assertSessionHasErrors('name');
    }

    public function test_a_class_with_students_is_kept(): void
    {
        $class = SchoolClass::where('name', '10A')->first();
        $this->delete("/classes/{$class->id}")->assertSessionHas('error');
        $this->assertModelExists($class);

        $empty = SchoolClass::create(['academic_year_id' => $class->academic_year_id, 'name' => '10Z', 'grade' => 10]);
        $this->delete("/classes/{$empty->id}")->assertSessionHas('status');
        $this->assertModelMissing($empty);
    }

    // ---------- Subjects ----------

    public function test_admin_adds_a_subject_and_codes_are_unique(): void
    {
        $this->post('/subjects', ['code' => 'geo', 'name_en' => 'Geography', 'name_km' => 'ភូមិវិទ្យា', 'hours_per_week' => 2])
            ->assertSessionHas('status');
        $this->assertDatabaseHas('subjects', ['code' => 'GEO']);

        $this->post('/subjects', ['code' => 'MAT', 'name_en' => 'Maths again', 'name_km' => 'x', 'hours_per_week' => 2])
            ->assertSessionHasErrors('code');
    }

    public function test_a_subject_that_is_taught_is_kept(): void
    {
        $maths = Subject::where('code', 'MAT')->first();
        $this->delete("/subjects/{$maths->id}")->assertSessionHas('error');
        $this->assertModelExists($maths);
    }

    // ---------- Teachers ----------

    public function test_admin_adds_a_teacher_who_can_then_log_in_with_the_phone(): void
    {
        $this->post('/teachers', [
            'name_km' => 'សុខ មាលា', 'name' => 'Sok Meala', 'phone' => '099 111 222',
            'email' => '', 'password' => 'secret123',
        ])->assertSessionHas('status');

        $teacher = User::where('phone', '099111222')->firstOrFail();
        $this->assertSame('teacher', $teacher->role);
        $this->assertNull($teacher->email);

        auth()->logout();
        $this->post('/login', ['login' => '099 111 222', 'password' => 'secret123'])->assertRedirect();
        $this->assertNotNull($teacher->fresh()->last_login_at); // the Teachers page now shows "Active"
    }

    public function test_editing_a_teacher_with_an_empty_password_keeps_the_old_one(): void
    {
        $teacher = $this->teacher('Pen Sovann');

        $this->put("/teachers/{$teacher->id}", [
            'name_km' => $teacher->name_km, 'name' => 'Pen Sovann', 'phone' => '012345678',
            'email' => 'sovann@school.edu.kh', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password', $teacher->fresh()->password));
    }

    public function test_a_new_teacher_needs_a_password_and_a_free_phone(): void
    {
        $this->post('/teachers', ['name_km' => 'x', 'name' => 'X', 'phone' => '012345678', 'password' => ''])
            ->assertSessionHasErrors(['phone', 'password']);
    }

    public function test_a_teacher_who_still_teaches_is_kept(): void
    {
        $teacher = $this->teacher('Pen Sovann');
        $this->delete("/teachers/{$teacher->id}")->assertSessionHas('error');
        $this->assertModelExists($teacher);
    }

    public function test_the_admin_account_cannot_be_changed_from_the_teachers_page(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->delete("/teachers/{$admin->id}")->assertNotFound();
    }

    // ---------- Parents ----------

    public function test_admin_adds_a_parent_and_links_a_second_child(): void
    {
        [$first, $second] = Student::orderBy('id')->take(2)->get();

        $this->post('/parents', ['name' => 'Chea Sokunthea', 'phone' => '017 333 444', 'relation' => 'mother', 'student_id' => $first->id])
            ->assertSessionHas('status');
        $parent = Guardian::where('phone', '017333444')->firstOrFail();

        $this->put("/parents/{$parent->id}", ['name' => 'Chea Sokunthea', 'phone' => '017333444', 'relation' => 'mother', 'student_id' => $second->id]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $parent->students()->pluck('students.id')->all());
    }

    public function test_every_student_keeps_at_least_one_parent(): void
    {
        $student = Student::first();
        $onlyParent = $student->guardians()->first();

        $this->delete("/parents/{$onlyParent->id}")->assertSessionHas('error');
        $this->delete("/parents/{$onlyParent->id}/students/{$student->id}")->assertSessionHas('error');
        $this->assertSame(1, $student->guardians()->count());

        // With a second parent, the first one can go
        $mother = Guardian::create(['name' => 'Second Parent', 'phone' => '010999888']);
        $mother->students()->attach($student->id, ['relation' => 'mother']);
        $this->delete("/parents/{$onlyParent->id}/students/{$student->id}")->assertSessionHas('status');
        $this->assertSame(1, $student->guardians()->count());
    }

    // ---------- Teaching assignments ----------

    public function test_a_class_has_one_teacher_per_subject(): void
    {
        $existing = TeachingAssignment::first();

        $this->post('/assignments', [
            'school_class_id' => $existing->school_class_id,
            'subject_id' => $existing->subject_id,
            'teacher_id' => $this->teacher('Tep Mony')->id,
        ])->assertSessionHasErrors('subject_id');
    }

    public function test_a_new_teacher_takes_over_the_lessons_in_the_timetable(): void
    {
        $slot = TimetableSlot::first();
        $assignment = TeachingAssignment::where('school_class_id', $slot->school_class_id)->where('subject_id', $slot->subject_id)->firstOrFail();
        $slots = TimetableSlot::where('school_class_id', $assignment->school_class_id)->where('subject_id', $assignment->subject_id);

        // A new teacher has no lessons yet, so is free at all of those times
        $free = User::create(['name' => 'New Teacher', 'name_km' => 'គ្រូថ្មី', 'phone' => '099000111', 'password' => 'password', 'role' => 'teacher']);

        $this->put("/assignments/{$assignment->id}", $assignment->only(['school_class_id', 'subject_id']) + ['teacher_id' => $free->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($free->id, $assignment->fresh()->teacher_id);
        $this->assertSame((clone $slots)->count(), (clone $slots)->where('teacher_id', $free->id)->count());
    }

    public function test_a_busy_teacher_cannot_take_over_lessons(): void
    {
        // Two lessons at the same time in different classes: their teachers cannot swap in
        $slot = TimetableSlot::first();
        $other = TimetableSlot::where('weekday', $slot->weekday)->where('period', $slot->period)
            ->where('teacher_id', '!=', $slot->teacher_id)->firstOrFail();
        $assignment = TeachingAssignment::where('school_class_id', $slot->school_class_id)->where('subject_id', $slot->subject_id)->firstOrFail();

        $this->put("/assignments/{$assignment->id}", $assignment->only(['school_class_id', 'subject_id']) + ['teacher_id' => $other->teacher_id])
            ->assertSessionHasErrors('teacher_id');
        $this->assertSame($slot->teacher_id, $slot->fresh()->teacher_id);
    }

    public function test_deleting_an_assignment_removes_its_lessons_but_keeps_attendance(): void
    {
        $slot = TimetableSlot::first();
        $assignment = TeachingAssignment::where('school_class_id', $slot->school_class_id)->where('subject_id', $slot->subject_id)->firstOrFail();
        $session = AttendanceSession::create([
            'school_class_id' => $slot->school_class_id, 'subject_id' => $slot->subject_id,
            'teacher_id' => $slot->teacher_id, 'date' => now()->toDateString(), 'period' => 1,
        ]);

        $this->delete("/assignments/{$assignment->id}")->assertSessionHas('status');

        $this->assertModelMissing($assignment);
        $this->assertFalse(TimetableSlot::where('school_class_id', $slot->school_class_id)->where('subject_id', $slot->subject_id)->exists());
        $this->assertModelExists($session);
    }
}
