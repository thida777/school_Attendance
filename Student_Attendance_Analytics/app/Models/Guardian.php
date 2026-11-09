<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
