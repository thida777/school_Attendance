@extends('layouts.app')

@section('title', __('My Dashboard'))

@section('content')
    <div class="hero-panel flex flex-wrap items-end justify-between gap-6 p-6 lg:p-8">
        <div>
            @if ($year)
                <span class="hero-chip"><i data-lucide="calendar-days" class="size-3.5"></i>{{ __('Academic year') }} {{ $year->name }}</span>
            @endif
            <h2 class="mt-4 text-2xl font-bold lg:text-3xl">{{ __('Hello, :name', ['name' => $teacher->name]) }}</h2>
            <p class="mt-1 opacity-85">
                {{ __('You teach :subjects subjects in :classes classes.', ['subjects' => $assignmentsByClass->flatten()->pluck('subject_id')->unique()->count(), 'classes' => $assignmentsByClass->count()]) }}
            </p>
            @if ($homeroomClass)
                <p class="mt-2 text-sm opacity-75">{{ __('Homeroom teacher of :class (:count students)', ['class' => $homeroomClass->name, 'count' => $homeroomClass->students_count]) }}</p>
            @endif
        </div>
        <a href="{{ route('attendance.take') }}" class="btn btn-lg">
            <i data-lucide="clipboard-check" class="size-5"></i>{{ __('Take attendance') }}
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="school"></i></div>
                <div class="stat-title">{{ __('My classes') }}</div>
                <div class="stat-value">{{ $assignmentsByClass->count() }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="clipboard-list"></i></div>
                <div class="stat-title">{{ __('Lessons recorded today') }}</div>
                <div class="stat-value">{{ $sessionsToday }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="house"></i></div>
                <div class="stat-title">{{ __('Homeroom class') }}</div>
                <div class="stat-value">{{ $homeroomClass?->name ?? '—' }}</div>
            </div>
        </div>
    </div>

    {{-- The classes and subjects this teacher is assigned to --}}
    <div class="card bg-base-100 border-base-300 border">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('My classes and subjects') }}</h3>
            @if ($assignmentsByClass->isEmpty())
                <p class="text-sm opacity-70">{{ __('You have no teaching assignments yet. Ask the school office to assign your classes.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>{{ __('Class') }}</th><th>{{ __('Subjects') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($assignmentsByClass as $className => $assignments)
                                <tr>
                                    <td class="font-medium">{{ $className }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($assignments as $assignment)
                                                <span class="badge badge-ghost">{{ app()->getLocale() === 'km' ? $assignment->subject->name_km : $assignment->subject->name_en }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('attendance.take') }}" class="btn btn-sm"><i data-lucide="clipboard-check" class="size-4"></i>{{ __('Take attendance') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
