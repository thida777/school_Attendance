<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubjectResource;
use App\Models\SchoolClass;
use App\Services\AttendanceRecorder;
use App\Support\Risk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Dashboard, My Classes and Class details screens of the app. */
class TeachingController extends Controller
{
    public function __construct(private AttendanceRecorder $recorder) {}

    /** Today's lessons of this teacher. */
    public function today(Request $request): JsonResponse
    {
        $lessons = $this->recorder->schedule($request->user(), today());

        return response()->json([
            'date' => today()->toDateString(),
            'done' => $lessons->whereNotNull('session')->count(),
            'total' => $lessons->count(),
            'lessons' => LessonResource::collection($lessons),
        ]);
    }

    /** The classes this teacher teaches, with the subjects taught there. */
    public function classes(Request $request): JsonResponse
    {
        $user = $request->user();
        $assignments = $user->teachingAssignments()->with('subject')->get()->groupBy('school_class_id');
        $homeroomIds = $user->homeroomClasses()->pluck('id');

        $classes = SchoolClass::query()
            ->whereIn('id', $assignments->keys()->merge($homeroomIds))
            ->withCount([
                'students' => fn ($q) => $q->where('status', 'active'),
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('attendance_records.status', 'present'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $classes->map(function ($class) use ($assignments, $homeroomIds) {
            $rate = Risk::rate($class->records_present, $class->records_total);

            return [
                'id' => $class->id,
                'name' => $class->name,
                'grade' => $class->grade,
                'room' => $class->room,
                'is_homeroom' => $homeroomIds->contains($class->id),
                'students' => $class->students_count,
                'attendance_rate' => $rate,
                'subjects' => SubjectResource::collection(($assignments[$class->id] ?? collect())->pluck('subject')),
            ];
        })]);
    }

    /** Students of one class with their attendance rate, risk and parent contact. */
    public function students(Request $request, SchoolClass $class): AnonymousResourceCollection
    {
        $user = $request->user();
        $allowed = $user->teachingAssignments()->where('school_class_id', $class->id)->exists()
            || $class->homeroom_teacher_id === $user->id;
        abort_unless($allowed, 403, __('You do not teach this class.'));

        $students = $class->students()
            ->where('status', 'active')
            ->with('guardians')
            ->withCount([
                'attendanceRecords as records_total',
                'attendanceRecords as records_present' => fn ($q) => $q->where('status', 'present'),
            ])
            ->orderBy('name_latin')
            ->get();

        return StudentResource::collection($students);
    }
}
