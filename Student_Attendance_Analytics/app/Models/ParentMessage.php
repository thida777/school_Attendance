<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['guardian_id', 'student_id', 'sent_by', 'type', 'channel', 'body', 'status', 'simulated', 'error', 'reply', 'replied_at', 'sent_at'])]
class ParentMessage extends Model
{
    /**
     * The buttons under an absence alert on Telegram: reply code => [emoji, Khmer, English].
     * sick / family_matter are also absence reasons (attendance_records.absence_reason).
     */
    public const REPLIES = [
        'sick' => ['🤒', 'ឈឺ', 'Sick'],
        'family_matter' => ['🏠', 'កិច្ចការគ្រួសារ', 'Family matter'],
        'call_me' => ['📞', 'សូមគ្រូទូរស័ព្ទមក', 'Please call me'],
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'replied_at' => 'datetime',
            'simulated' => 'boolean',
        ];
    }

    /** The parent's answer for the website, e.g. "🤒 Sick" (in the current language). */
    public function replyLabel(): ?string
    {
        if (! $this->reply || ! isset(self::REPLIES[$this->reply])) {
            return null;
        }
        [$emoji, , $english] = self::REPLIES[$this->reply];

        return $emoji.' '.__($english);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
