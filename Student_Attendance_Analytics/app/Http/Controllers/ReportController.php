<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports (admin): attendance for a period, by class and subject.
 *  - the page: numbers, charts and the summary table
 *  - Excel: one row per student (CSV in UTF-8, so Khmer names open correctly in Excel)
 *  - PDF: a clean printable page; the browser saves it as PDF (Khmer letters stay correct)
 */
class ReportController extends Controller
{
    public function index(Request $request, AttendanceReport $reports): View|StreamedResponse
    {
        $filters = $this->filters($request);
        $report = $reports->build($filters);

        if ($request->query('export') === 'csv') {
            return $this->export($reports, $report);
        }

        return view('reports.index', [
            'report' => $report,
            'filters' => $filters,
            'classes' => SchoolClass::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name_en')->get(),
        ]);
    }

    /** The printable report (opens the print window: choose "Save as PDF"). */
    public function print(Request $request, AttendanceReport $reports): View
    {
        $filters = $this->filters($request);

        return view('reports.print', [
            'report' => $reports->build($filters),
            'filters' => $filters,
            'by' => $request->user(),
        ]);
    }

    /** Period (default: this month until today), class and subject. */
    private function filters(Request $request): array
    {
        $request->mergeIfMissing([
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
        ]);

        return $request->validate([
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date', 'before_or_equal:today'],
            'class' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
            'subject' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
        ]);
    }

    private function export(AttendanceReport $reports, object $report): StreamedResponse
    {
        $rows = $reports->perStudent($report);
        $reasons = ['sick', 'family_matter', 'school_activity', 'other', 'not_given'];
        $name = 'attendance-report-'.$report->from->toDateString().'-to-'.$report->to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows, $reasons, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Attendance report', $report->from->toDateString().' to '.$report->to->toDateString(),
                $report->class?->name ?? 'All classes', $report->subject?->name_en ?? 'All subjects']);
            fputcsv($out, ['Student ID', 'Name (Latin)', 'Name (Khmer)', 'Class', 'Lessons', 'Present', 'Absent', 'Rate %',
                'Absent: sick', 'Absent: family', 'Absent: school activity', 'Absent: other', 'Absent: no reason', 'Risk today']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->student->student_code, $row->student->name_latin, $row->student->name_km, $row->student->schoolClass->name,
                    $row->total, $row->present, $row->absent, $row->rate,
                    ...array_map(fn ($r) => $row->reasons->get($r, 0), $reasons),
                    $row->risk?->level ? ucfirst($row->risk->level) : '',
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
