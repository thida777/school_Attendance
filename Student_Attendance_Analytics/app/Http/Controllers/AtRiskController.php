<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\RiskAnalyzer;
use App\Support\Risk;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * At-Risk Students (admin): how many students at each level, and a priority list
 * (the students who need help first are at the top), with an Excel export.
 */
class AtRiskController extends Controller
{
    public function index(Request $request, RiskAnalyzer $analyzer): View|StreamedResponse
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::in(Risk::LEVELS)],
            'class' => ['nullable', 'integer'],
        ]);

        $students = Student::with(['schoolClass', 'guardians'])->where('status', 'active')->get();
        $risks = $analyzer->forStudents($students->pluck('id'));

        $rows = $students
            ->map(fn (Student $s) => (object) ['student' => $s, 'risk' => $risks->get($s->id)])
            ->filter(fn ($row) => $row->risk->level !== null)
            // Without a level filter the list shows who needs attention: Critical, High and Medium
            ->filter(fn ($row) => ($filters['level'] ?? null)
                ? $row->risk->level === $filters['level']
                : $row->risk->level !== 'low')
            ->filter(fn ($row) => empty($filters['class']) || $row->student->school_class_id == $filters['class'])
            ->sort(fn ($a, $b) => [self::order($a->risk->level), $a->risk->rate, -$a->risk->daysInARow]
                <=> [self::order($b->risk->level), $b->risk->rate, -$b->risk->daysInARow])
            ->values();

        if ($request->query('export') === 'csv') {
            return $this->export($rows);
        }

        return view('analytics.at-risk', [
            'rows' => $rows,
            'counts' => collect(Risk::LEVELS)->mapWithKeys(fn ($level) => [$level => $risks->where('level', $level)->count()]),
            'classes' => SchoolClass::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    /** Critical first, Low last. */
    private static function order(?string $level): int
    {
        $index = array_search($level, Risk::LEVELS, true);

        return $index === false ? 99 : $index;
    }

    /** The list as a CSV file that opens in Excel (with Khmer names: UTF-8 with a BOM). */
    private function export(Collection $rows): StreamedResponse
    {
        $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

        return response()->streamDownload(function () use ($rows, $days) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Student ID', 'Name (Latin)', 'Name (Khmer)', 'Class', 'Risk', 'Suggested action',
                'Attendance rate %', 'Trend (points)', 'Days absent in a row', 'Days with no reason this month',
                'Most absences on', 'Parent', 'Parent phone']);
            foreach ($rows as $row) {
                $r = $row->risk;
                $parent = $row->student->guardians->first();
                fputcsv($out, [
                    $row->student->student_code, $row->student->name_latin, $row->student->name_km,
                    $row->student->schoolClass->name, ucfirst($r->level), $r->action(),
                    $r->rate, $r->trend, $r->daysInARow, $r->unexplained,
                    trim(($days[$r->patternDay] ?? '').($r->patternPeriod ? ' P'.$r->patternPeriod : '')),
                    $parent?->name, $parent?->phone,
                ]);
            }
            fclose($out);
        }, 'at-risk-students-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
