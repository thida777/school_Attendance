{{--
    Printable attendance report (A4). The print window opens by itself: choose "Save as PDF" to get a PDF file.
    The browser draws the Khmer letters, so they stay correct (PDF libraries often break them).
--}}
@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    $date = fn ($d) => $d->translatedFormat('j F Y');
    $name = fn ($s) => $km ? $s->name_km : $s->name_latin;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Attendance report') }} {{ $report->from->toDateString() }} – {{ $report->to->toDateString() }}</title>
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm; }
        body { background: #fff; color: #1f2d3a; }
        .sheet { max-width: 210mm; margin: 0 auto; padding: 12mm 0; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .report-table th, .report-table td { border-bottom: 1px solid #e1e0d9; padding: 6px 8px; text-align: left; }
        .report-table th { background: #f4f3ee; font-weight: 600; }
        .report-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .report-table tfoot th { background: #fff; border-top: 2px solid #1f2d3a; }
        section { break-inside: avoid; }
        @media print {
            .no-print { display: none !important; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print bg-base-200 border-base-300 sticky top-0 z-10 border-b">
        <div class="mx-auto flex max-w-[210mm] flex-wrap items-center justify-between gap-2 px-4 py-3">
            <span class="text-sm opacity-70">{{ __('In the print window, choose “Save as PDF” as the printer.') }}</span>
            <div class="flex gap-2">
                <a href="{{ route('reports', array_filter($filters)) }}" class="btn btn-sm">{{ __('Back to Reports') }}</a>
                <button class="btn btn-sm btn-primary" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
            </div>
        </div>
    </div>

    <main class="sheet space-y-6 px-4 text-sm">
        {{-- Heading --}}
        <header class="flex items-start justify-between gap-4 border-b-2 border-[#1f2d3a] pb-4">
            <div>
                <p class="text-xs tracking-wide uppercase opacity-60">{{ \App\Support\School::name() }}</p>
                <h1 class="mt-1 text-2xl font-bold">{{ __('Attendance report') }}</h1>
                <p class="mt-1">{{ $date($report->from) }} – {{ $date($report->to) }}</p>
                <p class="opacity-70">
                    {{ $report->class?->name ?? __('All classes') }} ·
                    {{ $report->subject ? ($km ? $report->subject->name_km : $report->subject->name_en) : __('All subjects') }}
                </p>
            </div>
            <div class="text-right text-xs opacity-70">
                <p>{{ __('Printed on') }} {{ now()->translatedFormat('j F Y, H:i') }}</p>
                <p>{{ __('By') }} {{ $by->name }}</p>
            </div>
        </header>

        @if ($report->total === 0)
            <p class="py-12 text-center opacity-70">{{ __('No attendance was recorded in this period.') }}</p>
        @else
            {{-- Headline numbers --}}
            <section class="grid grid-cols-4 gap-3">
                <div class="rounded-lg border border-[#e1e0d9] p-3">
                    <p class="text-xs opacity-60">{{ __('Average attendance rate') }}</p>
                    <p class="text-xl font-bold {{ $toneText[Risk::tone($report->rate)] }}">{{ $report->rate }}%</p>
                    @if ($report->change !== null)
                        <p class="text-xs opacity-70">{{ $report->change > 0 ? '+' : '' }}{{ $report->change }} {{ __('points vs the period before') }}</p>
                    @endif
                </div>
                <div class="rounded-lg border border-[#e1e0d9] p-3">
                    <p class="text-xs opacity-60">{{ __('Lessons recorded') }}</p>
                    <p class="text-xl font-bold">{{ number_format($report->recorded) }}</p>
                    <p class="text-xs opacity-70">{{ __('of :planned on the timetable', ['planned' => number_format($report->planned)]) }}</p>
                </div>
                <div class="rounded-lg border border-[#e1e0d9] p-3">
                    <p class="text-xs opacity-60">{{ __('Total absences') }}</p>
                    <p class="text-xl font-bold">{{ number_format($report->absent) }}</p>
                    <p class="text-xs opacity-70">{{ __(':count per student', ['count' => $report->studentsWithRecords ? round($report->absent / $report->studentsWithRecords, 1) : 0]) }}</p>
                </div>
                <div class="rounded-lg border border-[#e1e0d9] p-3">
                    <p class="text-xs opacity-60">{{ __('Absent rate') }}</p>
                    <p class="text-xl font-bold">{{ round(100 * $report->absent / $report->total, 1) }}%</p>
                    @if ($report->peakDay)
                        <p class="text-xs opacity-70">{{ __('most on :day, period :period', ['day' => $days[$report->peakDay], 'period' => $report->peakPeriod]) }}</p>
                    @endif
                </div>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold">{{ __('Summary by class') }}</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>{{ __('Class') }}</th><th class="num">{{ __('Students') }}</th><th class="num">{{ __('Present') }}</th>
                            <th class="num">{{ __('Absent') }}</th><th class="num">{{ __('Rate') }}</th><th class="num">{{ __('At risk') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->byClass as $row)
                            <tr>
                                <td>{{ $row->class->name }}</td>
                                <td class="num">{{ $row->students }}</td>
                                <td class="num">{{ number_format($row->present) }}</td>
                                <td class="num">{{ number_format($row->absent) }}</td>
                                <td class="num font-semibold {{ $toneText[Risk::tone($row->rate)] }}">{{ $row->rate !== null ? $row->rate.'%' : '—' }}</td>
                                <td class="num">{{ $row->atRisk }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($report->byClass->count() > 1)
                        <tfoot>
                            <tr>
                                <th>{{ __('Whole school') }}</th><th class="num">{{ $report->byClass->sum('students') }}</th>
                                <th class="num">{{ number_format($report->present) }}</th><th class="num">{{ number_format($report->absent) }}</th>
                                <th class="num">{{ $report->rate }}%</th><th class="num">{{ $report->byClass->sum('atRisk') }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </section>

            <section>
                <h2 class="mb-2 text-base font-semibold">{{ __('By subject') }}</h2>
                <table class="report-table">
                    <thead><tr><th>{{ __('Subject') }}</th><th class="num">{{ __('Absences') }}</th><th class="num">{{ __('Rate') }}</th></tr></thead>
                    <tbody>
                        @foreach ($report->bySubject as $row)
                            <tr>
                                <td>{{ $row->subject ? ($km ? $row->subject->name_km : $row->subject->name_en) : '—' }}</td>
                                <td class="num">{{ number_format($row->absent) }}</td>
                                <td class="num {{ $toneText[Risk::tone($row->rate)] }}">{{ $row->rate }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section>
                <h2 class="mb-1 text-base font-semibold">{{ __('Students at High or Critical risk') }}</h2>
                <p class="mb-2 text-xs opacity-60">{{ __('As of today, using the whole school year.') }}</p>
                @if ($report->atRisk->isEmpty())
                    <p class="opacity-70">{{ __('No students at High or Critical risk.') }}</p>
                @else
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Risk') }}</th>
                                <th class="num">{{ __('Rate') }}</th><th class="num">{{ __('In a row') }}</th><th>{{ __('Suggested action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report->atRisk as $row)
                                <tr>
                                    <td>{{ $name($row->student) }} <span class="opacity-60">· {{ $row->student->student_code }}</span></td>
                                    <td>{{ $row->student->schoolClass->name }}</td>
                                    <td>{{ __(ucfirst($row->risk->level)) }}</td>
                                    <td class="num">{{ $row->risk->rate }}%</td>
                                    <td class="num">{{ $row->risk->daysInARow }}</td>
                                    <td>{{ __($row->risk->action()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endif

        <p class="text-xs opacity-60">{{ __('Rate = present ÷ all records (present + absent). At risk = High or Critical today.') }}</p>

        {{-- Signatures --}}
        <section class="grid grid-cols-2 gap-12 pt-10 text-center">
            <div>
                <p>{{ __('Prepared by') }}</p>
                <div class="mt-14 border-t border-[#1f2d3a] pt-1">{{ $by->name }}</div>
            </div>
            <div>
                <p>{{ __('Approved by the school principal') }}</p>
                <div class="mt-14 border-t border-[#1f2d3a] pt-1">&nbsp;</div>
            </div>
        </section>
    </main>

    <script>
        // Open the print window once the fonts are ready (Khmer needs its font)
        window.addEventListener('load', () => (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => setTimeout(() => window.print(), 300)));
    </script>
</body>
</html>
