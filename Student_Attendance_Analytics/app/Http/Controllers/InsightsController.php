<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\Insights;
use App\Services\SchoolStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Insights (admin): findings in plain words, a drill-down School → Grade → Class,
 * and the day × period heat map. Everything is for the last 4 weeks compared with the 4 weeks before.
 */
class InsightsController extends Controller
{
    public function index(Request $request, Insights $insights, SchoolStats $stats): View
    {
        $insights->build();
        $grade = $request->integer('grade') ?: null;
        $classId = $request->integer('class') ?: null;
        $class = $classId ? $insights->classes->get($classId) : null;
        if ($class) {
            $grade = $class->grade;
        }

        return view('analytics.insights', [
            'insights' => $insights,
            'grade' => $grade,
            'class' => $class,
            'detail' => $class ? $this->classDetail($insights, $class->id) : null,
            'heatmap' => $stats->heatmap($insights->recent),
        ]);
    }

    /** One class: rate, trend, lowest day / period / subject, rate per subject, and the students to watch. */
    private function classDetail(Insights $insights, int $classId): array
    {
        $rows = $insights->recent->where('school_class_id', $classId);
        $rateBy = fn (string $key) => $rows->groupBy($key)->map(fn ($g) => SchoolStats::rate($g))->sort();
        $subjects = Subject::whereIn('id', $rows->pluck('subject_id')->unique())->get()->keyBy('id');
        $km = app()->getLocale() === 'km';

        return [
            'rate' => $insights->byClass[$classId]['rate'] ?? null,
            'trend' => $insights->byClass[$classId]['trend'] ?? null,
            'lowestDay' => $rateBy('weekday')->keys()->first(),
            'lowestPeriod' => $rateBy('period')->keys()->first(),
            'subjects' => $rateBy('subject_id')->mapWithKeys(fn ($rate, $id) => [($km ? $subjects[$id]->name_km : $subjects[$id]->name_en) => $rate]),
            'watch' => $insights->atRisk($classId)
                ->sortBy(fn ($r) => [$r->level === 'critical' ? 0 : 1, $r->rate])
                ->map(fn ($r) => ['student' => $insights->students[$r->studentId], 'risk' => $r]),
            'students' => $insights->students->where('school_class_id', $classId)->count(),
        ];
    }
}
