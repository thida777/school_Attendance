<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin manages students on the Students page. */
class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->first();
    }

    private function validForm(array $changes = []): array
    {
        return array_merge([
            'name_km' => 'ចាន់ សុភា',
            'name_latin' => 'Chan Sophea',
            'student_code' => 's-10a-099',
            'gender' => 'female',
            'school_class_id' => SchoolClass::where('name', '10A')->value('id'),
            'date_of_birth' => '2010-05-01',
            'status' => 'active',
            'guardian_name' => 'Chan Dara',
            'guardian_phone' => '012 888 999',
            'guardian_relation' => 'father',
        ], $changes);
    }

    public function test_the_list_shows_students_and_can_be_searched(): void
    {
        $this->actingAs($this->admin());

        $this->get('/students')->assertOk()->assertSee('Sok Dara')->assertSee('S-10A-001');
        $this->get('/students?q=Rithy')->assertOk()->assertSee('data-name="Chea Rithy"', false)->assertDontSee('data-name="Sok Dara"', false);
        $this->get('/students?q='.urlencode('ដារា'))->assertOk()->assertSee('Sok Dara');
    }

    public function test_admin_adds_a_student_with_a_parent_contact(): void
    {
        $this->actingAs($this->admin())->post('/students', $this->validForm())
            ->assertRedirect('/students')->assertSessionHas('status');

        $student = Student::where('student_code', 'S-10A-099')->firstOrFail(); // code saved in capitals
        $this->assertSame('Chan Sophea', $student->name_latin);
        $this->assertSame('012888999', $student->guardians->first()->phone);    // spaces removed
        $this->assertSame('father', $student->guardians->first()->pivot->relation);
    }

    public function test_two_children_with_the_same_parent_phone_share_one_contact(): void
    {
        $this->actingAs($this->admin());
        $this->post('/students', $this->validForm());
        $this->post('/students', $this->validForm(['student_code' => 'S-10A-100', 'name_latin' => 'Chan Rotha', 'name_km' => 'ចាន់ រដ្ឋា']));

        $this->assertSame(1, Guardian::where('phone', '012888999')->count());
        $this->assertSame(2, Guardian::where('phone', '012888999')->first()->students()->count());
    }

    public function test_the_form_explains_what_is_wrong(): void
    {
        $this->actingAs($this->admin())->from('/students')
            ->post('/students', $this->validForm(['name_latin' => '', 'student_code' => 'S-10A-001', 'guardian_phone' => '12']))
            ->assertRedirect('/students')
            ->assertSessionHasErrors(['name_latin', 'student_code', 'guardian_phone']);

        $this->assertSame(42, Student::count());
    }

    public function test_admin_edits_a_student(): void
    {
        $student = Student::where('student_code', 'S-10A-001')->first();

        $this->actingAs($this->admin())
            ->put("/students/{$student->id}", $this->validForm(['student_code' => 'S-10A-001', 'status' => 'inactive']))
            ->assertSessionHas('status');

        $this->assertSame('inactive', $student->fresh()->status);
        $this->assertSame('Chan Sophea', $student->fresh()->name_latin);
    }

    public function test_a_student_without_attendance_can_be_deleted(): void
    {
        $student = Student::first();

        $this->actingAs($this->admin())->delete("/students/{$student->id}")->assertSessionHas('status');
        $this->assertModelMissing($student);
    }

    public function test_a_student_with_attendance_records_is_kept(): void
    {
        $student = Student::first();
        $session = AttendanceSession::create([
            'school_class_id' => $student->school_class_id,
            'subject_id' => Subject::first()->id,
            'teacher_id' => User::where('role', 'teacher')->first()->id,
            'date' => now()->toDateString(),
            'period' => 1,
        ]);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present']);

        $this->actingAs($this->admin())->delete("/students/{$student->id}")->assertSessionHas('error');
        $this->assertModelExists($student);
    }

    public function test_teachers_cannot_change_students(): void
    {
        $this->actingAs(User::where('role', 'teacher')->first());

        $this->get('/students')->assertForbidden();
        $this->post('/students', $this->validForm())->assertForbidden();
        $this->delete('/students/'.Student::first()->id)->assertForbidden();
    }
}
