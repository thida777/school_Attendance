@extends('layouts.app')

@section('title', __('Dashboard'))

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
                <div class="text-sm opacity-85">{{ __('attendance rate today') }}</div>
            </div>
            <a href="{{ route('attendance.take') }}" class="btn btn-lg">
                <i data-lucide="clipboard-check" class="size-5"></i>{{ __('Take attendance') }}
            </a>
        </div>
    </div>

    {{-- Headline numbers, all read from the database --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="users"></i></div>
                <div class="stat-title">{{ __('Students') }}</div>
                <div class="stat-value">{{ $studentCount }}</div>
                <div class="stat-desc">{{ __('in :classes classes', ['classes' => $classCount]) }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="clipboard-list"></i></div>
                <div class="stat-title">{{ __('Lessons recorded today') }}</div>
                <div class="stat-value">{{ $sessionsToday }}</div>
                <div class="stat-desc">{{ __(':count student records', ['count' => $recordedToday]) }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="user-x"></i></div>
                <div class="stat-title">{{ __('Absent today') }}</div>
                <div class="stat-value">{{ $absentToday }}</div>
                <div class="stat-desc">{{ __('student records marked absent') }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border-base-300 border">
            <div class="stat">
                <div class="stat-figure"><i data-lucide="graduation-cap"></i></div>
                <div class="stat-title">{{ __('Teachers') }}</div>
                <div class="stat-value">{{ $teacherCount }}</div>
                <div class="stat-desc">{{ __('can log in to the app') }}</div>
            </div>
        </div>
    </div>

    {{-- Charts arrive with real attendance data --}}
    @if ($recordedToday === 0)
        <div class="card bg-base-100 border-base-300 border">
            <div class="card-body flex-row flex-wrap items-center gap-4">
                <span class="bg-info/15 text-info grid size-12 place-items-center rounded-2xl"><i data-lucide="chart-line" class="size-6"></i></span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ __('No attendance has been recorded yet.') }}</div>
                    <div class="text-sm opacity-70">{{ __('Trends, class comparisons and the at-risk list appear here once teachers start taking attendance.') }}</div>
                </div>
            </div>
        </div>
    @endif
@endsection
