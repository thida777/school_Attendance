@extends('layouts.app')

@section('title', __('My Classes'))

@php
    use App\Support\Risk;
    $km = app()->getLocale() === 'km';
    $subjectNames = $subjects->map(fn ($s) => $km ? $s->name_km : $s->name_en)->join(', ');
    // Teachers see simple words: Good, Watch, At risk
    $simple = fn (?string $level) => match ($level) {
        'low' => ['badge-success', __('Good')],
        'medium' => ['badge-warning', __('Watch')],
        'high', 'critical' => ['badge-error', __('At risk')],
        default => ['badge-ghost', __('No records yet')],
    };
@endphp

@section('content')
    <p class="text-sm opacity-60">
        @if ($subjectNames)
            {{ __('The classes you teach :subjects in', ['subjects' => $subjectNames]) }}
        @else
            {{ __('You have no teaching assignments yet.') }}
        @endif
        @if ($year) · {{ __('academic year :name', ['name' => $year->name]) }} @endif
    </p>

    @if ($classes->isEmpty())
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body items-center py-12 text-center">
                <i data-lucide="school" class="size-10 opacity-40"></i>
                <p class="mt-2 font-medium">{{ __('No classes yet.') }}</p>
                <p class="text-sm opacity-60">{{ __('Ask the admin to add your teaching assignments.') }}</p>
            </div>
        </div>
    @else
        {{-- One card per class --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $class)
                @php $rate = Risk::rate($class->records_present, $class->records_total); @endphp
                <div class="card bg-base-100 border-base-300 border">
                    <div class="card-body">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="card-title">{{ $class->name }}
                                    @if ($homeroomIds->contains($class->id))<span class="badge badge-soft badge-primary badge-sm">{{ __('Homeroom') }}</span>@endif
                                </h3>
                                <p class="text-sm opacity-60">{{ __('Grade') }} {{ $class->grade }}@if ($class->room) · {{ __('Room') }} {{ $class->room }}@endif</p>
                            </div>
                            @if ($rate === null)
                                <span class="badge badge-soft badge-ghost">{{ __('No records yet') }}</span>
                            @else
                                <span class="badge badge-soft {{ Risk::badge(Risk::level($rate)) }}">{{ $rate }}%</span>
                            @endif
                        </div>
                        <progress class="progress {{ Risk::progress($rate) }} mt-2" value="{{ $rate ?? 0 }}" max="100"></progress>
                        <dl class="mt-2 grid grid-cols-2 gap-y-1 text-sm">
                            <dt class="opacity-60">{{ __('Students') }}</dt>
                            <dd class="text-right">{{ __(':count (:girls girls)', ['count' => $class->students_count, 'girls' => $class->girls_count]) }}</dd>
                            <dt class="opacity-60">{{ __('Sessions this month') }}</dt>
                            <dd class="text-right">{{ $sessionsThisMonth[$class->id] ?? 0 }}</dd>
                            <dt class="opacity-60">{{ __('Teaching time') }}</dt>
                            <dd class="text-right">{{ $teachingTimes[$class->id] ?? '—' }}</dd>
                        </dl>
                        <div class="card-actions mt-2 justify-end">
                            <a href="{{ route('attendance.monitoring', ['class' => $class->id]) }}" class="btn btn-sm">{{ __('History') }}</a>
                            <a href="{{ route('attendance.take') }}" class="btn btn-primary btn-sm">{{ __('Take attendance') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Students of my classes (read only) --}}
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="card-title text-base">{{ __('Students in my classes') }}</h3>
                    <form method="GET" action="{{ route('teacher.classes') }}" class="flex flex-wrap gap-2" data-auto-submit>
                        <label class="input w-auto">
                            <i data-lucide="search" class="size-4 opacity-50"></i>
                            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search name or ID') }}" />
                        </label>
                        <select name="class" class="select w-auto" aria-label="{{ __('Class') }}">
                            <option value="">{{ __('All classes') }}</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(($filters['class'] ?? '') == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($students->isEmpty())
                    <div class="py-10 text-center opacity-60">{{ __('No students found.') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Code') }}</th><th>{{ __('Class') }}</th><th>{{ __('Attendance rate') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @foreach ($students as $student)
                                    @php
                                        $rate = Risk::rate($student->records_present, $student->records_total);
                                        [$badge, $label] = $simple(Risk::level($rate));
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $km ? $student->name_km : $student->name_latin }}</div>
                                            <div class="text-xs opacity-60">{{ $km ? $student->name_latin : $student->name_km }}</div>
                                        </td>
                                        <td>{{ $student->student_code }}</td>
                                        <td>{{ $student->schoolClass->name }}</td>
                                        <td>
                                            @if ($rate === null)
                                                <span class="text-sm opacity-60">—</span>
                                            @else
                                                <div class="flex items-center gap-2"><progress class="progress {{ Risk::progress($rate) }} w-20" value="{{ $rate }}" max="100"></progress><span>{{ $rate }}%</span></div>
                                            @endif
                                        </td>
                                        <td><span class="badge badge-soft {{ $badge }}">{{ $label }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $students->links('partials.pagination') }}
                @endif
                <p class="text-xs opacity-60">{{ __('Only admins can add or edit student records.') }}</p>
            </div>
        </div>
    @endif
@endsection
