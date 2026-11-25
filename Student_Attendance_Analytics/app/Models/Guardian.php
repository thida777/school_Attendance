<?php

namespace App\Models;

use App\Services\Telegram;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A parent or guardian. A contact only: receives messages, never logs in. */
#[Fillable(['name', 'name_km', 'phone', 'telegram_chat_id'])]
class Guardian extends Model
{
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot('relation')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ParentMessage::class);
    }

    /** Telegram when the parent joined the school's bot, otherwise SMS. */
    public function preferredChannel(): string
    {
        return $this->telegram_chat_id ? 'telegram' : 'sms';
    }

    /**
     * The parent's personal Telegram invite: https://t.me/<school bot>?start=<code>.
     * Opening it and pressing Start links this parent automatically (ParentReplies).
     * The code is made the first time and works once. Null when Telegram is not set up.
     */
    public function telegramInviteLink(): ?string
    {
        $bot = app(Telegram::class)->username();
        if (! $bot) {
            return null;
        }
        if (! $this->telegram_invite) {
            $this->forceFill(['telegram_invite' => Str::random(24)])->save();
        }

        return "https://t.me/{$bot}?start={$this->telegram_invite}";
    }
}
