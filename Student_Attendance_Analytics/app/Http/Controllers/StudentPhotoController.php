<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /students/{student}/photo?expires=…&signature=…
 * The photo of a student. Only a valid signed link works (see Student::photoUrl()),
 * so the website and the teacher app can show it without the file being public.
 */
class StudentPhotoController extends Controller
{
    public function __invoke(Student $student): Response
    {
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
