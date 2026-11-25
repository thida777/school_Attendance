<?php

namespace App\Services;

use App\Models\User;
use App\Support\School;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Forgot password?" for staff, by Telegram (website and app use the same rules):
 *
 *  1. The user types their phone number or email.
 *  2. If the account has a Telegram chat ID, the school bot sends a 6-digit code (valid 10 minutes).
 *     The answer on screen is always the same, so nobody can find out which accounts exist.
 *  3. Code + new password → the password changes, and the account is logged out everywhere (old app logins too).
 *  Limits: 1 code per minute per account, 5 wrong codes → wait 10 minutes.
 * Without Telegram, the admin resets the password on the Teachers page.
 */
class PasswordResetter
{
    public const CODE_MINUTES = 10;

    public const MAX_TRIES = 5;

    public function __construct(private Telegram $telegram) {}

    /** Row key in password_reset_tokens (its column is called "email", but teachers may have no email). */
    private function key(User $user): string
    {
        return 'user:'.$user->id;
    }

    /** The account for "012 345 678", "012345678" or an email; null when there is none. */
    public function find(string $login): ?User
    {
        $login = trim($login);

        return filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', Str::lower($login))->first()
            : User::where('phone', preg_replace('/\D/', '', $login))->whereNotNull('phone')->first();
    }

    /** Step 2. Sends a code when possible; says nothing about whether the account exists. */
    public function sendCode(string $login): void
    {
        $user = $this->find($login);
        if (! $user || ! $user->telegram_chat_id || ! $this->telegram->isLive()) {
            return;
        }

        $key = 'reset-code:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return; // one code per minute is enough
        }
        RateLimiter::hit($key, 60);

        $code = (string) random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->key($user)], // the table's "email" column holds a key: some teachers have no email
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        $this->telegram->send($user->telegram_chat_id,
            "លេខកូដប្តូរពាក្យសម្ងាត់របស់អ្នក៖ {$code}\nប្រើបានរយៈពេល ".self::CODE_MINUTES." នាទី។ កុំប្រាប់អ្នកណាទាំងអស់។\n\n"
            ."Your password reset code: {$code}\nIt works for ".self::CODE_MINUTES." minutes. Do not share it with anyone.\n\n"
            .School::signature());
    }

    /** Step 3. Changes the password, or throws a form error on "code". */
    public function reset(string $login, string $code, string $password): User
    {
        $tries = 'reset-try:'.Str::lower(trim($login));
        if (RateLimiter::tooManyAttempts($tries, self::MAX_TRIES)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($tries),
            ])]);
        }

        $user = $this->find($login);
        $row = $user ? DB::table('password_reset_tokens')->where('email', $this->key($user))->first() : null;
        $valid = $row
            && Carbon::parse($row->created_at)->gt(now()->subMinutes(self::CODE_MINUTES))
            && Hash::check(trim($code), $row->token);

        if (! $valid) {
            RateLimiter::hit($tries, self::CODE_MINUTES * 60);

            throw ValidationException::withMessages(['code' => __('The code is wrong or has expired. Ask for a new code.')]);
        }

        RateLimiter::clear($tries);
        DB::table('password_reset_tokens')->where('email', $this->key($user))->delete();
        $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();

        // Log out everywhere: other browsers and old app logins
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return $user;
    }
}
