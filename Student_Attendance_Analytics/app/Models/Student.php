<?php

namespace App\Models;

use App\Support\StudentPhoto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

#[Fillable(['school_class_id', 'student_code', 'name_km', 'name_latin', 'gender', 'date_of_birth', 'status'])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** Parents and guardians who receive messages about this student. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot('relation')
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
    }

    public function parentMessages(): HasMany
    {
        return $this->hasMany(ParentMessage::class);
    }

    // ---------------------------------------------------------------- photo

    /**
     * Link to the photo, or null (then the initials are shown).
     * Photos of children are private: the file is not public, and this link is signed and works
     * until the end of tomorrow. Only pages for logged-in staff and the teacher app receive it.
     * The link stays the same during one day, so browsers and the app can keep the picture.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return URL::temporarySignedRoute('students.photo', now()->addDay()->endOfDay(), [
            'student' => $this->id,
            'v' => substr(md5($this->photo_path), 0, 8), // a new photo gets a new link
        ]);
    }

    /** Save a new photo (shrunk to 400 × 400), or none, and delete the old file. */
    public function replacePhoto(?UploadedFile $file): void
    {
        $old = $this->photo_path;
        $new = $file ? StudentPhoto::store($file, $this->id) : null;
        $this->forceFill(['photo_path' => $new])->save();
        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    /** Two letters for the round picture when there is no photo, e.g. "Sok Dara" → "SD". */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name_latin)))
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->join('');
    }

    /** The same colour for a student every time (8 colours, from the student code). */
    public function colour(): int
    {
        return crc32((string) $this->student_code) % 8;
    }

    /** The values that fill the Edit student form (Students page and Student details page). */
    public function formValues(): array
    {
        $guardian = $this->guardians->first();

        return [
            'name_km' => $this->name_km,
            'name_latin' => $this->name_latin,
            'student_code' => $this->student_code,
            'gender' => $this->gender,
            'school_class_id' => $this->school_class_id,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'guardian_name' => $guardian?->name,
            'guardian_phone' => $guardian?->phone,
            'guardian_relation' => $guardian?->pivot->relation ?? 'guardian',
            // Not form fields: the photo preview, the initials, and whether the parent gets Telegram
            '_photo' => $this->photoUrl(),
            '_initials' => $this->initials(),
            '_telegram' => $guardian ? (bool) $guardian->telegram_chat_id : null,
        ];
    }
}
