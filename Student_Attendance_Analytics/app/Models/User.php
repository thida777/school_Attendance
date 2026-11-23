<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'name_km', 'email', 'phone', 'telegram_chat_id', 'photo_path', 'password', 'role', 'locale', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // Only school staff log in. Students and parents do not have accounts.
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    /** Teacher: the classes where this teacher is homeroom teacher. */
    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'homeroom_teacher_id');
    }

    /** Teacher: which subject in which class. */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    /** Teacher: lessons this teacher recorded. */
    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'teacher_id');
    }

    /** Teacher: the weekly timetable. */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class, 'teacher_id');
    }

    /** Teacher: early warnings about their students and lessons. */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'teacher_id');
    }

    /**
     * Web address of the profile photo, or null when there is none (then show the initials).
     * asset() uses the address the visitor came in on, so it also works for the Android emulator (10.0.2.2).
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    /** Save a new photo (or none) and delete the old file. Used by the website and the Android app. */
    public function replacePhoto(?UploadedFile $file): void
    {
        $old = $this->photo_path;
        $this->update(['photo_path' => $file?->store('avatars', 'public')]);
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }

    /** Two letters for the round avatar when there is no photo, e.g. "Pen Sovann" → "PS". */
    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->join('');
    }
}
