<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Forgot password?": a 6-digit code by Telegram, then a new password (website and app). */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->teacher = User::where('role', User::ROLE_TEACHER)->orderBy('id')->firstOrFail();
        $this->teacher->update(['telegram_chat_id' => '829169853']);
    }

    /** The code the fake Telegram received. */
    private function sentCode(): string
    {
        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            if (preg_match('/code: (\d{6})/', (string) $request['text'], $m)) {
                $code = $m[1];
            }

            return $request['chat_id'] === '829169853';
        });

        return $code;
    }

    public function test_the_website_flow_changes_the_password_with_the_telegram_code(): void
    {
        $this->get('/login')->assertOk()->assertSee('Forgot password?');
        $this->get('/forgot-password')->assertOk()->assertSee('Send code by Telegram');

        $this->post('/forgot-password', ['login' => '012 345 678'])
            ->assertRedirect('/reset-password?login=012%20345%20678');
        $code = $this->sentCode();

        $this->post('/reset-password', [
            'login' => '012 345 678', 'code' => $code,
            'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPass2026', $this->teacher->refresh()->password));
        $this->post('/login', ['login' => '012345678', 'password' => 'NewPass2026'])->assertRedirect();
        $this->assertAuthenticatedAs($this->teacher);
    }

    public function test_a_wrong_or_old_code_does_not_work_and_tries_are_limited(): void
    {
        $this->post('/forgot-password', ['login' => $this->teacher->phone]);
        $code = $this->sentCode();
        $old = $this->teacher->password;
        $form = ['login' => $this->teacher->phone, 'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026'];

        $this->post('/reset-password', [...$form, 'code' => '000000'])->assertSessionHasErrors('code');

        // After 10 minutes the right code is too old
        $this->travel(11)->minutes();
        $this->post('/reset-password', [...$form, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertSame($old, $this->teacher->refresh()->password);

        // 5 wrong codes → wait
        $this->travelBack();
        for ($i = 0; $i < 4; $i++) {
            $this->post('/reset-password', [...$form, 'code' => '111111']);
        }
        $this->post('/reset-password', [...$form, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertStringStartsWith('Too many attempts.', session('errors')->first('code'));
        $this->assertSame($old, $this->teacher->refresh()->password); // even the right code is refused now
    }

    public function test_unknown_accounts_and_accounts_without_telegram_get_the_same_answer_and_no_message(): void
    {
        $this->post('/forgot-password', ['login' => '099999999'])->assertRedirect('/reset-password?login=099999999');

        $other = User::where('role', User::ROLE_TEACHER)->whereKeyNot($this->teacher->id)->firstOrFail();
        $this->post('/forgot-password', ['login' => $other->phone])->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_the_app_can_reset_the_password_and_old_app_logins_are_signed_out(): void
    {
        $oldToken = $this->teacher->createToken('old-phone')->plainTextToken;

        $this->postJson('/api/v1/password/forgot', ['login' => $this->teacher->phone])
            ->assertOk()->assertJsonStructure(['message']);
        $code = $this->sentCode();

        $this->postJson('/api/v1/password/reset', [
            'login' => $this->teacher->phone, 'code' => $code,
            'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026',
        ])->assertOk();

        $this->assertSame(0, $this->teacher->tokens()->count());
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$oldToken])->assertUnauthorized();

        $this->postJson('/api/v1/password/reset', [
            'login' => $this->teacher->phone, 'code' => $code,
            'password' => 'Another2026', 'password_confirmation' => 'Another2026',
        ], ['Accept-Language' => 'km'])->assertStatus(422)->assertJsonPath('errors.code.0', 'លេខកូដមិនត្រឹមត្រូវ ឬផុតកំណត់ហើយ។ សូមស្នើលេខកូដថ្មី។');
    }

    public function test_the_admin_can_link_a_teachers_telegram(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();

        $this->actingAs($admin)->put('/teachers/'.$this->teacher->id, [
            'name_km' => $this->teacher->name_km, 'name' => $this->teacher->name, 'phone' => $this->teacher->phone,
            'telegram_chat_id' => '555111222',
        ])->assertSessionHasNoErrors();
        $this->assertSame('555111222', $this->teacher->refresh()->telegram_chat_id);

        $this->actingAs($admin)->put('/teachers/'.$this->teacher->id, [
            'name_km' => $this->teacher->name_km, 'name' => $this->teacher->name, 'phone' => $this->teacher->phone,
            'telegram_chat_id' => 'not-a-number',
        ])->assertSessionHasErrors('telegram_chat_id');
    }
}
