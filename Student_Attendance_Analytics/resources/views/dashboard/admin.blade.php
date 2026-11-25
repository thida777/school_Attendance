@extends('layouts.app')

@section('title', __('Dashboard'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $trend = $insights->schoolTrend;
    $levels = $insights->atRisk()->countBy('level');
    $streak = $insights->risks->filter(fn ($r) => $r->daysInARow >= 3)->count();
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    $headline = collect($insights->findings)->first(fn ($f) => in_array($f['type'], ['decline', 'warning'])) ?? ($insights->findings[0] ?? null);
    $second = collect($insights->findings)->first(fn ($f) => $f !== $headline && $f['type'] !== 'good');

    // Chart data, built here so the script below stays simple
    $weekLabels = $weekly->pluck('label')->all();
    $weekPresent = $weekly->pluck('rate')->all();
    $weekAbsent = array_map(fn ($r) => round(100 - $r, 1), $weekPresent);
    $classNames = $insights->classes->pluck('name')->values()->all();
    $classRates = $insights->classes->keys()->map(fn ($id) => $insights->byClass[$id]['rate'] ?? null)->values()->all();
    $dayLabels = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
    $chartText = ['present' => __('Present'), 'absent' => __('Absent'), 'rate' => __('Attendance rate'), 'absences' => __('Absences')];
    $todaySplit = [$recordedToday - $absentToday, $absentToday];
@endphp

@section('content')
    {{-- Greeting --}}
    <div class="hero-panel flex flex-wrap items-end justify-between gap-6 p-6 lg:p-8">
        <div>
            @if ($year)
                <span class="hero-chip"><i data-lucide="calendar-days" class="size-3.5"></i>{{ __('Academic year') }} {{ $year->name }}</span>
            @endif
            <h2 class="mt-4 text-2xl font-bold lg:text-3xl">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</h2>
            <p class="mt-1 opacity-85">{{ __('Here is the school attendance for today.') }}</p>
            <p class="mt-2 text-sm opacity-75">
                {{ __(':students students · :teachers teachers · :classes classes', ['students' => $studentCount, 'teachers' => $teacherCount, 'classes' => $classCount]) }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <div class="text-4xl font-extrabold">{{ $rateToday !== null ? $rateToday.'%' : '—' }}</div>
                <div class="text-sm opacity-85">{{ $rateToday !== null ? __('attendance rate today') : __('no lessons recorded today yet') }}</div>
            </div>
            <a href="{{ route('attendance.take') }}" class="btn btn-lg">
                <i data-lucide="clipboard-check" class="size-5"></i>{{ __('Take attendance') }}
            </a>
        </div>
    </div>

    {{-- 4 headline numbers, each with its meaning --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('insights') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="chart-line"></i></div>
                <div class="stat-title">{{ __('Attendance this month') }}</div>
                <div class="stat-value {{ $toneText[Risk::tone($insights->schoolRate)] }}">{{ $insights->schoolRate === null ? '—' : $insights->schoolRate.'%' }}</div>
                <div @class(['stat-desc font-semibold', 'text-error' => $trend !== null && $trend < 0, 'text-success' => $trend !== null && $trend > 0])>
                    @if ($trend !== null){{ $trend < 0 ? '▼' : '▲' }} {{ abs($trend) }} {{ __('vs the 4 weeks before') }}@else{{ __('last 4 weeks') }}@endif
                </div>
            </div>
        </a>
        <a href="{{ route('at-risk') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="triangle-alert"></i></div>
                <div class="stat-title">{{ __('High or Critical risk') }}</div>
                <div class="stat-value">{{ $insights->atRisk()->count() }}</div>
                <div class="stat-desc">{{ __(':critical critical · :high high', ['critical' => $levels['critical'] ?? 0, 'high' => $levels['high'] ?? 0]) }}</div>
            </div>
        </a>
        <a href="{{ route('at-risk') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-warning)"><i data-lucide="calendar-x"></i></div>
                <div class="stat-title">{{ __('Absent 3+ days in a row') }}</div>
                <div class="stat-value">{{ $streak }}</div>
                <div class="stat-desc">{{ __('students to contact first') }}</div>
            </div>
        </a>
        <a href="{{ route('attendance.take') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-info)"><i data-lucide="clipboard-x"></i></div>
                <div class="stat-title">{{ __('Not recorded today') }}</div>
                <div class="stat-value">{{ $notRecordedToday }}</div>
                <div class="stat-desc">{{ $lessonsToday ? __('lessons of :count', ['count' => $lessonsToday]) : __('no lessons today') }}</div>
            </div>
        </a>
    </div>

    {{-- The headline insight, in plain words --}}
    @if ($headline)
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body flex-row flex-wrap items-center gap-4">
                <span class="bg-warning/10 text-warning grid size-12 shrink-0 place-items-center rounded-2xl"><i data-lucide="lightbulb" class="size-6"></i></span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ $headline['text'] }}</div>
                    @if ($second)<div class="text-sm opacity-70">{{ $second['text'] }}</div>@endif
                </div>
                <a href="{{ route('insights') }}" class="btn btn-sm">{{ __('See insights') }}<i data-lucide="arrow-right" class="size-4"></i></a>
            </div>
        </div>
    @endif

    {{-- Trend + today's breakdown --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card bg-base-100 border-base-300 border xl:col-span-2">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('Attendance trend') }}</h3>
                    <span class="text-xs opacity-60">{{ __('week by week, this school year') }}</span>
                </div>
                @if ($weekly->isEmpty())
                    <p class="py-10 text-center text-sm opacity-60">{{ __('No attendance has been recorded yet.') }}</p>
                @else
                    <div id="chart-trend"></div>
                @endif
            </div>
        </div>
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Today by status') }}</h3>
                @if ($recordedToday === 0)
                    <div class="grid flex-1 place-items-center py-8 text-center">
                        <div>
                            <i data-lucide="calendar-clock" class="mx-auto size-10 opacity-40"></i>
                            <p class="mt-2 text-sm opacity-60">{{ __('No lessons recorded today yet.') }}</p>
                        </div>
                    </div>
                @else
                    <div id="chart-today"></div>
                    <div class="grid grid-cols-2 gap-2 text-center text-sm">
                        <div class="bg-success/10 rounded-box p-2"><div class="text-success font-semibold">{{ __('Present') }}</div><div class="text-xl font-bold">{{ $recordedToday - $absentToday }}</div></div>
                        <div class="bg-error/10 rounded-box p-2"><div class="text-error font-semibold">{{ __('Absent') }}</div><div class="text-xl font-bold">{{ $absentToday }}</div></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Class comparison + weekday pattern --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('Attendance rate by class') }}</h3>
                    <span class="text-xs opacity-60">{{ __('last 4 weeks') }}</span>
                </div>
                <div id="chart-classes"></div>
            </div>
        </div>
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('Absences by day of week') }}</h3>
                    <span class="text-xs opacity-60">{{ __('last 4 weeks') }}</span>
                </div>
                <div id="chart-weekday"></div>
            </div>
        </div>
    </div>

    {{-- At-risk preview --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="card-title text-base">{{ __('Students who need attention') }}</h3>
                <a href="{{ route('at-risk') }}" class="btn btn-ghost btn-sm">{{ __('View all') }}<i data-lucide="arrow-right" class="size-4"></i></a>
            </div>
            @if ($watch->isEmpty())
                <p class="py-6 text-center text-sm opacity-60">{{ __('No students at High or Critical risk. 🎉') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Risk') }}</th><th class="text-right">{{ __('Rate') }}</th><th class="text-right">{{ __('Trend') }}</th><th class="text-right">{{ __('In a row') }}</th></tr></thead>
                        <tbody>
                            @foreach ($watch as $w)
                                @php $r = $w['risk']; $s = $w['student']; @endphp
                                <tr>
                                    <td><a href="{{ route('students.show', $s) }}" class="link link-hover font-medium">{{ $km ? $s->name_km : $s->name_latin }}</a></td>
                                    <td>{{ $insights->classes[$s->school_class_id]->name }}</td>
                                    <td>@include('partials.risk-badge', ['level' => $r->level])</td>
                                    <td class="text-right font-semibold {{ $toneText[Risk::tone($r->rate)] }}">{{ $r->rate }}%</td>
                                    <td class="text-right">
                                        @if ($r->trend !== null)<span @class(['text-error font-semibold' => $r->trend < 0, 'text-success' => $r->trend > 0])>{{ $r->trend < 0 ? '▼' : '▲' }} {{ abs($r->trend) }}</span>@else – @endif
                                    </td>
                                    <td @class(['text-right', 'text-error font-bold' => $r->daysInARow >= 3])>{{ $r->daysInARow }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const t = @json($chartText);
            const weekLabels = @json($weekLabels);
            const weekPresent = @json($weekPresent);
            const weekAbsent = @json($weekAbsent);
            const classNames = @json($classNames);
            const classRates = @json($classRates);
            const dayLabels = @json($dayLabels);
            const dayAbsences = @json($absencesByWeekday);
            const today = @json($todaySplit);
            const todayRate = @json($rateToday);

            makeChart('#chart-trend', (c) => ({
                chart: { type: 'area', height: 280 },
                colors: [c.present, c.absent],
                series: [{ name: t.present, data: weekPresent }, { name: t.absent, data: weekAbsent }],
                stroke: { width: 3, curve: 'smooth' },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
                markers: { size: 0, hover: { size: 6 } },
                legend: { show: true, position: 'top', horizontalAlign: 'right', labels: { colors: c.muted } },
                xaxis: { categories: weekLabels, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: (v) => v.toFixed(0) + '%' } },
                tooltip: { shared: true, y: { formatter: (v) => v + '%' } },
            }));

            makeChart('#chart-today', (c) => ({
                chart: { type: 'donut', height: 200 },
                colors: [c.present, c.absent],
                series: today,
                labels: [t.present, t.absent],
                stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: t.rate, color: c.muted, formatter: () => todayRate + '%' } } } } },
            }));

            // One colour per class rate: green 90%+, orange 80–89%, red under 80% (the same rule as everywhere)
            makeChart('#chart-classes', (c) => ({
                chart: { type: 'bar', height: 260 },
                series: [{ name: t.rate, data: classRates }],
                colors: [({ value }) => (value >= 90 ? c.present : value >= 80 ? c.warning : c.absent)],
                plotOptions: { bar: { columnWidth: '35%', borderRadius: 8, borderRadiusApplication: 'end' } },
                xaxis: { categories: classNames, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: (v) => v.toFixed(0) + '%' } },
                tooltip: { y: { formatter: (v) => v + '%' } },
            }));

            makeChart('#chart-weekday', (c) => ({
                chart: { type: 'bar', height: 260 },
                colors: [c.absent],
                series: [{ name: t.absences, data: dayAbsences }],
                plotOptions: { bar: { columnWidth: '35%', borderRadius: 8, borderRadiusApplication: 'end' } },
                xaxis: { categories: dayLabels, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 0, tickAmount: 3, labels: { formatter: (v) => v.toFixed(0) } },
            }));
        });
    </script>
@endpush
