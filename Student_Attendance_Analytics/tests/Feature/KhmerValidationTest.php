<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Form errors follow the chosen language: Khmer on the website (session) and in the app (Accept-Language). */
class KhmerValidationTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    public function test_website_form_errors_are_in_khmer(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->withSession(['locale' => 'km'])
            ->post('/interventions', ['action_date' => '2026-11-30'])
            ->assertSessionHasErrors([
                'student_id' => 'សូមបញ្ចូល សិស្ស។',
                'action' => 'សូមបញ្ចូល សកម្មភាព។',
                'action_date' => 'ថ្ងៃធ្វើសកម្មភាព មិនអាចក្រោយ ថ្ងៃនេះ បានទេ។',
            ]);
    }

    public function test_english_stays_english(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->withSession(['locale' => 'en'])
            ->post('/interventions', [])
            ->assertSessionHasErrors(['student_id' => 'The student field is required.']);
    }

    public function test_app_errors_are_in_khmer(): void
    {
        $this->postJson('/api/v1/login', [], ['Accept-Language' => 'km'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'សូមបញ្ចូល លេខទូរស័ព្ទ ឬអ៊ីមែល។')
            ->assertJsonPath('errors.password.0', 'សូមបញ្ចូល ពាក្យសម្ងាត់។');
    }
}
