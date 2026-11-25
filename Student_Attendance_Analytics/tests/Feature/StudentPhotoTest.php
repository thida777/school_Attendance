<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Student photos: upload in the student form, private file, signed link, shown in lists and in the app API. */
class StudentPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private User $admin;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->student = Student::with('guardians')->orderBy('id')->firstOrFail();
    }

    /** The student's current form values, with changes. */
    private function form(array $changes = []): array
    {
        $values = collect($this->student->fresh('guardians')->formValues())->reject(fn ($v, $k) => str_starts_with($k, '_'))->all();

        return [...$values, ...$changes];
    }

    public function test_a_photo_is_saved_small_square_and_private(): void
    {
        $this->actingAs($this->admin)
            ->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->image('dara.jpg', 900, 1200)]))
            ->assertSessionHas('status', 'Student saved.');

        $path = $this->student->fresh()->photo_path;
        $this->assertStringStartsWith('student-photos/', $path);
        Storage::disk('local')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([400, 400], [$w, $h]);
        $this->assertFalse(Storage::disk('public')->exists($path)); // never a public file
    }

    public function test_only_a_signed_link_shows_the_photo(): void
    {
        $this->student->replacePhoto(UploadedFile::fake()->image('dara.png', 300, 300));
        $url = $this->student->fresh()->photoUrl();

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');               // no login needed: the link is the key
        $this->get(route('students.photo', $this->student))->assertForbidden();                 // no signature
        $this->get(preg_replace('/signature=\w+/', 'signature=abc', $url))->assertForbidden();  // wrong signature
        $this->get(str_replace("students/{$this->student->id}/", 'students/2/', $url))->assertForbidden(); // another student
    }

    public function test_the_photo_is_shown_on_the_pages_and_in_the_app_api(): void
    {
        $this->student->replacePhoto(UploadedFile::fake()->image('dara.jpg', 300, 300));
        $photo = e($this->student->fresh()->photoUrl());

        $this->actingAs($this->admin)->get('/students')->assertSee($photo, false);
        $this->actingAs($this->admin)->get("/students/{$this->student->id}")->assertSee($photo, false);

        // Without a photo: the initials
        $other = Student::orderBy('id')->skip(1)->firstOrFail();
        $this->actingAs($this->admin)->get('/students')->assertSee('>'.$other->initials().'<', false);

        // The teacher app gets the signed link (or null) and the initials
        $teacher = $this->student->schoolClass->teachingAssignments()->firstOrFail()->teacher;
        $this->app['auth']->forgetGuards(); // the admin login above must not count for the app
        Sanctum::actingAs($teacher);
        $response = $this->getJson("/api/v1/classes/{$this->student->school_class_id}/students")->assertOk();
        $json = $response->json('data') ?? $response->json('students');
        $mine = collect($json)->firstWhere('id', $this->student->id);
        $this->assertStringContainsString('/students/'.$this->student->id.'/photo', $mine['photo_url']);
        $this->assertSame($other->initials(), collect($json)->firstWhere('id', $other->id)['initials']);
        $this->assertNull(collect($json)->firstWhere('id', $other->id)['photo_url']);
    }

    public function test_remove_photo_deletes_the_file_and_a_new_photo_replaces_the_old_one(): void
    {
        $this->actingAs($this->admin)->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)]));
        $first = $this->student->fresh()->photo_path;

        $this->actingAs($this->admin)->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->image('b.jpg', 300, 300)]));
        $second = $this->student->fresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        $this->actingAs($this->admin)->put("/students/{$this->student->id}", $this->form(['remove_photo' => '1']));
        $this->assertNull($this->student->fresh()->photo_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_only_pictures_up_to_5_mb_and_only_the_admin(): void
    {
        $this->actingAs($this->admin)->withSession(['locale' => 'en'])
            ->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('photo');
        $this->actingAs($this->admin)
            ->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->image('big.jpg', 300, 300)->size(6000)]))
            ->assertSessionHasErrors('photo');
        $this->assertNull($this->student->fresh()->photo_path);

        $teacher = User::where('role', User::ROLE_TEACHER)->firstOrFail();
        $this->actingAs($teacher)
            ->put("/students/{$this->student->id}", $this->form(['photo' => UploadedFile::fake()->image('a.jpg', 300, 300)]))
            ->assertForbidden();
    }
}
