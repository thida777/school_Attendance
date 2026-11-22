@extends('layouts.app')

@section('title', __('Reports'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $days = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];
    // Written out in full so Tailwind finds these classes
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    $query = array_filter($filters);
    $trend = collect($report->trend)->map(fn ($rows) => [
        'labels' => $rows->pluck('label')->values()->all(),
        'present' => $rows->pluck('rate')->values()->all(),
        'absent' => $rows->pluck('rate')->map(fn ($r) => round(100 - $r, 1))->values()->all(),
    ])->all();
    $subjectNames = $report->bySubject->map(fn ($row) => $row->subject ? ($km ? $row->subject->name_km : $row->subject->name_en) : '—')->all();
    $subjectAbsences = $report->bySubject->pluck('absent')->all();
    $chartText = ['present' => __('Present'), 'absent' => __('Absent'), 'absences' => __('Absences')];
    $recordedShare = $report->planned ? min(100, round(100 * $report->recorded / $report->planned)) : null;
@endphp

@section('content')
    {{-- One filter row for the whole page --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <form method="GET" action="{{ route('reports') }}" class="grid grid-cols-2 gap-x-3 sm:flex sm:flex-wrap" data-auto-submit>
                    <fieldset class="fieldset sm:w-44">
                        <legend class="fieldset-legend">{{ __('From') }}</legend>
                        <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" class="input w-full" data-date-picker data-submit />
                    </fieldset>
                    <fieldset class="fieldset sm:w-44">
                        <legend class="fieldset-legend">{{ __('To') }}</legend>
                        <input type="date" name="to" value="{{ $filters['to'] }}" max="{{ today()->toDateString() }}" class="input w-full" data-date-picker data-submit />
                    </fieldset>
                    <fieldset class="fieldset sm:w-44">
                        <legend class="fieldset-legend">{{ __('Class') }}</legend>
                        <select name="class" class="select w-full" data-picker data-filter data-icon="school">
                            <option value="">{{ __('All classes') }}</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </fieldset>
                    <fieldset class="fieldset sm:w-52">
                        <legend class="fieldset-legend">{{ __('Subject') }}</legend>
                        <select name="subject" class="select w-full" data-picker data-filter data-icon="book-open">
                            <option value="">{{ __('All subjects') }}</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? '') == $subject->id)>{{ $km ? $subject->name_km : $subject->name_en }}</option>
                            @endforeach
                        </select>
                    </fieldset>
                </form>
                <div class="flex gap-2">
                    <a href="{{ route('reports', [...$query, 'export' => 'csv']) }}" class="btn" title="{{ __('One row per student. Opens in Excel.') }}">
                        <i data-lucide="file-spreadsheet" class="size-4"></i>Excel
                    </a>
                    <a href="{{ route('reports.print', $query) }}" target="_blank" class="btn btn-primary" title="{{ __('Opens a printable report. Choose “Save as PDF”.') }}">
                        <i data-lucide="file-text" class="size-4"></i>{{ __('Export PDF') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if ($report->total === 0)
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body items-center py-12 text-center">
                <i data-lucide="calendar-x" class="size-10 opacity-40"></i>
                <p class="mt-2 font-medium">{{ __('No attendance was recorded in this period.') }}</p>
                <p class="text-sm opacity-60">{{ __('Try other dates, or another class or subject.') }}</p>
            </div>
        </div>
    @else
        {{-- Headline numbers for the chosen period --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stats bg-base-100 border-base-300 border">
                <div class="stat">
                    <div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="percent"></i></div>
                    <div class="stat-title">{{ __('Average attendance rate') }}</div>
                    <div class="stat-value {{ $toneText[Risk::tone($report->rate)] }}">{{ $report->rate }}%</div>
                    <div class="stat-desc">
                        @if ($report->change === null)
                            {{ __('no earlier period to compare') }}
                        @else
                            <span @class(['text-error' => $report->change < 0, 'text-success' => $report->change > 0])>{{ $report->change < 0 ? '▼' : ($report->change > 0 ? '▲' : '') }} {{ abs($report->change) }}</span>
                            {{ __('points vs the period before') }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="stats bg-base-100 border-base-300 border">
                <div class="stat">
                    <div class="stat-figure" style="--chip: var(--color-primary)"><i data-lucide="clipboard-check"></i></div>
                    <div class="stat-title">{{ __('Lessons recorded') }}</div>
                    <div class="stat-value">{{ number_format($report->recorded) }}</div>
                    <div class="stat-desc">{{ __('of :planned on the timetable', ['planned' => number_format($report->planned)]) }}@if ($recordedShare !== null) ({{ $recordedShare }}%)@endif</div>
                </div>
            </div>
            <div class="stats bg-base-100 border-base-300 border">
                <div class="stat">
                    <div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="user-x"></i></div>
                    <div class="stat-title">{{ __('Total absences') }}</div>
                    <div class="stat-value">{{ number_format($report->absent) }}</div>
                    <div class="stat-desc">{{ __(':count per student', ['count' => $report->studentsWithRecords ? round($report->absent / $report->studentsWithRecords, 1) : 0]) }}</div>
                </div>
            </div>
            <div class="stats bg-base-100 border-base-300 border">
                <div class="stat">
                    <div class="stat-figure" style="--chip: var(--color-warning)"><i data-lucide="calendar-clock"></i></div>
                    <div class="stat-title">{{ __('Absent rate') }}</div>
                    <div class="stat-value">{{ round(100 * $report->absent / $report->total, 1) }}%</div>
                    <div class="stat-desc">
                        @if ($report->peakDay)
                            {{ __('most on :day, period :period', ['day' => $days[$report->peakDay], 'period' => $report->peakPeriod]) }}
                        @else
                            {{ __('no absences') }}
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="card bg-base-100 border-base-300 border">
                <div class="card-body">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="card-title text-base">{{ __('Attendance trend') }}</h3>
                            <p class="text-sm opacity-60">{{ __('Present vs absent, in this period') }}</p>
                        </div>
                        <div role="tablist" class="tabs tabs-box tabs-sm" id="trend-tabs">
                            <button role="tab" class="tab" data-range="daily">{{ __('Daily') }}</button>
                            <button role="tab" class="tab tab-active" data-range="weekly">{{ __('Weekly') }}</button>
                            <button role="tab" class="tab" data-range="monthly">{{ __('Monthly') }}</button>
                        </div>
                    </div>
                    <div id="chart-trend"></div>
                </div>
            </div>
            <div class="card bg-base-100 border-base-300 border">
                <div class="card-body">
                    <h3 class="card-title text-base">{{ __('Absences by subject') }}</h3>
                    <p class="text-sm opacity-60">{{ __('Number of absences in this period') }}</p>
                    <div id="chart-subjects"></div>
                </div>
            </div>
        </div>

        {{-- The same numbers as a table (this is what the PDF shows) --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Summary by class') }}</h3>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Class') }}</th><th class="text-right">{{ __('Students') }}</th><th class="text-right">{{ __('Present') }}</th>
                                <th class="text-right">{{ __('Absent') }}</th><th class="text-right">{{ __('Rate') }}</th><th class="text-right">{{ __('At risk') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report->byClass as $row)
                                <tr>
                                    <td class="font-medium">{{ $row->class->name }}</td>
                                    <td class="text-right tabular-nums">{{ $row->students }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row->present) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($row->absent) }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ $toneText[Risk::tone($row->rate)] }}">{{ $row->rate !== null ? $row->rate.'%' : '—' }}</td>
                                    <td @class(['text-right tabular-nums', 'text-error font-semibold' => $row->atRisk > 0])>{{ $row->atRisk }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($report->byClass->count() > 1)
                            <tfoot>
                                <tr>
                                    <th>{{ __('Whole school') }}</th>
                                    <th class="text-right">{{ $report->byClass->sum('students') }}</th>
                                    <th class="text-right">{{ number_format($report->present) }}</th>
                                    <th class="text-right">{{ number_format($report->absent) }}</th>
                                    <th class="text-right">{{ $report->rate }}%</th>
                                    <th class="text-right">{{ $report->byClass->sum('atRisk') }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                <p class="text-xs opacity-60">{{ __('Rate = present ÷ all records (present + absent). At risk = High or Critical today.') }}</p>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (!document.getElementById('chart-trend')) return;
            const t = @json($chartText);
            const trend = @json($trend);
            const subjectNames = @json($subjectNames);
            const subjectAbsences = @json($subjectAbsences);
            let range = 'weekly';

            const trendChart = makeChart('#chart-trend', (c) => ({
                chart: { type: 'area', height: 280 },
                colors: [c.present, c.absent],
                series: [{ name: t.present, data: trend[range].present }, { name: t.absent, data: trend[range].absent }],
                stroke: { width: 3, curve: 'smooth' },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
                markers: { size: 0, hover: { size: 6 } },
                dataLabels: { enabled: false },
                legend: { show: true, position: 'top', horizontalAlign: 'right', labels: { colors: c.muted } },
                xaxis: { categories: trend[range].labels, axisBorder: { color: c.axis }, axisTicks: { show: false }, tickAmount: 8 },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: (v) => v.toFixed(0) + '%' } },
                tooltip: { shared: true, y: { formatter: (v) => v + '%' } },
            }));

            // Daily / Weekly / Monthly: draw the same chart with another grouping
            document.querySelectorAll('#trend-tabs [data-range]').forEach((tab) => tab.addEventListener('click', () => {
                range = tab.dataset.range;
                document.querySelectorAll('#trend-tabs .tab').forEach((x) => x.classList.toggle('tab-active', x === tab));
                trendChart.redraw();
            }));

            makeChart('#chart-subjects', (c) => ({
                chart: { type: 'bar', height: 280 },
                colors: [c.absent],
                series: [{ name: t.absences, data: subjectAbsences }],
                plotOptions: { bar: { horizontal: true, barHeight: '55%', borderRadius: 6, borderRadiusApplication: 'end' } },
                dataLabels: { enabled: false },
                xaxis: { categories: subjectNames, axisBorder: { color: c.axis }, axisTicks: { show: false }, labels: { formatter: (v) => Math.round(v) } },
                tooltip: { y: { formatter: (v) => v } },
            }));
        });
    </script>
@endpush
