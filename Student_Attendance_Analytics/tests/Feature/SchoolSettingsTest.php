<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ParentMessenger;
use App\Support\School;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** School settings: the admin changes the school name, and it appears on the website, in the API and in messages. */
class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    public function test_the_default_name_is_shown_until_the_admin_changes_it(): void
    {
        $this->get('/login')->assertOk()->assertSee('Demo High School');
        $this->getJson('/api/v1/school')->assertOk()
            ->assertJson(['name' => 'Demo High School', 'name_en' => 'Demo High School', 'name_km' => 'វិទ្យាល័យសាកល្បង']);
    }

    public function test_the_admin_changes_the_name_and_it_appears_everywhere(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->get('/settings/school')->assertOk()
            ->assertSee('School settings')->assertSee('value="Demo High School"', false);

        $this->actingAs($admin)->put('/settings/school', [
            'school_name_en' => '  Sunrise High School ',
            'school_name_km' => 'វិទ្យាល័យថ្ងៃរះ',
        ])->assertRedirect()->assertSessionHas('status', 'School settings saved.');

        // Website menu, login page, API (in the app's language) and the signature of Telegram messages
        $this->actingAs($admin)->get('/dashboard')->assertSee('Sunrise High School');
        $this->getJson('/api/v1/school', ['Accept-Language' => 'km'])
            ->assertJson(['name' => 'វិទ្យាល័យថ្ងៃរះ', 'name_en' => 'Sunrise High School']);
        $this->assertSame('— វិទ្យាល័យថ្ងៃរះ / Sunrise High School', School::signature());

        $student = \App\Models\Student::with(['guardians', 'schoolClass.homeroomTeacher'])->firstOrFail();
        $text = app(ParentMessenger::class)->summaryText($student, $student->guardians->first());
        $this->assertStringEndsWith('— វិទ្យាល័យថ្ងៃរះ / Sunrise High School', $text);

        auth()->logout();
        $this->withSession(['locale' => 'en'])->get('/login')->assertSee('Sunrise High School')->assertDontSee('Demo High School');
        $this->withSession(['locale' => 'km'])->get('/login')->assertSee('វិទ្យាល័យថ្ងៃរះ');
    }

    public function test_both_names_are_required_and_teachers_cannot_change_them(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->actingAs($admin)->put('/settings/school', ['school_name_en' => '', 'school_name_km' => str_repeat('ក', 101)])
            ->assertSessionHasErrors(['school_name_en', 'school_name_km']);
        $this->assertSame('Demo High School', School::name('en'));

        $teacher = User::where('role', User::ROLE_TEACHER)->firstOrFail();
        $this->actingAs($teacher)->get('/settings/school')->assertForbidden();
        $this->actingAs($teacher)->put('/settings/school', ['school_name_en' => 'X', 'school_name_km' => 'X'])->assertForbidden();
    }
}
