@extends('layouts.app')

@section('title', $teacher->name)

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    // Written out in full so Tailwind finds these classes
    $toneText = ['error' => 'text-error', 'warning' => 'text-warning', 'success' => 'text-success', 'neutral' => ''];
    $missing = max(0, $planned - $recorded);
@endphp

@section('content')
    <div class="breadcrumbs text-sm">
        <ul>
            <li><a href="{{ route('teachers.index') }}">{{ __('Teachers') }}</a></li>
            <li>{{ $km ? $teacher->name_km : $teacher->name }}</li>
        </ul>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- Profile --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body items-center text-center">
                @include('partials.avatar', ['person' => $teacher, 'size' => 'w-20', 'text' => 'text-2xl', 'tone' => 'bg-primary/15 text-primary'])
                <h2 class="mt-2 text-xl font-bold">{{ $km ? $teacher->name_km : $teacher->name }}</h2>
                <p class="grow-0 opacity-60">{{ $km ? $teacher->name : $teacher->name_km }}</p>
                @if ($homeroom->isNotEmpty())
                    <span class="badge badge-soft badge-primary">{{ __('Homeroom teacher of :classes', ['classes' => $homeroom->pluck('name')->join(', ')]) }}</span>
                @endif
                <ul class="mt-4 w-full space-y-3 text-left text-sm">
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Phone') }}</span>
                        <span class="font-medium">@if ($teacher->phone)<a class="link link-hover" href="tel:{{ $teacher->phone }}">{{ trim(chunk_split($teacher->phone, 3, ' ')) }}</a>@else — @endif</span></li>
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Email') }}</span><span class="font-medium break-all">{{ $teacher->email }}</span></li>
                    <li class="flex justify-between gap-2"><span class="opacity-60">{{ __('Last login') }}</span>
                        <span class="font-medium">{{ $teacher->last_login_at?->translatedFormat('j M Y, H:i') ?? __('Never') }}</span></li>
                </ul>
                <div class="card-actions mt-4 w-full">
                    <a href="{{ route('assignments.index') }}" class="btn btn-primary grow">
                        <i data-lucide="calendar-check" class="size-4"></i>{{ __('Teaching Assignments') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="stats bg-base-100 border-base-300 border">
                    <div class="stat">
                        <div class="stat-figure" style="--chip: var(--color-primary)"><i data-lucide="school"></i></div>
                        <div class="stat-title">{{ __('Classes') }}</div>
                        <div class="stat-value">{{ $classCount }}</div>
                        <div class="stat-desc">{{ trans_choice('{1} :count student|[0,*] :count students', $studentCount) }}</div>
                    </div>
                </div>
                <div class="stats bg-base-100 border-base-300 border">
                    <div class="stat">
                        <div class="stat-figure" style="--chip: var(--color-warning)"><i data-lucide="clipboard-check"></i></div>
                        <div class="stat-title">{{ __('Lessons this week') }}</div>
                        <div class="stat-value">{{ $weekRecorded }}/{{ $weekPlanned }}</div>
                        <div class="stat-desc">{{ $weekRecorded >= $weekPlanned ? __('all recorded') : __(':count not recorded yet', ['count' => $weekPlanned - $weekRecorded]) }}</div>
                    </div>
                </div>
                <div class="stats bg-base-100 border-base-300 border">
                    <div class="stat">
                        <div class="stat-figure" style="--chip: var(--color-success)"><i data-lucide="percent"></i></div>
                        <div class="stat-title">{{ __('Attendance rate') }}</div>
                        <div class="stat-value {{ $toneText[Risk::tone($rate)] }}">{{ $rate !== null ? $rate.'%' : '—' }}</div>
                        <div class="stat-desc">
                            {{ __('in this teacher\'s lessons, last 4 weeks') }}
                            @if ($trend !== null && $trend != 0)
                                <span @class(['text-error' => $trend < 0, 'text-success' => $trend > 0])>{{ $trend < 0 ? '▼' : '▲' }} {{ abs($trend) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Teaching assignments --}}
            <div class="card bg-base-100 border-base-300 border">
                <div class="card-body">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="card-title text-base">{{ __('Teaching assignments') }}</h3>
                        <span class="text-sm opacity-60">{{ __('last 4 weeks') }}</span>
                    </div>
                    @if ($rows->isEmpty())
                        <p class="py-4 text-sm opacity-60">{{ __('No classes assigned yet.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th><th class="text-right">{{ __('Students') }}</th>
                                        <th class="text-right">{{ __('Lessons recorded') }}</th><th class="text-right">{{ __('Rate') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td class="font-medium">{{ $row->assignment->schoolClass->name }}</td>
                                            <td>{{ $km ? $row->assignment->subject->name_km : $row->assignment->subject->name_en }}</td>
                                            <td class="text-right tabular-nums">{{ $row->students }}</td>
                                            <td @class(['text-right tabular-nums', 'text-error font-semibold' => $row->recorded < $row->planned])>{{ $row->recorded }}/{{ $row->planned }}</td>
                                            <td class="text-right font-semibold tabular-nums {{ $toneText[Risk::tone($row->rate)] }}">{{ $row->rate !== null ? $row->rate.'%' : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs opacity-60">
                            @if ($missing > 0)
                                {{ __(':count lessons on the timetable have no attendance yet.', ['count' => $missing]) }}
                            @else
                                {{ __('Every lesson on the timetable was recorded.') }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Latest lessons --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Recent lessons') }}</h3>
            @if ($latest->isEmpty())
                <p class="py-4 text-sm opacity-60">{{ __('No attendance taken yet') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th><th>{{ __('Period') }}</th><th>{{ __('Class') }}</th><th>{{ __('Subject') }}</th>
                                <th class="text-right">{{ __('Present') }}</th><th class="text-right">{{ __('Absent') }}</th><th class="text-right">{{ __('Rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latest as $session)
                                @php $sessionRate = Risk::rate($session->present_count, $session->records_count); @endphp
                                <tr>
                                    <td class="whitespace-nowrap">
                                        {{ $session->date->translatedFormat('D j M Y') }}
                                        @if ($session->recorded_by && $session->recorded_by !== $teacher->id)
                                            <div class="text-xs opacity-60">{{ __('Recorded by :name', ['name' => $session->recordedBy?->name]) }}</div>
                                        @endif
                                    </td>
                                    <td>P{{ $session->period }}</td>
                                    <td>{{ $session->schoolClass->name }}</td>
                                    <td>{{ $km ? $session->subject->name_km : $session->subject->name_en }}</td>
                                    <td class="text-right tabular-nums">{{ $session->present_count }}</td>
                                    <td class="text-right tabular-nums">{{ $session->records_count - $session->present_count }}</td>
                                    <td class="text-right font-semibold tabular-nums {{ $toneText[Risk::tone($sessionRate)] }}">{{ $sessionRate !== null ? $sessionRate.'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
