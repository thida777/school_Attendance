@extends('layouts.app')

@section('title', __('At-Risk Students'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $days = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
    $cards = [
        'critical' => ['octagon-alert', 'var(--color-error)'],
        'high' => ['triangle-alert', 'var(--color-warning)'],
        'medium' => ['eye', 'var(--color-info)'],
        'low' => ['circle-check', 'var(--color-success)'],
    ];
    $active = $filters['level'] ?? null;
    // Written out in full so Tailwind finds these classes
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
@endphp

@section('content')
    {{-- How many students at each risk level (tap a card to see only that level) --}}
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ($cards as $level => [$icon, $color])
            <a href="{{ route('at-risk', array_filter(['level' => $active === $level ? null : $level, 'class' => $filters['class'] ?? null])) }}"
                @class(['stats bg-base-100 border-base-300 border transition hover:shadow-md', 'ring-2 ring-offset-2 ring-offset-base-200' => $active === $level])
                style="--tw-ring-color: {{ $color }}" aria-pressed="{{ $active === $level ? 'true' : 'false' }}">
                <div class="stat">
                    <div class="stat-figure" style="--chip: {{ $color }}"><i data-lucide="{{ $icon }}"></i></div>
                    <div class="stat-title">{{ Risk::label($level) }}</div>
                    <div class="stat-value">{{ $counts[$level] }}</div>
                    <div class="stat-desc">{{ trans_choice('{1} student|[0,*] students', $counts[$level]) }}</div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="card bg-base-100 border-base-300 border" data-list>
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="card-title text-base">{{ __('Priority list') }}</h3>
                    <p class="text-sm opacity-60">{{ __('Students who need help first are at the top.') }}</p>
                </div>
                <form method="GET" action="{{ route('at-risk') }}" class="flex flex-wrap gap-2" data-auto-submit>
                    <select name="level" class="select w-auto" aria-label="{{ __('Risk') }}" data-picker data-filter data-icon="gauge">
                        <option value="">{{ __('Critical, High risk and Watch') }}</option>
                        @foreach (Risk::LEVELS as $level)
                            <option value="{{ $level }}" @selected($active === $level)>{{ Risk::label($level) }}</option>
                        @endforeach
                    </select>
                    <select name="class" class="select w-auto" aria-label="{{ __('Class') }}" data-picker data-filter data-icon="school">
                        <option value="">{{ __('All classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('at-risk', array_filter([...$filters, 'export' => 'csv'])) }}" class="btn">
                        <i data-lucide="file-spreadsheet" class="size-4"></i>{{ __('Export list') }}
                    </a>
                </form>
            </div>

            @if ($rows->isEmpty())
                <div class="py-12 text-center">
                    <i data-lucide="party-popper" class="text-success mx-auto size-10"></i>
                    <p class="mt-2 font-medium">{{ __('No students match this filter.') }}</p>
                    <p class="text-sm opacity-60">{{ __('Try another level or class.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Risk · suggested action') }}</th>
                                <th class="text-right">{{ __('Rate') }}</th><th class="text-right">{{ __('Trend') }}</th>
                                <th class="text-right">{{ __('In a row') }}</th><th>{{ __('Pattern') }}</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php $r = $row->risk; $s = $row->student; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('students.show', $s) }}" class="link link-hover font-medium">{{ $km ? $s->name_km : $s->name_latin }}</a>
                                        <div class="text-xs opacity-60">{{ $km ? $s->name_latin : $s->name_km }}</div>
                                    </td>
                                    <td>{{ $s->schoolClass->name }}</td>
                                    <td>
                                        @include('partials.risk-badge', ['level' => $r->level])
                                        @if ($r->action())
                                            <div class="mt-1 text-xs whitespace-nowrap opacity-60">{{ __($r->action()) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-semibold tabular-nums {{ $toneText[Risk::tone($r->rate)] }}">{{ $r->rate }}%</td>
                                    <td class="text-right tabular-nums">
                                        @if ($r->trend === null)
                                            <span class="opacity-50">–</span>
                                        @elseif ($r->trend < 0)
                                            <span class="text-error font-semibold" title="{{ __('Last 4 weeks compared with the 4 weeks before') }}">▼ {{ abs($r->trend) }}</span>
                                        @elseif ($r->trend > 0)
                                            <span class="text-success" title="{{ __('Last 4 weeks compared with the 4 weeks before') }}">▲ {{ $r->trend }}</span>
                                        @else
                                            <span class="opacity-60">0</span>
                                        @endif
                                    </td>
                                    <td @class(['text-right tabular-nums', 'text-error font-bold' => $r->daysInARow >= 3])>{{ $r->daysInARow }}</td>
                                    <td class="whitespace-nowrap">
                                        @if ($r->patternDay || $r->patternPeriod)
                                            {{ collect([$days[$r->patternDay] ?? null, $r->patternPeriod ? 'P'.$r->patternPeriod : null])->filter()->join(' · ') }}
                                        @else
                                            <span class="opacity-50">–</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('interventions', ['student' => $s->id, 'add' => 1]) }}" class="btn btn-sm btn-primary">
                                            <i data-lucide="hand-heart" class="size-4"></i>{{ __('Follow up') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- The rules, so every reader knows why a student is on this list --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('How the risk level is decided') }}</h3>
            <p class="text-sm opacity-60">{{ __('The highest matching level is used. Trend = last 4 weeks compared with the 4 weeks before.') }}</p>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Level') }}</th><th>{{ __('Rule (any one is enough)') }}</th></tr></thead>
                    <tbody>
                        <tr><td>@include('partials.risk-badge', ['level' => 'critical'])</td><td>{{ __('Rate below 70%, or absent 5+ school days in a row') }}</td></tr>
                        <tr><td>@include('partials.risk-badge', ['level' => 'high'])</td><td>{{ __('Rate 70–79%, or absent 3–4 days in a row, or trend down 10+ points') }}</td></tr>
                        <tr><td>@include('partials.risk-badge', ['level' => 'medium'])</td><td>{{ __('Rate 80–89%, or trend down 5–9 points, or 3+ days absent with no reason this month') }}</td></tr>
                        <tr><td>@include('partials.risk-badge', ['level' => 'low'])</td><td>{{ __('Rate 90% or higher, and the trend is stable or improving') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs opacity-60">{{ __('A risk level is an early warning, not a prediction. People decide what to do.') }}</p>
        </div>
    </div>
@endsection
