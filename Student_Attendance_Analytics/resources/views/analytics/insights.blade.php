@extends('layouts.app')

@section('title', __('Insights'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $short = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
    $long = [1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];
    $badge = ['decline' => ['badge-error', '▼'], 'warning' => ['badge-warning', '!'], 'info' => ['badge-info', 'i'], 'good' => ['badge-success', '✓']];
    $bar = ['error' => 'progress-error', 'warning' => 'progress-warning', 'success' => 'progress-success', 'neutral' => ''];
    $avg = $insights->schoolRate;

    // ▼ 3.0 (red when 5+ points down, green when 5+ up)
    $trend = function (?float $t) {
        if ($t === null) return '<span class="text-sm opacity-50">–</span>';
        $class = $t <= -5 ? 'text-error' : ($t >= 5 ? 'text-success' : 'opacity-70');
        return '<span class="'.$class.' text-sm font-semibold">'.($t < 0 ? '▼' : '▲').' '.number_format(abs($t), 1).'</span>';
    };
    // Heat map cell: further below the school average = darker red; 5+ points below = hotspot with a border
    $cell = function (?float $rate) use ($avg) {
        if ($rate === null || $avg === null) return 'opacity-40';
        $diff = $rate - $avg;
        return match (true) {
            $diff <= -5 => 'bg-error/45 border-2 border-error font-bold',
            $diff <= -3 => 'bg-error/30',
            $diff < 0 => 'bg-warning/20',
            default => 'bg-success/20',
        };
    };
@endphp

@section('content')
    {{-- What the data shows, in plain words --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="card-title text-base"><i data-lucide="lightbulb" class="text-warning size-5"></i>{{ __('What the data shows this month') }}</h3>
                <span class="text-sm opacity-60">{{ __('last 4 weeks vs the 4 weeks before') }}</span>
            </div>
            <ul class="mt-1 space-y-2">
                @foreach ($insights->findings as $finding)
                    <li class="flex gap-3">
                        <span class="badge {{ $badge[$finding['type']][0] }} badge-sm mt-0.5 shrink-0">{{ $badge[$finding['type']][1] }}</span>
                        <span>{{ $finding['text'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="text-xs opacity-60">{{ __('These are patterns to look into, not causes, and they never blame a teacher or student.') }}</p>
        </div>
    </div>

    {{-- Drill-down: School → Grade → Class --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="breadcrumbs text-sm">
                    <ul>
                        <li><a href="{{ route('insights') }}">{{ __('Whole school') }}</a></li>
                        @if ($grade)<li><a href="{{ route('insights', ['grade' => $grade]) }}">{{ __('Grade') }} {{ $grade }}</a></li>@endif
                        @if ($class)<li>{{ $class->name }}</li>@endif
                    </ul>
                </div>
                @unless ($class)<span class="text-xs opacity-60">{{ __('Click a card to see more') }}</span>@endunless
            </div>

            @if (! $grade)
                {{-- Grades --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    @foreach ($insights->byGrade as $g => $stat)
                        @php $classCount = $insights->classes->where('grade', $g)->count(); $riskCount = $insights->atRisk()->filter(fn ($r) => $insights->classes[$insights->students[$r->studentId]->school_class_id]?->grade == $g)->count(); @endphp
                        @include('analytics._drill-card', [
                            'href' => route('insights', ['grade' => $g]), 'title' => __('Grade').' '.$g,
                            'sub' => trans_choice('{1} :count class|[0,*] :count classes', $classCount, ['count' => $classCount]),
                            'stat' => $stat, 'risk' => $riskCount,
                        ])
                    @endforeach
                </div>
            @elseif (! $class)
                {{-- Classes of one grade --}}
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($insights->classes->where('grade', $grade) as $c)
                        @php $students = $insights->students->where('school_class_id', $c->id)->count(); @endphp
                        @include('analytics._drill-card', [
                            'href' => route('insights', ['class' => $c->id]), 'title' => $c->name,
                            'sub' => __(':count students', ['count' => $students]),
                            'stat' => $insights->byClass[$c->id] ?? ['rate' => null, 'trend' => null], 'risk' => $insights->atRisk($c->id)->count(),
                        ])
                    @endforeach
                </div>
            @else
                {{-- One class --}}
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="space-y-3">
                        <div class="bg-base-200 rounded-box p-4">
                            <div class="text-sm opacity-60">{{ __('Attendance rate') }}</div>
                            <div class="text-3xl font-extrabold">{{ $detail['rate'] === null ? '—' : $detail['rate'].'%' }}</div>
                            {!! $trend($detail['trend']) !!} <span class="text-xs opacity-60">{{ __('points in 4 weeks') }}</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <div class="bg-base-200 rounded-box p-2"><div class="text-xs opacity-60">{{ __('Lowest day') }}</div><div class="font-bold">{{ $short[$detail['lowestDay']] ?? '—' }}</div></div>
                            <div class="bg-base-200 rounded-box p-2"><div class="text-xs opacity-60">{{ __('Lowest period') }}</div><div class="font-bold">{{ $detail['lowestPeriod'] ? 'P'.$detail['lowestPeriod'] : '—' }}</div></div>
                            <div class="bg-base-200 rounded-box p-2"><div class="text-xs opacity-60">{{ __('Lowest subject') }}</div><div class="truncate font-bold">{{ $detail['subjects']->keys()->first() ?? '—' }}</div></div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2 text-sm font-semibold">{{ __('Rate by subject') }}</div>
                        <ul class="space-y-2 text-sm">
                            @foreach ($detail['subjects'] as $name => $rate)
                                <li>
                                    <div class="flex justify-between"><span>{{ $name }}</span><span class="font-medium">{{ $rate }}%</span></div>
                                    <progress class="progress {{ $bar[Risk::tone($rate)] }}" value="{{ $rate }}" max="100"></progress>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <div class="mb-2 text-sm font-semibold">{{ __('Students to watch') }}</div>
                        @forelse ($detail['watch'] as $w)
                            <a href="{{ route('students.show', $w['student']) }}" class="hover:bg-base-200 rounded-box flex items-center justify-between gap-2 p-2">
                                <span class="font-medium">{{ $km ? $w['student']->name_km : $w['student']->name_latin }}</span>
                                <span class="badge {{ Risk::badge($w['risk']->level) }} badge-sm">{{ __(ucfirst($w['risk']->level)) }} · {{ $w['risk']->rate }}%</span>
                            </a>
                        @empty
                            <p class="text-sm opacity-60">{{ __('No students at High or Critical risk in this class.') }}</p>
                        @endforelse
                        <a href="{{ route('at-risk', ['class' => $class->id]) }}" class="btn btn-ghost btn-sm mt-2">{{ __('Open the at-risk list') }} <i data-lucide="arrow-right" class="size-4"></i></a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Hotspots: day × period --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="card-title text-base">{{ __('Hotspots: day × period') }}</h3>
                    <p class="text-sm opacity-60">{{ __('Attendance rate in the last 4 weeks. Darker red = further below the school average (:rate%).', ['rate' => $avg ?? '—']) }}</p>
                </div>
                <span class="flex items-center gap-2 text-xs"><span class="border-error inline-block size-3 rounded border-2"></span>{{ __('Hotspot (5+ points below)') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-130 border-separate border-spacing-1 text-center text-sm">
                    <thead>
                        <tr><th></th>@foreach ($short as $d)<th class="py-1 text-xs font-semibold opacity-70">{{ $d }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap as $period => $days)
                            <tr>
                                <th class="w-12 text-xs font-semibold opacity-70">P{{ $period }}</th>
                                @foreach ($days as $day => $rate)
                                    <td class="rounded-lg py-3 {{ $cell($rate) }}" title="{{ $long[$day] }} · {{ __('Period') }} {{ $period }}">
                                        {{ $rate === null ? '—' : $rate.'%' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
