<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** My Classes (teacher) and Profile (everyone). */
class ProfileAndMyClassesTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('name', 'Tep Mony')->firstOrFail();
    }

    // ---------- My Classes ----------

    public function test_a_teacher_sees_their_classes_and_their_students(): void
    {
        $teacher = $this->teacher();
        $classIds = $teacher->teachingAssignments()->pluck('school_class_id')->merge($teacher->homeroomClasses()->pluck('id'))->unique();
        $mine = Student::whereIn('school_class_id', $classIds)->orderBy('student_code')->first();

        $this->actingAs($teacher)->get('/my-classes')->assertOk()
            ->assertSee('Chemistry')                 // "The classes you teach Chemistry, Biology in"
            ->assertSee($mine->student_code)
            ->assertSee('Only admins can add or edit student records.');
    }

    public function test_the_student_list_can_be_searched(): void
    {
        $teacher = $this->teacher();
        $student = Student::whereIn('school_class_id', $teacher->teachingAssignments()->pluck('school_class_id'))->first();

        $this->actingAs($teacher)->get('/my-classes?q='.urlencode($student->student_code))->assertOk()
            ->assertSee($student->name_latin);
    }

    public function test_the_admin_does_not_open_my_classes(): void
    {
        $this->actingAs(User::where('role', 'admin')->first())->get('/my-classes')->assertForbidden();
    }

    // ---------- Profile ----------

    public function test_everyone_opens_their_profile(): void
    {
        $this->actingAs($this->teacher())->get('/profile')->assertOk()->assertSee('Tep Mony')->assertSee('Teacher');
        $this->actingAs(User::where('role', 'admin')->first())->get('/profile')->assertOk()->assertSee('Administrator');
    }

    public function test_a_user_changes_their_details(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->put('/profile', [
            'name' => 'Tep Mony', 'name_km' => 'ទេព មុនី', 'email' => '', 'phone' => '099 777 888',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame('099777888', $teacher->fresh()->phone);
        $this->assertNull($teacher->fresh()->email);
    }

    public function test_the_admin_must_keep_an_email_and_phones_stay_unique(): void
    {
        $admin = User::where('role', 'admin')->first();

        $this->actingAs($admin)->put('/profile', [
            'name' => $admin->name, 'name_km' => $admin->name_km, 'email' => '', 'phone' => '012345678', // Pen Sovann's phone
        ])->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_a_user_changes_the_password_and_the_app_must_log_in_again(): void
    {
        $teacher = $this->teacher();
        $teacher->createToken('android');
        $this->actingAs($teacher);

        $this->put('/profile/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'other123'])
            ->assertSessionHasErrors('password');

        $this->put('/profile/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpass123', $teacher->fresh()->password));
        $this->assertSame(0, $teacher->tokens()->count());
    }

    // ---------- Profile photo ----------

    public function test_a_teacher_adds_a_photo_and_it_shows_instead_of_the_initials(): void
    {
        Storage::fake('public');
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/profile')->assertSee('TM')->assertSee('Add photo');

        $this->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])
            ->assertRedirect()->assertSessionHas('status', 'Profile photo saved.');

        $path = $teacher->fresh()->photo_path;
        Storage::disk('public')->assertExists($path);
        $this->get('/profile')->assertSee('storage/'.$path)->assertSee('Remove photo');
    }

    public function test_a_new_photo_replaces_the_old_file_and_remove_goes_back_to_initials(): void
    {
        Storage::fake('public');
        $teacher = $this->teacher();
        $this->actingAs($teacher)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $first = $teacher->fresh()->photo_path;

        $this->post('/profile/photo', ['photo' => UploadedFile::fake()->image('b.png', 200, 200)]);
        $second = $teacher->fresh()->photo_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->delete('/profile/photo')->assertSessionHas('status', 'Profile photo removed.');
        $this->assertNull($teacher->fresh()->photo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_only_small_pictures_are_accepted(): void
    {
        Storage::fake('public');
        $teacher = $this->teacher();

        $this->actingAs($teacher)->post('/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->post('/profile/photo', ['photo' => UploadedFile::fake()->image('big.jpg', 300, 300)->size(3000)])
            ->assertSessionHasErrors('photo');
        $this->assertNull($teacher->fresh()->photo_path);
    }

    public function test_the_android_app_can_upload_and_remove_the_photo(): void
    {
        Storage::fake('public');
        $teacher = $this->teacher();
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/me')->assertJsonPath('data.photo_url', null)->assertJsonPath('data.initials', 'TM');

        $url = $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('cam.jpg', 400, 400)])
            ->assertOk()->json('data.photo_url');
        $this->assertStringContainsString('/storage/avatars/', $url);

        $this->deleteJson('/api/v1/me/photo')->assertOk()->assertJsonPath('data.photo_url', null);
    }
}
