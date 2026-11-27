@extends('layouts.app')

@section('title', __('My Dashboard'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $subjectNames = $subjects->map(fn ($s) => $km ? $s->name_km : $s->name_en)->join(', ');
    $done = $schedule->filter(fn ($l) => $l->session)->count();
    $nextId = $schedule->first(fn ($l) => $l->session === null)?->id;
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    // Teachers see simple words: Good, Watch, At risk (as on My Classes)
    $simple = fn (?string $level) => match ($level) {
        'low' => ['badge-success', __('Good')],
        'medium' => ['badge-warning', __('Watch')],
        'high', 'critical' => ['badge-error', __('At risk')],
        default => ['badge-ghost', __('No records yet')],
    };

    $weekLabels = $weekly->pluck('label')->all();
    $weekRates = $weekly->pluck('rate')->all();
    $classNames = $classes->pluck('name')->all();
    $classRates = $classes->map(fn ($c) => $byClass[$c->id]['rate'] ?? null)->values()->all();
    $chartText = ['rate' => __('Attendance rate')];
@endphp

@section('content')
    {{-- Greeting --}}
    <div class="hero-panel flex flex-wrap items-end justify-between gap-6 p-6 lg:p-8">
        <div>
            <span class="hero-chip"><i data-lucide="book-open" class="size-3.5"></i>{{ $subjectNames ?: __('No subjects yet') }} · {{ trans_choice('{1} :count class|[0,*] :count classes', $classes->count(), ['count' => $classes->count()]) }}</span>
            <h2 class="mt-4 text-2xl font-bold lg:text-3xl">{{ __('Hello, :name', ['name' => $teacher->name]) }}</h2>
            <p class="mt-1 opacity-85">{{ __('Here is your teaching day.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <div class="text-4xl font-extrabold">{{ $rateToday !== null ? $rateToday.'%' : '—' }}</div>
                <div class="text-sm opacity-85">{{ $rateToday !== null ? __('attendance in my classes today') : __('no lessons recorded today yet') }}</div>
            </div>
            <a href="{{ route('attendance.take') }}" class="btn btn-lg"><i data-lucide="clipboard-check" class="size-5"></i>{{ __('Take attendance') }}</a>
        </div>
    </div>

    {{-- 4 headline numbers --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('teacher.classes') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="school"></i></div>
                <div class="stat-title">{{ __('My classes') }}</div>
                <div class="stat-value">{{ $classes->count() }}</div>
                <div class="stat-desc truncate">{{ $classNames ? implode(' · ', $classNames) : '—' }}</div>
            </div>
        </a>
        <a href="{{ route('teacher.classes') }}#students" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="users"></i></div>
                <div class="stat-title">{{ __('My students') }}</div>
                <div class="stat-value">{{ $studentCount }}</div>
                <div class="stat-desc">{{ trans_choice('{1} in :count class|[0,*] in :count classes', $classes->count(), ['count' => $classes->count()]) }}</div>
            </div>
        </a>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-error)"><i data-lucide="user-x"></i></div>
                <div class="stat-title">{{ __('Absent today') }}</div>
                <div class="stat-value">{{ $absentToday }}</div>
                <div class="stat-desc truncate">{{ $absentWhere ?: __('in my lessons') }}</div>
            </div>
        </div>
        <a href="{{ route('attendance.take') }}" class="stats bg-base-100 border-base-300 border transition hover:shadow-md">
            <div class="stat">
                <div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="calendar-check"></i></div>
                <div class="stat-title">{{ __('Marked today') }}</div>
                <div class="stat-value">{{ $done }}/{{ $schedule->count() }}</div>
                <div class="stat-desc">
                    @if ($schedule->isEmpty()) {{ __('no lessons today') }}
                    @elseif ($done === $schedule->count()) {{ __('all done today') }} 🎉
                    @else {{ trans_choice('{1} :count period still to mark|[0,*] :count periods still to mark', $schedule->count() - $done, ['count' => $schedule->count() - $done]) }}
                    @endif
                </div>
            </div>
        </a>
    </div>

    {{-- Today's schedule + students to watch --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="card bg-base-100 border-base-300 border xl:col-span-2">
            <div class="card-body">
                <div class="flex items-center justify-between">
                    <h3 class="card-title text-base">{{ __("Today's schedule") }}</h3>
                    <a href="{{ route('attendance.take') }}" class="btn btn-ghost btn-sm">{{ __('Take attendance') }}</a>
                </div>
                @if ($schedule->isEmpty())
                    <div class="py-8 text-center">
                        <i data-lucide="coffee" class="mx-auto size-10 opacity-40"></i>
                        <p class="mt-2 text-sm opacity-60">{{ __('No lessons on your timetable today.') }}</p>
                    </div>
                @else
                    <ul>
                        @foreach ($schedule as $lesson)
                            @php $isNext = $lesson->id === $nextId; [$start, $end] = \App\Models\TimetableSlot::PERIODS[$lesson->period] ?? ['', '']; @endphp
                            <li class="border-base-300 flex flex-wrap items-center gap-3 border-b py-3 last:border-0">
                                <span @class(['grid size-11 place-items-center rounded-2xl font-bold',
                                    'bg-success/10 text-success' => $lesson->session,
                                    'bg-primary/10 text-primary' => ! $lesson->session && $isNext,
                                    'bg-base-200 opacity-70' => ! $lesson->session && ! $isNext])>P{{ $lesson->period }}</span>
                                <div class="min-w-0 grow">
                                    <div class="font-semibold">{{ $lesson->schoolClass->name }} · {{ $km ? $lesson->subject->name_km : $lesson->subject->name_en }}</div>
                                    <div class="text-sm opacity-60">{{ $start }}–{{ $end }}@if ($lesson->schoolClass->room) · {{ __('Room') }} {{ $lesson->schoolClass->room }}@endif</div>
                                </div>
                                @if ($lesson->session)
                                    <span class="badge badge-soft badge-success"><i data-lucide="check" class="size-3.5"></i>{{ __('Done') }} {{ $lesson->session->present_count }}/{{ $lesson->session->records_count }}</span>
                                    <a href="{{ route('attendance.mark', ['slot' => $lesson->id]) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                @elseif ($isNext)
                                    <span class="badge badge-soft badge-primary">{{ __('Up next') }}</span>
                                    <a href="{{ route('attendance.mark', ['slot' => $lesson->id]) }}" class="btn btn-primary btn-sm"><i data-lucide="play" class="size-4"></i>{{ __('Start') }}</a>
                                @else
                                    <span class="badge badge-soft badge-ghost">{{ __('Later') }}</span>
                                    <a href="{{ route('attendance.mark', ['slot' => $lesson->id]) }}" class="btn btn-sm">{{ __('Start') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <h3 class="card-title text-base">{{ __('Students to watch') }}</h3>
                @forelse ($watch as $w)
                    @php [$badge, $label] = $simple($w['risk']->level); $s = $w['student']; @endphp
                    <a href="{{ route('teacher.classes', ['class' => $s->school_class_id, 'q' => $s->student_code]) }}#students" class="hover:bg-base-200 rounded-box -mx-2 flex items-center gap-3 p-2">
                        <div class="min-w-0 grow">
                            <div class="truncate font-medium">{{ $km ? $s->name_km : $s->name_latin }}</div>
                            <div class="text-xs opacity-60">
                                {{ $classes->firstWhere('id', $s->school_class_id)?->name }} · <span class="{{ $toneText[Risk::tone($w['risk']->rate)] }}">{{ $w['risk']->rate }}%</span>
                                @if ($w['risk']->daysInARow >= 2) · {{ __(':count days in a row', ['count' => $w['risk']->daysInARow]) }}@endif
                            </div>
                        </div>
                        <span class="badge badge-soft {{ $badge }} shrink-0">{{ $label }}</span>
                    </a>
                @empty
                    <p class="py-4 text-sm opacity-60">{{ __('All your students are doing well. 🎉') }}</p>
                @endforelse
                <p class="mt-auto text-xs opacity-60">{{ __('Talk with the homeroom teacher or the school office before contacting parents.') }}</p>
            </div>
        </div>
    </div>

    {{-- My analytics --}}
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('My attendance trend') }}</h3>
                    <span class="text-xs opacity-60">{{ __('my lessons, week by week') }}</span>
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
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('Attendance rate by class') }}</h3>
                    <span class="text-xs opacity-60">{{ __('my lessons, last 4 weeks') }}</span>
                </div>
                <div id="chart-classes"></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const t = @json($chartText);
            const weekLabels = @json($weekLabels);
            const weekRates = @json($weekRates);
            const classNames = @json($classNames);
            const classRates = @json($classRates);

            makeChart('#chart-trend', (c) => ({
                chart: { type: 'area', height: 240 },
                colors: [c.series],
                series: [{ name: t.rate, data: weekRates }],
                stroke: { width: 3, curve: 'smooth' },
                fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
                xaxis: { categories: weekLabels, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 60, max: 100, tickAmount: 4, labels: { formatter: (v) => v.toFixed(0) + '%' } },
                annotations: { yaxis: [
                    { y: 90, borderColor: c.present, strokeDashArray: 4, opacity: 0.6 },
                    { y: 80, borderColor: c.warning, strokeDashArray: 4, opacity: 0.6 },
                ] },
                tooltip: { y: { formatter: (v) => v + '%' } },
            }));

            makeChart('#chart-classes', (c) => ({
                chart: { type: 'bar', height: 240 },
                series: [{ name: t.rate, data: classRates }],
                colors: [({ value }) => (value >= 90 ? c.present : value >= 80 ? c.warning : c.absent)],
                plotOptions: { bar: { columnWidth: '35%', borderRadius: 8, borderRadiusApplication: 'end' } },
                xaxis: { categories: classNames, axisBorder: { color: c.axis }, axisTicks: { show: false } },
                yaxis: { min: 0, max: 100, tickAmount: 4, labels: { formatter: (v) => v.toFixed(0) + '%' } },
                tooltip: { y: { formatter: (v) => v + '%' } },
            }));
        });
    </script>
@endpush
