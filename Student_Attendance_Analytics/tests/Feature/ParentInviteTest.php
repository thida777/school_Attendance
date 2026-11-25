<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Parents page: Telegram status and filters, and the one-time Telegram invite (link / QR code) that links a parent by itself. */
class ParentInviteTest extends TestCase
{
    use RefreshDatabase;

    protected string $seeder = SchoolSeeder::class;

    protected bool $seed = true;

    private const SECRET = 'test-webhook-secret-1234567890';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.token' => 'test-token',
            'services.telegram.username' => 'school_test_bot',
            'services.telegram.webhook_secret' => self::SECRET,
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        Guardian::query()->update(['telegram_chat_id' => null]);
        Guardian::orderBy('name')->first()->update(['telegram_chat_id' => '111']);
    }

    private function start(string $text, string $chat = '424242')
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', self::SECRET)->postJson('/api/telegram/webhook', [
            'update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => (int) $chat], 'text' => $text],
        ]);
    }

    public function test_the_page_counts_parents_on_telegram_and_filters_them(): void
    {
        $linked = Guardian::orderBy('name')->first();
        $other = Guardian::orderBy('name')->skip(1)->first();

        $this->actingAs($this->admin)->withSession(['locale' => 'en'])->get('/parents')
            ->assertOk()->assertSee('On Telegram')->assertSee('Not on Telegram yet')->assertSee('Telegram ✓');

        $this->actingAs($this->admin)->get('/parents?telegram=yes')->assertSee($linked->name)->assertDontSee($other->name);
        $this->actingAs($this->admin)->get('/parents?telegram=no')->assertDontSee($linked->name)->assertSee($other->name);

        $class = SchoolClass::where('name', '12B')->firstOrFail();
        $inClass = Guardian::whereHas('students', fn ($q) => $q->where('school_class_id', $class->id))->orderBy('name')->first();
        $notInClass = Guardian::whereDoesntHave('students', fn ($q) => $q->where('school_class_id', $class->id))->orderBy('name')->first();
        $this->actingAs($this->admin)->get("/parents?class={$class->id}")->assertSee($inClass->name)->assertDontSee($notInClass->name);
    }

    public function test_an_invite_link_links_the_parent_once(): void
    {
        $parent = Guardian::orderBy('name')->skip(1)->first();
        $link = $parent->telegramInviteLink();
        $this->assertMatchesRegularExpression('~^https://t\.me/school_test_bot\?start=[A-Za-z0-9]{24}$~', $link);
        $this->assertSame($link, $parent->fresh()->telegramInviteLink()); // the same link until it is used
        $code = substr($link, -24);

        $this->start("/start {$code}")->assertOk();

        $parent->refresh();
        $this->assertSame('424242', $parent->telegram_chat_id);
        $this->assertNull($parent->telegram_invite);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/sendMessage') && $r['chat_id'] === '424242'
            && str_contains((string) $r['text'], 'you are now connected to the school'));

        // The same code a second time (e.g. someone else scans the card): refused, nothing changes
        $this->start("/start {$code}", '999')->assertOk();
        $this->assertSame('424242', $parent->fresh()->telegram_chat_id);
        Http::assertSent(fn (Request $r) => $r['chat_id'] === '999' && str_contains((string) $r['text'], 'can no longer be used'));
    }

    public function test_the_printable_invites_show_only_parents_not_on_telegram(): void
    {
        $linked = Guardian::orderBy('name')->first();
        $other = Guardian::orderBy('name')->skip(1)->first();

        $this->actingAs($this->admin)->withSession(['locale' => 'en'])->get('/parents/invites')->assertOk()
            ->assertSee('ភ្ជាប់ជាមួយសាលាតាម Telegram')
            ->assertSee($other->name)->assertDontSee($linked->name)
            ->assertSee('https://t.me/school_test_bot?start=', false)
            ->assertSee('data-qr=', false);

        $this->actingAs($this->admin)->get("/parents/invites?parent={$other->id}")->assertOk()->assertSee($other->name);

        $teacher = User::where('role', User::ROLE_TEACHER)->firstOrFail();
        $this->actingAs($teacher)->get('/parents/invites')->assertForbidden();
    }

    public function test_removing_a_child_asks_with_the_right_words(): void
    {
        $this->actingAs($this->admin)->withSession(['locale' => 'en'])->get('/parents')
            ->assertSee('Remove this child from the parent?')
            ->assertSee('The child and the parent both stay in the system.');
    }
}
