<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login for the 2 roles (admin, teacher), each role only opens its own pages,
 * and the database rules of Plan v2.0 (parents are contacts, Present/Absent only).
 */
class LoginAndRolesTest extends TestCase
{
    use RefreshDatabase;

    /** Fill the test database with the demo school. */
    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private function userWithRole(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_admin_logs_in_with_email_and_lands_on_the_dashboard(): void
    {
        $this->post('/login', ['login' => 'admin@school.edu.kh', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($this->userWithRole('admin'));
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Hello, Sok Admin');
    }

    public function test_teacher_logs_in_with_phone_number_written_with_spaces(): void
    {
        $this->post('/login', ['login' => '012 345 678', 'password' => 'password'])
            ->assertRedirect('/');

        $this->get('/')->assertRedirect('/my-dashboard');
        $this->get('/my-dashboard')->assertOk()->assertSee('Pen Sovann')->assertSee('10A');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->from('/login')
            ->post('/login', ['login' => 'admin@school.edu.kh', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_login_is_blocked_after_five_wrong_tries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'admin@school.edu.kh', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'admin@school.edu.kh', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_admin_can_open_admin_and_shared_pages(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        foreach (['/dashboard', '/students', '/teachers', '/reports', '/attendance', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/my-dashboard')->assertForbidden();
    }

    public function test_teacher_cannot_open_admin_pages(): void
    {
        $this->actingAs($this->userWithRole('teacher'));

        foreach (['/my-dashboard', '/my-classes', '/attendance', '/attendance/history', '/profile'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/dashboard', '/students', '/teachers', '/reports', '/at-risk', '/interventions', '/attendance/corrections'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_only_admins_and_teachers_have_accounts(): void
    {
        $this->assertEqualsCanonicalizing(['admin', 'teacher'], User::distinct()->pluck('role')->all());

        // Parents are contacts only: the database refuses a parent login account
        $this->expectException(QueryException::class);
        User::create(['name' => 'A parent', 'phone' => '099999999', 'password' => 'password', 'role' => 'parent']);
    }

    public function test_every_student_has_a_parent_contact(): void
    {
        $this->assertSame(0, Student::doesntHave('guardians')->count());
    }

    public function test_attendance_can_only_be_present_or_absent(): void
    {
        $session = AttendanceSession::create([
            'school_class_id' => SchoolClass::first()->id,
            'subject_id' => Subject::first()->id,
            'teacher_id' => $this->userWithRole('teacher')->id,
            'date' => now()->toDateString(),
            'period' => 1,
        ]);

        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => Student::first()->id, 'status' => 'absent', 'absence_reason' => 'sick']);
        $this->assertSame(1, AttendanceRecord::count());

        $this->expectException(QueryException::class);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => Student::skip(1)->first()->id, 'status' => 'late']);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_language_switch_shows_khmer_and_is_saved_for_the_user(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $this->from('/dashboard')->post('/locale/km')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertSee('ផ្ទាំងគ្រប់គ្រង');
        $this->assertSame('km', $admin->fresh()->locale);
    }
}
